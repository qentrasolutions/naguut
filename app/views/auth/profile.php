<section class="narrow">
    <h1><?= e(t('Profil')) ?></h1>

    <form method="post" action="<?= e(url('/profile')) ?>" class="form panel">
        <h2><?= e(t('Name und E-Mail')) ?></h2>
        <?php $list = $errors ?? []; require APP_DIR . '/views/partials/errors.php'; ?>
        <?= csrf_field() ?>
        <label class="field">
            <span><?= e(t('Name')) ?></span>
            <input type="text" name="display_name" value="<?= e($user['display_name']) ?>" maxlength="80" required>
        </label>
        <label class="field">
            <span><?= e(t('E-Mail-Adresse')) ?></span>
            <input type="email" name="email" value="<?= e($user['email']) ?>" required>
        </label>
        <label class="field">
            <span><?= e(t('Aktuelles Passwort')) ?></span>
            <input type="password" name="current_password" autocomplete="current-password">
            <small><?= e(t('Nur nötig, wenn du die E-Mail-Adresse änderst.')) ?></small>
        </label>
        <button type="submit" class="btn btn-ink"><?= e(t('Profil speichern')) ?></button>
    </form>

    <form method="post" action="<?= e(url('/profile/password')) ?>" class="form panel">
        <h2><?= e(t('Passwort ändern')) ?></h2>
        <?php $list = $pwErrors ?? []; require APP_DIR . '/views/partials/errors.php'; ?>
        <?= csrf_field() ?>
        <label class="field">
            <span><?= e(t('Aktuelles Passwort')) ?></span>
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label class="field">
            <span><?= e(t('Neues Passwort')) ?></span>
            <input type="password" name="password" minlength="8" autocomplete="new-password" required>
        </label>
        <label class="field">
            <span><?= e(t('Neues Passwort wiederholen')) ?></span>
            <input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
        </label>
        <button type="submit" class="btn btn-ink"><?= e(t('Passwort ändern')) ?></button>
    </form>

    <form method="post" action="<?= e(url('/profile/delete')) ?>" class="form panel panel-danger" data-confirm="<?= e(t('Konto wirklich löschen? Alle Quizze und Spielergebnisse gehen verloren.')) ?>">
        <h2><?= e(t('Konto löschen')) ?></h2>
        <p><?= e(t('Löscht dein Konto mit allen Quizzen, Spielen und Ergebnissen. Das lässt sich nicht rückgängig machen.')) ?></p>
        <?php $list = $delErrors ?? []; require APP_DIR . '/views/partials/errors.php'; ?>
        <?= csrf_field() ?>
        <label class="field">
            <span><?= e(t('Passwort zur Bestätigung')) ?></span>
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn btn-danger"><?= e(t('Konto endgültig löschen')) ?></button>
    </form>
</section>
