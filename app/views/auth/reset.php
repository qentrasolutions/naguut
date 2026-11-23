<section class="narrow card-plain">
    <h1><?= e(t('Neues Passwort festlegen')) ?></h1>
    <?php $list = isset($error) ? [$error] : []; require APP_DIR . '/views/partials/errors.php'; ?>
    <form method="post" action="<?= e(url('/reset')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="field">
            <span><?= e(t('Neues Passwort')) ?></span>
            <input type="password" name="password" minlength="8" autocomplete="new-password" required autofocus>
            <small><?= e(t('Mindestens 8 Zeichen.')) ?></small>
        </label>
        <label class="field">
            <span><?= e(t('Passwort wiederholen')) ?></span>
            <input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
        </label>
        <button type="submit" class="btn btn-ink btn-block"><?= e(t('Passwort speichern')) ?></button>
    </form>
</section>
