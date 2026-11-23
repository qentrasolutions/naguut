<section class="narrow card-plain">
    <h1><?= e(t('Naguut ist installiert')) ?></h1>
    <p class="lead"><?= e(t('Die Datenbank ist eingerichtet und dein Super-Admin-Konto angelegt. Der Installer ist jetzt gesperrt.')) ?></p>
    <ol class="steps steps-compact">
        <li><span><?= e(t('Melde dich als Super-Admin an und passe unter Verwaltung → Rechtstexte Impressum, Datenschutz und AGB an.')) ?></span></li>
        <li><span><?= e(t('Richte einen täglichen Cronjob ein: php …/app/cron/cleanup.php')) ?></span></li>
        <li><span><?= e(t('Lege dein erstes Quiz an.')) ?></span></li>
    </ol>
    <p><a class="btn btn-ink" href="<?= e(url('/login')) ?>"><?= e(t('Zur Anmeldung')) ?></a></p>
</section>
