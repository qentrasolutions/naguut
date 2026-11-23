<?php
// JSON-API für Editor, Host und Spieler

// ---------- Host-Konto ----------

function api_login(): void
{
    $in = input_json();
    $result = auth_attempt((string)($in['email'] ?? ''), (string)($in['password'] ?? ''));
    if (isset($result['error'])) {
        json_error($result['error'], 401);
    }
    json_out(['ok' => true, 'csrf' => csrf_token(), 'user' => ['id' => (int)$result['user']['id'], 'name' => $result['user']['display_name']]]);
}

function api_host(): array
{
    $user = require_login();
    if (request_method() !== 'GET') {
        csrf_check();
    }
    return $user;
}

// ---------- Quizze ----------

function api_quiz_list(): void
{
    $user = api_host();
    $rows = q_all('SELECT id, title, is_public, updated_at, (SELECT COUNT(*) FROM questions x WHERE x.quiz_id = q.id) AS question_count
                   FROM quizzes q WHERE user_id = ? ORDER BY updated_at DESC', [$user['id']]);
    json_out(['quizzes' => array_map(fn($r) => [
        'id' => (int)$r['id'], 'title' => $r['title'], 'is_public' => (bool)$r['is_public'],
        'question_count' => (int)$r['question_count'], 'updated_at' => (int)$r['updated_at'],
    ], $rows)]);
}

function api_quiz_create(): void
{
    $user = api_host();
    $in = input_json();
    $id = quiz_create($user);
    if ($in) {
        [$data, $errors] = quiz_validate($in);
        if ($errors) {
            q('DELETE FROM quizzes WHERE id = ?', [$id]);
            json_error(t('Bitte prüfe die Eingaben.'), 422, ['errors' => $errors]);
        }
        quiz_save($id, $data);
    }
    json_out(['quiz' => quiz_export(quiz_find($id))], 201);
}

function api_quiz_get(int $id): void
{
    $user = api_host();
    json_out(['quiz' => quiz_export(quiz_playable($id, $user))]);
}

function api_quiz_update(int $id): void
{
    $user = api_host();
    $quiz = quiz_owned($id, $user);
    [$data, $errors] = quiz_validate(input_json());
    if ($errors) {
        json_error(t('Bitte prüfe die Eingaben.'), 422, ['errors' => $errors]);
    }
    quiz_save((int)$quiz['id'], $data);
    json_out(['quiz' => quiz_export(quiz_find((int)$quiz['id']))]);
}

function api_quiz_delete(int $id): void
{
    $user = api_host();
    $quiz = quiz_owned($id, $user);
    q('DELETE FROM quizzes WHERE id = ?', [$quiz['id']]);
    json_out(['ok' => true]);
}

function api_quiz_image(int $id): void
{
    $user = api_host();
    quiz_owned($id, $user);
    if (!rate_limit_hit('upload:' . $user['id'], 120, 3600)) {
        abort(429);
    }
    if (empty($_FILES['image'])) {
        json_error(t('Es wurde kein Bild gesendet. Ist es größer als das Upload-Limit des Servers?'), 400);
    }
    $result = store_uploaded_image($_FILES['image']);
    if (isset($result['error'])) {
        json_error($result['error'], 422);
    }
    json_out(['file' => $result['file'], 'url' => upload_url($result['file'])], 201);
}

function api_quiz_sound(int $id): void
{
    $user = api_host();
    quiz_owned($id, $user);
    if (!rate_limit_hit('upload:' . $user['id'], 120, 3600)) {
        abort(429);
    }
    if (empty($_FILES['sound'])) {
        json_error(t('Es wurde keine Datei gesendet. Ist sie größer als das Upload-Limit des Servers ({limit})?', ['limit' => php_upload_limit()]), 400);
    }
    $result = store_uploaded_sound($_FILES['sound']);
    if (isset($result['error'])) {
        json_error($result['error'], 422);
    }
    json_out(['file' => $result['file'], 'url' => upload_url($result['file'])], 201);
}

/**
 * Fragen aus einer CSV- oder JSON-Datei lesen und geprüft zurückgeben.
 * Gespeichert wird erst, wenn im Editor auf „Speichern“ geklickt wird.
 */
function api_quiz_import(int $id): void
{
    $user = api_host();
    quiz_owned($id, $user);
    if (!rate_limit_hit('import:' . $user['id'], 40, 3600)) {
        abort(429);
    }
    $file = $_FILES['file'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $tooBig = $file && in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        json_error($tooBig || !$file
            ? t('Es wurde keine Datei empfangen. Ist sie größer als das Upload-Limit des Servers ({limit})? Exportiere dann ohne Bilder als CSV.', ['limit' => php_upload_limit()])
            : t('Die Datei konnte nicht hochgeladen werden.'), 400);
    }
    if ($file['size'] > IMPORT_MAX_BYTES) {
        json_error(t('Die Datei ist zu groß (höchstens {n} MB).', ['n' => IMPORT_MAX_BYTES / 1048576]), 422);
    }
    $raw = (string)file_get_contents($file['tmp_name']);
    $start = ltrim(str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw);
    $isJson = str_ends_with(strtolower((string)$file['name']), '.json') || ($start !== '' && ($start[0] === '{' || $start[0] === '['));
    $meta = null;
    $parseErrors = [];

    if ($isJson) {
        $parsed = transfer_parse_json($start);
        if (isset($parsed['error'])) {
            json_error($parsed['error'], 422);
        }
        $quizzes = $parsed['quizzes'];
        $pick = isset($_POST['pick']) && ctype_digit((string)$_POST['pick']) ? (int)$_POST['pick'] : null;
        if (count($quizzes) > 1 && ($pick === null || !isset($quizzes[$pick]))) {
            json_out(['choose' => array_map(fn($qz, $i) => [
                'index' => $i, 'title' => mb_substr(trim((string)($qz['title'] ?? '')), 0, 150) ?: t('Quiz {n}', ['n' => $i + 1]),
                'count' => is_array($qz['questions'] ?? null) ? count($qz['questions']) : 0,
            ], $quizzes, array_keys($quizzes))]);
        }
        $qz = $quizzes[$pick ?? 0];
        $items = [];
        foreach (array_values(is_array($qz['questions'] ?? null) ? $qz['questions'] : []) as $i => $q) {
            $items[] = ['label' => t('Frage {n}', ['n' => $i + 1]), 'q' => $q];
        }
        $catId = null;
        if (!empty($qz['category']) && is_string($qz['category'])) {
            $catId = q_val('SELECT id FROM categories WHERE LOWER(name) = LOWER(?)', [$qz['category']]);
        }
        $meta = [
            'title' => mb_substr(trim((string)($qz['title'] ?? '')), 0, 150),
            'description' => mb_substr(trim((string)($qz['description'] ?? '')), 0, 1000),
            'language' => array_key_exists($qz['language'] ?? '', QUIZ_LANGUAGES) ? $qz['language'] : null,
            'tags' => array_slice(tags_parse($qz['tags'] ?? []), 0, TAGS_MAX),
            'category_id' => $catId !== null ? (int)$catId : null,
            'show_on_devices' => !empty($qz['show_on_devices']),
            'cover_image' => null,
        ];
        // Titelbild nur speichern, wenn der Editor die Quiz-Angaben übernehmen will
        if (!empty($_POST['adopt']) && is_string($qz['cover_image'] ?? null) && str_starts_with($qz['cover_image'], 'data:')) {
            $meta['cover_image'] = transfer_store_image($qz['cover_image']);
        }
    } else {
        $parsed = transfer_parse_csv($raw);
        $items = $parsed['items'];
        $parseErrors = $parsed['errors'];
    }

    $check = transfer_check_questions($items);
    $errors = array_merge($parseErrors, $check['errors']);
    $lineNo = fn(string $e) => preg_match('/^\D*(\d+)/u', $e, $m) ? (int)$m[1] : 0;
    usort($errors, fn($a, $b) => $lineNo($a) <=> $lineNo($b)); // nach Zeile/Frage sortieren
    if (!$check['questions']) {
        json_error($errors ? t('Keine Frage konnte importiert werden.') : t('In der Datei wurden keine Fragen gefunden.'), 422,
            ['errors' => array_slice($errors, 0, 30)]);
    }
    json_out(['questions' => $check['questions'], 'errors' => array_slice($errors, 0, 30), 'error_count' => count($errors),
        'warnings' => array_slice($check['warnings'], 0, 10), 'meta' => $meta]);
}

// ---------- Spiel (Host) ----------

function api_game_create(): void
{
    $user = api_host();
    $quiz = quiz_playable((int)(input_json()['quiz_id'] ?? 0), $user);
    $result = game_create($user, $quiz);
    if (isset($result['error'])) {
        json_error($result['error'], 422);
    }
    json_out(['game_id' => $result['id'], 'pin' => $result['pin'], 'host_url' => url('/host/' . $result['pin'])], 201);
}

function api_host_game(string $pin): array
{
    $user = api_host();
    session_write_close();
    $game = game_by_pin($pin);
    if (!$game || (int)$game['host_id'] !== (int)$user['id']) {
        json_error(t('Dieses Spiel gibt es nicht oder es gehört dir nicht.'), 404);
    }
    return $game;
}

function api_game_next(string $pin): void
{
    $game = api_host_game($pin);
    $expected = (string)(input_json()['from'] ?? $game['status']);
    if ($error = game_next($game, $expected)) {
        json_error($error, 409);
    }
    json_out(['ok' => true]);
}

function api_game_skip(string $pin): void
{
    $game = api_host_game($pin);
    $expected = (string)(input_json()['from'] ?? $game['status']);
    if ($error = game_skip($game, $expected)) {
        json_error($error, 409);
    }
    json_out(['ok' => true]);
}

function api_game_devices(string $pin): void
{
    $game = api_host_game($pin);
    $on = !empty(input_json()['on']);
    game_set_show_on_devices($game, $on);
    json_out(['ok' => true, 'show_on_devices' => $on]);
}

function api_game_end(string $pin): void
{
    $game = api_host_game($pin);
    game_end($game);
    json_out(['ok' => true, 'report_url' => url('/games/' . $game['id'])]);
}

function api_game_kick(string $pin, int $playerId): void
{
    $game = api_host_game($pin);
    if (!game_kick($game, $playerId)) {
        json_error(t('Diese Person ist nicht im Spiel.'), 404);
    }
    json_out(['ok' => true]);
}

// ---------- Spiel (Spieler und Host) ----------

/** PIN prüfen, bevor der Nickname abgefragt wird. Falsche PINs werden gezählt. */
function api_game_check(string $pin): void
{
    $limitKey = 'pin:' . client_ip();
    if (!rate_limit_allowed($limitKey, 120, 600)) {
        json_error(t('Zu viele falsche PINs. Warte ein paar Minuten.'), 429);
    }
    $game = game_by_pin($pin);
    if (!$game || $game['status'] === 'finished') {
        rate_limit_hit($limitKey, 120, 600);
        json_error(t('Zu dieser PIN läuft gerade kein Spiel. Prüfe die Zahl am Beamer.'), 404);
    }
    json_out(['ok' => true, 'quiz_title' => $game['quiz_title'], 'status' => $game['status']]);
}

function api_game_join(string $pin): void
{
    $limitKey = 'pin:' . client_ip();
    if (!rate_limit_allowed($limitKey, 120, 600)) {
        json_error(t('Zu viele falsche PINs. Warte ein paar Minuten.'), 429);
    }
    $game = game_by_pin($pin);
    if (!$game || $game['status'] === 'finished') {
        rate_limit_hit($limitKey, 120, 600);
        json_error(t('Zu dieser PIN läuft gerade kein Spiel.'), 404);
    }
    $in = input_json();
    $result = game_join($game, (string)($in['nickname'] ?? ''), (string)($in['avatar'] ?? ''));
    if (isset($result['error'])) {
        json_error($result['error'], 422);
    }
    json_out(['ok' => true, 'token' => $result['token'], 'player_id' => (int)$result['player']['id'], 'nickname' => $result['player']['nickname'], 'avatar' => $result['player']['avatar']], 201);
}

function api_player_token(): string
{
    return (string)($_SERVER['HTTP_X_PLAYER_TOKEN'] ?? '');
}

function api_game_answer(string $pin): void
{
    $game = game_by_pin($pin);
    $player = $game ? game_player_by_token($game, api_player_token()) : null;
    if (!$player) {
        json_error(t('Du bist nicht in diesem Spiel. Tritt bitte neu bei.'), 401);
    }
    $in = input_json();
    $error = game_answer($game, $player, (int)($in['question_no'] ?? -1), (int)($in['answer_idx'] ?? -1), $in['value'] ?? null);
    if ($error) {
        json_error($error, 409);
    }
    json_out(['ok' => true]);
}

/** Spieler verlässt die Lobby (z. B. um den Nickname zu ändern) */
function api_game_leave(string $pin): void
{
    $game = game_by_pin($pin);
    $player = $game ? game_player_by_token($game, api_player_token()) : null;
    if (!$player) {
        json_error(t('Du bist nicht in diesem Spiel.'), 401);
    }
    if ($game['status'] !== 'lobby') {
        json_error(t('Das Spiel läuft schon.'), 409);
    }
    q('DELETE FROM players WHERE id = ?', [$player['id']]);
    game_bump((int)$game['id']);
    json_out(['ok' => true]);
}

/**
 * Statusabfrage (Polling, 1×/s).
 * Antwortet 204 ohne Inhalt, wenn sich seit ?since=<version> nichts geändert hat.
 */
function api_game_state(string $pin): void
{
    $game = game_by_pin($pin);
    if (!$game) {
        json_error(t('Dieses Spiel gibt es nicht.'), 404);
    }
    $token = api_player_token();
    $player = null;
    if ($token !== '') {
        $player = game_player_by_token($game, $token);
        if (!$player) {
            json_error(t('Du bist nicht in diesem Spiel. Tritt bitte neu bei.'), 401);
        }
        game_touch_player($player);
    } else {
        $user = current_user();
        session_write_close();
        if (!$user || (int)$user['id'] !== (int)$game['host_id']) {
            json_error(t('Bitte melde dich an.'), 401);
        }
    }

    $game = game_tick($game);
    $since = (int)($_GET['since'] ?? 0);
    if ($since > 0 && $since === (int)$game['state_version']) {
        http_response_code(204);
        header('Cache-Control: no-store');
        exit;
    }
    json_out(game_state($game, $player));
}
