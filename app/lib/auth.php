<?php
// Anmeldung, Rollen und Ratenbegrenzung

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    session_boot();
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $row = q_one('SELECT * FROM users WHERE id = ?', [(int)$_SESSION['user_id']]);
        $blocked = $row ? account_block_message($row) : null;
        if ($row && !$row['is_locked'] && $blocked === null) {
            $user = $row;
        } else {
            if ($blocked !== null) {
                flash('error', $blocked); // z. B. Testzugang während der Sitzung abgelaufen
            }
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        if (is_api()) {
            json_error(t('Bitte melde dich an.'), 401);
        }
        $_SESSION['after_login'] = current_path();
        flash('info', t('Bitte melde dich an, um fortzufahren.'));
        redirect('/login');
    }
    return $user;
}

const ROLES = ['host' => 'Quizersteller', 'admin' => 'Admin', 'superadmin' => 'Super-Admin'];

/** Admin oder Super-Admin */
function is_admin(?array $user): bool
{
    return $user !== null && in_array($user['role'], ['admin', 'superadmin'], true);
}

function is_superadmin(?array $user): bool
{
    return $user !== null && $user['role'] === 'superadmin';
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        abort(403);
    }
    return $user;
}

/** Nur Super-Admins: Rechtstexte und Super-Admin-Rechte vergeben */
function require_superadmin(): array
{
    $user = require_login();
    if (!is_superadmin($user)) {
        abort(403, t('Das dürfen nur Super-Admins.'));
    }
    return $user;
}

function login_user(array $user): void
{
    session_boot();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['csrf'] = random_token(16);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [time(), $user['id']]);
}

function logout_user(): void
{
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function password_ok(string $password): ?string
{
    if (mb_len($password) < 8) {
        return t('Das Passwort muss mindestens 8 Zeichen lang sein.');
    }
    if (strlen($password) > 200) {
        return t('Das Passwort ist zu lang.');
    }
    return null;
}

function hash_password(string $password): string
{
    return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

/**
 * Einfache Ratenbegrenzung über die Datenbank.
 * Gibt true zurück, solange das Limit nicht erreicht ist.
 */
function rate_limit_hit(string $key, int $max, int $windowSeconds, bool $count = true): bool
{
    $key = substr($key, 0, 190);
    $now = time();
    $row = q_one('SELECT hits, window_start FROM rate_limits WHERE rkey = ?', [$key]);
    if (!$row || $row['window_start'] + $windowSeconds <= $now) {
        if ($count) {
            if ($row) {
                q('UPDATE rate_limits SET hits = 1, window_start = ? WHERE rkey = ?', [$now, $key]);
            } else {
                try {
                    q('INSERT INTO rate_limits (rkey, hits, window_start) VALUES (?, 1, ?)', [$key, $now]);
                } catch (PDOException $e) {
                    q('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?', [$key]);
                }
            }
        }
        return true;
    }
    if ($row['hits'] >= $max) {
        return false;
    }
    if ($count) {
        q('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?', [$key]);
    }
    return true;
}

function rate_limit_allowed(string $key, int $max, int $windowSeconds): bool
{
    return rate_limit_hit($key, $max, $windowSeconds, false);
}

function rate_limit_clear(string $key): void
{
    q('DELETE FROM rate_limits WHERE rkey = ?', [substr($key, 0, 190)]);
}
