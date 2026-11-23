<?php
// Freischaltung neuer Konten durch Super-Admins und befristete Testzugänge
//
//   users.approved_at  Zeitpunkt der Freischaltung (NULL = wartet)
//   users.expires_at   Zugang gültig bis Ende dieses Tages (NULL = unbegrenzt).
//                      Gilt nur für Quizersteller; Admins und Super-Admins laufen nie ab.
//
// Einstellungen (Tabelle settings, nur Super-Admins):
//   approval_required  '1' = neue Konten müssen freigeschaltet werden (Standard), '0' = nicht nötig
//   trial_days         Testzeitraum für neue Konten in Tagen, '0' = unbegrenzt (Standard)

const TRIAL_DAYS_MAX = 3650;

function accounts_approval_required(): bool
{
    return setting_get('approval_required', '1') !== '0';
}

function accounts_trial_days(): int
{
    return max(0, min(TRIAL_DAYS_MAX, (int)setting_get('trial_days', '0')));
}

function app_timezone(): DateTimeZone
{
    return new DateTimeZone((string)cfg('timezone', 'Europe/Vienna'));
}

/** Letzte Sekunde eines Tages (Y-m-d) in der Zeitzone der Anwendung */
function end_of_day(string $ymd): ?int
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, app_timezone());
    if (!$d || $d->format('Y-m-d') !== $ymd) {
        return null;
    }
    return $d->setTime(23, 59, 59)->getTimestamp();
}

/** Zeitstempel → Y-m-d für <input type="date"> */
function ymd(?int $ts): string
{
    return $ts ? (new DateTimeImmutable('@' . $ts))->setTimezone(app_timezone())->format('Y-m-d') : '';
}

/** Ablaufdatum für ein Konto, das jetzt freigeschaltet wird (Testzeitraum aus den Einstellungen) */
function accounts_default_expiry(): ?int
{
    $days = accounts_trial_days();
    if ($days <= 0) {
        return null;
    }
    $d = (new DateTimeImmutable('now', app_timezone()))->modify('+' . $days . ' days');
    return end_of_day($d->format('Y-m-d'));
}

/** Läuft der Zugang dieses Kontos überhaupt ab? (nur Quizersteller) */
function account_can_expire(array $u): bool
{
    return ($u['role'] ?? 'host') === 'host';
}

/** @return 'locked'|'unverified'|'pending'|'expired'|'active' */
function account_status(array $u): string
{
    if (!empty($u['is_locked'])) {
        return 'locked';
    }
    if (empty($u['verified_at'])) {
        return 'unverified';
    }
    if (empty($u['approved_at']) && accounts_approval_required()) {
        return 'pending';
    }
    if (account_can_expire($u) && !empty($u['expires_at']) && (int)$u['expires_at'] < time()) {
        return 'expired';
    }
    return 'active';
}

/** Ganze Tage bis zum Ablauf (0 = läuft heute ab), null = unbegrenzt */
function account_days_left(array $u): ?int
{
    if (!account_can_expire($u) || empty($u['expires_at'])) {
        return null;
    }
    $today = new DateTimeImmutable('today', app_timezone());
    $end = (new DateTimeImmutable('@' . (int)$u['expires_at']))->setTimezone(app_timezone())->setTime(0, 0);
    return (int)$today->diff($end)->format('%r%a');
}

/** Meldung für gesperrte Anmeldung, sonst null */
function account_block_message(array $u): ?string
{
    switch (account_status($u)) {
        case 'pending':
            return t('Dein Konto ist noch nicht freigeschaltet. Sobald ein Super-Admin es freigibt, bekommst du eine E-Mail.');
        case 'expired':
            return t('Dein Testzugang ist am {date} abgelaufen. Wende dich an die Verwaltung, wenn du Naguut weiter nutzen möchtest.',
                ['date' => fmt_date((int)$u['expires_at'], false)]);
    }
    return null;
}

/**
 * Konto freischalten. Ohne Pflicht zur Freischaltung passiert das automatisch
 * bei der Bestätigung der E-Mail-Adresse bzw. bei der nächsten Anmeldung.
 */
function account_approve(int $userId, ?int $expiresAt): void
{
    q('UPDATE users SET approved_at = ?, expires_at = ? WHERE id = ?', [time(), $expiresAt, $userId]);
}

/** Bestätigtes, aber nie freigeschaltetes Konto automatisch freischalten, wenn keine Freischaltung nötig ist */
function account_auto_approve(array $u): array
{
    if (empty($u['approved_at']) && !empty($u['verified_at']) && !accounts_approval_required()) {
        $u['approved_at'] = time();
        $u['expires_at'] = account_can_expire($u) ? accounts_default_expiry() : null;
        account_approve((int)$u['id'], $u['expires_at']);
    }
    return $u;
}

function accounts_pending_count(): int
{
    if (!accounts_approval_required()) {
        return 0;
    }
    return (int)q_val('SELECT COUNT(*) FROM users WHERE approved_at IS NULL AND verified_at IS NOT NULL AND is_locked = 0');
}

// ---------- E-Mails ----------

/** Super-Admins über ein neues Konto informieren, das auf Freischaltung wartet */
function send_approval_request_mail(array $newUser): void
{
    foreach (q_all("SELECT email, display_name FROM users WHERE role = 'superadmin' AND is_locked = 0") as $sa) {
        $body = t("Hallo {name},\n\n{new} ({email}) hat ein Konto bei Naguut angelegt und die E-Mail-Adresse bestätigt. Das Konto wartet auf deine Freischaltung:\n\n{link}", [
            'name' => $sa['display_name'], 'new' => $newUser['display_name'], 'email' => $newUser['email'],
            'link' => abs_url('/admin#freischaltung'),
        ]);
        send_mail($sa['email'], t('Naguut: Neues Konto wartet auf Freischaltung'), $body);
    }
}

function send_approved_mail(array $user, ?int $expiresAt): bool
{
    $until = $expiresAt
        ? t('Dein Testzugang gilt bis {date}.', ['date' => fmt_date($expiresAt, false)])
        : t('Dein Zugang ist unbefristet.');
    $body = t("Hallo {name},\n\ndein Konto bei Naguut ist freigeschaltet. {until}\n\nHier meldest du dich an:\n\n{link}", [
        'name' => $user['display_name'], 'until' => $until, 'link' => abs_url('/login'),
    ]);
    return send_mail($user['email'], t('Naguut: Dein Konto ist freigeschaltet'), $body);
}

function send_rejected_mail(array $user): bool
{
    $body = t("Hallo {name},\n\ndein Konto bei Naguut wurde nicht freigeschaltet und ist gelöscht. Wenn du Fragen dazu hast, wende dich an die Betreiber der Seite.", [
        'name' => $user['display_name'],
    ]);
    return send_mail($user['email'], t('Naguut: Konto nicht freigeschaltet'), $body);
}
