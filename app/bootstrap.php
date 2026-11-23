<?php
// Naguut – Start der Anwendung

declare(strict_types=1);

define('APP_DIR', __DIR__);
define('PUBLIC_DIR', dirname(__DIR__));

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('Naguut benötigt PHP 8.2 oder neuer. Aktuell: ' . PHP_VERSION);
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Vienna');

$configFile = getenv('NAGUUT_CONFIG') ?: APP_DIR . '/config.php';
$GLOBALS['NAGUUT_CONFIG'] = is_file($configFile) ? (array)require $configFile : [];
define('NAGUUT_INSTALLED', is_file($configFile));

require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/accounts.php';
require APP_DIR . '/lib/mailer.php';
require APP_DIR . '/lib/images.php';
require APP_DIR . '/lib/sounds.php';
require APP_DIR . '/lib/wordfilter.php';
require APP_DIR . '/lib/richtext.php';
require APP_DIR . '/lib/estimate.php';
require APP_DIR . '/lib/qtypes.php';
require APP_DIR . '/lib/taxonomy.php';
require APP_DIR . '/lib/quizzes.php';
require APP_DIR . '/lib/transfer.php';
require APP_DIR . '/lib/game.php';
require APP_DIR . '/lib/cleanup.php';
require APP_DIR . '/lib/legal.php';

ini_set('display_errors', cfg('debug') ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    error_log('[Naguut] ' . $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string)$e . PHP_EOL);
        exit(1);
    }
    $msg = cfg('debug') ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        : t('Ein interner Fehler ist aufgetreten. Bitte versuche es später noch einmal.');
    if (!headers_sent()) {
        if (is_api()) {
            json_error($msg, 500);
        }
        http_response_code(500);
    }
    try {
        view('pages/error', ['title' => t('Fehler'), 'code' => 500, 'message' => $msg]);
    } catch (Throwable) {
        echo e($msg);
    }
});
