<?php
// Konten: Anmeldung, Registrierung, Bestätigung, Passwort, Profil

function auth_login_form(): void
{
    if (current_user()) {
        redirect('/dashboard');
    }
    view('auth/login', ['title' => t('Anmelden')]);
}

/** @return array{user?:array, error?:string, unverified?:bool} */
function auth_attempt(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $ipKey = 'login-ip:' . client_ip();
    $key = 'login:' . client_ip() . ':' . $email;
    if (!rate_limit_allowed($key, 5, 900) || !rate_limit_allowed($ipKey, 30, 900)) {
        return ['error' => t('Zu viele Anmeldeversuche. Warte 15 Minuten und versuche es dann erneut.')];
    }
    $user = q_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        rate_limit_hit($key, 5, 900);
        rate_limit_hit($ipKey, 30, 900);
        return ['error' => t('E-Mail-Adresse oder Passwort stimmen nicht.')];
    }
    if ($user['is_locked']) {
        return ['error' => t('Dieses Konto ist gesperrt. Wende dich an die Verwaltung.')];
    }
    if (!$user['verified_at']) {
        return ['error' => t('Bitte bestätige zuerst deine E-Mail-Adresse. Den Link haben wir dir geschickt.'), 'unverified' => true];
    }
    $user = account_auto_approve($user);
    if ($blocked = account_block_message($user)) {
        return ['error' => $blocked];
    }
    rate_limit_clear($key);
    if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $user['id']]);
    }
    login_user($user);
    return ['user' => $user];
}

function auth_login(): void
{
    csrf_check();
    $email = (string)post('email');
    $result = auth_attempt($email, (string)($_POST['password'] ?? ''));
    if (isset($result['error'])) {
        $GLOBALS['NAGUUT_OLD'] = ['email' => $email];
        view('auth/login', ['title' => t('Anmelden'), 'error' => $result['error'], 'unverified' => !empty($result['unverified'])]);
        return;
    }
    $to = $_SESSION['after_login'] ?? '/dashboard';
    unset($_SESSION['after_login']);
    redirect(str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/dashboard');
}

function auth_logout(): void
{
    csrf_check();
    logout_user();
    redirect('/');
}

function auth_register_form(): void
{
    if (!cfg('registration_open', true)) {
        abort(403, t('Die Registrierung ist derzeit geschlossen.'));
    }
    view('auth/register', ['title' => t('Konto anlegen')]);
}

function auth_register(): void
{
    if (!cfg('registration_open', true)) {
        abort(403, t('Die Registrierung ist derzeit geschlossen.'));
    }
    csrf_check();
    $name = (string)post('display_name');
    $email = mb_strtolower((string)post('email'));
    $password = (string)($_POST['password'] ?? '');
    $errors = [];

    if (!rate_limit_hit('register:' . client_ip(), 10, 3600)) {
        abort(429);
    }
    if ($name === '' || mb_len($name) > 80) {
        $errors[] = t('Gib deinen Namen an (höchstens 80 Zeichen).');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = t('Gib eine gültige E-Mail-Adresse an.');
    }
    if ($e = password_ok($password)) {
        $errors[] = $e;
    }
    if ($password !== (string)($_POST['password_confirm'] ?? '')) {
        $errors[] = t('Die beiden Passwörter stimmen nicht überein.');
    }
    if (empty($_POST['privacy'])) {
        $errors[] = t('Bitte akzeptiere die AGB und bestätige, dass du die Datenschutzerklärung gelesen hast.');
    }
    if (!$errors && q_val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
        $errors[] = t('Für diese E-Mail-Adresse gibt es schon ein Konto. Melde dich an oder setze das Passwort zurück.');
    }
    if ($errors) {
        $GLOBALS['NAGUUT_OLD'] = ['display_name' => $name, 'email' => $email];
        view('auth/register', ['title' => t('Konto anlegen'), 'errors' => $errors]);
        return;
    }

    $token = random_token(24);
    $id = q_insert('users', [
        'email' => $email, 'password_hash' => hash_password($password), 'display_name' => $name,
        'role' => 'host', 'is_locked' => 0, 'verify_token_hash' => token_hash($token), 'created_at' => time(),
    ]);
    send_verification_mail(['email' => $email, 'display_name' => $name, 'id' => $id], $token);
    view('auth/message', [
        'title' => t('Fast geschafft'),
        'message' => t('Wir haben dir eine E-Mail an {email} geschickt. Öffne den Link darin, um dein Konto zu bestätigen.', ['email' => $email])
            . (accounts_approval_required() ? ' ' . t('Danach schaltet ein Super-Admin dein Konto frei. Auch darüber bekommst du eine E-Mail.') : ''),
    ]);
}

function auth_verify(): void
{
    $token = (string)($_GET['token'] ?? '');
    $user = preg_match('/^[a-f0-9]{48}$/', $token)
        ? q_one('SELECT * FROM users WHERE verify_token_hash = ?', [token_hash($token)]) : null;
    if (!$user) {
        view('auth/message', ['title' => t('Link ungültig'),
            'message' => t('Dieser Bestätigungslink ist ungültig oder wurde schon verwendet. Melde dich einfach an.'),
            'link' => ['/login', t('Zur Anmeldung')]]);
        return;
    }
    q('UPDATE users SET verified_at = ?, verify_token_hash = NULL WHERE id = ?', [time(), $user['id']]);
    $user = account_auto_approve(q_one('SELECT * FROM users WHERE id = ?', [$user['id']]));
    if (account_status($user) === 'pending') {
        send_approval_request_mail($user);
        view('auth/message', ['title' => t('E-Mail-Adresse bestätigt'),
            'message' => t('Danke! Jetzt muss noch ein Super-Admin dein Konto freischalten. Sobald das passiert ist, bekommst du eine E-Mail und kannst dich anmelden.')]);
        return;
    }
    login_user($user);
    flash('success', t('Deine E-Mail-Adresse ist bestätigt. Willkommen bei Naguut!'));
    redirect('/dashboard');
}

function auth_verify_resend(): void
{
    csrf_check();
    $email = mb_strtolower((string)post('email'));
    if (!rate_limit_hit('resend:' . client_ip(), 5, 3600)) {
        abort(429);
    }
    $user = q_one('SELECT * FROM users WHERE email = ? AND verified_at IS NULL', [$email]);
    if ($user) {
        $token = random_token(24);
        q('UPDATE users SET verify_token_hash = ? WHERE id = ?', [token_hash($token), $user['id']]);
        send_verification_mail($user, $token);
    }
    view('auth/message', ['title' => t('E-Mail verschickt'),
        'message' => t('Falls ein unbestätigtes Konto mit dieser Adresse existiert, ist ein neuer Bestätigungslink unterwegs.')]);
}

function auth_forgot_form(): void
{
    view('auth/forgot', ['title' => t('Passwort vergessen')]);
}

function auth_forgot(): void
{
    csrf_check();
    $email = mb_strtolower((string)post('email'));
    if (!rate_limit_hit('forgot:' . client_ip(), 5, 3600)) {
        abort(429);
    }
    $user = q_one('SELECT * FROM users WHERE email = ? AND is_locked = 0', [$email]);
    if ($user) {
        $token = random_token(24);
        q('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
        q_insert('password_resets', ['user_id' => $user['id'], 'token_hash' => token_hash($token), 'expires_at' => time() + 3600]);
        send_reset_mail($user, $token);
    }
    view('auth/message', ['title' => t('E-Mail verschickt'),
        'message' => t('Falls ein Konto mit dieser Adresse existiert, bekommst du gleich eine E-Mail mit einem Link. Er ist 60 Minuten gültig.')]);
}

function auth_reset_row(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    return q_one('SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > ?', [token_hash($token), time()]);
}

function auth_reset_form(): void
{
    $token = (string)($_GET['token'] ?? '');
    if (!auth_reset_row($token)) {
        view('auth/message', ['title' => t('Link abgelaufen'),
            'message' => t('Dieser Link ist abgelaufen oder ungültig. Fordere einfach einen neuen an.'),
            'link' => ['/forgot', t('Neuen Link anfordern')]]);
        return;
    }
    view('auth/reset', ['title' => t('Neues Passwort'), 'token' => $token]);
}

function auth_reset(): void
{
    csrf_check();
    $token = (string)post('token');
    $row = auth_reset_row($token);
    if (!$row) {
        redirect('/reset?token=invalid');
    }
    $password = (string)($_POST['password'] ?? '');
    $error = password_ok($password) ?? ($password !== (string)($_POST['password_confirm'] ?? '') ? t('Die beiden Passwörter stimmen nicht überein.') : null);
    if ($error) {
        view('auth/reset', ['title' => t('Neues Passwort'), 'token' => $token, 'error' => $error]);
        return;
    }
    q('UPDATE users SET password_hash = ?, verified_at = COALESCE(verified_at, ?) WHERE id = ?', [hash_password($password), time(), $row['user_id']]);
    q('DELETE FROM password_resets WHERE user_id = ?', [$row['user_id']]);
    flash('success', t('Dein Passwort ist geändert. Du kannst dich jetzt anmelden.'));
    redirect('/login');
}

function auth_profile(): void
{
    $user = require_login();
    view('auth/profile', ['title' => t('Profil'), 'user' => $user]);
}

function auth_profile_save(): void
{
    $user = require_login();
    csrf_check();
    $name = (string)post('display_name');
    $email = mb_strtolower((string)post('email'));
    $errors = [];
    if ($name === '' || mb_len($name) > 80) {
        $errors[] = t('Gib deinen Namen an (höchstens 80 Zeichen).');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('Gib eine gültige E-Mail-Adresse an.');
    }
    if ($email !== $user['email']) {
        if (!password_verify((string)($_POST['current_password'] ?? ''), $user['password_hash'])) {
            $errors[] = t('Zum Ändern der E-Mail-Adresse brauchen wir dein aktuelles Passwort.');
        } elseif (q_val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])) {
            $errors[] = t('Diese E-Mail-Adresse wird schon verwendet.');
        }
    }
    if ($errors) {
        view('auth/profile', ['title' => t('Profil'), 'user' => array_merge($user, ['display_name' => $name, 'email' => $email]), 'errors' => $errors]);
        return;
    }
    q('UPDATE users SET display_name = ?, email = ? WHERE id = ?', [$name, $email, $user['id']]);
    flash('success', t('Dein Profil ist gespeichert.'));
    redirect('/profile');
}

function auth_password_save(): void
{
    $user = require_login();
    csrf_check();
    $errors = [];
    if (!password_verify((string)($_POST['current_password'] ?? ''), $user['password_hash'])) {
        $errors[] = t('Das aktuelle Passwort stimmt nicht.');
    }
    $new = (string)($_POST['password'] ?? '');
    if ($e = password_ok($new)) {
        $errors[] = $e;
    } elseif ($new !== (string)($_POST['password_confirm'] ?? '')) {
        $errors[] = t('Die beiden Passwörter stimmen nicht überein.');
    }
    if ($errors) {
        view('auth/profile', ['title' => t('Profil'), 'user' => $user, 'pwErrors' => $errors]);
        return;
    }
    q('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($new), $user['id']]);
    session_regenerate_id(true);
    flash('success', t('Dein Passwort ist geändert.'));
    redirect('/profile');
}

function auth_account_delete(): void
{
    $user = require_login();
    csrf_check();
    if (!password_verify((string)($_POST['current_password'] ?? ''), $user['password_hash'])) {
        view('auth/profile', ['title' => t('Profil'), 'user' => $user, 'delErrors' => [t('Das Passwort stimmt nicht. Dein Konto wurde nicht gelöscht.')]]);
        return;
    }
    if ($user['role'] === 'superadmin' && (int)q_val("SELECT COUNT(*) FROM users WHERE role = 'superadmin'") <= 1) {
        view('auth/profile', ['title' => t('Profil'), 'user' => $user, 'delErrors' => [t('Du bist der einzige Super-Admin. Ernenne zuerst eine andere Person zum Super-Admin.')]]);
        return;
    }
    user_delete((int)$user['id']);
    logout_user();
    session_boot();
    flash('success', t('Dein Konto und alle deine Quizze und Spiele wurden gelöscht.'));
    redirect('/');
}

/** Löscht Konto mit Quizzen, Spielen, Spielern und Antworten */
function user_delete(int $userId): void
{
    foreach (q_all('SELECT id FROM games WHERE host_id = ?', [$userId]) as $g) {
        game_delete((int)$g['id']);
    }
    q('DELETE FROM users WHERE id = ?', [$userId]);
}
