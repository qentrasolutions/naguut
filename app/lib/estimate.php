<?php
// Schätzfrage mit Schieberegler (Fragetyp "est")
//
// Einstellungen je Frage:
//   min, max     Bereich des Schiebereglers (frei wählbar, auch negativ oder mit Kommastellen)
//   step         Schrittweite, z. B. 1, 0.5, 0.1, 10
//   correct      richtiger Wert
//   tolerance    ± Toleranz. Innerhalb gibt es Punkte, je näher, desto mehr.
//   unit         optionale Einheit, z. B. "m", "€", "km/h"
//
// Wertung innerhalb der Toleranz:
//   Punkte = Zeitpunkte × (1 − Abstand / Toleranz / 2)
//   Volltreffer = volle Zeitpunkte, am Rand der Toleranz die Hälfte. Außerhalb: 0 Punkte.

const EST_MAX_STEPS = 100000;

function est_decimals($step): int
{
    $s = rtrim(rtrim(sprintf('%.6F', (float)$step), '0'), '.');
    $p = strpos($s, '.');
    return $p === false ? 0 : min(4, strlen($s) - $p - 1);
}

/** Wert auf den Raster des Schiebereglers runden und in den Bereich zwingen */
function est_snap(float $value, array $est): float
{
    $value = max($est['min'], min($est['max'], $value));
    $steps = round(($value - $est['min']) / $est['step']);
    $v = $est['min'] + $steps * $est['step'];
    return round(max($est['min'], min($est['max'], $v)), est_decimals($est['step']));
}

/** Liest eine Zahl, auch mit Komma oder Tausenderpunkt ("1.250,5") */
function est_parse_number($v): ?float
{
    if (is_int($v) || is_float($v)) {
        return is_finite((float)$v) ? (float)$v : null;
    }
    $s = str_replace([' ', "\u{00A0}", "'"], '', trim((string)$v));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);   // 1.250,5 → 1250.5
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^-?[1-9]\d{0,2}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);   // 1.000 oder 12.500 → Tausenderpunkte
    }
    if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) {
        return null;
    }
    return (float)$s;
}

/**
 * Prüft die Einstellungen einer Schätzfrage.
 * @return array{0: array, 1: string[]}
 */
function est_validate($in, int $n): array
{
    $in = is_array($in) ? $in : [];
    $errors = [];
    $num = function (string $key, string $label) use ($in, $n, &$errors): ?float {
        $v = est_parse_number($in[$key] ?? null);
        if ($v === null) {
            $errors[] = t('Frage {n}: Gib bei „{label}“ eine Zahl ein.', ['n' => $n, 'label' => $label]);
        }
        return $v;
    };
    $min = $num('min', t('Von'));
    $max = $num('max', t('Bis'));
    $step = $num('step', t('Schrittweite'));
    $correct = $num('correct', t('Richtiger Wert'));
    $tol = est_parse_number($in['tolerance'] ?? 0) ?? 0.0;
    $unit = trim((string)($in['unit'] ?? ''));

    if ($min !== null && $max !== null && $min >= $max) {
        $errors[] = t('Frage {n}: „Von“ muss kleiner sein als „Bis“.', ['n' => $n]);
    }
    if ($step !== null && $step <= 0) {
        $errors[] = t('Frage {n}: Die Schrittweite muss größer als 0 sein.', ['n' => $n]);
    }
    if ($min !== null && $max !== null && $step !== null && $step > 0 && $min < $max) {
        $count = ($max - $min) / $step;
        if ($count > EST_MAX_STEPS) {
            $errors[] = t('Frage {n}: Zu viele Schritte. Wähle eine größere Schrittweite (höchstens {m} Schritte).', ['n' => $n, 'm' => number_format(EST_MAX_STEPS, 0, ',', '.')]);
        } elseif ($count < 2) {
            $errors[] = t('Frage {n}: Die Schrittweite ist größer als der halbe Bereich.', ['n' => $n]);
        }
        if ($correct !== null && ($correct < $min || $correct > $max)) {
            $errors[] = t('Frage {n}: Der richtige Wert muss zwischen „Von“ und „Bis“ liegen.', ['n' => $n]);
        }
        if ($tol < 0 || $tol > $max - $min) {
            $errors[] = t('Frage {n}: Die Toleranz muss zwischen 0 und der Breite des Bereichs liegen.', ['n' => $n]);
        }
    }
    if (mb_len($unit) > 12) {
        $errors[] = t('Frage {n}: Die Einheit darf höchstens 12 Zeichen haben.', ['n' => $n]);
    }
    $est = [
        'min' => $min ?? 0.0, 'max' => $max ?? 100.0, 'step' => ($step !== null && $step > 0) ? $step : 1.0,
        'correct' => $correct ?? 0.0, 'tolerance' => max(0.0, $tol), 'unit' => mb_substr($unit, 0, 12),
    ];
    if (!$errors) {
        $est['correct'] = est_snap($est['correct'], $est);
    }
    return [$est, $errors];
}

/** @return array{correct: bool, factor: float} */
function est_score(float $value, array $est): array
{
    $diff = abs($value - $est['correct']);
    $eps = $est['step'] / 1000;
    if ($diff <= $eps) {
        return ['correct' => true, 'factor' => 1.0];
    }
    if ($est['tolerance'] <= 0 || $diff > $est['tolerance'] + $eps) {
        return ['correct' => false, 'factor' => 0.0];
    }
    return ['correct' => true, 'factor' => max(0.5, 1 - min(1, $diff / $est['tolerance']) / 2)];
}

/** Zahl wie in Österreich: 1.250,5 */
function est_format(float $value, array $est, bool $withUnit = true): string
{
    $s = number_format($value, est_decimals($est['step']), ',', '.');
    return $withUnit && $est['unit'] !== '' ? $s . ' ' . $est['unit'] : $s;
}
