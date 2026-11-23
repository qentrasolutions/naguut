<?php
// Export eigener Quizze und Import von Fragen
//
// JSON (vollständig, mit Bildern als data:-URL):
//   {"format": "naguut-quiz", "version": 1, "app": "1.8.0", "exported_at": "…", "quizzes": [ {…}, … ]}
//   Jede Frage hat dasselbe Format wie im Editor (type, text, image, time_limit_s, points_factor,
//   answers, estimate, map). Beim Import werden auch ein einzelnes Quiz-Objekt oder eine reine
//   Fragenliste akzeptiert.
//
// CSV (zum Bearbeiten in Excel, ohne Bilder): Semikolon, UTF-8 mit BOM, eine Frage pro Zeile.
//   Spalten siehe CSV_COLUMNS. Excel-Dateien im Windows-Zeichensatz werden ebenfalls gelesen.

const TRANSFER_FORMAT = 'naguut-quiz';
const TRANSFER_VERSION = 1;
const IMPORT_MAX_BYTES = 25 * 1024 * 1024;
const IMPORT_MAX_IMAGES = 120;

/** Spaltenköpfe der CSV-Datei: Schlüssel => Überschrift */
const CSV_COLUMNS = [
    'type' => 'Typ', 'text' => 'Frage',
    'a1' => 'Antwort 1', 'a2' => 'Antwort 2', 'a3' => 'Antwort 3', 'a4' => 'Antwort 4', 'a5' => 'Antwort 5',
    'correct' => 'Richtig', 'time' => 'Zeit (s)', 'points' => 'Punkte',
    'min' => 'Von', 'max' => 'Bis', 'step' => 'Schrittweite', 'tolerance' => 'Toleranz', 'unit' => 'Einheit',
    'lat' => 'Breitengrad', 'lng' => 'Längengrad', 'radius' => 'Umkreis (km)', 'label' => 'Ort',
];

/** Weitere Schreibweisen der Spaltenköpfe (klein, nur Buchstaben und Ziffern) */
const CSV_HEADER_ALIASES = [
    'typ' => 'type', 'type' => 'type', 'fragetyp' => 'type', 'art' => 'type',
    'frage' => 'text', 'fragetext' => 'text', 'question' => 'text',
    'richtig' => 'correct', 'richtigeantwort' => 'correct', 'loesung' => 'correct', 'lösung' => 'correct', 'correct' => 'correct',
    'zeit' => 'time', 'zeits' => 'time', 'zeitlimit' => 'time', 'sekunden' => 'time', 'time' => 'time',
    'punkte' => 'points', 'points' => 'points',
    'von' => 'min', 'min' => 'min', 'bis' => 'max', 'max' => 'max',
    'schrittweite' => 'step', 'schritt' => 'step', 'step' => 'step',
    'toleranz' => 'tolerance', 'tolerance' => 'tolerance', 'einheit' => 'unit', 'unit' => 'unit',
    'breitengrad' => 'lat', 'breite' => 'lat', 'lat' => 'lat', 'latitude' => 'lat',
    'längengrad' => 'lng', 'laengengrad' => 'lng', 'länge' => 'lng', 'laenge' => 'lng', 'lng' => 'lng', 'lon' => 'lng', 'longitude' => 'lng',
    'umkreiskm' => 'radius', 'umkreis' => 'radius', 'radius' => 'radius', 'radiuskm' => 'radius',
    'ort' => 'label', 'ortsname' => 'label', 'label' => 'label',
];

/** Fragetyp aus Klartext (CSV) */
function transfer_type(string $s): ?string
{
    $k = preg_replace('/[^a-z0-9äöüß]/u', '', mb_strtolower(trim($s), 'UTF-8'));
    $map = [
        'mc' => 'mc', 'multiplechoice' => 'mc', 'auswahl' => 'mc', 'mehrfachauswahl' => 'mc', 'quiz' => 'mc',
        'tf' => 'tf', 'wahrfalsch' => 'tf', 'wahroderfalsch' => 'tf', 'richtigfalsch' => 'tf', 'truefalse' => 'tf', 'janein' => 'tf',
        'est' => 'est', 'schätzen' => 'est', 'schaetzen' => 'est', 'schätzfrage' => 'est', 'schaetzfrage' => 'est', 'schieberegler' => 'est', 'slider' => 'est',
        'txt' => 'txt', 'eingabe' => 'txt', 'antworteingeben' => 'txt', 'typeanswer' => 'txt', 'text' => 'txt', 'freitext' => 'txt',
        'ord' => 'ord', 'puzzle' => 'ord', 'reihenfolge' => 'ord', 'sortieren' => 'ord',
        'map' => 'map', 'karte' => 'map', 'kartenfrage' => 'map', 'pin' => 'map',
    ];
    return $map[$k] ?? null;
}

const TRANSFER_TYPE_LABELS = ['mc' => 'Multiple Choice', 'tf' => 'Wahr oder Falsch', 'est' => 'Schätzen', 'txt' => 'Antwort eingeben', 'ord' => 'Puzzle', 'map' => 'Karte'];

// ---------- Export ----------

/** Bild aus uploads als data:-URL (für den JSON-Export) */
function transfer_image_data(?string $file): ?string
{
    if (!$file || !is_valid_image_name($file)) {
        return null;
    }
    $mime = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'][pathinfo($file, PATHINFO_EXTENSION)] ?? null;
    $data = @file_get_contents(upload_dir() . '/' . $file);
    return $mime && $data !== false ? 'data:' . $mime . ';base64,' . base64_encode($data) : null;
}

/** Quiz in das Austauschformat bringen (ohne IDs, Freigabe und Musik) */
function transfer_quiz(array $quiz): array
{
    $x = quiz_export($quiz);
    $cat = $x['category_id'] ? q_val('SELECT name FROM categories WHERE id = ?', [$x['category_id']]) : null;
    return [
        'title' => $x['title'], 'description' => $x['description'], 'language' => $x['language'],
        'category' => $cat, 'tags' => $x['tags'], 'show_on_devices' => $x['show_on_devices'],
        'cover_image' => transfer_image_data($x['cover_image']),
        'questions' => array_map(function ($q) {
            $q['image'] = transfer_image_data($q['image']);
            if ($q['type'] !== 'est') unset($q['estimate']);
            if ($q['type'] !== 'map') unset($q['map']);
            return $q;
        }, $x['questions']),
    ];
}

/** Dateiname aus dem Titel: "Hauptstädte Europas" → "hauptstaedte-europas" */
function transfer_slug(string $title): string
{
    $s = strtr(mb_strtolower($title, 'UTF-8'), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s), '-');
    return substr($s, 0, 60) ?: 'quiz';
}

function transfer_download_headers(string $filename, string $type): void
{
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
}

/** JSON-Export streamen (Quiz für Quiz, damit auch viele Bilder nicht den Speicher sprengen) */
function transfer_send_json(array $quizzes, string $filename): void
{
    transfer_download_headers($filename, 'application/json; charset=utf-8');
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    echo "{\n    \"format\": \"" . TRANSFER_FORMAT . "\",\n    \"version\": " . TRANSFER_VERSION . ",\n    \"app\": " . json_encode(APP_NAME . ' ' . APP_VERSION)
        . ",\n    \"exported_at\": " . json_encode(date('c')) . ",\n    \"quizzes\": [\n";
    foreach (array_values($quizzes) as $i => $quiz) {
        echo ($i ? ",\n" : '') . preg_replace('/^/m', '        ', json_encode(transfer_quiz($quiz), $flags));
        flush();
    }
    echo "\n    ]\n}\n";
}

/** Text für Excel entschärfen: Zellen, die mit = + - @ beginnen, würden als Formel ausgeführt */
function csv_safe(string $s): string
{
    return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
}

function csv_num(?float $v): string
{
    if ($v === null) {
        return '';
    }
    $s = rtrim(rtrim(number_format($v, 6, ',', ''), '0'), ',');
    return $s === '-0' ? '0' : $s;
}

/** Eine Frage als CSV-Zeile (Schlüssel wie CSV_COLUMNS) */
function transfer_csv_row(array $q): array
{
    $row = array_fill_keys(array_keys(CSV_COLUMNS), '');
    $row['type'] = TRANSFER_TYPE_LABELS[$q['type']] ?? $q['type'];
    $row['text'] = csv_safe($q['text']);
    $row['time'] = (string)$q['time_limit_s'];
    $row['points'] = ['0' => 'Keine', '1' => 'Standard', '2' => 'Doppelt'][(string)$q['points_factor']] ?? '1';
    foreach (array_slice($q['answers'], 0, 5) as $i => $a) {
        $row['a' . ($i + 1)] = csv_safe($a['text']);
    }
    switch ($q['type']) {
        case 'mc':
            $nums = [];
            foreach ($q['answers'] as $i => $a) {
                if ($a['is_correct']) $nums[] = $i + 1;
            }
            $row['correct'] = implode(',', $nums);
            break;
        case 'tf':
            $row['a1'] = $row['a2'] = '';
            $row['correct'] = !empty($q['answers'][0]['is_correct']) ? 'wahr' : 'falsch';
            break;
        case 'est':
            $e = $q['estimate'] ?? [];
            $row['correct'] = csv_num((float)($e['correct'] ?? 0));
            $row['min'] = csv_num((float)($e['min'] ?? 0));
            $row['max'] = csv_num((float)($e['max'] ?? 0));
            $row['step'] = csv_num((float)($e['step'] ?? 1));
            $row['tolerance'] = csv_num((float)($e['tolerance'] ?? 0));
            $row['unit'] = csv_safe((string)($e['unit'] ?? ''));
            break;
        case 'map':
            $m = $q['map'] ?? [];
            $row['lat'] = csv_num((float)($m['lat'] ?? 0));
            $row['lng'] = csv_num((float)($m['lng'] ?? 0));
            $row['radius'] = csv_num((float)($m['radius_km'] ?? 50));
            $row['label'] = csv_safe((string)($m['label'] ?? ''));
            break;
    }
    return $row;
}

/** @param array[] $rows Zeilen mit Schlüsseln wie CSV_COLUMNS */
function transfer_send_csv(array $rows, string $filename): void
{
    transfer_download_headers($filename, 'text/csv; charset=utf-8');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_values(CSV_COLUMNS), ';', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, array_values($r), ';', '"', '');
    }
    fclose($out);
}

/** Beispielzeilen für die CSV-Vorlage – je Fragetyp eine */
function transfer_template_rows(): array
{
    $r = fn(array $v) => array_merge(array_fill_keys(array_keys(CSV_COLUMNS), ''), $v);
    return [
        $r(['type' => 'Multiple Choice', 'text' => 'Wie heißt die Hauptstadt von Österreich?', 'a1' => 'Graz', 'a2' => 'Wien', 'a3' => 'Linz', 'a4' => 'Salzburg', 'correct' => '2', 'time' => '20', 'points' => 'Standard']),
        $r(['type' => 'Multiple Choice', 'text' => 'Welche Zahlen sind <b>gerade</b>?', 'a1' => '2', 'a2' => '3', 'a3' => '8', 'correct' => '1,3', 'time' => '20', 'points' => 'Standard']),
        $r(['type' => 'Wahr oder Falsch', 'text' => 'Der Großglockner ist der höchste Berg Österreichs.', 'correct' => 'wahr', 'time' => '10', 'points' => 'Standard']),
        $r(['type' => 'Antwort eingeben', 'text' => 'Welches chemische Element hat das Zeichen Fe?', 'a1' => 'Eisen', 'a2' => 'Ferrum', 'time' => '20', 'points' => 'Standard']),
        $r(['type' => 'Puzzle', 'text' => 'Ordne die Planeten nach ihrem Abstand zur Sonne.', 'a1' => 'Merkur', 'a2' => 'Venus', 'a3' => 'Erde', 'a4' => 'Mars', 'time' => '30', 'points' => 'Doppelt']),
        $r(['type' => 'Schätzen', 'text' => 'Wie hoch ist der Großglockner?', 'correct' => '3798', 'time' => '30', 'points' => 'Standard', 'min' => '2000', 'max' => '5000', 'step' => '1', 'tolerance' => '200', 'unit' => 'm']),
        $r(['type' => 'Karte', 'text' => 'Wo steht der Stephansdom?', 'time' => '30', 'points' => 'Standard', 'lat' => '48,20849', 'lng' => '16,37208', 'radius' => '20', 'label' => 'Stephansdom']),
    ];
}

// ---------- Import ----------

/** Fehlermeldung des Validators auf Zeile/Frage der Importdatei umschreiben */
function transfer_relabel(string $msg, string $label): string
{
    return preg_replace('/^' . preg_quote(t('Frage {n}', ['n' => 1]), '/') . ':/u', $label . ':', $msg);
}

/** Zahl aus CSV, Komma oder Punkt als Dezimalzeichen (für Koordinaten) */
function transfer_coord(string $s): ?float
{
    $s = str_replace([' ', "\u{00A0}"], '', trim($s));
    $s = str_replace(',', '.', $s);
    return preg_match('/^-?\d+(\.\d+)?$/', $s) ? (float)$s : null;
}

/** Ein Wert aus Excel: führendes ' (Formelschutz) entfernen */
function csv_cell(?string $v): string
{
    $v = trim((string)$v);
    return preg_match("/^'[=+\\-@]/", $v) ? substr($v, 1) : $v;
}

/**
 * CSV-Text in Fragen umwandeln.
 * @return array{items: array<int, array{label:string, q:array}>, errors: string[]}
 */
function transfer_parse_csv(string $raw): array
{
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    } elseif (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'); // Excel unter Windows
    }
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $firstLine = strtok($raw, "\n") ?: '';
    $counts = [';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t"), ',' => substr_count($firstLine, ',')];
    arsort($counts);
    $delim = (string)array_key_first($counts);

    $fh = fopen('php://memory', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    $rows = [];
    while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        $rows[] = $r;
    }
    fclose($fh);

    // Spalten über die Kopfzeile zuordnen, sonst Standardreihenfolge
    $keys = array_keys(CSV_COLUMNS);
    $colMap = $keys;
    $start = 0;
    $head = array_map(fn($h) => preg_replace('/[^a-z0-9äöüß]/u', '', mb_strtolower(csv_cell($h), 'UTF-8')), $rows[0] ?? []);
    if (in_array('frage', $head, true) || in_array('typ', $head, true) || in_array('question', $head, true)) {
        $start = 1;
        $colMap = [];
        foreach ($head as $i => $h) {
            if (preg_match('/^(antwort|answer|begriff)(\d)$/u', $h, $m) && $m[2] >= 1 && $m[2] <= 5) {
                $colMap[$i] = 'a' . $m[2];
            } else {
                $colMap[$i] = CSV_HEADER_ALIASES[$h] ?? null;
            }
        }
    }

    $items = [];
    $errors = [];
    for ($li = $start; $li < count($rows); $li++) {
        $c = array_fill_keys($keys, '');
        foreach ($rows[$li] as $i => $v) {
            if (isset($colMap[$i]) && $colMap[$i] !== null) {
                $c[$colMap[$i]] = csv_cell($v);
            }
        }
        if (implode('', $c) === '') {
            continue; // Leerzeile
        }
        $label = t('Zeile {n}', ['n' => $li + 1]);
        $type = $c['type'] === '' ? 'mc' : transfer_type($c['type']);
        if ($type === null) {
            $errors[] = t('{label}: Den Fragetyp „{type}“ kenne ich nicht. Möglich sind: {list}.', [
                'label' => $label, 'type' => $c['type'], 'list' => implode(', ', array_map('t', TRANSFER_TYPE_LABELS))]);
            continue;
        }
        $items[] = ['label' => $label, 'q' => transfer_csv_question($type, $c)];
    }
    return ['items' => $items, 'errors' => $errors];
}

/** Zeitlimit auf den nächsten erlaubten Wert runden */
function transfer_time(string $v, string $type): int
{
    $default = in_array($type, ['est', 'map', 'ord'], true) ? 30 : 20;
    if (!preg_match('/^\d+/', $v, $m)) {
        return $default;
    }
    $n = (int)$m[0];
    $best = QUIZ_TIME_LIMITS[0];
    foreach (QUIZ_TIME_LIMITS as $t) {
        if (abs($t - $n) < abs($best - $n)) {
            $best = $t;
        }
    }
    return $best;
}

function transfer_points(string $v): int
{
    $k = mb_strtolower(trim($v), 'UTF-8');
    return ['0' => 0, 'keine' => 0, 'ohne' => 0, '2' => 2, 'doppelt' => 2, 'double' => 2][$k] ?? 1;
}

function transfer_csv_question(string $type, array $c): array
{
    $q = ['type' => $type, 'text' => $c['text'], 'image' => null, 'time_limit_s' => transfer_time($c['time'], $type),
        'points_factor' => transfer_points($c['points']), 'answers' => [], 'estimate' => null, 'map' => null];
    $texts = [$c['a1'], $c['a2'], $c['a3'], $c['a4'], $c['a5']];
    switch ($type) {
        case 'mc':
            // "Richtig": Nummern (1,3) oder Buchstaben (A, C) der richtigen Antworten
            $right = [];
            foreach (preg_split('/[^0-9a-z]+/i', mb_strtolower($c['correct'], 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) as $tok) {
                if (ctype_digit($tok)) $right[] = (int)$tok;
                elseif (strlen($tok) === 1 && $tok >= 'a' && $tok <= 'e') $right[] = ord($tok) - 96;
            }
            // Alternativ steht in "Richtig" der Text der richtigen Antwort
            if (!$right && $c['correct'] !== '') {
                foreach ($texts as $i => $tx) {
                    if ($tx !== '' && txt_normalize($tx) === txt_normalize($c['correct'])) $right[] = $i + 1;
                }
            }
            foreach ($texts as $i => $tx) {
                if ($tx !== '') {
                    // eine 5. Antwort führt beim Prüfen zur Meldung „2 bis 4 Antworten“
                    $q['answers'][] = ['text' => $tx, 'is_correct' => in_array($i + 1, $right, true)];
                }
            }
            break;
        case 'tf':
            $k = mb_strtolower(trim($c['correct']), 'UTF-8');
            $true = in_array($k, ['wahr', 'w', 'ja', 'j', 'true', 't', 'richtig', 'r', '1', 'yes', 'y', 'x'], true);
            $false = in_array($k, ['falsch', 'f', 'nein', 'n', 'false', '0', '2', 'no'], true);
            $q['answers'] = [['text' => t('Wahr'), 'is_correct' => $true], ['text' => t('Falsch'), 'is_correct' => $false]];
            break;
        case 'txt':
        case 'ord':
            foreach ($texts as $tx) {
                if ($tx !== '') $q['answers'][] = ['text' => $tx, 'is_correct' => true];
            }
            // Bei "Antwort eingeben" darf die Lösung auch nur in "Richtig" stehen
            if ($type === 'txt' && !$q['answers'] && $c['correct'] !== '') {
                $q['answers'][] = ['text' => $c['correct'], 'is_correct' => true];
            }
            break;
        case 'est':
            $q['estimate'] = ['min' => $c['min'], 'max' => $c['max'], 'step' => $c['step'] !== '' ? $c['step'] : '1',
                'correct' => $c['correct'], 'tolerance' => $c['tolerance'] !== '' ? $c['tolerance'] : '0', 'unit' => $c['unit']];
            break;
        case 'map':
            $lat = transfer_coord($c['lat']);
            $lng = transfer_coord($c['lng']);
            if (($lat === null || $lng === null) && preg_match('/^\s*(-?\d+(?:[.,]\d+)?)\s*[;\/ ]\s*(-?\d+(?:[.,]\d+)?)\s*$|^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $c['correct'], $m)) {
                // "48.2085, 16.3721" in der Spalte "Richtig"
                $lat = transfer_coord(($m[1] ?? '') !== '' ? $m[1] : $m[3]);
                $lng = transfer_coord(($m[2] ?? '') !== '' ? $m[2] : $m[4]);
            }
            $q['map'] = ['lat' => $lat, 'lng' => $lng, 'radius_km' => $c['radius'] !== '' ? $c['radius'] : '50', 'view' => null, 'label' => $c['label']];
            break;
    }
    return $q;
}

/** Bild aus data:-URL speichern (wird dabei neu kodiert und verkleinert) */
function transfer_store_image(string $dataUrl): ?string
{
    if (!preg_match('#^data:image/(webp|jpeg|jpg|png);base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)
        || strlen($m[2]) > IMAGE_MAX_BYTES * 4 / 3 + 1024) {
        return null;
    }
    $bin = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
    if ($bin === false) {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'ngi');
    file_put_contents($tmp, $bin);
    $res = store_uploaded_image(['error' => UPLOAD_ERR_OK, 'size' => strlen($bin), 'tmp_name' => $tmp]);
    @unlink($tmp);
    return $res['file'] ?? null;
}

/**
 * JSON-Datei lesen.
 * @return array{quizzes?: array[], error?: string}
 */
function transfer_parse_json(string $raw): array
{
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['error' => t('Die Datei ist kein gültiges JSON. Exportiere das Quiz noch einmal aus Naguut.')];
    }
    if (isset($data['quiz']) && is_array($data['quiz'])) {
        $data = $data['quiz']; // Antwort der API
    }
    if (isset($data['quizzes']) && is_array($data['quizzes'])) {
        $quizzes = array_values(array_filter($data['quizzes'], 'is_array'));
    } elseif (isset($data['questions']) && is_array($data['questions'])) {
        $quizzes = [$data];
    } elseif (array_is_list($data) && $data && is_array($data[0]) && isset($data[0]['text'])) {
        $quizzes = [['title' => '', 'questions' => $data]];
    } else {
        return ['error' => t('In der Datei wurden keine Fragen gefunden.')];
    }
    if (!$quizzes) {
        return ['error' => t('In der Datei wurden keine Fragen gefunden.')];
    }
    return ['quizzes' => $quizzes];
}

/**
 * Fragen eines Quiz aus der Importdatei prüfen; gültige übernehmen, Bilder speichern.
 * @param array<int, array{label:string, q:array}> $items
 * @return array{questions: array[], errors: string[], warnings: string[]}
 */
function transfer_check_questions(array $items): array
{
    $ok = [];
    $errors = [];
    $warnings = [];
    $images = 0;
    foreach (array_slice($items, 0, QUIZ_MAX_QUESTIONS * 2) as $item) {
        $q = $item['q'];
        if (!is_array($q)) {
            $errors[] = t('{label}: Die Frage ist ungültig.', ['label' => $item['label']]);
            continue;
        }
        // Bild: data:-URL speichern; bekannter Dateiname bleibt; sonst ohne Bild
        $img = $q['image'] ?? null;
        $q['image'] = null;
        if (is_string($img) && $img !== '') {
            if (str_starts_with($img, 'data:') && $images < IMPORT_MAX_IMAGES) {
                $images++;
                $q['image'] = transfer_store_image($img);
            } elseif (is_valid_image_name($img)) {
                $q['image'] = $img;
            }
            if ($q['image'] === null) {
                $warnings[] = t('{label}: Das Bild konnte nicht übernommen werden. Die Frage ist ohne Bild importiert.', ['label' => $item['label']]);
            }
        }
        [$data, $errs] = quiz_validate(['title' => 'Import', 'questions' => [$q]]);
        if ($errs) {
            foreach ($errs as $e) {
                $errors[] = transfer_relabel($e, $item['label']);
            }
            continue;
        }
        $ok[] = $data['questions'][0];
    }
    if (count($items) > QUIZ_MAX_QUESTIONS * 2) {
        $warnings[] = t('Die Datei enthält sehr viele Fragen. Nur die ersten {n} wurden gelesen.', ['n' => QUIZ_MAX_QUESTIONS * 2]);
    }
    return ['questions' => $ok, 'errors' => $errors, 'warnings' => $warnings];
}
