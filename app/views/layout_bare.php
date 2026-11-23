<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') . ' – ' . APP_NAME) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="page-bare">
<header class="topbar"><span class="brand"><?php require APP_DIR . '/views/partials/logo.php'; ?></span></header>
<main id="main" class="main"><?= $content ?></main>
<footer class="footer"><p><?= e(APP_NAME) ?> <?= e(APP_VERSION) ?>, <?= e(t('entwickelt von {vendor}', ['vendor' => APP_VENDOR])) ?></p></footer>
</body>
</html>
