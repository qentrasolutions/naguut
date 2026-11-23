<?php
/** @var string $content */
$user = current_user();
$flashes = take_flashes();
$pageTitle = isset($title) && $title ? $title . ' – ' . APP_NAME : APP_NAME . ' – ' . t('Live-Quiz für die Klasse');
$bodyClass = $bodyClass ?? 'page-' . str_replace('/', '-', $__view);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e(t('Naguut ist ein Live-Quiz für Schule und Seminar: Fragen am Beamer, Antworten am Handy.')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<meta name="theme-color" content="#1E2A78">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip" href="#main"><?= e(t('Zum Inhalt springen')) ?></a>
<header class="topbar">
    <a class="brand" href="<?= e(url($user ? '/dashboard' : '/')) ?>" aria-label="<?= e(t('Naguut Startseite')) ?>">
        <?php require APP_DIR . '/views/partials/logo.php'; ?>
    </a>
    <nav class="topnav" aria-label="<?= e(t('Hauptnavigation')) ?>">
        <?php if ($user): ?>
            <a href="<?= e(url('/dashboard')) ?>" <?= current_path() === '/dashboard' ? 'aria-current="page"' : '' ?>><?= e(t('Quizze')) ?></a>
            <a href="<?= e(url('/games')) ?>" <?= str_starts_with(current_path(), '/games') ? 'aria-current="page"' : '' ?>><?= e(t('Spielverlauf')) ?></a>
            <?php if (is_admin($user)): ?>
                <a href="<?= e(url('/admin')) ?>" <?= str_starts_with(current_path(), '/admin') ? 'aria-current="page"' : '' ?>><?= e(t('Verwaltung')) ?><?php if (is_superadmin($user) && ($pc = accounts_pending_count())): ?> <span class="count-badge" title="<?= e(t('{n} Konten warten auf Freischaltung', ['n' => $pc])) ?>"><?= (int)$pc ?></span><?php endif; ?></a>
            <?php endif; ?>
            <a href="<?= e(url('/profile')) ?>" <?= current_path() === '/profile' ? 'aria-current="page"' : '' ?>><?= e($user['display_name']) ?></a>
            <form method="post" action="<?= e(url('/logout')) ?>" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="linkbtn"><?= e(t('Abmelden')) ?></button>
            </form>
        <?php else: ?>
            <a href="<?= e(url('/play')) ?>"><?= e(t('Mitspielen')) ?></a>
            <a href="<?= e(url('/login')) ?>" class="btn btn-small btn-outline"><?= e(t('Für Quizersteller')) ?></a>
        <?php endif; ?>
    </nav>
</header>

<main id="main" class="main">
    <?php if ($flashes): ?>
        <div class="flashes" role="status">
            <?php foreach ($flashes as $f): ?>
                <p class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?= $content ?>
</main>

<footer class="footer">
    <p><?= e(APP_NAME) ?> <?= e(APP_VERSION) ?>, <?= e(t('entwickelt von {vendor}', ['vendor' => APP_VENDOR])) ?></p>
    <p>
        <a href="<?= e(url('/impressum')) ?>"><?= e(t('Impressum')) ?></a>
        <a href="<?= e(url('/datenschutz')) ?>"><?= e(t('Datenschutz')) ?></a>
        <a href="<?= e(url('/agb')) ?>"><?= e(t('AGB')) ?></a>
    </p>
</footer>
<?php if (!empty($scripts)): foreach ($scripts as $s): ?>
<script src="<?= e(asset($s)) ?>" defer></script>
<?php endforeach; endif; ?>
<script src="<?= e(asset('js/common.js')) ?>" defer></script>
</body>
</html>
