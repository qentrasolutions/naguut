<?php
$bodyClass = 'page-editor';
$scripts = ['js/richtext.js', 'js/map.js', 'js/editor.js'];
$i18n = [
    'Frage', 'Fragen', 'Multiple Choice', 'Wahr oder Falsch', 'Wahr', 'Falsch', 'Fragetext', 'Antwort {n}',
    'richtig', 'Bild hinzufügen', 'Bild entfernen', 'Zeitlimit', 'Punkte', 'Standard', 'Doppelt', 'Keine',
    'Sekunden', 'Nach oben', 'Nach unten', 'Duplizieren', 'Löschen', 'Antwort hinzufügen', 'Antwort entfernen',
    'Zum Verschieben ziehen', 'Gespeichert', 'Nicht gespeicherte Änderungen', 'Wird gespeichert …', 'Speichern fehlgeschlagen',
    'Bild wird hochgeladen …', 'Diese Frage löschen?', 'Noch keine Fragen. Füge unten die erste hinzu.',
    'Die Verbindung zum Server ist unterbrochen. Prüfe das Internet und speichere erneut.',
    'Bitte zuerst die Fehler beheben.', 'Wer richtig antwortet', 'Bild der Frage',
    'Musik hochladen', 'Andere Datei wählen', 'Musik entfernen', 'Musik wird hochgeladen …',
    'Die Sounddatei darf höchstens {n} MB groß sein.',
    'Schätzfrage (Schieberegler)', 'Von', 'Bis', 'Schrittweite', 'Richtiger Wert', 'Toleranz ±', 'Einheit', 'z. B. m, €, km/h',
    'Gib „Von“, „Bis“ und den richtigen Wert als Zahlen ein.',
    'Volle Punkte bei {c}. Punkte gibt es zwischen {a} und {b}, je näher, desto mehr.', 'Punkte gibt es nur für genau {c}.',
    'Formatierung', 'Fett', 'Kursiv', 'Unterstrichen', 'Hochgestellt', 'Tiefgestellt', 'Zeilenumbruch',
    'wirkt auf das zuletzt angeklickte Feld',
    'Stichwort entfernen: {t}', 'Höchstens {n} Stichwörter pro Quiz.',
    'Antwort eingeben', 'Puzzle (Reihenfolge)', 'Karte (Pin setzen)', 'Richtige Antwort {n}', 'Begriff {n}', 'Entfernen',
    'Weitere Schreibweise', 'Begriff hinzufügen',
    'Zählt nur bei exakter Übereinstimmung. Groß-/Kleinschreibung und Leerzeichen am Rand sind egal. Trage Schreibvarianten als weitere richtige Antworten ein.',
    'In der richtigen Reihenfolge eintragen. Die Teilnehmenden bekommen die Begriffe gemischt und tippen sie der Reihe nach an.',
    'Tippe auf die Karte, um den richtigen Ort zu setzen.', 'Richtiger Ort: {lat}, {lng}', 'Ortsname (wird bei der Auflösung gezeigt)',
    'z. B. Stephansdom', 'Umkreis für Punkte (km)', 'Volltreffer = volle Punkte, am Rand des Umkreises die Hälfte, außerhalb keine.',
    'Eigener Kartenausschnitt für diese Frage', 'Sonst gilt der Standard-Ausschnitt aus der Verwaltung.', 'Aktuellen Ausschnitt übernehmen',
    'Fragen importieren …', 'Wird importiert …', '{n} Fragen importiert.', '1 Frage importiert.',
    'Klicke auf „Speichern“, um sie zu behalten.', 'Titel, Beschreibung und Stichwörter wurden aus der Datei übernommen.',
    '{n} Zeilen bzw. Fragen wurden übersprungen:', 'und {n} weitere', 'Ein Quiz hat höchstens {n} Fragen. {m} Fragen aus der Datei haben keinen Platz mehr.',
    'Das Quiz ist schon voll ({n} Fragen).', 'Die Datei enthält mehrere Quizze. Aus welchem sollen die Fragen kommen?',
    '{title} ({n} Fragen)', '{title} (1 Frage)', 'Fragen übernehmen', 'Abbrechen',
];
?>
<script type="application/json" id="quiz-data"><?= json_encode($quiz, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="i18n"><?= js_strings($i18n) ?></script>
<script type="application/json" id="editor-config"><?= json_encode([
    'saveUrl' => url('/api/quizzes/' . $quiz['id']),
    'imageUrl' => url('/api/quizzes/' . $quiz['id'] . '/images'),
    'uploadBase' => url('/uploads/'),
    'csrf' => csrf_token(),
    'timeLimits' => QUIZ_TIME_LIMITS,
    'maxText' => QUIZ_MAX_TEXT,
    'maxAnswer' => QUIZ_MAX_ANSWER,
    'maxTyped' => TXT_MAX_LEN,
    'maxTags' => TAGS_MAX,
    'map' => map_client_config(),
    'soundUrl' => url('/api/quizzes/' . $quiz['id'] . '/sound'),
    'maxSoundMb' => (int)cfg('max_sound_mb', 10),
    'importUrl' => url('/api/quizzes/' . $quiz['id'] . '/import'),
    'maxQuestions' => QUIZ_MAX_QUESTIONS,
], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?></script>

<div class="editor-bar">
    <a href="<?= e(url('/dashboard')) ?>" class="editor-back"><?= e(t('Alle Quizze')) ?></a>
    <span class="save-state" id="save-state" aria-live="polite"><?= e(t('Gespeichert')) ?></span>
    <div class="editor-bar-actions">
        <button type="button" class="btn btn-ink" id="save-btn"><?= e(t('Speichern')) ?></button>
        <form method="post" action="<?= e(url('/quiz/' . $quiz['id'] . '/play')) ?>" id="play-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sun"><?= e(t('Spiel starten')) ?></button>
        </form>
    </div>
</div>

<div class="editor">
    <div id="editor-errors" class="form-errors" role="alert" hidden></div>

    <section class="panel quiz-settings" aria-labelledby="settings-h">
        <h1 id="settings-h" class="visually-hidden"><?= e(t('Quiz bearbeiten')) ?></h1>
        <div class="settings-grid">
            <div class="settings-main">
                <label class="field field-title">
                    <span><?= e(t('Titel')) ?></span>
                    <input type="text" id="q-title" maxlength="150" required>
                </label>
                <label class="field">
                    <span><?= e(t('Beschreibung')) ?> <small>(<?= e(t('optional')) ?>)</small></span>
                    <textarea id="q-desc" rows="2" maxlength="1000"></textarea>
                </label>
                <div class="settings-row settings-tax">
                    <label class="field">
                        <span><?= e(t('Kategorie')) ?></span>
                        <select id="q-category">
                            <option value=""><?= e(t('Keine Kategorie')) ?></option>
                            <?php foreach (categories_all() as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="field tags-field">
                        <label for="q-tag-input"><span><?= e(t('Stichwörter')) ?></span></label>
                        <div class="tag-input" id="q-tags">
                            <span class="tag-chips" id="q-tag-chips"></span>
                            <input id="q-tag-input" maxlength="<?= TAG_MAX_LEN ?>" placeholder="<?= e(t('Stichwort eingeben, Enter')) ?>" autocomplete="off">
                        </div>
                        <small><?= e(t('Helfen beim Suchen, z. B. „hauptstädte“, „2. klasse“. Höchstens {n}.', ['n' => TAGS_MAX])) ?></small>
                    </div>
                </div>
                <div class="settings-row">
                    <label class="field">
                        <span><?= e(t('Sprache')) ?></span>
                        <select id="q-lang">
                            <?php foreach (QUIZ_LANGUAGES as $code => $label): ?>
                                <option value="<?= e($code) ?>"><?= e(t($label)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <fieldset class="visibility" id="q-visibility">
                        <legend><?= e(t('Wer darf das Quiz verwenden?')) ?></legend>
                        <label class="check">
                            <input type="radio" name="visibility" value="private">
                            <span><?= e(t('Nur ich')) ?><small><?= e(t('Nur du siehst und spielst dieses Quiz.')) ?></small></span>
                        </label>
                        <label class="check">
                            <input type="radio" name="visibility" value="public">
                            <span><?= e(t('Alle registrierten Quizersteller')) ?><small><?= e(t('Alle mit Konto können es spielen und als Kopie übernehmen. Bearbeiten kannst nur du.')) ?></small></span>
                        </label>
                    </fieldset>
                    <label class="check devices-check">
                        <input type="checkbox" id="q-devices">
                        <span><?= e(t('Fragen auch auf den Handys anzeigen')) ?><small><?= e(t('Für Spiele auf Distanz, z. B. im Videocall: Fragetext, Bild und Antworten erscheinen zusätzlich auf jedem Gerät. Lässt sich im Spiel jederzeit umschalten.')) ?></small></span>
                    </label>
                </div>
                <div class="music">
                    <span class="cover-label"><?= e(t('Musik am Beamer')) ?> <small class="muted">(<?= e(t('optional')) ?>)</small></span>
                    <div class="music-box" id="music-box"></div>
                    <small class="muted"><?= e(t('MP3, OGG, M4A oder WAV, höchstens {n} MB. Läuft in Schleife, solange das Spiel läuft; am Beamer lässt sie sich jederzeit stumm schalten.', ['n' => (int)cfg('max_sound_mb', 10)])) ?>
                        <?= e(default_music() ? t('Ohne eigene Datei läuft die Standard-Musik.') : '') ?></small>
                </div>
            </div>
            <div class="cover" id="cover">
                <span class="cover-label"><?= e(t('Titelbild')) ?></span>
                <div class="cover-box" id="cover-box"></div>
            </div>
        </div>
    </section>

    <ol class="questions" id="questions" aria-label="<?= e(t('Fragen')) ?>"></ol>

    <div class="add-question">
        <button type="button" class="btn btn-outline" data-add="mc"><?= e(t('Multiple-Choice-Frage hinzufügen')) ?></button>
        <button type="button" class="btn btn-outline" data-add="tf"><?= e(t('Wahr-oder-Falsch-Frage hinzufügen')) ?></button>
        <button type="button" class="btn btn-outline" data-add="est"><?= e(t('Schätzfrage hinzufügen')) ?></button>
        <button type="button" class="btn btn-outline" data-add="txt"><?= e(t('Antwort-eingeben-Frage hinzufügen')) ?></button>
        <button type="button" class="btn btn-outline" data-add="ord"><?= e(t('Puzzle hinzufügen')) ?></button>
        <button type="button" class="btn btn-outline" data-add="map"><?= e(t('Kartenfrage hinzufügen')) ?></button>
    </div>
    <section class="panel transfer-box" aria-labelledby="transfer-h">
        <h2 id="transfer-h"><?= e(t('Fragen importieren')) ?></h2>
        <p><?= e(t('Füge Fragen aus einer CSV-Datei (z. B. aus Excel) oder aus einem Naguut-Export hinzu. Sie kommen ans Ende der Liste. Gespeichert wird erst mit „Speichern“.')) ?></p>
        <div class="btn-row">
            <button type="button" class="btn btn-outline" id="import-btn"><?= e(t('Fragen importieren …')) ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/quiz/import-vorlage.csv')) ?>" download><?= e(t('CSV-Vorlage herunterladen')) ?></a>
        </div>
        <div class="import-choose" id="import-choose" hidden>
            <label class="field">
                <span><?= e(t('Die Datei enthält mehrere Quizze. Aus welchem sollen die Fragen kommen?')) ?></span>
                <select id="import-pick"></select>
            </label>
            <div class="btn-row">
                <button type="button" class="btn btn-ink" id="import-pick-btn"><?= e(t('Fragen übernehmen')) ?></button>
                <button type="button" class="btn btn-ghost" id="import-cancel"><?= e(t('Abbrechen')) ?></button>
            </div>
        </div>
        <div class="import-result" id="import-result" role="status" hidden></div>
        <details class="csv-help">
            <summary><?= e(t('So ist die CSV-Datei aufgebaut')) ?></summary>
            <p><?= e(t('Eine Frage pro Zeile, Spalten getrennt durch Semikolon. Die erste Zeile enthält die Spaltennamen; am einfachsten startest du mit der Vorlage.')) ?></p>
            <dl>
                <dt><?= e(t('Typ')) ?></dt><dd><?= e(t('Multiple Choice, Wahr oder Falsch, Antwort eingeben, Puzzle, Schätzen oder Karte. Leer = Multiple Choice.')) ?></dd>
                <dt><?= e(t('Antwort 1 bis 5')) ?></dt><dd><?= e(t('Multiple Choice: 2 bis 4 Antworten. Antwort eingeben: alle richtigen Schreibweisen. Puzzle: die Begriffe in der richtigen Reihenfolge.')) ?></dd>
                <dt><?= e(t('Richtig')) ?></dt><dd><?= e(t('Multiple Choice: Nummer(n) der richtigen Antworten, z. B. 2 oder 1,3. Wahr oder Falsch: wahr bzw. falsch. Schätzen: der richtige Wert.')) ?></dd>
                <dt><?= e(t('Zeit (s), Punkte')) ?></dt><dd><?= e(t('Zeitlimit in Sekunden (5, 10, 20, 30, 60, 90 oder 120) und Standard, Doppelt oder Keine.')) ?></dd>
                <dt><?= e(t('Von, Bis, Schrittweite, Toleranz, Einheit')) ?></dt><dd><?= e(t('Nur für Schätzfragen.')) ?></dd>
                <dt><?= e(t('Breitengrad, Längengrad, Umkreis (km), Ort')) ?></dt><dd><?= e(t('Nur für Kartenfragen, z. B. 48,20849 und 16,37208 für den Stephansdom.')) ?></dd>
            </dl>
            <p><?= e(t('Fett, kursiv usw. gehen mit <b>…</b>, <i>…</i>, <u>…</u>, <sup>…</sup>, <sub>…</sub>. Bilder sind nur im JSON-Export enthalten.')) ?></p>
        </details>
        <p class="transfer-export">
            <span><?= e(t('Exportieren (gespeicherter Stand):')) ?></span>
            <a href="<?= e(url('/quiz/' . $quiz['id'] . '/export.json')) ?>" download><?= e(t('JSON mit Bildern')) ?></a>
            <a href="<?= e(url('/quiz/' . $quiz['id'] . '/export.csv')) ?>" download><?= e(t('CSV für Excel')) ?></a>
        </p>
        <input type="file" id="import-picker" accept=".csv,.json,.txt,text/csv,application/json,text/plain" hidden>
    </section>

    <input type="file" id="file-picker" accept="image/jpeg,image/png,image/webp" hidden>
    <input type="file" id="sound-picker" accept="audio/mpeg,audio/ogg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,.mp3,.ogg,.oga,.m4a,.wav" hidden>
</div>
