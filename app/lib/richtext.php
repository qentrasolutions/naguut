<?php
// Erlaubte HTML-Elemente sicher ausgeben.
// Alles, was nicht ausdrücklich erlaubt ist, wird als Text angezeigt (escaped).
// Attribute sind verboten – einzige Ausnahme: href bei Links (nur http, https, mailto, relativ).
// Nicht geschlossene Elemente werden automatisch geschlossen.

const RICH_INLINE_TAGS = ['b', 'strong', 'i', 'em', 'u', 'sub', 'sup', 'br'];
const RICH_DOC_TAGS = ['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'small',
    'ul', 'ol', 'li', 'a', 'blockquote', 'hr', 'code'];
const RICH_VOID_TAGS = ['br', 'hr'];

/** Text escapen, vorhandene HTML-Entities wie &amp; oder &#169; aber erhalten */
function rich_text(string $s): string
{
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    return $s;
}

function rich_href(string $attrs): ?string
{
    if (!preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $m)) {
        return null;
    }
    $href = trim(html_entity_decode($m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : ($m[4] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $href = preg_replace('/[\x00-\x20]+/', '', $href); // "java script:" usw. zusammenziehen
    if (preg_match('#^(https?://|mailto:)#i', $href)) {
        return $href;
    }
    if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
        return url($href);
    }
    if (str_starts_with($href, '#')) {
        return $href;
    }
    return null;
}

/**
 * Lässt nur die Tags aus $allowed durch.
 * @param string[] $allowed
 */
function rich_sanitize(string $html, array $allowed): string
{
    $out = '';
    $stack = [];
    $pos = 0;
    $len = strlen($html);
    while ($pos < $len && preg_match('/<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b([^<>]*)>/', $html, $m, PREG_OFFSET_CAPTURE, $pos)) {
        $start = $m[0][1];
        $out .= rich_text(substr($html, $pos, $start - $pos));
        $pos = $start + strlen($m[0][0]);
        $closing = $m[1][0] === '/';
        $tag = strtolower($m[2][0]);
        if (!in_array($tag, $allowed, true)) {
            $out .= rich_text($m[0][0]); // unbekanntes Tag sichtbar als Text
            continue;
        }
        if (in_array($tag, RICH_VOID_TAGS, true)) {
            if (!$closing) {
                $out .= '<' . $tag . '>';
            }
            continue;
        }
        if ($closing) {
            $idx = array_search($tag, array_reverse($stack, true), true);
            if ($idx === false) {
                continue; // Schließ-Tag ohne Gegenstück verwerfen
            }
            while ($stack) {
                $open = array_pop($stack);
                $out .= '</' . $open . '>';
                if ($open === $tag) {
                    break;
                }
            }
            continue;
        }
        if ($tag === 'a') {
            $href = rich_href($m[3][0]);
            if ($href === null) {
                $out .= '<a>';
            } else {
                $external = preg_match('#^https?://#i', $href) ? ' rel="noopener" target="_blank"' : '';
                $out .= '<a href="' . e($href) . '"' . $external . '>';
            }
        } else {
            $out .= '<' . $tag . '>';
        }
        $stack[] = $tag;
    }
    $out .= rich_text(substr($html, $pos));
    while ($stack) {
        $out .= '</' . array_pop($stack) . '>';
    }
    return $out;
}

/** Fragetexte und Antworten: fett, kursiv, unterstrichen, hoch/tief, Zeilenumbruch */
function rich_inline(?string $text): string
{
    static $cache = [];
    $text = (string)$text;
    return $cache[$text] ??= rich_sanitize($text, RICH_INLINE_TAGS);
}

/** Reiner Text ohne Tags (CSV, title-Attribute, Längenprüfung) */
function rich_plain(?string $text): string
{
    $s = preg_replace('/<br\s*\/?>/i', ' ', (string)$text);
    $s = strip_tags($s);
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}
