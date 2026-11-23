<section class="narrow">
    <h1><?= e(t('Naguut installieren')) ?></h1>
    <p class="lead"><?= e(t('Lege zuerst bei deinem Hoster eine leere MySQL- oder MariaDB-Datenbank an. Dann trägst du hier die Zugangsdaten ein.')) ?></p>

    <div class="panel">
        <h2><?= e(t('Server-Voraussetzungen')) ?></h2>
        <ul class="checklist">
            <?php foreach ($checks as $c): ?>
                <li class="<?= $c['ok'] ? 'ok' : 'bad' ?>"><?= $c['ok'] ? '✓' : '✗' ?> <?= e($c['label']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php $list = $errors; require APP_DIR . '/views/partials/errors.php'; ?>

    <form method="post" class="form panel">
        <?= csrf_field() ?>
        <h2><?= e(t('Datenbank')) ?></h2>
        <label class="field"><span><?= e(t('Server')) ?></span><input name="db_host" value="<?= e($v['db_host']) ?>" required></label>
        <label class="field"><span><?= e(t('Datenbankname')) ?></span><input name="db_name" value="<?= e($v['db_name']) ?>" required></label>
        <label class="field"><span><?= e(t('Benutzer')) ?></span><input name="db_user" value="<?= e($v['db_user']) ?>" required autocomplete="off"></label>
        <label class="field"><span><?= e(t('Passwort')) ?></span><input type="password" name="db_pass" autocomplete="off"></label>

        <h2><?= e(t('Website')) ?></h2>
        <label class="field"><span><?= e(t('Adresse der Seite')) ?></span><input type="url" name="base_url" value="<?= e($v['base_url']) ?>" required>
            <small><?= e(t('Mit https:// und ohne Schrägstrich am Ende. Daraus entstehen QR-Code und Links in E-Mails.')) ?></small></label>
        <label class="field"><span><?= e(t('Absender für E-Mails')) ?></span><input type="email" name="mail_from" value="<?= e($v['mail_from']) ?>" required>
            <small><?= e(t('Am besten eine Adresse der eigenen Domain, sonst landen Mails im Spam.')) ?></small></label>

        <h2><?= e(t('Super-Admin-Konto')) ?></h2>
        <p class="muted"><?= e(t('Darf alles, auch Rechtstexte bearbeiten und weitere Admins ernennen.')) ?></p>
        <label class="field"><span><?= e(t('Name')) ?></span><input name="admin_name" value="<?= e($v['admin_name']) ?>" required></label>
        <label class="field"><span><?= e(t('E-Mail-Adresse')) ?></span><input type="email" name="admin_email" value="<?= e($v['admin_email']) ?>" required></label>
        <label class="field"><span><?= e(t('Passwort')) ?></span><input type="password" name="admin_pass" minlength="8" required autocomplete="new-password"></label>

        <button type="submit" class="btn btn-ink btn-block"><?= e(t('Installieren')) ?></button>
    </form>
</section>
