<section class="narrow card-plain">
    <h1><?= e(t('Anmelden')) ?></h1>
    <?php $list = isset($error) ? [$error] : []; require APP_DIR . '/views/partials/errors.php'; ?>
    <?php if (!empty($unverified)): ?>
        <form method="post" action="<?= e(url('/verify/resend')) ?>" class="note">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="<?= e(old('email')) ?>">
            <button type="submit" class="linkbtn"><?= e(t('Bestätigungslink erneut senden')) ?></button>
        </form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/login')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field">
            <span><?= e(t('E-Mail-Adresse')) ?></span>
            <input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required autofocus>
        </label>
        <label class="field">
            <span><?= e(t('Passwort')) ?></span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn btn-ink btn-block"><?= e(t('Anmelden')) ?></button>
    </form>
    <p class="form-links">
        <a href="<?= e(url('/forgot')) ?>"><?= e(t('Passwort vergessen?')) ?></a>
        <?php if (cfg('registration_open', true)): ?>
            <a href="<?= e(url('/register')) ?>"><?= e(t('Noch kein Konto? Jetzt anlegen')) ?></a>
        <?php endif; ?>
    </p>
</section>
