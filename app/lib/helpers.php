<?php
// Allgemeine Hilfsfunktionen

const APP_NAME = 'Naguut';
const APP_VERSION = '1.8.1';
const APP_VENDOR = 'Qentra Solutions';

function cfg(string $key, $default = null)
{
    $value = $GLOBALS['NAGUUT_CONFIG'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** HTML-sicher ausgeben */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Übersetzung: deutscher Quelltext ist der Schlüssel */
function t(string $text, array $vars = []): string
{
    static $strings = null;
    if ($strings === null) {
        $locale = preg_replace('/[^a-z]/', '', (string)cfg('locale', 'de'));
        $file = APP_DIR . '/lang/' . $locale . '.php';
        $strings = is_file($file) ? (array)require $file : [];
    }
    $out = $strings[$text] ?? $text;
    foreach ($vars as $k => $v) {
        $out = str_replace('{' . $k . '}', (string)$v, $out);
    }
    return $out;
}

/** Übersetzungen für JavaScript als JSON */
function js_strings(array $keys): string
{
    $map = [];
    foreach ($keys as $k) {
        $map[$k] = t($k);
    }
    return json_encode($map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}

function base_path(): string
{
    static $base = null;
    if ($base === null) {
        if (PHP_SAPI === 'cli-server' || PHP_SAPI === 'cli') {
            return $base = ''; // Entwicklungsserver: Anwendung liegt im Wurzelverzeichnis
        }
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir, '/');
    }
    return $base;
}

/** Relativer Link innerhalb der Anwendung */
function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Absoluter Link (für E-Mails und QR-Codes) */
function abs_url(string $path = '/'): string
{
    $base = rtrim((string)cfg('base_url', ''), '/');
    if ($base === '') {
        $scheme = is_https() ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
    }
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = PUBLIC_DIR . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string)filemtime($file), -6) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function upload_url(?string $file): ?string
{
    return $file ? url('uploads/' . $file) : null;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function now_ms(): int
{
    return (int)floor(microtime(true) * 1000);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function token_hash(string $token): string
{
    return hash('sha256', $token);
}

function request_method(): string
{
    $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    // Manche Hoster blockieren PUT/DELETE – dann per POST + Header
    $override = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '');
    if ($m === 'POST' && in_array($override, ['PUT', 'DELETE'], true)) {
        return $override;
    }
    return $m;
}

function is_api(): bool
{
    return str_starts_with(current_path(), '/api/');
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . trim($path, '/');
    return $path;
}

function input_json(): array
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true);
        if (!is_array($data)) {
            $data = [];
        }
    }
    return $data;
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $code = 400, array $extra = []): void
{
    json_out(['error' => $message] + $extra, $code);
}

/** Fehlerseite bzw. JSON-Fehler und Abbruch */
function abort(int $code, string $message = ''): void
{
    if ($message === '') {
        $message = [
            400 => t('Die Anfrage ist ungültig.'),
            403 => t('Dafür fehlt dir die Berechtigung.'),
            404 => t('Diese Seite gibt es nicht.'),
            405 => t('Diese Aktion ist hier nicht erlaubt.'),
            419 => t('Die Sitzung ist abgelaufen. Lade die Seite neu und versuche es noch einmal.'),
            429 => t('Zu viele Versuche. Warte ein paar Minuten und versuche es dann erneut.'),
        ][$code] ?? t('Ein Fehler ist aufgetreten.');
    }
    if (is_api()) {
        json_error($message, $code);
    }
    http_response_code($code);
    view('pages/error', ['title' => t('Fehler'), 'code' => $code, 'message' => $message]);
    exit;
}

// ---------- Session, CSRF, Flash ----------

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('naguut_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(16);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    session_boot();
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        abort(419);
    }
}

function flash(string $type, string $message): void
{
    session_boot();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_boot();
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Alte Formularwerte nach Fehlern */
function old(string $key, $default = '')
{
    return $GLOBALS['NAGUUT_OLD'][$key] ?? $default;
}

// ---------- Views ----------

function view(string $__name, array $__data = [], ?string $__layout = 'layout'): void
{
    $__data['__view'] = $__name;
    extract($__data, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $__name . '.php';
    $content = ob_get_clean();
    if ($__layout === null) {
        echo $content;
        return;
    }
    require APP_DIR . '/views/' . $__layout . '.php';
}

function fmt_date(?int $ts, bool $withTime = true): string
{
    if (!$ts) {
        return '–';
    }
    $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(cfg('timezone', 'Europe/Vienna')));
    return $dt->format($withTime ? 'd.m.Y, H:i' : 'd.m.Y');
}

function plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n === 1 ? $one : $many);
}

function mb_len(string $s): int
{
    return mb_strlen($s, 'UTF-8');
}
