<?php
// Rechtstexte (Impressum, Datenschutz, AGB): im Backend vom Super-Admin bearbeitbar.
// Gespeichert in der Tabelle settings; solange nichts gespeichert ist, gilt die Vorlage.

const LEGAL_PAGES = [
    'impressum'   => ['key' => 'legal_imprint', 'title' => 'Impressum', 'path' => '/impressum'],
    'datenschutz' => ['key' => 'legal_privacy', 'title' => 'Datenschutzerklärung', 'path' => '/datenschutz'],
    'agb'         => ['key' => 'legal_terms',   'title' => 'Allgemeine Geschäftsbedingungen', 'path' => '/agb'],
];
const LEGAL_MAX_CHARS = 60000;

function legal_text(string $page): string
{
    $saved = setting_get(LEGAL_PAGES[$page]['key']);
    return $saved !== null && trim($saved) !== '' ? $saved : legal_default($page);
}

function legal_is_custom(string $page): bool
{
    $saved = setting_get(LEGAL_PAGES[$page]['key']);
    return $saved !== null && trim($saved) !== '';
}

function legal_updated_at(string $page): ?int
{
    $v = setting_get(LEGAL_PAGES[$page]['key'] . '_updated');
    return $v ? (int)$v : null;
}

function legal_save(string $page, ?string $text): void
{
    $key = LEGAL_PAGES[$page]['key'];
    setting_set($key, $text);
    setting_set($key . '_updated', $text === null ? null : (string)time());
}

/** Vorlagen. Platzhalter in eckigen Klammern bitte ersetzen. */
function legal_default(string $page): string
{
    $op = cfg('operator', []);
    $name = ($op['name'] ?? '') ?: '[Name der Schule oder Organisation]';
    $address = ($op['address'] ?? '') ?: '[Straße, PLZ Ort]';
    $email = ($op['email'] ?? '') ?: '[E-Mail-Adresse]';
    $days = (int)cfg('retention_days', 90);

    return match ($page) {
        'impressum' => <<<MD
## Angaben nach § 5 E-Commerce-Gesetz und § 25 Mediengesetz

$name
$address
E-Mail: $email

## Vertretungsbefugt

[Name der vertretungsbefugten Person]

## Behörde und Aufsicht

[falls zutreffend, z. B. Bildungsdirektion oder Gewerbebehörde]
MD,
        'datenschutz' => <<<MD
**Vorlage – bitte vor dem Livebetrieb prüfen und an die eigene Organisation anpassen.**

## Verantwortlich

$name, $address, $email

## Mitspielende

Zum Mitspielen werden keine personenbezogenen Daten abgefragt. Gespeichert werden nur der frei gewählte Nickname, der gewählte Avatar, die abgegebenen Antworten mit Antwortzeit und die Punkte. Wir empfehlen, keinen echten Namen als Nickname zu verwenden.

Damit ein Neuladen der Seite nicht aus dem Spiel wirft, speichert der Browser einen zufälligen Spielschlüssel im lokalen Speicher (localStorage). Es werden keine Tracking-Cookies verwendet.

## Registrierte Quizersteller

Für ein Konto speichern wir Name, E-Mail-Adresse und ein verschlüsseltes Passwort (Hash) sowie die erstellten Quizze. Rechtsgrundlage ist die Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO). Für die Anmeldung wird ein technisch notwendiges Sitzungs-Cookie gesetzt.

## Speicherdauer

- Spiele mit allen Nicknames und Antworten werden nach $days Tagen automatisch gelöscht, auf Wunsch des Quizerstellers auch sofort.
- Unbestätigte Konten werden nach 7 Tagen gelöscht.
- Ein Konto kann jederzeit im Profil gelöscht werden.

## Hosting

Die Anwendung läuft auf einem Server in der EU. Es werden keine Inhalte von fremden Servern geladen (keine externen Schriften, keine Analyse-Dienste). Der Server protokolliert technisch bedingt IP-Adressen in Zugriffsprotokollen.

## Kartenfragen (OpenStreetMap)

Enthält ein Quiz eine Kartenfrage, lädt der Browser während dieser Frage Kartenbilder von den Servern der OpenStreetMap Foundation (St John’s Innovation Centre, Cowley Road, Cambridge, CB4 0WS, Vereinigtes Königreich). Dabei werden technisch bedingt die IP-Adresse und die Browserkennung übermittelt. Für das Vereinigte Königreich besteht ein Angemessenheitsbeschluss der EU-Kommission. Datenschutzerklärung der OSMF: [osmfoundation.org](https://osmfoundation.org/wiki/Privacy_Policy)

## Deine Rechte

Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch. Beschwerden kannst du an die österreichische Datenschutzbehörde richten: [dsb.gv.at](https://www.dsb.gv.at)
MD,
        'agb' => <<<MD
**Vorlage – bitte vor dem Livebetrieb rechtlich prüfen lassen und anpassen.**

## 1. Geltungsbereich

Diese Bedingungen gelten für die Nutzung der Quiz-Plattform Naguut, betrieben von $name ("Betreiber"). Mit der Registrierung eines Kontos stimmst du ihnen zu. Mitspielende ohne Konto nutzen die Plattform nur zum Beitreten zu Spielen.

## 2. Leistungen

- Der Betreiber stellt die Plattform zum Erstellen und Durchführen von Live-Quizzen bereit.
- Ein Anspruch auf ständige Verfügbarkeit besteht nicht. Wartungsarbeiten können die Nutzung zeitweise einschränken.
- [Die Nutzung ist kostenlos. / Entgelte laut gesonderter Vereinbarung.]

## 3. Konto

- Für das Konto sind wahre Angaben zu machen. Zugangsdaten sind geheim zu halten.
- Der Betreiber kann Konten sperren oder löschen, wenn gegen diese Bedingungen verstoßen wird.

## 4. Inhalte der Nutzerinnen und Nutzer

- Du bist für deine Quizze, Bilder und Musikdateien selbst verantwortlich. Lade nur Inhalte hoch, an denen du die nötigen Rechte hast (insbesondere Urheberrechte an Bildern und Musik).
- Verboten sind rechtswidrige, beleidigende, diskriminierende oder jugendgefährdende Inhalte.
- Quizze sollen keine personenbezogenen Daten von Schülerinnen und Schülern enthalten.
- Gibst du ein Quiz für alle registrierten Quizersteller frei, dürfen diese es spielen und als Kopie übernehmen. Die Freigabe kannst du jederzeit aufheben; bereits angelegte Kopien bleiben bestehen.

## 5. Haftung

Der Betreiber haftet nur für Vorsatz und grobe Fahrlässigkeit, soweit gesetzlich zulässig. Dies gilt nicht für Personenschäden. Für Inhalte der Nutzerinnen und Nutzer übernimmt der Betreiber keine Haftung.

## 6. Änderungen

Der Betreiber kann diese Bedingungen ändern. Registrierte Nutzerinnen und Nutzer werden über wesentliche Änderungen informiert.

## 7. Recht und Gerichtsstand

Es gilt österreichisches Recht unter Ausschluss der Verweisungsnormen. Gerichtsstand ist [Ort], soweit gesetzlich zulässig.
MD,
        default => '',
    };
}

/**
 * Wandelt den gespeicherten Text sicher in HTML um.
 * Erlaubt sind grundlegende HTML-Elemente (siehe RICH_DOC_TAGS) und eine Kurzschreibweise:
 *   ## Überschrift   ### kleinere Überschrift   - Aufzählung   **fett**   [Linktext](https://…)
 * Leerzeile = neuer Absatz, Zeilenumbruch bleibt erhalten.
 * Am Ende geht alles durch rich_sanitize(): nicht erlaubte Elemente und Attribute
 * werden als Text angezeigt bzw. entfernt, Skripte können nicht ausgeführt werden.
 */
function legal_render(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    $blockTag = '/^<\/?(p|h[1-6]|ul|ol|li|blockquote|hr|div|table)\b/i';
    $html = '';
    foreach (preg_split('/\n\s*\n/', $text) as $block) {
        $trimmed = trim($block);
        if ($trimmed === '') {
            continue;
        }
        // Block beginnt mit einem HTML-Blockelement: unverändert übernehmen
        if (preg_match($blockTag, $trimmed)) {
            $html .= $trimmed . "\n";
            continue;
        }
        $lines = array_values(array_filter(array_map('rtrim', explode("\n", $block)), fn($l) => $l !== ''));
        while ($lines && preg_match('/^(#{1,3})\s+(.+)$/', $lines[0], $m)) {
            $level = strlen($m[1]) === 3 ? 3 : 2;
            $html .= "<h$level>" . legal_inline($m[2]) . "</h$level>\n";
            array_shift($lines);
        }
        if (!$lines) {
            continue;
        }
        if (count(array_filter($lines, fn($l) => preg_match('/^\s*[-*]\s+/', $l))) === count($lines)) {
            $html .= "<ul>\n";
            foreach ($lines as $l) {
                $html .= '<li>' . legal_inline(preg_replace('/^\s*[-*]\s+/', '', $l)) . "</li>\n";
            }
            $html .= "</ul>\n";
        } else {
            $html .= '<p>' . implode("<br>\n", array_map('legal_inline', $lines)) . "</p>\n";
        }
    }
    return rich_sanitize($html, RICH_DOC_TAGS);
}

/** Kurzschreibweise innerhalb einer Zeile (noch ohne Escaping – das macht rich_sanitize) */
function legal_inline(string $s): string
{
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', fn($m) => '<a href="' . str_replace('"', '%22', $m[2]) . '">' . $m[1] . '</a>', $s);
}
