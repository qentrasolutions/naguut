<?php
// Aufräumen und Datenlöschung (F-53). Läuft per Cronjob oder gelegentlich nebenbei.

function cleanup_run(): array
{
    $now = time();
    $report = [];

    // 1. Spiele samt Spielern und Antworten nach Ablauf der Aufbewahrungsfrist löschen
    $days = max(1, (int)cfg('retention_days', 90));
    $oldGames = q_all('SELECT id FROM games WHERE created_at < ?', [$now - $days * 86400]);
    foreach ($oldGames as $g) {
        game_delete((int)$g['id']);
    }
    $report['games_deleted'] = count($oldGames);

    // 2. Verwaiste Spiele (12 Stunden ohne Aktivität) beenden
    $st = q("UPDATE games SET status = 'finished', finished_at = ?, state_version = state_version + 1
             WHERE status <> 'finished' AND updated_at < ?", [$now, $now - 12 * 3600]);
    $report['games_closed'] = $st->rowCount();

    // 3. Unbestätigte Konten nach 7 Tagen löschen
    $st = q('DELETE FROM users WHERE verified_at IS NULL AND created_at < ?', [$now - 7 * 86400]);
    $report['unverified_deleted'] = $st->rowCount();

    // 4. Abgelaufene Tokens und alte Ratenbegrenzungen
    q('DELETE FROM password_resets WHERE expires_at < ?', [$now]);
    q('DELETE FROM rate_limits WHERE window_start < ?', [$now - 86400]);

    // 5. Bilder löschen, die nirgends mehr verwendet werden (älter als 1 Tag)
    $report['images_deleted'] = cleanup_images();

    return $report;
}

function game_delete(int $gameId): void
{
    tx(function () use ($gameId) {
        q('DELETE FROM responses WHERE game_id = ?', [$gameId]);
        q('DELETE FROM players WHERE game_id = ?', [$gameId]);
        q('DELETE FROM games WHERE id = ?', [$gameId]);
    });
}

function cleanup_images(): int
{
    $dir = upload_dir();
    $used = [];
    foreach (q_all('SELECT cover_image AS f FROM quizzes WHERE cover_image IS NOT NULL
                    UNION SELECT music AS f FROM quizzes WHERE music IS NOT NULL
                    UNION SELECT image AS f FROM questions WHERE image IS NOT NULL') as $r) {
        $used[$r['f']] = true;
    }
    foreach (q_all('SELECT svalue AS f FROM settings WHERE svalue IS NOT NULL') as $r) {
        $used[$r['f']] = true;
    }
    foreach (q_all("SELECT quiz_snapshot FROM games WHERE status <> 'finished'") as $g) {
        preg_match_all('/[a-f0-9]{24}\.(?:webp|jpg|png|mp3|ogg|m4a|wav)/', $g['quiz_snapshot'], $m);
        foreach ($m[0] as $f) {
            $used[$f] = true;
        }
    }
    $deleted = 0;
    foreach (glob($dir . '/*.{webp,jpg,png,mp3,ogg,m4a,wav}', GLOB_BRACE) ?: [] as $path) {
        $name = basename($path);
        if ((preg_match(IMAGE_NAME_PATTERN, $name) || preg_match(SOUND_NAME_PATTERN, $name)) && !isset($used[$name]) && filemtime($path) < time() - 86400) {
            if (@unlink($path)) {
                $deleted++;
            }
        }
    }
    return $deleted;
}
