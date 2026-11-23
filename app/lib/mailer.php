<?php
// E-Mail-Versand über PHP mail() (über den Mailserver des Hosters)

function send_mail(string $to, string $subject, string $body): bool
{
    $fromEmail = (string)cfg('mail_from', 'noreply@localhost');
    $fromName = (string)cfg('mail_from_name', APP_NAME);
    $body .= "\n\n-- \n" . APP_NAME . "\n" . abs_url('/');

    if (cfg('mail_mode', 'mail') === 'log') {
        $dir = APP_DIR . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $entry = '[' . date('c') . "] An: $to\nBetreff: $subject\n\n$body\n" . str_repeat('-', 60) . "\n";
        return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers = [
        'From: ' . $encodedName . ' <' . $fromEmail . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: ' . APP_NAME,
    ];
    return @mail($to, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
}

function send_verification_mail(array $user, string $token): bool
{
    $link = abs_url('/verify?token=' . $token);
    $body = t("Hallo {name},\n\nbitte bestätige deine E-Mail-Adresse für Naguut mit diesem Link:\n\n{link}\n\nWenn du dich nicht registriert hast, kannst du diese Nachricht ignorieren.", [
        'name' => $user['display_name'],
        'link' => $link,
    ]);
    return send_mail($user['email'], t('Naguut: E-Mail-Adresse bestätigen'), $body);
}

function send_reset_mail(array $user, string $token): bool
{
    $link = abs_url('/reset?token=' . $token);
    $body = t("Hallo {name},\n\nüber diesen Link kannst du ein neues Passwort festlegen. Er ist 60 Minuten gültig:\n\n{link}\n\nWenn du das nicht angefordert hast, ändert sich nichts.", [
        'name' => $user['display_name'],
        'link' => $link,
    ]);
    return send_mail($user['email'], t('Naguut: Passwort zurücksetzen'), $body);
}
