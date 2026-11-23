<?php
// Quizze laden, prüfen und speichern

const QUIZ_TIME_LIMITS = [5, 10, 20, 30, 60, 90, 120];
const QUIZ_POINT_FACTORS = [0, 1, 2];
const QUIZ_MAX_QUESTIONS = 100;
const QUIZ_TYPES = ['mc', 'tf', 'est', 'txt', 'ord', 'map'];
const QUIZ_MAX_TEXT = 400;    // Fragetext inklusive Formatierungs-Tags
const QUIZ_MAX_ANSWER = 160;  // Antworttext inklusive Formatierungs-Tags
const QUIZ_LANGUAGES = ['de' => 'Deutsch', 'en' => 'Englisch', 'fr' => 'Französisch', 'it' => 'Italienisch', 'es' => 'Spanisch', 'other' => 'Andere'];

function quiz_find(int $id): ?array
{
    return q_one('SELECT * FROM quizzes WHERE id = ?', [$id]);
}

/** Quiz des Benutzers oder Abbruch mit 404 */
function quiz_owned(int $id, array $user): array
{
    $quiz = quiz_find($id);
    if (!$quiz || (int)$quiz['user_id'] !== (int)$user['id']) {
        abort(404, t('Dieses Quiz gibt es nicht oder es gehört dir nicht.'));
    }
    return $quiz;
}

/** Quiz zum Spielen: eigenes oder öffentliches */
function quiz_playable(int $id, array $user): array
{
    $quiz = quiz_find($id);
    if (!$quiz || ((int)$quiz['user_id'] !== (int)$user['id'] && !$quiz['is_public'])) {
        abort(404, t('Dieses Quiz gibt es nicht oder es ist nicht freigegeben.'));
    }
    return $quiz;
}

/** Vollständiges Quiz als Array (wie im Editor und in der API) */
function quiz_export(array $quiz): array
{
    $questions = q_all('SELECT * FROM questions WHERE quiz_id = ? ORDER BY position', [$quiz['id']]);
    $answersByQ = [];
    if ($questions) {
        $ids = array_column($questions, 'id');
        $rows = q_all('SELECT * FROM answers WHERE question_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY position', $ids);
        foreach ($rows as $a) {
            $answersByQ[$a['question_id']][] = ['text' => $a['text'], 'is_correct' => (bool)$a['is_correct']];
        }
    }
    return [
        'id'          => (int)$quiz['id'],
        'title'       => $quiz['title'],
        'description' => (string)$quiz['description'],
        'cover_image' => $quiz['cover_image'],
        'music'       => $quiz['music'] ?? null,
        'language'    => $quiz['language'],
        'category_id' => isset($quiz['category_id']) ? (int)$quiz['category_id'] : null,
        'tags'        => tags_load($quiz['tags'] ?? ''),
        'is_public'   => (bool)$quiz['is_public'],
        'show_on_devices' => (bool)($quiz['show_on_devices'] ?? false),
        'updated_at'  => (int)$quiz['updated_at'],
        'questions'   => array_map(fn($q) => [
            'type'          => $q['type'],
            'estimate'      => $q['type'] === 'est' ? (json_decode((string)($q['extra'] ?? ''), true) ?: null) : null,
            'map'           => $q['type'] === 'map' ? (json_decode((string)($q['extra'] ?? ''), true) ?: null) : null,
            'text'          => $q['text'],
            'image'         => $q['image'],
            'time_limit_s'  => (int)$q['time_limit_s'],
            'points_factor' => (int)$q['points_factor'],
            'answers'       => $answersByQ[$q['id']] ?? [],
        ], $questions),
    ];
}

/**
 * Prüft Quizdaten aus dem Editor.
 * @return array{0: array, 1: array} [bereinigte Daten, Fehlerliste]
 */
function quiz_validate(array $in): array
{
    $errors = [];
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') {
        $errors[] = t('Gib dem Quiz einen Titel.');
    } elseif (mb_len($title) > 150) {
        $errors[] = t('Der Titel darf höchstens 150 Zeichen haben.');
    }
    $desc = trim((string)($in['description'] ?? ''));
    if (mb_len($desc) > 1000) {
        $errors[] = t('Die Beschreibung darf höchstens 1000 Zeichen haben.');
    }
    $categoryId = (int)($in['category_id'] ?? 0);
    if ($categoryId && !category_exists($categoryId)) {
        $errors[] = t('Die gewählte Kategorie gibt es nicht mehr. Wähle bitte eine andere.');
        $categoryId = 0;
    }
    $tags = tags_parse($in['tags'] ?? []);
    if (count($tags) > TAGS_MAX) {
        $errors[] = t('Höchstens {n} Stichwörter pro Quiz.', ['n' => TAGS_MAX]);
        $tags = array_slice($tags, 0, TAGS_MAX);
    }
    $lang = (string)($in['language'] ?? 'de');
    if (!array_key_exists($lang, QUIZ_LANGUAGES)) {
        $lang = 'de';
    }
    $cover = $in['cover_image'] ?? null;
    if ($cover !== null && $cover !== '' && !is_valid_image_name($cover)) {
        $errors[] = t('Das Titelbild wurde nicht gefunden. Lade es bitte neu hoch.');
        $cover = null;
    }
    $music = $in['music'] ?? null;
    if ($music !== null && $music !== '' && !is_valid_sound_name($music)) {
        $errors[] = t('Die Musikdatei wurde nicht gefunden. Lade sie bitte neu hoch.');
        $music = null;
    }
    $questionsIn = is_array($in['questions'] ?? null) ? array_values($in['questions']) : [];
    if (count($questionsIn) > QUIZ_MAX_QUESTIONS) {
        $errors[] = t('Ein Quiz kann höchstens {n} Fragen haben.', ['n' => QUIZ_MAX_QUESTIONS]);
        $questionsIn = array_slice($questionsIn, 0, QUIZ_MAX_QUESTIONS);
    }

    $questions = [];
    foreach ($questionsIn as $i => $q) {
        $n = $i + 1;
        if (!is_array($q)) {
            $errors[] = t('Frage {n} ist ungültig.', ['n' => $n]);
            continue;
        }
        $type = in_array($q['type'] ?? 'mc', QUIZ_TYPES, true) ? $q['type'] : 'mc';
        $text = trim((string)($q['text'] ?? ''));
        if ($text === '') {
            $errors[] = t('Frage {n}: Der Fragetext fehlt.', ['n' => $n]);
        } elseif (mb_len($text) > QUIZ_MAX_TEXT) {
            $errors[] = t('Frage {n}: Der Fragetext darf höchstens {m} Zeichen haben (inklusive Formatierung).', ['n' => $n, 'm' => QUIZ_MAX_TEXT]);
        } elseif (rich_plain($text) === '') {
            $errors[] = t('Frage {n}: Der Fragetext fehlt.', ['n' => $n]);
        }
        $image = $q['image'] ?? null;
        if ($image !== null && $image !== '' && !is_valid_image_name($image)) {
            $errors[] = t('Frage {n}: Das Bild wurde nicht gefunden.', ['n' => $n]);
            $image = null;
        }
        $time = (int)($q['time_limit_s'] ?? 20);
        if (!in_array($time, QUIZ_TIME_LIMITS, true)) {
            $errors[] = t('Frage {n}: Das Zeitlimit ist ungültig.', ['n' => $n]);
            $time = 20;
        }
        $factor = (int)($q['points_factor'] ?? 1);
        if (!in_array($factor, QUIZ_POINT_FACTORS, true)) {
            $factor = 1;
        }
        $answersIn = is_array($q['answers'] ?? null) ? array_values($q['answers']) : [];
        $answers = [];
        $estimate = null;
        $map = null;
        if ($type === 'map') {
            [$map, $mapErrors] = map_validate($q['map'] ?? [], $n);
            array_push($errors, ...$mapErrors);
        } elseif ($type === 'txt' || $type === 'ord') {
            $max = $type === 'txt' ? TXT_MAX_LEN : QUIZ_MAX_ANSWER;
            foreach ($answersIn as $a) {
                $at = trim((string)($a['text'] ?? ''));
                if ($at === '') {
                    continue;
                }
                if (mb_len($at) > $max) {
                    $errors[] = $type === 'txt'
                        ? t('Frage {n}: Eine richtige Antwort darf höchstens {m} Zeichen haben.', ['n' => $n, 'm' => $max])
                        : t('Frage {n}: Ein Begriff darf höchstens {m} Zeichen haben (inklusive Formatierung).', ['n' => $n, 'm' => $max]);
                    $at = mb_substr($at, 0, $max);
                }
                $answers[] = ['text' => $type === 'txt' ? trim(preg_replace('/\s+/u', ' ', $at)) : $at, 'is_correct' => true];
            }
            if ($type === 'txt' && (count($answers) < 1 || count($answers) > TXT_MAX_ACCEPTED)) {
                $errors[] = t('Frage {n}: Gib 1 bis {m} richtige Antworten an (z. B. verschiedene Schreibweisen).', ['n' => $n, 'm' => TXT_MAX_ACCEPTED]);
            }
            if ($type === 'ord' && (count($answers) < 2 || count($answers) > 4)) {
                $errors[] = t('Frage {n}: Ein Puzzle braucht 2 bis 4 Begriffe.', ['n' => $n]);
            }
        } elseif ($type === 'est') {
            [$estimate, $estErrors] = est_validate($q['estimate'] ?? [], $n);
            array_push($errors, ...$estErrors);
        } elseif ($type === 'tf') {
            $trueCorrect = !empty($answersIn[0]['is_correct']);
            $falseCorrect = !empty($answersIn[1]['is_correct']);
            if ($trueCorrect === $falseCorrect) {
                $errors[] = t('Frage {n}: Wähle aus, ob „Wahr“ oder „Falsch“ richtig ist.', ['n' => $n]);
            }
            $answers = [
                ['text' => t('Wahr'), 'is_correct' => $trueCorrect && !$falseCorrect],
                ['text' => t('Falsch'), 'is_correct' => $falseCorrect && !$trueCorrect],
            ];
        } else {
            foreach ($answersIn as $a) {
                $at = trim((string)($a['text'] ?? ''));
                if ($at === '') {
                    continue;
                }
                if (mb_len($at) > QUIZ_MAX_ANSWER) {
                    $errors[] = t('Frage {n}: Eine Antwort darf höchstens {m} Zeichen haben (inklusive Formatierung).', ['n' => $n, 'm' => QUIZ_MAX_ANSWER]);
                    $at = mb_substr($at, 0, QUIZ_MAX_ANSWER);
                }
                $answers[] = ['text' => $at, 'is_correct' => !empty($a['is_correct'])];
            }
            if (count($answers) < 2 || count($answers) > 4) {
                $errors[] = t('Frage {n}: Es braucht 2 bis 4 ausgefüllte Antworten.', ['n' => $n]);
            } elseif (!array_filter($answers, fn($a) => $a['is_correct'])) {
                $errors[] = t('Frage {n}: Markiere mindestens eine richtige Antwort.', ['n' => $n]);
            }
        }
        $questions[] = [
            'type' => $type, 'text' => $text, 'image' => $image ?: null,
            'time_limit_s' => $time, 'points_factor' => $factor, 'answers' => $answers, 'estimate' => $estimate, 'map' => $map,
        ];
    }

    return [[
        'title' => $title, 'description' => $desc, 'language' => $lang,
        'category_id' => $categoryId ?: null, 'tags' => $tags,
        'cover_image' => $cover ?: null, 'music' => $music ?: null, 'is_public' => !empty($in['is_public']),
        'show_on_devices' => !empty($in['show_on_devices']),
        'questions' => $questions,
    ], $errors];
}

/** Speichert geprüfte Daten (Fragen werden komplett ersetzt) */
function quiz_save(int $quizId, array $data): void
{
    tx(function () use ($quizId, $data) {
        q('UPDATE quizzes SET title = ?, description = ?, language = ?, cover_image = ?, music = ?, is_public = ?, show_on_devices = ?, category_id = ?, tags = ?, updated_at = ? WHERE id = ?', [
            $data['title'], $data['description'], $data['language'], $data['cover_image'], $data['music'] ?? null,
            $data['is_public'] ? 1 : 0, !empty($data['show_on_devices']) ? 1 : 0, $data['category_id'] ?? null, tags_store($data['tags'] ?? []), time(), $quizId,
        ]);
        q('DELETE FROM questions WHERE quiz_id = ?', [$quizId]);
        foreach ($data['questions'] as $pos => $qd) {
            $qid = q_insert('questions', [
                'quiz_id' => $quizId, 'position' => $pos, 'type' => $qd['type'], 'text' => $qd['text'],
                'image' => $qd['image'], 'time_limit_s' => $qd['time_limit_s'], 'points_factor' => $qd['points_factor'],
                'extra' => !empty($qd['estimate']) ? json_encode($qd['estimate']) : (!empty($qd['map']) ? json_encode($qd['map']) : null),
            ]);
            foreach ($qd['answers'] as $apos => $a) {
                q_insert('answers', [
                    'question_id' => $qid, 'position' => $apos, 'text' => $a['text'], 'is_correct' => $a['is_correct'] ? 1 : 0,
                ]);
            }
        }
    });
}

function quiz_create(array $user, string $title = ''): int
{
    $now = time();
    return q_insert('quizzes', [
        'user_id' => $user['id'], 'title' => $title !== '' ? $title : t('Neues Quiz'), 'description' => '',
        'cover_image' => null, 'language' => 'de', 'is_public' => 0, 'created_at' => $now, 'updated_at' => $now,
    ]);
}

function quiz_duplicate(array $quiz, array $user): int
{
    $data = quiz_export($quiz);
    $title = mb_substr(t('{title} (Kopie)', ['title' => $data['title']]), 0, 150);
    $newId = quiz_create($user, $title);
    $data['title'] = $title;
    $data['is_public'] = false;
    quiz_save($newId, $data);
    return $newId;
}

function quiz_question_count(int $quizId): int
{
    return (int)q_val('SELECT COUNT(*) FROM questions WHERE quiz_id = ?', [$quizId]);
}
