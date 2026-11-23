<?php
// Naguut – Front-Controller. Alle Seiten und API-Aufrufe laufen hier durch.

require __DIR__ . '/app/bootstrap.php';
require APP_DIR . '/routes.php';

// Sicherheits-Header (zusätzlich zur .htaccess, falls mod_headers fehlt)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
$tileOrigin = NAGUUT_INSTALLED ? map_tile_origin() : '';
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:" . ($tileOrigin ? ' ' . $tileOrigin : '') . "; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}

$path = current_path();

if (!NAGUUT_INSTALLED) {
    require APP_DIR . '/controllers/install.php';
    install_handle();
    exit;
}

// Gelegentlich aufräumen, falls kein Cronjob eingerichtet ist (ca. jede 500. Anfrage)
if (random_int(1, 500) === 1) {
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        try {
            cleanup_run();
        } catch (Throwable $e) {
            error_log('[Naguut] Aufräumen fehlgeschlagen: ' . $e->getMessage());
        }
    });
}

db_migrate_once();
dispatch(request_method(), $path);
