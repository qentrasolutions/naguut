<?php
// Spiel-Engine: Phasen, Antworten, Wertung und Status für das Polling.
//
// Phasen: lobby → question → reveal → leaderboard → question … → finished
// Ohne dauerhaft laufenden Prozess: Zeitabläufe werden bei jeder Anfrage
// geprüft (game_tick). Jede Änderung erhöht state_version; Clients fragen
// mit ?since=<version> ab und bekommen 204, wenn sich nichts geändert hat.

const GAME_READY_MS = 3000;        // Vorlaufzeit: Frage lesen, bevor Antworten zählen
const GAME_ANSWER_GRACE_MS = 400;  // Toleranz für Netzlaufzeit am Fragenende
const GAME_ACTIVE_SECONDS = 25;    // Spieler gilt als verbunden, wenn so lange gesehen
const GAME_BASE_POINTS = 1000;

// Avatare – Zeichnungen in assets/js/avatars.js, IDs müssen übereinstimmen
const NAGUUT_AVATARS = ['katze', 'hund', 'frosch', 'eule', 'panda', 'schwein', 'pinguin', 'fuchs', 'baer', 'kueken',
    'krake', 'dino', 'monster', 'alien', 'roboter', 'gespenst', 'kaktus', 'pizza', 'einhorn', 'faultier'];

function game_questions(array $game): array
{
    static $cache = [];
    $key = $game['id'] . ':' . md5($game['quiz_snapshot']);
    if (!isset($cache[$key])) {
        $data = json_decode($game['quiz_snapshot'], true);
        $cache[$key] = $data['questions'] ?? [];
    }
    return $cache[$key];
}

function game_find(int $id): ?array
{
    return q_one('SELECT * FROM games WHERE id = ?', [$id]);
}

/** Neuestes Spiel mit dieser PIN (laufend oder gerade beendet) */
function game_by_pin(string $pin): ?array
{
    if (!preg_match('/^\d{6}$/', $pin)) {
        return null;
    }
    return q_one('SELECT * FROM games WHERE pin = ? ORDER BY id DESC LIMIT 1', [$pin]);
}

function game_generate_pin(): string
{
    for ($i = 0; $i < 50; $i++) {
        $pin = (string)random_int(100000, 999999);
        $taken = q_val("SELECT COUNT(*) FROM games WHERE pin = ? AND status <> 'finished'", [$pin]);
        if (!$taken) {
            return $pin;
        }
    }
    throw new RuntimeException('Keine freie PIN gefunden');
}

function game_create(array $user, array $quiz): array
{
    $data = quiz_export($quiz);
    if (!$data['questions']) {
        return ['error' => t('Das Quiz hat noch keine Fragen.')];
    }
    $now = time();
    $pin = game_generate_pin();
    $id = q_insert('games', [
        'quiz_id' => $quiz['id'], 'host_id' => $user['id'], 'pin' => $pin, 'status' => 'lobby',
        'current_question' => -1, 'state_version' => 1, 'quiz_title' => $data['title'],
        'show_on_devices' => !empty($data['show_on_devices']) ? 1 : 0,
        'quiz_snapshot' => json_encode(['title' => $data['title'], 'music' => $data['music'], 'questions' => $data['questions']], JSON_UNESCAPED_UNICODE),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    return ['id' => $id, 'pin' => $pin];
}

function game_bump(int $gameId): void
{
    q('UPDATE games SET state_version = state_version + 1, updated_at = ? WHERE id = ?', [time(), $gameId]);
}

/**
 * Atomarer Phasenwechsel: greift nur, wenn das Spiel noch im erwarteten Zustand ist.
 * So lösen Doppelklicks oder gleichzeitige Anfragen keinen doppelten Wechsel aus.
 */
function game_transition(array $game, array $set): bool
{
    $cols = [];
    $params = [];
    foreach ($set as $k => $v) {
        $cols[] = "$k = ?";
        $params[] = $v;
    }
    $cols[] = 'state_version = state_version + 1';
    $cols[] = 'updated_at = ?';
    $params[] = time();
    $params[] = $game['id'];
    $params[] = $game['status'];
    $params[] = $game['current_question'];
    $st = q('UPDATE games SET ' . implode(', ', $cols) . ' WHERE id = ? AND status = ? AND current_question = ?', $params);
    return $st->rowCount() > 0;
}

function game_reload(array $game): array
{
    return game_find((int)$game['id']) ?? $game;
}

/** Prüft Zeitablauf; wechselt bei abgelaufener Frage zur Auflösung */
function game_tick(array $game): array
{
    if ($game['status'] === 'question' && now_ms() >= (int)$game['question_ends_ms']) {
        if (game_transition($game, ['status' => 'reveal'])) {
            return game_reload($game);
        }
        return game_reload($game);
    }
    return $game;
}

function game_active_players(int $gameId): int
{
    return (int)q_val('SELECT COUNT(*) FROM players WHERE game_id = ? AND is_kicked = 0 AND last_seen_at >= ?',
        [$gameId, time() - GAME_ACTIVE_SECONDS]);
}

function game_answer_count(int $gameId, int $qno): int
{
    return (int)q_val('SELECT COUNT(*) FROM responses r JOIN players p ON p.id = r.player_id
        WHERE r.game_id = ? AND r.question_no = ? AND p.is_kicked = 0', [$gameId, $qno]);
}

function game_start_question(array $game, int $qno, array $extra = []): bool
{
    $questions = game_questions($game);
    if (!isset($questions[$qno])) {
        return false;
    }
    $starts = now_ms() + GAME_READY_MS;
    $ends = $starts + $questions[$qno]['time_limit_s'] * 1000;
    return game_transition($game, [
        'status' => 'question', 'current_question' => $qno,
        'question_starts_ms' => $starts, 'question_ends_ms' => $ends,
    ] + $extra);
}

/** Nummern (ab 0) der übersprungenen Fragen */
function game_skipped(array $game): array
{
    $raw = trim((string)($game['skipped'] ?? ''));
    return $raw === '' ? [] : array_map('intval', explode(',', $raw));
}

/** Nimmt Antworten und Punkte einer Frage zurück */
function game_void_question(array $game, int $qno): void
{
    tx(function () use ($game, $qno) {
        foreach (q_all('SELECT player_id, points FROM responses WHERE game_id = ? AND question_no = ? AND points <> 0', [$game['id'], $qno]) as $r) {
            q('UPDATE players SET score = score - ? WHERE id = ?', [$r['points'], $r['player_id']]);
        }
        q('DELETE FROM responses WHERE game_id = ? AND question_no = ?', [$game['id'], $qno]);
    });
}

/**
 * Frage überspringen. $expected = Phase, die der Host gerade sieht.
 * - Lobby: erste Frage überspringen
 * - Frage läuft / Auflösung: aktuelle Frage wird nicht gewertet, weiter zur nächsten
 * - Rangliste: die kommende Frage überspringen
 * Ist keine Frage mehr übrig, endet das Spiel mit dem Podest.
 * @return string|null Fehlermeldung oder null
 */
function game_skip(array $game, string $expected): ?string
{
    $game = game_tick($game);
    if ($game['status'] !== $expected) {
        return null; // Host war nicht mehr aktuell; nichts doppelt überspringen
    }
    $cur = (int)$game['current_question'];
    switch ($game['status']) {
        case 'lobby':
            if (!(int)q_val('SELECT COUNT(*) FROM players WHERE game_id = ? AND is_kicked = 0', [$game['id']])) {
                return t('Es ist noch niemand beigetreten.');
            }
            $target = 0;
            break;
        case 'question':
        case 'reveal':
            $target = $cur;
            break;
        case 'leaderboard':
            $target = $cur + 1;
            break;
        default:
            return t('Das Spiel ist schon beendet.');
    }
    $count = count(game_questions($game));
    $skipped = array_values(array_unique(array_merge(game_skipped($game), [$target])));
    sort($skipped);
    $extra = ['skipped' => implode(',', $skipped)];
    if ($target === $cur && in_array($game['status'], ['question', 'reveal'], true)) {
        game_void_question($game, $cur);
    }
    $next = $target + 1;
    if ($next >= $count) {
        game_transition($game, ['status' => 'finished', 'finished_at' => time(), 'current_question' => max($cur, $target)] + $extra);
    } else {
        game_start_question($game, $next, $extra);
    }
    return null;
}

/**
 * Host schaltet weiter. $expected = Phase, die der Host gerade sieht.
 * @return string|null Fehlermeldung oder null
 */
function game_next(array $game, string $expected): ?string
{
    $game = game_tick($game);
    if ($game['status'] !== $expected) {
        return null; // schon weitergeschaltet (z. B. Zeit abgelaufen) – kein Fehler
    }
    $questions = game_questions($game);
    $cur = (int)$game['current_question'];
    switch ($game['status']) {
        case 'lobby':
            $players = (int)q_val('SELECT COUNT(*) FROM players WHERE game_id = ? AND is_kicked = 0', [$game['id']]);
            if ($players === 0) {
                return t('Es ist noch niemand beigetreten.');
            }
            game_start_question($game, 0);
            return null;
        case 'question':
            game_transition($game, ['status' => 'reveal', 'question_ends_ms' => min((int)$game['question_ends_ms'], now_ms())]);
            return null;
        case 'reveal':
            if ($cur >= count($questions) - 1) {
                game_transition($game, ['status' => 'finished', 'finished_at' => time()]);
            } else {
                game_transition($game, ['status' => 'leaderboard']);
            }
            return null;
        case 'leaderboard':
            game_start_question($game, $cur + 1);
            return null;
    }
    return null;
}

/** Distanz-Modus ein- oder ausschalten (Fragen auf den Endgeräten anzeigen) */
function game_set_show_on_devices(array $game, bool $on): void
{
    q('UPDATE games SET show_on_devices = ?, state_version = state_version + 1, updated_at = ? WHERE id = ?', [$on ? 1 : 0, time(), $game['id']]);
}

function game_end(array $game): void
{
    if ($game['status'] !== 'finished') {
        game_transition($game, ['status' => 'finished', 'finished_at' => time()]);
    }
}

// ---------- Spieler ----------

/** @return array{player?:array, token?:string, error?:string} */
function game_join(array $game, string $nickname, string $avatar = ''): array
{
    if ($game['status'] === 'finished') {
        return ['error' => t('Dieses Spiel ist schon vorbei.')];
    }
    $nickname = preg_replace('/\s+/u', ' ', trim($nickname));
    if ($err = nickname_error($nickname)) {
        return ['error' => $err];
    }
    $count = (int)q_val('SELECT COUNT(*) FROM players WHERE game_id = ? AND is_kicked = 0', [$game['id']]);
    if ($count >= (int)cfg('max_players', 150)) {
        return ['error' => t('Das Spiel ist voll.')];
    }
    $exists = q_val('SELECT COUNT(*) FROM players WHERE game_id = ? AND LOWER(nickname) = ?', [$game['id'], mb_strtolower($nickname, 'UTF-8')]);
    if ($exists) {
        return ['error' => t('Dieser Nickname ist schon vergeben.')];
    }
    $token = random_token(24);
    try {
        $id = q_insert('players', [
            'game_id' => $game['id'], 'nickname' => $nickname,
            'avatar' => in_array($avatar, NAGUUT_AVATARS, true) ? $avatar : NAGUUT_AVATARS[array_rand(NAGUUT_AVATARS)],
            'rejoin_token_hash' => token_hash($token),
            'score' => 0, 'is_kicked' => 0, 'joined_at' => time(), 'last_seen_at' => time(),
        ]);
    } catch (PDOException $e) {
        if (is_unique_violation($e)) {
            return ['error' => t('Dieser Nickname ist schon vergeben.')];
        }
        throw $e;
    }
    game_bump((int)$game['id']);
    return ['player' => q_one('SELECT * FROM players WHERE id = ?', [$id]), 'token' => $token];
}

function game_player_by_token(array $game, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    return q_one('SELECT * FROM players WHERE game_id = ? AND rejoin_token_hash = ?', [$game['id'], token_hash($token)]);
}

function game_touch_player(array $player): void
{
    if (time() - (int)$player['last_seen_at'] >= 10) {
        q('UPDATE players SET last_seen_at = ? WHERE id = ?', [time(), $player['id']]);
    }
}

function game_kick(array $game, int $playerId): bool
{
    $st = q('UPDATE players SET is_kicked = 1 WHERE id = ? AND game_id = ?', [$playerId, $game['id']]);
    if ($st->rowCount()) {
        game_bump((int)$game['id']);
        return true;
    }
    return false;
}

/** Punkte: Basispunkte × Faktor × (1 − (Antwortzeit / Zeitlimit) / 2) */
function game_points(bool $correct, int $responseMs, int $limitMs, int $factor): int
{
    if (!$correct || $factor === 0) {
        return 0;
    }
    $ratio = max(0, min(1, $responseMs / max(1, $limitMs)));
    return (int)round(GAME_BASE_POINTS * $factor * (1 - $ratio / 2));
}

/** @return string|null Fehlermeldung oder null bei Erfolg */
function game_answer(array $game, array $player, int $qno, int $idx, $value = null): ?string
{
    $game = game_tick($game);
    if ($player['is_kicked']) {
        return t('Du wurdest aus dem Spiel entfernt.');
    }
    if ($game['status'] !== 'question' || (int)$game['current_question'] !== $qno) {
        return t('Diese Frage ist schon vorbei.');
    }
    $now = now_ms();
    $starts = (int)$game['question_starts_ms'];
    $ends = (int)$game['question_ends_ms'];
    if ($now < $starts - 200) {
        return t('Warte, bis die Antworten freigegeben sind.');
    }
    if ($now > $ends + GAME_ANSWER_GRACE_MS) {
        return t('Die Zeit ist abgelaufen.');
    }
    $question = game_questions($game)[$qno] ?? null;
    if (!$question) {
        return t('Diese Antwort gibt es nicht.');
    }
    $limitMs = $question['time_limit_s'] * 1000;
    $responseMs = max(0, min($limitMs, $now - $starts));
    $answerValue = null;
    $answerText = null;
    $type = $question['type'];
    if ($type === 'txt') {
        $given = trim(preg_replace('/\s+/u', ' ', is_scalar($value) ? (string)$value : ''));
        if ($given === '') {
            return t('Bitte tippe eine Antwort ein.');
        }
        $answerText = mb_substr($given, 0, TXT_MAX_LEN);
        $correct = txt_is_correct($answerText, $question['answers']);
        $points = game_points($correct, $responseMs, $limitMs, (int)$question['points_factor']);
        $idx = 0;
    } elseif ($type === 'ord') {
        $perm = ord_perm((int)$game['id'], $qno, count($question['answers']));
        $slots = is_array($value) ? array_map('intval', array_values($value)) : [];
        if (count($slots) !== count($perm) || count(array_unique($slots)) !== count($perm) || min($slots) < 0 || max($slots) >= count($perm)) {
            return t('Bitte bring alle Begriffe in eine Reihenfolge.');
        }
        $answerText = implode(',', $slots);
        $correct = ord_is_correct($slots, $perm);
        $points = game_points($correct, $responseMs, $limitMs, (int)$question['points_factor']);
        $idx = 0;
    } elseif ($type === 'map') {
        $m = $question['map'] ?? null;
        $lat = is_array($value) ? ($value['lat'] ?? null) : null;
        $lng = is_array($value) ? ($value['lng'] ?? null) : null;
        if (!$m || !is_numeric($lat) || !is_numeric($lng) || abs((float)$lat) > 85 || abs((float)$lng) > 180) {
            return t('Bitte setze einen Pin auf die Karte.');
        }
        $lat = round((float)$lat, 5);
        $lng = round((float)$lng, 5);
        $answerValue = map_distance_km($lat, $lng, (float)$m['lat'], (float)$m['lng']);
        $answerText = $lat . ',' . $lng;
        $score = map_score($answerValue, (float)$m['radius_km']);
        $correct = $score['correct'];
        $points = (int)round(game_points($correct, $responseMs, $limitMs, (int)$question['points_factor']) * $score['factor']);
        $idx = 0;
    } elseif ($type === 'est') {
        $est = $question['estimate'];
        $v = est_parse_number($value);
        if ($v === null || !$est) {
            return t('Bitte wähle einen Wert mit dem Schieberegler.');
        }
        $answerValue = est_snap($v, $est);
        $score = est_score($answerValue, $est);
        $correct = $score['correct'];
        $points = (int)round(game_points($correct, $responseMs, $limitMs, (int)$question['points_factor']) * $score['factor']);
        $idx = 0;
    } else {
        if (!isset($question['answers'][$idx])) {
            return t('Diese Antwort gibt es nicht.');
        }
        $correct = !empty($question['answers'][$idx]['is_correct']);
        $points = game_points($correct, $responseMs, $limitMs, (int)$question['points_factor']);
    }

    try {
        tx(function () use ($game, $player, $qno, $idx, $answerValue, $answerText, $responseMs, $correct, $points) {
            q_insert('responses', [
                'game_id' => $game['id'], 'player_id' => $player['id'], 'question_no' => $qno,
                'answer_idx' => $idx, 'answer_value' => $answerValue, 'answer_text' => $answerText, 'response_ms' => $responseMs, 'is_correct' => $correct ? 1 : 0,
                'points' => $points, 'created_at' => time(),
            ]);
            if ($points) {
                q('UPDATE players SET score = score + ? WHERE id = ?', [$points, $player['id']]);
            }
        });
    } catch (PDOException $e) {
        if (is_unique_violation($e)) {
            return t('Du hast schon geantwortet.');
        }
        throw $e;
    }
    game_touch_player($player);

    // Alle haben geantwortet → Frage sofort beenden, sonst nur Antwortzähler aktualisieren
    $active = max(1, game_active_players((int)$game['id']));
    if (game_answer_count((int)$game['id'], $qno) >= $active) {
        game_transition($game, ['status' => 'reveal', 'question_ends_ms' => min($ends, now_ms())]);
    } else {
        game_bump((int)$game['id']);
    }
    return null;
}

// ---------- Status ----------

function game_ranking(int $gameId): array
{
    return q_all('SELECT id, nickname, avatar, score FROM players WHERE game_id = ? AND is_kicked = 0 ORDER BY score DESC, joined_at ASC, id ASC', [$gameId]);
}

function game_rank_of(array $ranking, int $playerId): int
{
    foreach ($ranking as $i => $r) {
        if ((int)$r['id'] === $playerId) {
            return $i + 1;
        }
    }
    return 0;
}

function game_top(array $ranking, int $n): array
{
    return array_map(fn($r) => ['nickname' => $r['nickname'], 'avatar' => $r['avatar'], 'score' => (int)$r['score']], array_slice($ranking, 0, $n));
}

/**
 * Baut den Status für Host oder Spieler.
 * $player = null → Host-Sicht.
 */
function game_state(array $game, ?array $player): array
{
    $isHost = $player === null;
    $questions = game_questions($game);
    $cur = (int)$game['current_question'];
    $q = $questions[$cur] ?? null;
    $gid = (int)$game['id'];

    $s = [
        'version'        => (int)$game['state_version'],
        'status'         => $game['status'],
        'now_ms'         => now_ms(),
        'pin'            => $game['pin'],
        'quiz_title'     => $game['quiz_title'],
        'question_no'    => $cur,
        'question_count' => count($questions),
        'show_on_devices' => !empty($game['show_on_devices']),
    ];

    if (!$isHost) {
        if ($player['is_kicked']) {
            return ['version' => $s['version'], 'status' => 'kicked', 'now_ms' => $s['now_ms']];
        }
        $s['nickname'] = $player['nickname'];
        $s['avatar'] = $player['avatar'];
    }

    if ($game['status'] === 'lobby') {
        if ($isHost) {
            $s['players'] = array_map(fn($p) => ['id' => (int)$p['id'], 'nickname' => $p['nickname'], 'avatar' => $p['avatar']],
                q_all('SELECT id, nickname, avatar FROM players WHERE game_id = ? AND is_kicked = 0 ORDER BY id', [$gid]));
            $s['join_url'] = abs_url('/play?pin=' . $game['pin']);
            $s['join_host'] = rtrim(preg_replace('#^https?://#', '', abs_url('/')), '/');
        } else {
            $s['player_count'] = (int)q_val('SELECT COUNT(*) FROM players WHERE game_id = ? AND is_kicked = 0', [$gid]);
        }
        return $s;
    }

    if ($q && in_array($game['status'], ['question', 'reveal'], true)) {
        $s['question'] = [
            'type'          => $q['type'],
            'answer_count'  => count($q['answers']),
            'time_limit_s'  => (int)$q['time_limit_s'],
            'points_factor' => (int)$q['points_factor'],
            'starts_ms'     => (int)$game['question_starts_ms'],
            'ends_ms'       => (int)$game['question_ends_ms'],
        ];
        if ($q['type'] === 'est' && !empty($q['estimate'])) {
            $e = $q['estimate'];
            // Der richtige Wert bleibt bis zur Auflösung auf dem Server
            $s['question']['estimate'] = ['min' => $e['min'], 'max' => $e['max'], 'step' => $e['step'],
                'unit' => $e['unit'], 'decimals' => est_decimals($e['step'])];
        }
        // Texte der Antwortmöglichkeiten, die gezeigt werden dürfen (nie die Lösungen von "Antwort eingeben")
        $visibleAnswers = [];
        if ($q['type'] === 'mc' || $q['type'] === 'tf') {
            $visibleAnswers = array_map(fn($a) => rich_inline($a['text']), $q['answers']);
        } elseif ($q['type'] === 'ord') {
            $visibleAnswers = array_map(fn($i) => rich_inline($q['answers'][$i]['text']), ord_perm($gid, $cur, count($q['answers'])));
        }
        if ($q['type'] === 'txt') {
            $s['question']['answer_count'] = 0;
            $s['question']['max_len'] = TXT_MAX_LEN;
        }
        if ($q['type'] === 'map') {
            $s['question']['answer_count'] = 0;
            $s['question']['view'] = map_clean_view($q['map']['view'] ?? null) ?? map_default_view();
        }
        if ($isHost) {
            $s['question']['text_html'] = rich_inline($q['text']);
            $s['question']['image'] = upload_url($q['image']);
            $s['question']['answers_html'] = $visibleAnswers;
            $s['answered'] = game_answer_count($gid, $cur);
            $s['players_active'] = game_active_players($gid);
        } else {
            $mine = q_one('SELECT answer_idx, answer_value, is_correct, points FROM responses WHERE player_id = ? AND question_no = ?', [$player['id'], $cur]);
            $s['my_answer'] = $mine ? (int)$mine['answer_idx'] : null;
            if (!empty($game['show_on_devices'])) {
                // Distanz-Modus: Frage und Antworttexte auch am Handy (ohne Lösung)
                $s['question']['text_html'] = rich_inline($q['text']);
                $s['question']['image'] = upload_url($q['image']);
                $s['question']['answers_html'] = $visibleAnswers;
            }
        }
    }

    if ($game['status'] === 'reveal' && $q) {
        $correct = [];
        foreach ($q['answers'] as $i => $a) {
            if (!empty($a['is_correct'])) {
                $correct[] = $i;
            }
        }
        $s['correct'] = $correct;
        if ($q['type'] === 'est' && !empty($q['estimate'])) {
            $s['question']['estimate']['correct'] = $q['estimate']['correct'];
            $s['question']['estimate']['tolerance'] = $q['estimate']['tolerance'];
        }
        if (in_array($q['type'], ['txt', 'ord', 'map'], true)) {
            $s['correct'] = [];
            $s['hits'] = (int)q_val('SELECT COUNT(*) FROM responses r JOIN players p ON p.id = r.player_id
                WHERE r.game_id = ? AND r.question_no = ? AND p.is_kicked = 0 AND r.is_correct = 1', [$gid, $cur]);
            $s['answered'] = game_answer_count($gid, $cur);
            if ($q['type'] === 'txt') {
                $s['accepted'] = array_map(fn($a) => $a['text'], $q['answers']);
            }
            if ($q['type'] === 'ord') {
                $perm = ord_perm($gid, $cur, count($q['answers']));
                $s['question']['answers_html'] = array_map(fn($i) => rich_inline($q['answers'][$i]['text']), $perm);
                $s['correct_order'] = array_map(fn($i) => array_search($i, $perm, true), range(0, count($perm) - 1)); // Anzeigeplätze in richtiger Reihenfolge
            }
            if ($q['type'] === 'map') {
                $s['target'] = ['lat' => (float)$q['map']['lat'], 'lng' => (float)$q['map']['lng'], 'radius_km' => (float)$q['map']['radius_km'], 'label' => (string)($q['map']['label'] ?? '')];
            }
        }
        if ($isHost && $q['type'] === 'txt') {
            // Häufigste Antworten (anstößige Eingaben werden ausgeblendet)
            $groups = [];
            foreach (q_all('SELECT r.answer_text, r.is_correct FROM responses r JOIN players p ON p.id = r.player_id
                WHERE r.game_id = ? AND r.question_no = ? AND p.is_kicked = 0', [$gid, $cur]) as $r) {
                $k = txt_normalize((string)$r['answer_text']);
                $groups[$k] ??= ['text' => (string)$r['answer_text'], 'n' => 0, 'correct' => (bool)$r['is_correct']];
                $groups[$k]['n']++;
            }
            usort($groups, fn($a, $b) => [$b['correct'], $b['n']] <=> [$a['correct'], $a['n']]);
            $s['top_answers'] = array_map(fn($g) => $g + ['hidden' => !$g['correct'] && nickname_is_offensive($g['text'])], array_slice($groups, 0, 8));
            $s['is_last'] = $cur >= count($questions) - 1;
        } elseif ($isHost && $q['type'] === 'ord') {
            $s['is_last'] = $cur >= count($questions) - 1;
        } elseif ($isHost && $q['type'] === 'map') {
            $s['pins'] = array_map(function ($r) {
                [$la, $ln] = array_map('floatval', explode(',', (string)$r['answer_text']) + [1 => 0]);
                return ['lat' => $la, 'lng' => $ln, 'correct' => (bool)$r['is_correct'], 'avatar' => $r['avatar']];
            }, q_all('SELECT r.answer_text, r.is_correct, p.avatar FROM responses r JOIN players p ON p.id = r.player_id
                WHERE r.game_id = ? AND r.question_no = ? AND p.is_kicked = 0 AND r.answer_text IS NOT NULL', [$gid, $cur]));
            $avg = q_val('SELECT AVG(r.answer_value) FROM responses r JOIN players p ON p.id = r.player_id
                WHERE r.game_id = ? AND r.question_no = ? AND p.is_kicked = 0', [$gid, $cur]);
            $s['avg_km'] = $avg !== null ? map_format_km((float)$avg) : null;
            $s['is_last'] = $cur >= count($questions) - 1;
        } elseif ($isHost && $q['type'] === 'est') {
            $s['guesses'] = array_map('floatval', array_column(q_all('SELECT r.answer_value FROM responses r JOIN players p ON p.id = r.player_id
                WHERE r.game_id = ? AND r.question_no = ? AND p.is_kicked = 0 AND r.answer_value IS NOT NULL ORDER BY r.answer_value', [$gid, $cur]), 'answer_value'));
            $s['hits'] = (int)q_val('SELECT COUNT(*) FROM responses r JOIN players p ON p.id = r.player_id
                WHERE r.game_id = ? AND r.question_no = ? AND p.is_kicked = 0 AND r.is_correct = 1', [$gid, $cur]);
            $s['is_last'] = $cur >= count($questions) - 1;
        } elseif ($isHost) {
            $counts = array_fill(0, count($q['answers']), 0);
            foreach (q_all('SELECT r.answer_idx, COUNT(*) AS n FROM responses r JOIN players p ON p.id = r.player_id
                WHERE r.game_id = ? AND r.question_no = ? AND p.is_kicked = 0 GROUP BY r.answer_idx', [$gid, $cur]) as $row) {
                $counts[(int)$row['answer_idx']] = (int)$row['n'];
            }
            $s['distribution'] = $counts;
            $s['is_last'] = $cur >= count($questions) - 1;
        } else {
            $mine = q_one('SELECT answer_idx, answer_value, answer_text, is_correct, points FROM responses WHERE player_id = ? AND question_no = ?', [$player['id'], $cur]);
            $s['result'] = [
                'answered' => (bool)$mine,
                'correct'  => $mine ? (bool)$mine['is_correct'] : false,
                'points'   => $mine ? (int)$mine['points'] : 0,
                'value'    => $mine && $mine['answer_value'] !== null ? (float)$mine['answer_value'] : null,
            ];
            if ($mine && $q['type'] === 'txt') {
                $s['result']['text'] = (string)$mine['answer_text'];
            }
            if ($mine && $q['type'] === 'ord') {
                $s['result']['order'] = array_map('intval', explode(',', (string)$mine['answer_text']));
            }
            if ($mine && $q['type'] === 'map') {
                [$la, $ln] = array_map('floatval', explode(',', (string)$mine['answer_text']) + [1 => 0]);
                $s['result']['pin'] = ['lat' => $la, 'lng' => $ln];
                $s['result']['distance'] = map_format_km((float)$mine['answer_value']);
            }
            if ($q['type'] === 'map') {
                $s['question']['view'] = map_clean_view($q['map']['view'] ?? null) ?? map_default_view();
            }
        }
    }

    if (in_array($game['status'], ['reveal', 'leaderboard', 'finished'], true)) {
        $ranking = game_ranking($gid);
        $s['player_total'] = count($ranking);
        if ($isHost) {
            if ($game['status'] !== 'reveal') {
                $s['top'] = game_top($ranking, 5);
            }
            if ($game['status'] === 'finished') {
                $s['report_url'] = url('/games/' . $gid);
            }
        } else {
            $s['rank'] = game_rank_of($ranking, (int)$player['id']);
            $s['score'] = (int)(q_val('SELECT score FROM players WHERE id = ?', [$player['id']]) ?? 0);
            if ($game['status'] !== 'reveal') {
                $s['top'] = game_top($ranking, $game['status'] === 'finished' ? 3 : 5);
            }
        }
    }

    return $s;
}
