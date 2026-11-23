<?php
$bodyClass = 'host';
$scripts = ['js/qr.js', 'js/avatars.js', 'js/map.js', 'js/game-common.js', 'js/host.js'];
$i18n = [
    'Beitreten unter', 'Game-PIN', 'Warte auf Mitspielende …', 'Spiel starten', '{n} dabei', 'Antippen zum Entfernen',
    '{name} aus dem Spiel entfernen?', 'Frage {n} von {total}', 'Gleich geht’s los', 'Sekunden Zeit', 'Los geht’s in', 'Antworten', 'Frage beenden',
    'Weiter', 'Rangliste', 'Nächste Frage', 'Endergebnis', 'Siegerpodest', 'Zur Auswertung', 'Spiel beenden',
    'Das Spiel wirklich beenden? Die bisherigen Ergebnisse bleiben gespeichert.', 'Verbindung unterbrochen – versuche es weiter …',
    'Punkte', 'doppelte Punkte', 'ohne Punkte', 'richtig', 'Vollbild', 'Noch niemand dabei', 'Platz', 'Neues Spiel',
    'Es ist noch niemand beigetreten.', 'Frage überspringen', 'Nicht werten', 'Nächste Frage überspringen',
    'Diese Frage überspringen? Sie wird nicht gewertet.', 'Diese Frage nicht werten? Bereits vergebene Punkte werden abgezogen.',
    'Die nächste Frage überspringen?', 'Ton an', 'Ton aus', 'Fragen am Handy: an', 'Fragen am Handy: aus',
    'Link kopieren', 'Link kopiert', 'Distanz-Modus: Fragen und Antworten erscheinen auch auf den Handys. Teile den Link z. B. im Videocall.', 'Schätze mit dem Schieberegler am Handy', 'Richtig',
    '{n} von {total} im Toleranzbereich', 'Ø Schätzung {v}', '{n} von {total} richtig', 'Ø Abstand {v}', 'Auch richtig: {v}',
    'Tippe die Antwort am Handy ein', 'Tippe die Symbole am Handy in der richtigen Reihenfolge an', 'Setze am Handy einen Pin auf der Karte',
];
$music = upload_url(game_music($game));
?>
<script type="application/json" id="i18n"><?= js_strings($i18n) ?></script>
<script type="application/json" id="game-config"><?= json_encode([
    'pin' => $game['pin'],
    'stateUrl' => url('/api/games/' . $game['pin'] . '/state'),
    'nextUrl' => url('/api/games/' . $game['pin'] . '/next'),
    'endUrl' => url('/api/games/' . $game['pin'] . '/end'),
    'skipUrl' => url('/api/games/' . $game['pin'] . '/skip'),
    'devicesUrl' => url('/api/games/' . $game['pin'] . '/devices'),
    'map' => map_client_config(),
    'musicUrl' => $music,
    'kickUrl' => url('/api/games/' . $game['pin'] . '/players/'),
    'dashboardUrl' => url('/dashboard'),
    'csrf' => csrf_token(),
], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?></script>

<div class="host-shell">
    <header class="host-top">
        <span class="brand brand-light"><?php require APP_DIR . '/views/partials/logo.php'; ?></span>
        <span class="host-quiz" id="host-quiz"><?= e($game['quiz_title']) ?></span>
        <div class="host-tools">
            <span class="conn" id="conn" hidden></span>
            <?php if ($music): ?>
                <button type="button" class="btn btn-small btn-ghost-light" id="music-btn" aria-pressed="false"><?= e(t('Ton an')) ?></button>
            <?php endif; ?>
            <button type="button" class="btn btn-small btn-ghost-light" id="devices-btn" aria-pressed="<?= $game['show_on_devices'] ? 'true' : 'false' ?>"
                    title="<?= e(t('Für Spiele auf Distanz: Fragen und Antworten auch auf den Handys anzeigen')) ?>"><?= e($game['show_on_devices'] ? t('Fragen am Handy: an') : t('Fragen am Handy: aus')) ?></button>
            <button type="button" class="btn btn-small btn-ghost-light" id="fullscreen-btn"><?= e(t('Vollbild')) ?></button>
            <button type="button" class="btn btn-small btn-ghost-light" id="end-btn"><?= e(t('Spiel beenden')) ?></button>
        </div>
    </header>
    <main class="host-stage" id="stage" aria-live="polite">
        <p class="loading"><?= e(t('Lädt …')) ?></p>
    </main>
</div>
