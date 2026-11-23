<?php
$bodyClass = 'player';
$scripts = ['js/avatars.js', 'js/map.js', 'js/game-common.js', 'js/play.js'];
$i18n = [
    'Game-PIN', 'Weiter', 'Nickname', 'Los geht’s', 'Du bist dabei!', 'Siehst du deinen Namen am Beamer?',
    'Warte auf den Start …', '{n} dabei', 'Frage {n} von {total}', 'Gleich geht’s los', 'Du hast', 'Sekunden', 'Los geht’s in', 'noch {n} Sekunden', 'Antwort gesendet',
    'Warte auf die anderen …', 'Richtig!', 'Leider falsch', 'Keine Antwort', '+{n} Punkte', 'Platz {n} von {total}',
    '{n} Punkte', 'Du bist auf dem Podest!', 'Danke fürs Mitspielen!', 'Du wurdest aus dem Spiel entfernt.',
    'Neu beitreten', 'Verbindung unterbrochen – versuche es weiter …', 'Zu dieser PIN läuft gerade kein Spiel.',
    'Die Zeit ist abgelaufen.', 'Wahr', 'Falsch', 'Rangliste', 'Endergebnis', 'Gib die sechsstellige PIN ein.',
    'Wähle einen Nickname, den die Klasse sehen darf.', 'Spiel beendet', 'Zur Startseite', 'Antwort {n}',
    'Nickname ändern', 'Bitte keinen vollen echten Namen.', 'Wähle deinen Avatar', 'Zufällig',
    'Deine Schätzung', 'Weniger', 'Mehr', 'Antwort senden', 'Volltreffer!', 'Gut geschätzt!', 'Daneben',
    'Deine Schätzung: {v}', 'Richtig: {v}', 'Richtig:', 'Deine Antwort', 'Deine Antwort: {v}', 'Tippe in der richtigen Reihenfolge',
    'Neu ordnen', 'Deine Reihenfolge:', 'Tippe auf die Karte, um deinen Pin zu setzen', '{v} vom Ziel entfernt', 'Im Umkreis!',
];
?>
<script type="application/json" id="i18n"><?= js_strings($i18n) ?></script>
<script type="application/json" id="game-config"><?= json_encode([
    'pin' => $pin,
    'apiBase' => url('/api/games/'),
    'homeUrl' => url('/'),
    'map' => NAGUUT_INSTALLED ? map_client_config() : null,
], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?></script>

<div class="player-shell">
    <header class="player-top">
        <a class="brand brand-light" href="<?= e(url('/')) ?>"><?php require APP_DIR . '/views/partials/logo.php'; ?></a>
        <span class="player-me" id="player-me"></span>
    </header>
    <main class="player-stage" id="stage" aria-live="polite">
        <noscript><p class="player-msg"><?= e(t('Zum Mitspielen muss JavaScript aktiviert sein.')) ?></p></noscript>
    </main>
</div>
