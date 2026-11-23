<?php
// Verwaltung für Admins: Konten und öffentliche Quizze

function admin_index(): void
{
    $admin = require_admin();
    $users = q_all('SELECT u.*, (SELECT COUNT(*) FROM quizzes q WHERE q.user_id = u.id) AS quiz_count,
                    (SELECT COUNT(*) FROM games g WHERE g.host_id = u.id) AS game_count
                    FROM users u ORDER BY u.created_at DESC');
    $public = q_all('SELECT q.id, q.title, q.updated_at, u.display_name AS author, u.email
                     FROM quizzes q JOIN users u ON u.id = q.user_id WHERE q.is_public = 1 ORDER BY q.updated_at DESC');
    $stats = [
        'games_running' => (int)q_val("SELECT COUNT(*) FROM games WHERE status <> 'finished'"),
        'games_total' => (int)q_val('SELECT COUNT(*) FROM games'),
        'players_total' => (int)q_val('SELECT COUNT(*) FROM players'),
    ];
    $pending = array_values(array_filter($users, fn($u) => account_status($u) === 'pending'));
    view('admin/index', ['title' => t('Verwaltung'), 'admin' => $admin, 'users' => $users, 'public' => $public, 'stats' => $stats,
        'pending' => $pending, 'approvalRequired' => accounts_approval_required(), 'trialDays' => accounts_trial_days(),
        'defaultExpiry' => accounts_default_expiry(),
        'categories' => categories_all(true),
        'defaultMusic' => default_music(), 'mapCustom' => setting_get('map_default_view') !== null]);
}

function admin_target(int $id, array $admin): array
{
    csrf_check();
    $user = q_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$user) {
        abort(404);
    }
    if ((int)$user['id'] === (int)$admin['id']) {
        flash('error', t('Dein eigenes Konto kannst du hier nicht ändern.'));
        redirect('/admin');
    }
    if ($user['role'] === 'superadmin' && !is_superadmin($admin)) {
        flash('error', t('Super-Admin-Konten können nur Super-Admins ändern.'));
        redirect('/admin');
    }
    return $user;
}

function admin_user_lock(int $id): void
{
    $admin = require_admin();
    $user = admin_target($id, $admin);
    q('UPDATE users SET is_locked = 1 WHERE id = ?', [$user['id']]);
    flash('success', t('{email} ist gesperrt.', ['email' => $user['email']]));
    redirect('/admin');
}

function admin_user_unlock(int $id): void
{
    $admin = require_admin();
    $user = admin_target($id, $admin);
    q('UPDATE users SET is_locked = 0 WHERE id = ?', [$user['id']]);
    flash('success', t('{email} ist entsperrt.', ['email' => $user['email']]));
    redirect('/admin');
}

function admin_user_role(int $id): void
{
    $admin = require_admin();
    $user = admin_target($id, $admin);
    $role = (string)post('role');
    if (!array_key_exists($role, ROLES)) {
        abort(400);
    }
    if ($role === 'superadmin' && !is_superadmin($admin)) {
        flash('error', t('Nur Super-Admins können weitere Super-Admins ernennen.'));
        redirect('/admin');
    }
    q('UPDATE users SET role = ? WHERE id = ?', [$role, $user['id']]);
    if ($role !== 'host' && empty($user['approved_at'])) {
        account_approve((int)$user['id'], null); // Admins brauchen keine eigene Freischaltung
    }
    flash('success', t('{email} ist jetzt {role}.', ['email' => $user['email'], 'role' => t(ROLES[$role])]));
    redirect('/admin');
}

function admin_user_delete(int $id): void
{
    $admin = require_admin();
    $user = admin_target($id, $admin);
    user_delete((int)$user['id']);
    flash('success', t('Das Konto {email} wurde gelöscht.', ['email' => $user['email']]));
    redirect('/admin');
}

// ---------- Freischaltung und Testzugang (nur Super-Admin) ----------

/** Datum aus dem Formular: '' = unbegrenzt, sonst Ende des gewählten Tages; bei Fehler Abbruch */
function admin_expiry_input(string $back): ?int
{
    $v = trim((string)post('expires_at'));
    if ($v === '' || post('unlimited') === '1') {
        return null;
    }
    $ts = end_of_day($v);
    if ($ts === null) {
        flash('error', t('Das Datum konnte nicht gelesen werden.'));
        redirect($back);
    }
    if ($ts < time()) {
        flash('error', t('Das Ablaufdatum liegt in der Vergangenheit. Wähle heute oder einen späteren Tag.'));
        redirect($back);
    }
    return $ts;
}

function admin_user_approve(int $id): void
{
    $admin = require_superadmin();
    $user = admin_target($id, $admin);
    $expires = account_can_expire($user) ? admin_expiry_input('/admin#freischaltung') : null;
    account_approve((int)$user['id'], $expires);
    send_approved_mail($user, $expires);
    flash('success', $expires
        ? t('{email} ist freigeschaltet, Zugang bis {date}.', ['email' => $user['email'], 'date' => fmt_date($expires, false)])
        : t('{email} ist unbefristet freigeschaltet.', ['email' => $user['email']]));
    redirect('/admin#freischaltung');
}

function admin_user_reject(int $id): void
{
    $admin = require_superadmin();
    $user = admin_target($id, $admin);
    if (!empty($user['approved_at'])) {
        flash('error', t('Dieses Konto ist schon freigeschaltet. Zum Entfernen „Löschen“ verwenden.'));
        redirect('/admin');
    }
    user_delete((int)$user['id']);
    send_rejected_mail($user);
    flash('success', t('Das Konto {email} wurde abgelehnt und gelöscht.', ['email' => $user['email']]));
    redirect('/admin#freischaltung');
}

function admin_user_expiry(int $id): void
{
    $admin = require_superadmin();
    $user = admin_target($id, $admin);
    if (!account_can_expire($user)) {
        flash('error', t('Admins und Super-Admins haben immer unbefristeten Zugang.'));
        redirect('/admin#konten');
    }
    $expires = admin_expiry_input('/admin#konten');
    q('UPDATE users SET expires_at = ? WHERE id = ?', [$expires, $user['id']]);
    flash('success', $expires
        ? t('Der Zugang von {email} gilt jetzt bis {date}.', ['email' => $user['email'], 'date' => fmt_date($expires, false)])
        : t('Der Zugang von {email} ist jetzt unbefristet.', ['email' => $user['email']]));
    redirect('/admin#konten');
}

function admin_accounts_save(): void
{
    require_superadmin();
    csrf_check();
    $days = trim((string)post('trial_days'));
    if ($days === '') {
        $days = '0';
    }
    if (!ctype_digit($days) || (int)$days > TRIAL_DAYS_MAX) {
        flash('error', t('Der Testzeitraum muss eine ganze Zahl zwischen 0 und {n} Tagen sein.', ['n' => TRIAL_DAYS_MAX]));
        redirect('/admin#neue-konten');
    }
    $required = !empty($_POST['approval_required']);
    setting_set('approval_required', $required ? '1' : '0');
    setting_set('trial_days', (string)(int)$days);
    flash('success', t('Die Einstellungen für neue Konten sind gespeichert.'));
    redirect('/admin#neue-konten');
}

function admin_music_upload(): void
{
    require_admin();
    // Ist die Datei größer als post_max_size, kommt das Formular leer an
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', t('Die Datei ist größer als das Upload-Limit des Servers ({limit}).', ['limit' => php_upload_limit()]));
        redirect('/admin#musik');
    }
    csrf_check();
    if (empty($_FILES['sound'])) {
        flash('error', t('Bitte wähle eine Sounddatei aus.'));
        redirect('/admin#musik');
    }
    $result = store_uploaded_sound($_FILES['sound']);
    if (isset($result['error'])) {
        flash('error', $result['error']);
        redirect('/admin#musik');
    }
    setting_set('default_music', $result['file']);
    flash('success', t('Die Standard-Musik ist gespeichert. Sie läuft in allen Spielen, deren Quiz keine eigene Musik hat.'));
    redirect('/admin#musik');
}

// ---------- Kategorien ----------

function admin_category_name(): string
{
    $name = trim(preg_replace('/\s+/u', ' ', (string)post('name')));
    if ($name === '' || mb_len($name) > CATEGORY_MAX_LEN) {
        flash('error', t('Der Name einer Kategorie muss 1 bis {n} Zeichen haben.', ['n' => CATEGORY_MAX_LEN]));
        redirect('/admin#kategorien');
    }
    return $name;
}

function admin_category_add(): void
{
    require_admin();
    csrf_check();
    $name = admin_category_name();
    try {
        q_insert('categories', ['name' => $name, 'position' => (int)q_val('SELECT COALESCE(MAX(position), 0) + 1 FROM categories')]);
        flash('success', t('Die Kategorie „{name}“ ist angelegt.', ['name' => $name]));
    } catch (PDOException $e) {
        if (!is_unique_violation($e)) {
            throw $e;
        }
        flash('error', t('Die Kategorie „{name}“ gibt es schon.', ['name' => $name]));
    }
    redirect('/admin#kategorien');
}

function admin_category_rename(int $id): void
{
    require_admin();
    csrf_check();
    $name = admin_category_name();
    try {
        q('UPDATE categories SET name = ? WHERE id = ?', [$name, $id]);
        flash('success', t('Die Kategorie heißt jetzt „{name}“.', ['name' => $name]));
    } catch (PDOException $e) {
        if (!is_unique_violation($e)) {
            throw $e;
        }
        flash('error', t('Die Kategorie „{name}“ gibt es schon.', ['name' => $name]));
    }
    redirect('/admin#kategorien');
}

function admin_category_move(int $id): void
{
    require_admin();
    csrf_check();
    $cats = categories_all();
    $ids = array_column($cats, 'id');
    $i = array_search($id, $ids, true);
    $j = $i === false ? false : $i + (post('dir') === 'up' ? -1 : 1);
    if ($i !== false && isset($ids[$j])) {
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        tx(function () use ($ids) {
            foreach ($ids as $pos => $cid) {
                q('UPDATE categories SET position = ? WHERE id = ?', [$pos + 1, $cid]);
            }
        });
    }
    redirect('/admin#kategorien');
}

function admin_category_delete(int $id): void
{
    require_admin();
    csrf_check();
    $name = q_val('SELECT name FROM categories WHERE id = ?', [$id]);
    if ($name === null) {
        abort(404);
    }
    tx(function () use ($id) {
        q('UPDATE quizzes SET category_id = NULL WHERE category_id = ?', [$id]);
        q('DELETE FROM categories WHERE id = ?', [$id]);
    });
    flash('success', t('Die Kategorie „{name}“ ist gelöscht. Ihre Quizze sind jetzt ohne Kategorie.', ['name' => $name]));
    redirect('/admin#kategorien');
}

function admin_map_save(): void
{
    require_admin();
    csrf_check();
    if (post('action') === 'reset') {
        setting_set('map_default_view', null);
        flash('success', t('Der Kartenausschnitt ist wieder auf Österreich gestellt.'));
        redirect('/admin#karte');
    }
    $view = map_clean_view(['lat' => post('lat'), 'lng' => post('lng'), 'zoom' => post('zoom')]);
    if (!$view) {
        flash('error', t('Der Kartenausschnitt konnte nicht gelesen werden.'));
        redirect('/admin#karte');
    }
    setting_set('map_default_view', json_encode($view));
    flash('success', t('Der Kartenausschnitt ist gespeichert. Er gilt für alle Kartenfragen ohne eigenen Ausschnitt.'));
    redirect('/admin#karte');
}

function admin_music_delete(): void
{
    require_admin();
    csrf_check();
    setting_set('default_music', null);
    flash('success', t('Die Standard-Musik wurde entfernt.'));
    redirect('/admin#musik');
}

// ---------- Rechtstexte (nur Super-Admin) ----------

function admin_legal(): void
{
    $admin = require_superadmin();
    $pages = [];
    foreach (LEGAL_PAGES as $slug => $info) {
        $pages[$slug] = $info + ['text' => legal_text($slug), 'custom' => legal_is_custom($slug), 'updated' => legal_updated_at($slug)];
    }
    view('admin/legal', ['title' => t('Rechtstexte'), 'pages' => $pages]);
}

function admin_legal_save(): void
{
    require_superadmin();
    csrf_check();
    $slug = (string)post('page');
    if (!isset(LEGAL_PAGES[$slug])) {
        abort(400);
    }
    $title = t(LEGAL_PAGES[$slug]['title']);
    if (post('action') === 'reset') {
        legal_save($slug, null);
        flash('success', t('{title}: Die Vorlage ist wiederhergestellt.', ['title' => $title]));
        redirect('/admin/rechtstexte#' . $slug);
    }
    $text = str_replace("\r\n", "\n", (string)($_POST['text'] ?? ''));
    if (trim($text) === '') {
        flash('error', t('{title}: Der Text darf nicht leer sein. Zum Zurücksetzen „Vorlage wiederherstellen“ wählen.', ['title' => $title]));
        redirect('/admin/rechtstexte#' . $slug);
    }
    if (mb_len($text) > LEGAL_MAX_CHARS) {
        flash('error', t('{title}: Der Text ist zu lang (höchstens {n} Zeichen).', ['title' => $title, 'n' => LEGAL_MAX_CHARS]));
        redirect('/admin/rechtstexte#' . $slug);
    }
    legal_save($slug, $text);
    flash('success', t('{title} ist gespeichert.', ['title' => $title]));
    redirect('/admin/rechtstexte#' . $slug);
}

function admin_quiz_hide(int $id): void
{
    require_admin();
    csrf_check();
    q('UPDATE quizzes SET is_public = 0 WHERE id = ?', [$id]);
    flash('success', t('Die Freigabe ist aufgehoben. Das Quiz ist nur noch für die Autorin oder den Autor sichtbar.'));
    redirect('/admin');
}

function admin_quiz_delete(int $id): void
{
    require_admin();
    csrf_check();
    q('DELETE FROM quizzes WHERE id = ?', [$id]);
    flash('success', t('Das Quiz wurde gelöscht.'));
    redirect('/admin');
}
