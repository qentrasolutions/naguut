<?php
// Web-Installer: läuft nur, solange app/config.php fehlt.

function install_handle(): void
{
    session_boot();
    $errors = [];
    $checks = install_checks();
    $values = [
        'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
        'base_url' => install_guess_url(), 'mail_from' => 'noreply@' . preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'example.at'),
        'admin_name' => '', 'admin_email' => '', 'admin_pass' => '',
    ];

    if (request_method() === 'POST') {
        csrf_check();
        foreach ($values as $k => $_) {
            $values[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $values['db_pass'] = (string)($_POST['db_pass'] ?? '');
        $values['admin_pass'] = (string)($_POST['admin_pass'] ?? '');

        if (in_array(false, array_column($checks, 'ok'), true)) {
            $errors[] = t('Bitte behebe zuerst die markierten Server-Voraussetzungen.');
        }
        if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = t('Gib eine gültige E-Mail-Adresse für das Admin-Konto an.');
        }
        if ($values['admin_name'] === '') {
            $errors[] = t('Gib einen Namen für das Admin-Konto an.');
        }
        if ($e = password_ok($values['admin_pass'])) {
            $errors[] = $e;
        }
        if (!filter_var($values['base_url'], FILTER_VALIDATE_URL)) {
            $errors[] = t('Die Adresse der Seite ist ungültig.');
        }

        $pdo = null;
        if (!$errors) {
            $dsn = 'mysql:host=' . $values['db_host'] . ';dbname=' . $values['db_name'] . ';charset=utf8mb4';
            try {
                $pdo = new PDO($dsn, $values['db_user'], $values['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (PDOException $e) {
                $errors[] = t('Keine Verbindung zur Datenbank: {msg}', ['msg' => $e->getMessage()]);
            }
        }

        if (!$errors && $pdo) {
            $config = [
                'db' => ['dsn' => $dsn, 'user' => $values['db_user'], 'pass' => $values['db_pass']],
                'base_url' => rtrim($values['base_url'], '/'),
                'mail_from' => $values['mail_from'],
                'mail_from_name' => 'Naguut',
                'mail_mode' => 'mail',
                'registration_open' => true,
                'retention_days' => 90,
                'operator' => ['name' => '', 'address' => '', 'email' => $values['admin_email']],
                'secret' => random_token(32),
                'debug' => false,
            ];
            try {
                $GLOBALS['NAGUUT_CONFIG'] = $config;
                db_install_schema($pdo);
                db_migrate($pdo);
                $now = time();
                $st = $pdo->prepare('INSERT INTO users (email, password_hash, display_name, role, is_locked, verified_at, approved_at, created_at)
                                     VALUES (?, ?, ?, ?, 0, ?, ?, ?)');
                $st->execute([mb_strtolower($values['admin_email']), hash_password($values['admin_pass']), $values['admin_name'], 'superadmin', $now, $now, $now]);
            } catch (PDOException $e) {
                $errors[] = t('Die Tabellen konnten nicht angelegt werden: {msg}', ['msg' => $e->getMessage()]);
            }
            if (!$errors) {
                $php = "<?php\n// Naguut – erzeugt vom Installer am " . date('d.m.Y H:i') . "\n// Weitere Optionen: siehe config.sample.php\nreturn " . var_export($config, true) . ";\n";
                if (@file_put_contents(APP_DIR . '/config.php', $php, LOCK_EX) === false) {
                    $errors[] = t('app/config.php konnte nicht geschrieben werden. Lege die Datei selbst an (Vorlage: config.sample.php).');
                } else {
                    @chmod(APP_DIR . '/config.php', 0640);
                    view('pages/install_done', ['title' => t('Installation abgeschlossen')], 'layout_bare');
                    return;
                }
            }
        }
    }

    view('pages/install', ['title' => t('Naguut installieren'), 'checks' => $checks, 'errors' => $errors, 'v' => $values], 'layout_bare');
}

function install_guess_url(): string
{
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
}

function install_checks(): array
{
    return [
        ['label' => t('PHP 8.2 oder neuer (vorhanden: {v})', ['v' => PHP_VERSION]), 'ok' => PHP_VERSION_ID >= 80200],
        ['label' => t('PHP-Erweiterung pdo_mysql'), 'ok' => extension_loaded('pdo_mysql')],
        ['label' => t('PHP-Erweiterung mbstring'), 'ok' => extension_loaded('mbstring')],
        ['label' => t('PHP-Erweiterung gd (Bilder)'), 'ok' => extension_loaded('gd')],
        ['label' => t('Ordner app/ beschreibbar (für config.php)'), 'ok' => is_writable(APP_DIR)],
        ['label' => t('Ordner uploads/ beschreibbar'), 'ok' => is_writable(PUBLIC_DIR . '/uploads')],
    ];
}
