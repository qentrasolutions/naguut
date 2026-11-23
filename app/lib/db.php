<?php
// Datenbankzugriff über PDO (immer mit Prepared Statements)

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(cfg('db.dsn'), cfg('db.user'), cfg('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        if (db_driver($pdo) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $pdo->exec("SET time_zone = '+00:00'");
        }
    }
    return $pdo;
}

function db_driver(?PDO $pdo = null): string
{
    return ($pdo ?? db())->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function q_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES ('
        . implode(', ', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function tx(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function is_unique_violation(PDOException $e): bool
{
    $code = (string)$e->getCode();
    return $code === '23000' || $code === '23505' || str_contains($e->getMessage(), 'UNIQUE');
}

function db_has_column(PDO $pdo, string $table, string $column): bool
{
    if (db_driver($pdo) === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if ($c['name'] === $column) {
                return true;
            }
        }
        return false;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

const DB_SCHEMA_VERSION = 10;

/** Bringt eine bestehende Datenbank auf den aktuellen Stand (Updates von 1.0.0) */
function db_migrate(PDO $pdo): void
{
    $sqlite = db_driver($pdo) === 'sqlite';
    if (!db_has_column($pdo, 'quizzes', 'music')) {
        $pdo->exec('ALTER TABLE quizzes ADD COLUMN music ' . ($sqlite ? 'TEXT' : 'VARCHAR(64)') . ' NULL');
    }
    if (!db_has_column($pdo, 'games', 'skipped')) {
        $pdo->exec('ALTER TABLE games ADD COLUMN skipped ' . ($sqlite ? 'TEXT' : 'VARCHAR(500)') . " NOT NULL DEFAULT ''");
    }
    foreach (['quizzes', 'games'] as $tbl) {
        if (!db_has_column($pdo, $tbl, 'show_on_devices')) {
            $pdo->exec("ALTER TABLE $tbl ADD COLUMN show_on_devices " . ($sqlite ? 'INTEGER' : 'TINYINT(1)') . ' NOT NULL DEFAULT 0');
        }
    }
    $pdo->exec($sqlite
        ? 'CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE COLLATE NOCASE, position INTEGER NOT NULL DEFAULT 0)'
        : 'CREATE TABLE IF NOT EXISTS categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL, position INT NOT NULL DEFAULT 0, UNIQUE KEY uq_categories_name (name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    if (!db_has_column($pdo, 'users', 'approved_at')) {
        // Bestehende Konten gelten als freigeschaltet, damit sich nach dem Update niemand aussperrt
        $pdo->exec('ALTER TABLE users ADD COLUMN approved_at ' . ($sqlite ? 'INTEGER' : 'INT UNSIGNED') . ' NULL');
        $pdo->exec('UPDATE users SET approved_at = COALESCE(verified_at, created_at)');
    }
    if (!db_has_column($pdo, 'users', 'expires_at')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN expires_at ' . ($sqlite ? 'INTEGER' : 'INT UNSIGNED') . ' NULL');
    }
    if (!db_has_column($pdo, 'quizzes', 'category_id')) {
        $pdo->exec('ALTER TABLE quizzes ADD COLUMN category_id ' . ($sqlite ? 'INTEGER' : 'INT UNSIGNED') . ' NULL');
    }
    if (!db_has_column($pdo, 'quizzes', 'tags')) {
        $pdo->exec('ALTER TABLE quizzes ADD COLUMN tags ' . ($sqlite ? 'TEXT' : 'VARCHAR(500)') . " NOT NULL DEFAULT ''");
    }
    if (!db_has_column($pdo, 'responses', 'answer_text')) {
        $pdo->exec('ALTER TABLE responses ADD COLUMN answer_text ' . ($sqlite ? 'TEXT' : 'VARCHAR(255)') . ' NULL');
    }
    if (!db_has_column($pdo, 'questions', 'extra')) {
        $pdo->exec('ALTER TABLE questions ADD COLUMN extra TEXT NULL');
    }
    if (!db_has_column($pdo, 'responses', 'answer_value')) {
        $pdo->exec('ALTER TABLE responses ADD COLUMN answer_value ' . ($sqlite ? 'REAL' : 'DOUBLE') . ' NULL');
    }
    if (!db_has_column($pdo, 'players', 'avatar')) {
        $pdo->exec('ALTER TABLE players ADD COLUMN avatar ' . ($sqlite ? 'TEXT' : 'VARCHAR(20)') . ' NULL');
    }
    if (!(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn()) {
        $first = $pdo->query("SELECT MIN(id) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($first) {
            $pdo->prepare("UPDATE users SET role = 'superadmin' WHERE id = ?")->execute([(int)$first]);
        }
    }
    $pdo->exec($sqlite
        ? 'CREATE TABLE IF NOT EXISTS settings (skey TEXT NOT NULL PRIMARY KEY, svalue TEXT NULL)'
        : 'CREATE TABLE IF NOT EXISTS settings (skey VARCHAR(64) NOT NULL PRIMARY KEY, svalue TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

/** Führt die Migration einmal aus und merkt sich das in app/storage */
function db_migrate_once(): void
{
    $flag = APP_DIR . '/storage/schema-' . DB_SCHEMA_VERSION;
    if (is_file($flag)) {
        return;
    }
    db_migrate(db());
    if (!is_dir(APP_DIR . '/storage')) {
        @mkdir(APP_DIR . '/storage', 0750, true);
    }
    @touch($flag);
}

/** Systemweite Einstellung lesen (z. B. Standard-Musik) */
function setting_get(string $key, ?string $default = null): ?string
{
    $v = q_val('SELECT svalue FROM settings WHERE skey = ?', [$key]);
    return $v === null ? $default : (string)$v;
}

function setting_set(string $key, ?string $value): void
{
    q('DELETE FROM settings WHERE skey = ?', [$key]);
    if ($value !== null) {
        q('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
    }
}

/** Schema aus SQL-Datei anlegen (Installer und Tests) */
function db_install_schema(PDO $pdo): void
{
    $file = APP_DIR . '/sql/' . (db_driver($pdo) === 'sqlite' ? 'sqlite' : 'mysql') . '.sql';
    $sql = file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $pdo->exec($stmt);
    }
}
