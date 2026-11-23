<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') . ' – ' . APP_NAME) ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<meta name="theme-color" content="#1E2A78">
<meta name="robots" content="noindex">
</head>
<body class="game-body <?= e($bodyClass ?? '') ?>">
<?= $content ?>
<?php foreach ($scripts ?? [] as $s): ?>
<script src="<?= e(asset($s)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
