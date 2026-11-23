# Naguut 1.8.1

Live-Quiz für Schule und Seminar: Fragen am Beamer, Antworten am Handy, Rangliste nach jeder Runde.
Entwickelt von **Qentra Solutions**.

Läuft auf gewöhnlichem Shared Hosting mit Apache und PHP (getestet für Virtualmin). Keine App,
kein Node.js, keine externen Dienste, keine fremden Schriften – Mitspielende brauchen nur PIN und Nickname.

---

## Voraussetzungen

| | Mindestens |
|---|---|
| PHP | 8.2 (empfohlen 8.3), über PHP-FPM |
| PHP-Erweiterungen | `pdo_mysql`, `mbstring`, `gd` (für Bilder), optional `exif` |
| Datenbank | MySQL 8.0 oder MariaDB 10.6 |
| Webserver | Apache 2.4 mit `mod_rewrite`, `.htaccess` erlaubt (`AllowOverride All`, in Virtualmin Standard) |
| HTTPS | Let’s-Encrypt-Zertifikat (in Virtualmin mit einem Klick) |

## Installation in Virtualmin

1. **PHP-Version wählen**
   Virtualmin → Domain auswählen → *Web Configuration → PHP Options*: PHP 8.2 oder neuer, Modus *FPM*.

2. **Datenbank anlegen**
   *Edit Databases → Create a new database*, z. B. `naguut`. Benutzername und Passwort notieren
   (in Virtualmin meist der Domain-Benutzer).

3. **Dateien hochladen**
   Inhalt der ZIP-Datei nach `public_html/` hochladen (File Manager oder SFTP) und dort entpacken.
   Eine Installation in einem Unterordner (z. B. `public_html/quiz/`) funktioniert ebenfalls.
   Die Ordner `app/` und `uploads/` müssen für den Domain-Benutzer beschreibbar sein (Standard).

4. **SSL einschalten**
   *Server Configuration → SSL Certificate → Let’s Encrypt*. Naguut leitet danach automatisch auf HTTPS um.

5. **Installer aufrufen**
   `https://deine-domain.at/` im Browser öffnen. Der Installer prüft die Voraussetzungen, fragt die
   Datenbank-Zugangsdaten ab, legt die Tabellen und das Super-Admin-Konto an und schreibt `app/config.php`.
   Danach ist er gesperrt.

6. **Rechtstexte anpassen**
   Als Super-Admin unter *Verwaltung → Rechtstexte bearbeiten* Impressum, Datenschutzerklärung und AGB
   ausfüllen. Die mitgelieferten Texte sind Vorlagen mit Platzhaltern in eckigen Klammern – bitte vor dem
   Livebetrieb rechtlich prüfen lassen. Die Konfigurationsdatei muss dafür nicht angefasst werden.

7. **Cronjob für Datenlöschung einrichten**
   *Webmin → System → Scheduled Cron Jobs → Create*:
   - Benutzer: Domain-Benutzer
   - Befehl: `php /home/BENUTZER/public_html/app/cron/cleanup.php`
   - Zeit: täglich, z. B. 3:17 Uhr

   Der Job löscht Spiele nach 90 Tagen (einstellbar über `retention_days`), schließt verwaiste Spiele,
   entfernt unbestätigte Konten nach 7 Tagen und nicht mehr verwendete Bilder. Ohne Cronjob läuft
   die Bereinigung gelegentlich nebenbei mit, aber unregelmäßig.

8. **Kontrolle**
   `https://deine-domain.at/app/config.php` muss mit *403 Forbidden* antworten. Falls nicht,
   erlaubt der Server keine `.htaccess` – dann den Ordner `app/` außerhalb von `public_html` legen
   und in `index.php` den Pfad zu `app/bootstrap.php` anpassen.

### Hoster-Grenzen (wichtig bei großen Klassen)

Jedes Handy fragt einmal pro Sekunde den Spielstand ab. 100 Mitspielende erzeugen also rund
100 kleine Anfragen pro Sekunde. Jede einzelne ist sehr schlank (im Test: Median 3 ms, ohne Änderung
antwortet der Server mit `204 No Content`). Trotzdem bitte prüfen:

- **PHP-FPM-Prozesse je Domain** (`pm.max_children`): mindestens 10, für 100 Spieler besser 20.
  In Virtualmin unter *Web Configuration → PHP-FPM Configuration*.
- **Upload-Limit** (`upload_max_filesize`, `post_max_size`): mindestens 3 MB für Fragebilder,
  für Musikdateien besser 12 MB. In Virtualmin unter *Web Configuration → PHP Options* bzw. in der
  `php.ini` der Domain. Die Obergrenze für Musik selbst steht in `app/config.php` (`max_sound_mb`, Standard 10).
- **Mod_Security**: Manche Hoster blockieren PUT/DELETE. Naguut sendet diese deshalb als POST
  mit `X-HTTP-Method-Override` – es ist nichts weiter zu tun.

Empfehlung aus dem Anforderungsprofil: bis zu 3 gleichzeitige Spiele mit je 100 Personen.
Für mehr parallele Spiele einen vServer einplanen.

### Aktualisieren

Neue Version über die bestehende Installation kopieren. **Nicht überschreiben:** `app/config.php`
und den Ordner `uploads/`. Die Datenbank wird beim ersten Seitenaufruf automatisch ergänzt
(Musik, übersprungene Fragen, Einstellungen, Avatare, Super-Admin).
Beim Update von einer älteren Version wird das älteste Admin-Konto automatisch zum Super-Admin.

---

## Bedienung in Kürze

**Rollen:**

| Rolle | Darf |
|---|---|
| Quizersteller | eigene Quizze erstellen, spielen, auswerten; freigegebene Quizze spielen und kopieren |
| Admin | zusätzlich Konten sperren, löschen, Rollen bis Admin vergeben, Freigaben aufheben, Standard-Musik |
| Super-Admin | zusätzlich neue Konten freischalten, Testzugänge befristen, Rechtstexte bearbeiten, Super-Admins ernennen; nur Super-Admins können Super-Admin-Konten ändern |

**Wer darf ein Quiz verwenden?** Im Editor oder in der Quizliste (Menü „…“) wählst du
*Nur ich* oder *Alle registrierten Quizersteller*. Freigegebene Quizze können alle mit Konto spielen und
als Kopie übernehmen; bearbeiten kann sie nur die Person, die sie erstellt hat. Neue Quizze sind
zunächst *Nur ich*.

**Freischaltung und Testzugang:** Unter *Verwaltung → Neue Konten* legt ein Super-Admin fest, ob neue Konten
freigeschaltet werden müssen (Standard: ja) und wie lange ein Testzugang läuft (0 = unbefristet). Nach der
Bestätigung der E-Mail-Adresse bekommen alle Super-Admins eine E-Mail; das Konto erscheint unter
*Wartet auf Freischaltung*, mit Zähler in der Navigation. Dort *Freischalten* (Ablaufdatum wird vorgeschlagen,
leer = unbefristet) oder *Ablehnen* (Konto wird gelöscht). Die Person bekommt jeweils eine E-Mail. In der
Kontenliste lässt sich das Ablaufdatum jederzeit ändern oder auf *Unbefristet* setzen. Nach Ablauf (Ende des
Tages) ist keine Anmeldung mehr möglich, eine laufende Sitzung endet. Quizersteller sehen in der Quizliste,
bis wann ihr Zugang läuft. Admins und Super-Admins laufen nie ab. Beim Update auf 1.8 gelten alle bestehenden
Konten als freigeschaltet und unbefristet.

**Quizersteller:** Konto anlegen → E-Mail bestätigen → (Freischaltung abwarten) → *Neues Quiz* → Fragen anlegen (Multiple Choice
mit 2–4 Antworten, Wahr/Falsch, Schätzfrage, Antwort eingeben, Puzzle oder Kartenfrage; Bild, Zeitlimit, Punkte) → *Spiel starten*. Am Beamer erscheinen
PIN und QR-Code. Mit *Spiel starten*, *Weiter* und *Nächste Frage* führst du durchs Spiel
(Leertaste oder Presenter funktioniert auch).

**Schätzfrage:** Im Editor *Schätzfrage hinzufügen* wählen und den Bereich frei festlegen: *Von*, *Bis*,
*Schrittweite* (z. B. 1, 0,5 oder 10), *Richtiger Wert*, *Toleranz ±* und optional eine *Einheit*.
Die Mitspielenden schätzen am Handy mit einem Schieberegler (plus Feineinstellung mit − und +).
Ein Volltreffer bringt die vollen Punkte, am Rand der Toleranz die Hälfte, außerhalb nichts; schnelleres
Antworten zählt wie bei den anderen Fragen. Am Beamer zeigt die Auflösung alle Schätzungen als Verteilung
mit richtigem Wert und Toleranzbereich.

**Antwort eingeben:** Die Teilnehmenden tippen einen Begriff (max. 40 Zeichen). Es zählen nur exakte
Treffer; Groß-/Kleinschreibung und Leerzeichen am Rand sind egal. Bis zu 5 richtige Schreibweisen
(z. B. „Klagenfurt“ und „Klagenfurt am Wörthersee“). Der Beamer zeigt nach der Auflösung die häufigsten
Antworten; anstößige falsche Eingaben werden ausgeblendet.

**Puzzle (Reihenfolge):** 2–4 Begriffe in der richtigen Reihenfolge eintragen. Alle sehen sie gemischt
(für alle gleich) und tippen am Handy die Symbole der Reihe nach an. Punkte nur für die ganz richtige Reihenfolge.

**Kartenfrage:** Ort im Editor per Klick auf die Karte setzen, Umkreis in km und optional einen Ortsnamen
angeben. Die Teilnehmenden setzen am Handy einen Pin. Volltreffer = volle Punkte, am Rand des Umkreises die
Hälfte, außerhalb nichts. Die Auflösung zeigt alle Pins mit Avataren, den Umkreis und den Ø-Abstand.
Startausschnitt: systemweit unter *Verwaltung → Kartenausschnitt* (Vorgabe: Österreich), pro Frage
abweichend mit *Eigener Kartenausschnitt*. Die Karte nutzt OpenStreetMap-Kacheln (in `app/config.php`
unter `map` austauschbar); die Datenschutz-Vorlage enthält den passenden Hinweis. Bei bereits angepasster
Datenschutzerklärung bitte den Abschnitt „Kartenfragen“ aus der Vorlage übernehmen.

**Kategorien, Stichwörter und Suche:** Admins legen unter *Verwaltung → Kategorien für Quizze* beliebige
Kategorien an, benennen sie um, sortieren und löschen sie (die Quizze bleiben dann ohne Kategorie erhalten).
Im Editor wählt man eine Kategorie und vergibt bis zu 12 Stichwörter (Enter oder Komma trennt; klein geschrieben,
ohne #). In der Quizliste gibt es eine Suche über Titel, Beschreibung, Stichwörter, Kategorie und Fragetexte
(mehrere Begriffe müssen alle vorkommen), einen Kategoriefilter und anklickbare Stichwörter. Filter gelten für
eigene und für freigegebene Quizze; private Quizze anderer bleiben unsichtbar.

**Export und Import:** In der Quizliste über „…“ *Exportieren (JSON)* (vollständig, mit Bildern) oder
*Exportieren (CSV für Excel)*; *Alle exportieren* lädt alle eigenen Quizze in einer JSON-Datei. Exportieren
lassen sich nur eigene Quizze. Im Editor fügt *Fragen importieren* Fragen aus einer CSV- oder JSON-Datei ans
Ende des Quiz an; gespeichert wird erst mit *Speichern*. Enthält eine JSON-Datei mehrere Quizze, wählt man eines
aus. Ist das Quiz noch leer und heißt *Neues Quiz*, werden auch Titel, Beschreibung, Stichwörter, Kategorie
und Titelbild übernommen – so lässt sich ein exportiertes Quiz vollständig wiederherstellen. Fehlerhafte Zeilen
werden übersprungen und mit Zeilennummer gemeldet. Die CSV-Vorlage (Link im Editor) enthält für jeden
Fragetyp ein Beispiel. CSV: Semikolon, UTF-8 (Excel-Dateien im Windows-Zeichensatz gehen auch), Spalten
*Typ, Frage, Antwort 1–5, Richtig, Zeit (s), Punkte, Von, Bis, Schrittweite, Toleranz, Einheit, Breitengrad,
Längengrad, Umkreis (km), Ort*. Bilder und eigene Kartenausschnitte gibt es nur im JSON-Format, Musik wird nicht
exportiert.

**Formatierung:** Fragen und Antworten können fett, kursiv, unterstrichen, hoch- und tiefgestellt
(z. B. H<sub>2</sub>O, m²) und mit Zeilenumbruch formatiert werden – über die Leiste über dem Fragetext.
Die Rechtstexte erlauben grundlegendes HTML (Absätze, Überschriften, Listen, Links, Hervorhebungen,
Trennlinie). Alles andere – Skripte, Stile, Attribute außer Link-Adressen – wird nicht übernommen.

**Spielen auf Distanz:** Für Runden, in denen nicht alle vor demselben Beamer sitzen (z. B. im Videocall),
zeigt der Distanz-Modus Fragetext, Bild und Antworttexte zusätzlich auf jedem Handy, nach der Auflösung die
richtige Antwort und nach jeder Runde die Top 5. Voreinstellung pro Quiz im Editor („Fragen auch auf den Handys
anzeigen“), im Spiel jederzeit am Beamer mit *Fragen am Handy: an/aus* umschaltbar. In der Lobby erscheint
dann *Link kopieren* für den Beitrittslink. Ausgeschaltet zeigen die Handys wie gewohnt nur die Farbsymbole.

**Frage überspringen:** Während einer Frage *Frage überspringen*, bei der Auflösung *Nicht werten*
(bereits vergebene Punkte werden abgezogen), in der Rangliste *Nächste Frage überspringen*.
Übersprungene Fragen sind in Auswertung und CSV markiert.

**Musik:** Im Editor lässt sich pro Quiz eine Musikdatei hochladen (MP3, OGG, M4A, WAV). Unter
*Verwaltung → Standard-Musik für alle Spiele* legt ein Admin eine Vorgabe fest, die in jedem Spiel
ohne eigene Quiz-Musik läuft. Am Beamer spielt sie in Schleife, wird bei Auflösung und Rangliste
leiser und lässt sich mit *Ton aus* stumm schalten (bleibt im Browser gespeichert). Manche Browser
starten Ton erst nach dem ersten Klick auf der Seite. Unter *Spielverlauf* gibt es die Auswertung und den
CSV-Export für Excel.

**Mitspielende:** Startseite öffnen oder QR-Code scannen → PIN → Nickname und Avatar → mitspielen.
Zur Wahl stehen 20 eigene Avatare (Katze, Hund, Frosch, Eule, Panda, Schwein, Pinguin, Fuchs, Bär, Küken,
Krake, Dino, Monster, Alien, Roboter, Gespenst, Kaktus, Pizza, Einhorn, Faultier). Sie erscheinen in der
Lobby, in der Rangliste und auf dem Podest. Das Handy merkt sich den zuletzt gewählten Avatar.
Neuladen der Seite wirft niemanden aus dem Spiel.

**Vorlauf und Countdown:** Bevor die Antworten freigegeben werden (3 Sekunden), zeigen Beamer und Handys,
wie viel Zeit es für die Frage gibt („20 Sekunden“) und zählen bis zum Start. Während der Frage steht der
Countdown am Handy groß in Gelb oben rechts; in den letzten 5 Sekunden wird er rot.

**Wertung:** Punkte = 1.000 × Faktor × (1 − Antwortzeit / Zeitlimit / 2), gerundet. Wer sofort
richtig antwortet, bekommt fast 1.000 Punkte, wer in der letzten Sekunde richtig antwortet, rund 500.
Gemessen wird ausschließlich auf dem Server. Vor jeder Frage gibt es 3 Sekunden zum Lesen.

---

## Umsetzung des Anforderungsprofils

Alle **Muss**-Anforderungen sind umgesetzt, dazu diese **Soll**-Punkte: F-05 Admin-Verwaltung,
F-15 Punktefaktor, F-17 Duplizieren und öffentliche Quizze, F-35 Wiederverbinden, F-53 automatische
Löschung, aus F-27 Frage überspringen, Frage vorzeitig beenden und Spiel beenden, aus F-28 Musik.

Noch nicht enthalten (für spätere Versionen vorgesehen):

- F-27 *Pausieren* einer laufenden Frage (Überspringen und Beenden gehen)
- F-18 Import/Export als Datei (die JSON-API unter `/api/quizzes/{id}` liefert das Format bereits)
- F-28 Soundeffekte (Musik ist enthalten), F-29 Team-Modus, F-42 Streak-Bonus

Abweichung im Datenmodell: Beim Spielstart wird das Quiz als Kopie (`games.quiz_snapshot`) im
Spiel gespeichert. Antworten beziehen sich auf die Fragennummer im Spiel. So kann ein Quiz während
eines laufenden Spiels bearbeitet oder später gelöscht werden, ohne dass Spiel oder Auswertung
kaputtgehen.

## API (JSON)

| Methode | Pfad | Zweck |
|---|---|---|
| POST | `/api/auth/login` | Anmelden (liefert CSRF-Token) |
| GET / POST | `/api/quizzes` | Quizze auflisten / anlegen |
| GET / PUT / DELETE | `/api/quizzes/{id}` | Quiz lesen, speichern, löschen |
| POST | `/api/quizzes/{id}/images` | Bild hochladen (Feld `image`) |
| POST | `/api/quizzes/{id}/sound` | Musik hochladen (Feld `sound`) |
| POST | `/api/quizzes/{id}/import` | Fragen aus CSV/JSON lesen (Feld `file`, optional `pick`, `adopt`); liefert geprüfte Fragen, speichert nichts |
| GET | `/quiz/{id}/export.json`, `/quiz/{id}/export.csv`, `/quiz/export-all.json` | Export eigener Quizze |
| POST | `/api/games` | Spiel starten (`quiz_id`), liefert PIN |
| GET | `/api/games/{pin}/check` | PIN prüfen |
| POST | `/api/games/{pin}/join` | Beitreten (`nickname`, optional `avatar`), liefert Spielschlüssel |
| POST | `/api/games/{pin}/answer` | Antworten (`question_no`, `answer_idx`; `value` bei Schätzfrage = Zahl, Antwort eingeben = Text, Puzzle = Liste der Anzeigeplätze, Karte = `{lat, lng}`) |
| POST | `/api/games/{pin}/leave` | Lobby verlassen |
| GET | `/api/games/{pin}/state?since={version}` | Spielstand; `204`, wenn unverändert |
| POST | `/api/games/{pin}/next` | Host: weiterschalten (`from` = aktuelle Phase) |
| POST | `/api/games/{pin}/skip` | Host: Frage überspringen (`from` = aktuelle Phase) |
| POST | `/api/games/{pin}/devices` | Host: Distanz-Modus ein/aus (`on`) |
| POST | `/api/games/{pin}/end` | Host: Spiel beenden |
| DELETE | `/api/games/{pin}/players/{id}` | Host: Person entfernen |
| GET | `/api/games/{id}/report.csv` | Ergebnisexport |

Host-Aufrufe brauchen die Sitzung und den Header `X-CSRF-Token`, Spieler-Aufrufe den Header
`X-Player-Token`.

## Projektaufbau

```
index.php            Front-Controller
.htaccess            Umleitungen, Schutz von app/
assets/              CSS, JavaScript (inkl. eigenem QR-Code-Generator), Icons
uploads/             hochgeladene Bilder (keine Skriptausführung)
app/
  bootstrap.php      Start, Konfiguration, Fehlerbehandlung
  routes.php         alle Adressen
  controllers/       Seiten und API
  lib/               Datenbank, Anmeldung, Spiel-Engine, Bilder, Wortfilter, Aufräumen
  views/             HTML-Vorlagen
  lang/de.php        Sprachdatei (weitere Sprachen: Datei kopieren und übersetzen)
  sql/               Datenbankschema (MySQL; SQLite nur für Tests)
  cron/cleanup.php   nächtliche Bereinigung
```

Sicherheit: Passwörter mit Argon2id (bzw. bcrypt, falls der Server kein Argon2 hat), alle Abfragen als Prepared Statements, HTML-Ausgabe escaped,
CSRF-Schutz für alle Formulare, strenge Content-Security-Policy, Ratenbegrenzung für Login und
PIN-Eingabe, Lösungen werden erst nach Fragenende an die Handys geschickt.

---

© Qentra Solutions
