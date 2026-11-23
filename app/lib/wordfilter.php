<?php
// Einfacher Wortfilter für Nicknames.
// Liste bei Bedarf in app/lang/badwords.txt ergänzen (ein Wort pro Zeile).

function nickname_normalize(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's',
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    return preg_replace('/[^a-z]/', '', $s);
}

function nickname_is_offensive(string $nick): bool
{
    static $words = null;
    if ($words === null) {
        $words = ['arsch', 'fick', 'fotze', 'hure', 'wichs', 'nazi', 'hitler', 'heil', 'schwuchtel', 'spast',
            'missgeburt', 'neger', 'nigger', 'nigga', 'kanake', 'schlampe', 'muschi', 'penis', 'vagina', 'porn',
            'fuck', 'shit', 'bitch', 'cunt', 'pussy', 'whore', 'slut', 'retard', 'faggot',
            'wixer', 'wixxer', 'kacke', 'scheiss', 'scheis', 'piss', 'sieg', 'kkk', 'isis', 'opfer', 'behindert'];
        $extra = APP_DIR . '/lang/badwords.txt';
        if (is_file($extra)) {
            foreach (file($extra, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $w) {
                $w = nickname_normalize($w);
                if ($w !== '') {
                    $words[] = $w;
                }
            }
        }
    }
    $n = nickname_normalize($nick);
    // harmlose Wörter, die ein Filterwort enthalten
    $n = str_replace(['marsch', 'harsch', 'barsch', 'sappho'], '', $n);
    // "sieg" nur als ganzes Wort werten, damit z. B. "Siegfried" erlaubt bleibt
    foreach ($words as $w) {
        if ($w === 'sieg' || $w === 'heil' || $w === 'opfer' || $w === 'piss') {
            if ($n === $w || $n === 'siegheil') {
                return true;
            }
            continue;
        }
        if (str_contains($n, $w)) {
            return true;
        }
    }
    return false;
}

/** Prüft einen Nickname; gibt eine Fehlermeldung oder null zurück */
function nickname_error(string $nick): ?string
{
    $len = mb_len($nick);
    if ($len < 3 || $len > 15) {
        return t('Der Nickname muss 3 bis 15 Zeichen lang sein.');
    }
    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _.\-]*$/u', $nick)) {
        return t('Erlaubt sind Buchstaben, Ziffern, Leerzeichen, Punkt, Binde- und Unterstrich.');
    }
    if (nickname_is_offensive($nick)) {
        return t('Bitte wähle einen anderen Nickname.');
    }
    return null;
}
