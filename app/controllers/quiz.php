<?php
// Quiz-Verwaltung (Seiten). Das Speichern im Editor läuft über die API.

function quiz_dashboard(): void
{
    $user = require_login();
    $f = quiz_filters_from_request();
    [$filterSql, $filterParams] = quiz_filter_sql($f);
    $select = 'SELECT q.*, c.name AS category_name, (SELECT COUNT(*) FROM questions x WHERE x.quiz_id = q.id) AS question_count';
    $mine = q_all($select . ' FROM quizzes q LEFT JOIN categories c ON c.id = q.category_id
                   WHERE q.user_id = ?' . $filterSql . ' ORDER BY q.updated_at DESC', array_merge([$user['id']], $filterParams));
    $public = q_all($select . ', u.display_name AS author
                     FROM quizzes q JOIN users u ON u.id = q.user_id LEFT JOIN categories c ON c.id = q.category_id
                     WHERE q.is_public = 1 AND q.user_id <> ? AND u.is_locked = 0' . $filterSql . '
                     ORDER BY q.updated_at DESC LIMIT ' . ($f['active'] ? 200 : 60), array_merge([$user['id']], $filterParams));
    // Häufigste Stichwörter aller Quizze, die der Benutzer sehen darf
    $tagCounts = [];
    foreach (q_all('SELECT q.tags FROM quizzes q JOIN users u ON u.id = q.user_id
                    WHERE q.tags <> \'\' AND (q.user_id = ? OR (q.is_public = 1 AND u.is_locked = 0))', [$user['id']]) as $r) {
        foreach (tags_load($r['tags']) as $tg) {
            $tagCounts[$tg] = ($tagCounts[$tg] ?? 0) + 1;
        }
    }
    arsort($tagCounts);
    $running = q_all("SELECT id, pin, quiz_title, status FROM games WHERE host_id = ? AND status <> 'finished' ORDER BY id DESC", [$user['id']]);
    view('quiz/dashboard', ['title' => t('Meine Quizze'), 'user' => $user, 'mine' => $mine, 'public' => $public, 'running' => $running,
        'f' => $f, 'categories' => categories_all(), 'daysLeft' => account_days_left($user), 'tagCloud' => array_slice($tagCounts, 0, 24, true)]);
}

function quiz_new(): void
{
    $user = require_login();
    csrf_check();
    $id = quiz_create($user);
    redirect('/quiz/' . $id . '/edit');
}

function quiz_edit(int $id): void
{
    $user = require_login();
    $quiz = quiz_owned($id, $user);
    view('quiz/edit', [
        'title' => t('Quiz bearbeiten'),
        'user' => $user,
        'quiz' => quiz_export($quiz),
    ]);
}

function quiz_duplicate_action(int $id): void
{
    $user = require_login();
    csrf_check();
    $quiz = quiz_playable($id, $user);
    $newId = quiz_duplicate($quiz, $user);
    flash('success', t('Die Kopie ist angelegt. Du kannst sie jetzt bearbeiten.'));
    redirect('/quiz/' . $newId . '/edit');
}

function quiz_visibility_action(int $id): void
{
    $user = require_login();
    csrf_check();
    $quiz = quiz_owned($id, $user);
    $public = post('visibility') === 'public' ? 1 : 0;
    q('UPDATE quizzes SET is_public = ?, updated_at = ? WHERE id = ?', [$public, time(), $quiz['id']]);
    flash('success', $public
        ? t('„{title}“ ist jetzt für alle registrierten Quizersteller freigegeben.', ['title' => $quiz['title']])
        : t('„{title}“ ist jetzt nur noch für dich sichtbar.', ['title' => $quiz['title']]));
    redirect('/dashboard');
}

function quiz_delete_action(int $id): void
{
    $user = require_login();
    csrf_check();
    $quiz = quiz_owned($id, $user);
    q('DELETE FROM quizzes WHERE id = ?', [$quiz['id']]);
    flash('success', t('„{title}“ wurde gelöscht.', ['title' => $quiz['title']]));
    redirect('/dashboard');
}

// ---------- Export und CSV-Vorlage ----------

function quiz_export_json(int $id): void
{
    $user = require_login();
    $quiz = quiz_owned($id, $user);
    transfer_send_json([$quiz], transfer_slug($quiz['title']) . '.naguut.json');
}

function quiz_export_csv(int $id): void
{
    $user = require_login();
    $quiz = quiz_owned($id, $user);
    $rows = array_map('transfer_csv_row', quiz_export($quiz)['questions']);
    transfer_send_csv($rows, transfer_slug($quiz['title']) . '.csv');
}

function quiz_export_all(): void
{
    $user = require_login();
    $quizzes = q_all('SELECT * FROM quizzes WHERE user_id = ? ORDER BY title', [$user['id']]);
    if (!$quizzes) {
        flash('info', t('Du hast noch kein Quiz, das du exportieren könntest.'));
        redirect('/dashboard');
    }
    $date = (new DateTimeImmutable('now', app_timezone()))->format('Y-m-d');
    transfer_send_json($quizzes, 'naguut-quizze-' . $date . '.naguut.json');
}

function quiz_import_template(): void
{
    require_login();
    transfer_send_csv(transfer_template_rows(), 'naguut-fragen-vorlage.csv');
}
