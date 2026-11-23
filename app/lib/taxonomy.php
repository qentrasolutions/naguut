<?php
// Kategorien (im Adminbereich frei definierbar) und Stichwörter (Tags) für Quizze

const TAGS_MAX = 12;
const TAG_MAX_LEN = 30;
const CATEGORY_MAX_LEN = 60;

/** @return array<int, array{id:int, name:string, position:int, quiz_count:int}> */
function categories_all(bool $withCounts = false): array
{
    $sql = $withCounts
        ? 'SELECT c.*, (SELECT COUNT(*) FROM quizzes q WHERE q.category_id = c.id) AS quiz_count FROM categories c ORDER BY c.position, c.name'
        : 'SELECT * FROM categories ORDER BY position, name';
    return array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name'], 'position' => (int)$c['position'],
        'quiz_count' => (int)($c['quiz_count'] ?? 0)], q_all($sql));
}

function category_exists(int $id): bool
{
    return (bool)q_val('SELECT COUNT(*) FROM categories WHERE id = ?', [$id]);
}

/**
 * Stichwörter aus Eingabe (Liste oder kommagetrennter Text) bereinigen:
 * klein geschrieben, ohne #, ohne Doppelte.
 * @return string[]
 */
function tags_parse($in): array
{
    $list = is_array($in) ? $in : preg_split('/[,;\n]+/u', (string)$in);
    $out = [];
    foreach ($list as $t) {
        $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', ltrim(trim((string)$t), '#'))), 'UTF-8');
        $t = str_replace(',', '', $t);
        if ($t === '' || in_array($t, $out, true)) {
            continue;
        }
        $out[] = mb_substr($t, 0, TAG_MAX_LEN);
    }
    return $out;
}

/** Speicherform ",tag1,tag2," – so lässt sich exakt nach einem Stichwort filtern */
function tags_store(array $tags): string
{
    return $tags ? ',' . implode(',', $tags) . ',' : '';
}

/** @return string[] */
function tags_load(?string $stored): array
{
    $stored = trim((string)$stored, ',');
    return $stored === '' ? [] : explode(',', $stored);
}

/** LIKE-Muster sicher bauen: % und _ maskieren (Escape-Zeichen ist "!", siehe ESCAPE '!') */
function like_escape(string $s): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
}

/**
 * Filter aus der Adresszeile lesen: ?q=Suchbegriffe&cat=ID|none&tag=stichwort
 * @return array{q:string, cat:string, tag:string, active:bool}
 */
function quiz_filters_from_request(): array
{
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
    $cat = (string)($_GET['cat'] ?? '');
    if ($cat !== 'none' && !ctype_digit($cat)) {
        $cat = '';
    }
    $tag = tags_parse([(string)($_GET['tag'] ?? '')])[0] ?? '';
    return ['q' => $q, 'cat' => $cat, 'tag' => $tag, 'active' => $q !== '' || $cat !== '' || $tag !== ''];
}

/**
 * SQL-Bedingung für Suche und Filter (Tabellen-Alias q für quizzes, c für categories).
 * Jeder Suchbegriff muss in Titel, Beschreibung, Stichwörtern, Kategorie oder einem Fragetext vorkommen.
 * @return array{0: string, 1: array}
 */
function quiz_filter_sql(array $f): array
{
    $where = [];
    $params = [];
    if ($f['cat'] === 'none') {
        $where[] = 'q.category_id IS NULL';
    } elseif ($f['cat'] !== '') {
        $where[] = 'q.category_id = ?';
        $params[] = (int)$f['cat'];
    }
    if ($f['tag'] !== '') {
        $where[] = "q.tags LIKE ? ESCAPE '!'";
        $params[] = '%,' . like_escape($f['tag']) . ',%';
    }
    $terms = array_slice(array_filter(preg_split('/\s+/u', $f['q'])), 0, 6);
    foreach ($terms as $term) {
        $like = '%' . like_escape(mb_strtolower(ltrim($term, '#'), 'UTF-8')) . '%';
        $where[] = "(LOWER(q.title) LIKE ? ESCAPE '!' OR LOWER(q.description) LIKE ? ESCAPE '!' OR q.tags LIKE ? ESCAPE '!'
            OR LOWER(COALESCE(c.name, '')) LIKE ? ESCAPE '!'
            OR EXISTS (SELECT 1 FROM questions x WHERE x.quiz_id = q.id AND LOWER(x.text) LIKE ? ESCAPE '!'))";
        array_push($params, $like, $like, $like, $like, $like);
    }
    return [$where ? ' AND ' . implode(' AND ', $where) : '', $params];
}

/** Link zur Quizliste mit geänderten Filtern */
function quiz_filter_url(array $f, array $change): string
{
    $p = array_filter(array_merge(['q' => $f['q'], 'cat' => $f['cat'], 'tag' => $f['tag']], $change), fn($v) => $v !== '' && $v !== null);
    return url('/dashboard') . ($p ? '?' . http_build_query($p) : '');
}
