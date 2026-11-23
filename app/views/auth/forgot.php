<section class="narrow card-plain">
    <h1><?= e(t('Passwort vergessen')) ?></h1>
    <p class="lead"><?= e(t('Gib deine E-Mail-Adresse ein. Wir schicken dir einen Link, mit dem du ein neues Passwort festlegst.')) ?></p>
    <form method="post" action="<?= e(url('/forgot')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field">
            <span><?= e(t('E-Mail-Adresse')) ?></span>
            <input type="email" name="email" autocomplete="email" required autofocus>
        </label>
        <button type="submit" class="btn btn-ink btn-block"><?= e(t('Link schicken')) ?></button>
    </form>
    <p class="form-links"><a href="<?= e(url('/login')) ?>"><?= e(t('Zurück zur Anmeldung')) ?></a></p>
</section>
