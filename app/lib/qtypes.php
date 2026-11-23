<?php
// Weitere Fragetypen:
//   txt  Antwort eingeben – Begriff eintippen, exakte Treffer zählen
//        (Groß-/Kleinschreibung und Leerzeichen am Rand/doppelt werden ignoriert)
//   ord  Puzzle – 2 bis 4 Begriffe in die richtige Reihenfolge bringen (alles oder nichts)
//   map  Karte – Pin auf einer OpenStreetMap-Karte setzen, Punkte nach Entfernung

const TXT_MAX_LEN = 40;
const TXT_MAX_ACCEPTED = 5;
const MAP_DEFAULT_VIEW = ['lat' => 47.6, 'lng' => 13.4, 'zoom' => 7]; // Österreich

// ---------- Antwort eingeben ----------

function txt_normalize(string $s): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    $s = str_replace(['’', '‘', '´', '`'], "'", $s);
    return mb_strtolower($s, 'UTF-8');
}

function txt_is_correct(string $given, array $accepted): bool
{
    $g = txt_normalize($given);
    if ($g === '') {
        return false;
    }
    foreach ($accepted as $a) {
        if (txt_normalize((string)$a['text']) === $g) {
            return true;
        }
    }
    return false;
}

// ---------- Puzzle ----------

/**
 * Anzeige-Reihenfolge der Begriffe: für alle gleich, je Spiel und Frage gemischt, nie schon richtig.
 * @return int[] perm[Anzeigeplatz] = ursprünglicher Index (= richtige Position)
 */
function ord_perm(int $gameId, int $qno, int $n): array
{
    $idx = range(0, $n - 1);
    usort($idx, fn($a, $b) => strcmp(hash('sha256', "$gameId:$qno:$a"), hash('sha256', "$gameId:$qno:$b")));
    if ($n > 1 && $idx === range(0, $n - 1)) {
        $idx[] = array_shift($idx);
    }
    return $idx;
}

/** @param int[] $slots gewählte Anzeigeplätze in der Reihenfolge des Spielers */
function ord_is_correct(array $slots, array $perm): bool
{
    $n = count($perm);
    if (count($slots) !== $n || count(array_unique($slots)) !== $n) {
        return false;
    }
    foreach ($slots as $pos => $slot) {
        if (!isset($perm[$slot]) || $perm[$slot] !== $pos) {
            return false;
        }
    }
    return true;
}

// ---------- Karte ----------

/** Systemweiter Kartenausschnitt (Verwaltung), sonst Österreich */
function map_default_view(): array
{
    $v = json_decode((string)setting_get('map_default_view'), true);
    return is_array($v) && isset($v['lat'], $v['lng'], $v['zoom']) ? map_clean_view($v) : MAP_DEFAULT_VIEW;
}

function map_clean_view($v): ?array
{
    if (!is_array($v) || !isset($v['lat'], $v['lng'], $v['zoom']) || !is_numeric($v['lat']) || !is_numeric($v['lng']) || !is_numeric($v['zoom'])) {
        return null;
    }
    return [
        'lat' => round(max(-85, min(85, (float)$v['lat'])), 5),
        'lng' => round(max(-180, min(180, (float)$v['lng'])), 5),
        'zoom' => (int)max(1, min(18, (int)round((float)$v['zoom']))),
    ];
}

/** Kachel-Server (in config.php änderbar) */
function map_tiles(): array
{
    return [
        'url' => (string)cfg('map.tiles', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => (string)cfg('map.attribution', '© OpenStreetMap-Mitwirkende'),
        'attribution_url' => (string)cfg('map.attribution_url', 'https://www.openstreetmap.org/copyright'),
        'max_zoom' => (int)cfg('map.max_zoom', 18),
    ];
}

/** Konfiguration für das JavaScript der Karte */
function map_client_config(): array
{
    return ['tiles' => map_tiles(), 'default_view' => map_default_view()];
}

/** Herkunft des Kachel-Servers für die Content-Security-Policy */
function map_tile_origin(): string
{
    $u = parse_url(str_replace(['{s}', '{z}', '{x}', '{y}'], ['a', '0', '0', '0'], map_tiles()['url']));
    if (empty($u['host'])) {
        return '';
    }
    $host = str_contains(map_tiles()['url'], '{s}.') ? '*.' . preg_replace('/^a\./', '', $u['host']) : $u['host'];
    return ($u['scheme'] ?? 'https') . '://' . $host;
}

/** Entfernung in km (Haversine) */
function map_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371.0088;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

/** Wertung wie bei der Schätzfrage: Volltreffer = voll, am Rand des Umkreises die Hälfte, außerhalb 0 */
function map_score(float $km, float $radius): array
{
    if ($km <= max(0.05, $radius * 0.03)) {
        return ['correct' => true, 'factor' => 1.0];
    }
    if ($km > $radius) {
        return ['correct' => false, 'factor' => 0.0];
    }
    return ['correct' => true, 'factor' => max(0.5, 1 - $km / $radius / 2)];
}

function map_format_km(float $km): string
{
    if ($km < 1) {
        return number_format($km * 1000, 0, ',', '.') . ' m';
    }
    return number_format($km, $km < 10 ? 1 : 0, ',', '.') . ' km';
}

/** @return array{0: array, 1: string[]} */
function map_validate($in, int $n): array
{
    $in = is_array($in) ? $in : [];
    $errors = [];
    $lat = $in['lat'] ?? null;
    $lng = $in['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng) || abs((float)$lat) > 85 || abs((float)$lng) > 180) {
        $errors[] = t('Frage {n}: Tippe auf die Karte, um den richtigen Ort festzulegen.', ['n' => $n]);
        $lat = $lng = 0;
    }
    $radius = est_parse_number($in['radius_km'] ?? null);
    if ($radius === null || $radius <= 0 || $radius > 5000) {
        $errors[] = t('Frage {n}: Der Umkreis muss zwischen 0 und 5.000 km liegen.', ['n' => $n]);
        $radius = 50.0;
    }
    $view = !empty($in['view']) ? map_clean_view($in['view']) : null;
    $label = trim((string)($in['label'] ?? ''));
    if (mb_len($label) > 60) {
        $errors[] = t('Frage {n}: Die Ortsbezeichnung darf höchstens 60 Zeichen haben.', ['n' => $n]);
    }
    return [[
        'lat' => round((float)$lat, 5), 'lng' => round((float)$lng, 5), 'radius_km' => round($radius, 2),
        'view' => $view, 'label' => mb_substr($label, 0, 60),
    ], $errors];
}
