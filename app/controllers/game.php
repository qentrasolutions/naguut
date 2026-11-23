<?php
// Spiel starten, Host-Ansicht, Historie und Auswertung

function game_start_action(int $quizId): void
{
    $user = require_login();
    csrf_check();
    $quiz = quiz_playable($quizId, $user);
    $result = game_create($user, $quiz);
    if (isset($result['error'])) {
        flash('error', $result['error']);
        redirect('/dashboard');
    }
    redirect('/host/' . $result['pin']);
}

function game_host_owned_by_pin(string $pin, array $user): array
{
    $game = game_by_pin($pin);
    if (!$game || (int)$game['host_id'] !== (int)$user['id']) {
        abort(404, t('Dieses Spiel gibt es nicht oder es gehört dir nicht.'));
    }
    return $game;
}

function game_host_page(string $pin): void
{
    $user = require_login();
    $game = game_host_owned_by_pin($pin, $user);
    if ($game['status'] === 'finished') {
        redirect('/games/' . $game['id']);
    }
    view('game/host', ['title' => $game['quiz_title'], 'game' => $game], 'layout_game');
}

function game_owned(int $id, array $user): array
{
    $game = game_find($id);
    if (!$game || ((int)$game['host_id'] !== (int)$user['id'] && !is_admin($user))) {
        abort(404, t('Dieses Spiel gibt es nicht oder es gehört dir nicht.'));
    }
    return $game;
}

function game_history(): void
{
    $user = require_login();
    $games = q_all('SELECT g.*, (SELECT COUNT(*) FROM players p WHERE p.game_id = g.id AND p.is_kicked = 0) AS player_count
                    FROM games g WHERE g.host_id = ? ORDER BY g.created_at DESC', [$user['id']]);
    view('game/history', ['title' => t('Spielverlauf'), 'games' => $games, 'retention' => (int)cfg('retention_days', 90)]);
}

/** Auswertungsdaten für Bericht und CSV */
function game_report_data(array $game): array
{
    $questions = game_questions($game);
    $players = q_all('SELECT * FROM players WHERE game_id = ? AND is_kicked = 0 ORDER BY score DESC, joined_at ASC, id ASC', [$game['id']]);
    $responses = q_all('SELECT * FROM responses WHERE game_id = ?', [$game['id']]);
    $byPlayer = [];
    $qStats = array_fill(0, count($questions), ['answered' => 0, 'correct' => 0, 'time_sum' => 0, 'dist' => [], 'value_sum' => 0.0, 'values' => 0]);
    $kicked = array_column(q_all('SELECT id FROM players WHERE game_id = ? AND is_kicked = 1', [$game['id']]), 'id', 'id');

    foreach ($responses as $r) {
        if (isset($kicked[$r['player_id']])) {
            continue;
        }
        $qn = (int)$r['question_no'];
        $byPlayer[$r['player_id']][$qn] = $r;
        if (isset($qStats[$qn])) {
            $qStats[$qn]['answered']++;
            $qStats[$qn]['correct'] += (int)$r['is_correct'];
            $qStats[$qn]['time_sum'] += (int)$r['response_ms'];
            if ($r['answer_value'] !== null) {
                $qStats[$qn]['value_sum'] += (float)$r['answer_value'];
                $qStats[$qn]['values']++;
            }
            $qStats[$qn]['dist'][(int)$r['answer_idx']] = ($qStats[$qn]['dist'][(int)$r['answer_idx']] ?? 0) + 1;
        }
    }
    $playerCount = count($players);
    $skipped = array_flip(game_skipped($game));
    $reached = $game['status'] === 'finished' ? count($questions) : max(0, (int)$game['current_question'] + 1);
    $played = 0;
    for ($i = 0; $i < $reached; $i++) {
        if (!isset($skipped[$i])) {
            $played++;
        }
    }
    $rows = [];
    foreach ($players as $i => $p) {
        $correct = 0;
        foreach ($byPlayer[$p['id']] ?? [] as $r) {
            $correct += (int)$r['is_correct'];
        }
        $rows[] = ['rank' => $i + 1, 'player' => $p, 'correct' => $correct, 'responses' => $byPlayer[$p['id']] ?? []];
    }
    $qRows = [];
    foreach ($questions as $i => $q) {
        $s = $qStats[$i];
        $qRows[] = [
            'no' => $i + 1, 'question' => $q, 'played' => $i < $reached && !isset($skipped[$i]), 'skipped' => isset($skipped[$i]),
            'answered' => $s['answered'], 'correct' => $s['correct'], 'dist' => $s['dist'],
            'rate' => $playerCount ? round(100 * $s['correct'] / $playerCount) : 0,
            'avg_time' => $s['answered'] ? round($s['time_sum'] / $s['answered'] / 1000, 1) : null,
            'avg_value' => $s['values'] ? $s['value_sum'] / $s['values'] : null,
        ];
    }
    $totalCorrect = array_sum(array_column($rows, 'correct'));
    return [
        'questions' => $questions, 'players' => $rows, 'question_rows' => $qRows, 'played' => $played, 'skipped' => $skipped,
        'avg_rate' => ($playerCount && $played) ? round(100 * $totalCorrect / ($playerCount * $played)) : 0,
    ];
}

function game_report(int $id): void
{
    $user = require_login();
    $game = game_owned($id, $user);
    view('game/report', ['title' => t('Auswertung: {title}', ['title' => $game['quiz_title']]), 'game' => $game, 'data' => game_report_data($game)]);
}

/** CSV-Export (UTF-8 mit BOM, Semikolon – öffnet sich direkt in Excel) */
function game_report_csv(int $id): void
{
    $user = require_login();
    $game = game_owned($id, $user);
    $data = game_report_data($game);
    $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v; // Formel-Injection verhindern

    $filename = 'naguut-' . date('Y-m-d', (int)$game['created_at']) . '-' . preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $game['quiz_title']) ?: 'quiz') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . trim($filename, '-') . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $put = fn(array $row) => fputcsv($out, array_map($safe, $row), ';', '"', '', "\r\n");

    $head = [t('Platz'), t('Nickname'), t('Punkte'), t('Richtige Antworten')];
    foreach ($data['questions'] as $i => $q) {
        $head[] = t('Frage {n}', ['n' => $i + 1]);
        $head[] = t('Frage {n} Punkte', ['n' => $i + 1]);
        $head[] = t('Frage {n} Zeit (s)', ['n' => $i + 1]);
    }
    $put($head);
    foreach ($data['players'] as $row) {
        $line = [$row['rank'], $row['player']['nickname'], $row['player']['score'], $row['correct']];
        foreach ($data['questions'] as $i => $q) {
            $r = $row['responses'][$i] ?? null;
            if (isset($data['skipped'][$i])) {
                array_push($line, t('übersprungen'), 0, '');
                continue;
            }
            $verdict = $r ? ($r['is_correct'] ? t('richtig') : t('falsch')) : t('keine Antwort');
            if ($r && $q['type'] === 'txt') {
                $verdict = (string)$r['answer_text'] . ' (' . $verdict . ')';
            } elseif ($r && $q['type'] === 'map' && $r['answer_value'] !== null) {
                $verdict = t('{v} entfernt', ['v' => map_format_km((float)$r['answer_value'])]) . ' (' . $verdict . ')';
            } elseif ($r && $q['type'] === 'est' && $r['answer_value'] !== null && !empty($q['estimate'])) {
                $verdict = est_format((float)$r['answer_value'], $q['estimate']) . ' (' . $verdict . ')';
            }
            $line[] = $verdict;
            $line[] = $r ? $r['points'] : 0;
            $line[] = $r ? number_format($r['response_ms'] / 1000, 1, ',', '') : '';
        }
        $put($line);
    }
    $put([]);
    $put([t('Frage'), t('Fragetext'), t('Richtig in %'), t('Antworten'), t('Ø Zeit (s)')]);
    foreach ($data['question_rows'] as $qr) {
        $put([$qr['no'], rich_plain($qr['question']['text']), $qr['skipped'] ? t('übersprungen') : $qr['rate'], $qr['answered'], $qr['avg_time'] !== null ? number_format($qr['avg_time'], 1, ',', '') : '']);
    }
    fclose($out);
    exit;
}

function game_delete_action(int $id): void
{
    $user = require_login();
    csrf_check();
    $game = game_owned($id, $user);
    game_delete((int)$game['id']);
    flash('success', t('Das Spiel und alle Spielerdaten wurden gelöscht.'));
    redirect('/games');
}
