<?php $bodyClass = 'page-home'; ?>
<section class="hero">
    <div class="hero-inner">
        <h1 class="hero-title"><?= e(t('Mitspielen? PIN eingeben.')) ?></h1>
        <p class="hero-sub"><?= e(t('Die sechsstellige Zahl steht groß am Beamer.')) ?></p>
        <form class="pin-form" method="get" action="<?= e(url('/play')) ?>">
            <label for="home-pin" class="visually-hidden"><?= e(t('Game-PIN')) ?></label>
            <input id="home-pin" name="pin" class="pin-input" inputmode="numeric" pattern="\d{6}" maxlength="6"
                   autocomplete="off" placeholder="000000" required aria-describedby="home-pin-hint">
            <button type="submit" class="btn btn-sun btn-large"><?= e(t('Beitreten')) ?></button>
        </form>
        <p id="home-pin-hint" class="hero-hint"><?= e(t('Kein Konto, keine App. Nur ein Nickname.')) ?></p>
    </div>
</section>

<section class="teachers">
    <div class="teachers-text">
        <h2><?= e(t('Für Quizersteller')) ?></h2>
        <p><?= e(t('Fragen am Beamer, Antworten am Handy, Rangliste nach jeder Runde. Danach siehst du, welche Fragen die Klasse noch nicht sicher beherrscht.')) ?></p>
        <?php if ($user): ?>
            <a class="btn btn-ink" href="<?= e(url('/dashboard')) ?>"><?= e(t('Zu meinen Quizzen')) ?></a>
        <?php else: ?>
            <div class="btn-row">
                <?php if (cfg('registration_open', true)): ?>
                    <a class="btn btn-ink" href="<?= e(url('/register')) ?>"><?= e(t('Konto anlegen')) ?></a>
                <?php endif; ?>
                <a class="btn btn-outline" href="<?= e(url('/login')) ?>"><?= e(t('Anmelden')) ?></a>
            </div>
        <?php endif; ?>
    </div>
    <ol class="steps">
        <li><strong><?= e(t('Quiz anlegen')) ?></strong><span><?= e(t('Multiple Choice oder Wahr/Falsch, mit Bild und Zeitlimit.')) ?></span></li>
        <li><strong><?= e(t('Spiel starten')) ?></strong><span><?= e(t('Die Klasse tritt mit PIN oder QR-Code bei.')) ?></span></li>
        <li><strong><?= e(t('Auswerten')) ?></strong><span><?= e(t('Ergebnisse je Frage und je Person, als CSV für Excel.')) ?></span></li>
    </ol>
</section>
