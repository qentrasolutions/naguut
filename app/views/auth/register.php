<section class="narrow card-plain">
    <h1><?= e(t('Konto anlegen')) ?></h1>
    <p class="lead"><?= e(t('Ein Konto brauchst du nur, um Quizze zu erstellen und Spiele zu leiten. Mitspielende brauchen keines.')) ?></p>
    <?php $list = $errors ?? []; require APP_DIR . '/views/partials/errors.php'; ?>
    <form method="post" action="<?= e(url('/register')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field">
            <span><?= e(t('Name')) ?></span>
            <input type="text" name="display_name" value="<?= e(old('display_name')) ?>" maxlength="80" autocomplete="name" required>
            <small><?= e(t('So siehst du dich im Menü. Mitspielende sehen den Namen nicht.')) ?></small>
        </label>
        <label class="field">
            <span><?= e(t('E-Mail-Adresse')) ?></span>
            <input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required>
        </label>
        <label class="field">
            <span><?= e(t('Passwort')) ?></span>
            <input type="password" name="password" minlength="8" autocomplete="new-password" required>
            <small><?= e(t('Mindestens 8 Zeichen.')) ?></small>
        </label>
        <label class="field">
            <span><?= e(t('Passwort wiederholen')) ?></span>
            <input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
        </label>
        <label class="check">
            <input type="checkbox" name="privacy" value="1" required>
            <span><?= t('Ich akzeptiere die {agb} und habe die {privacy} gelesen.', [
                'agb' => '<a href="' . e(url('/agb')) . '" target="_blank">' . e(t('AGB')) . '</a>',
                'privacy' => '<a href="' . e(url('/datenschutz')) . '" target="_blank">' . e(t('Datenschutzerklärung')) . '</a>']) ?></span>
        </label>
        <button type="submit" class="btn btn-ink btn-block"><?= e(t('Konto anlegen')) ?></button>
    </form>
    <p class="form-links"><a href="<?= e(url('/login')) ?>"><?= e(t('Schon registriert? Anmelden')) ?></a></p>
</section>
