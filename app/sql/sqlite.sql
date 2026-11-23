-- Naguut – Schema für SQLite (nur für lokale Entwicklung und Tests)
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'host',
    is_locked INTEGER NOT NULL DEFAULT 0,
    verify_token_hash TEXT NULL,
    verified_at INTEGER NULL,
    approved_at INTEGER NULL,
    expires_at INTEGER NULL,
    created_at INTEGER NOT NULL,
    last_login_at INTEGER NULL
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE COLLATE NOCASE,
    position INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS quizzes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    description TEXT NULL,
    cover_image TEXT NULL,
    music TEXT NULL,
    show_on_devices INTEGER NOT NULL DEFAULT 0,
    category_id INTEGER NULL,
    tags TEXT NOT NULL DEFAULT '',
    language TEXT NOT NULL DEFAULT 'de',
    is_public INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_quizzes_user ON quizzes(user_id);

CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quiz_id INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
    position INTEGER NOT NULL,
    type TEXT NOT NULL,
    text TEXT NOT NULL,
    image TEXT NULL,
    time_limit_s INTEGER NOT NULL DEFAULT 20,
    points_factor INTEGER NOT NULL DEFAULT 1,
    extra TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_questions_quiz ON questions(quiz_id, position);

CREATE TABLE IF NOT EXISTS answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
    position INTEGER NOT NULL,
    text TEXT NOT NULL,
    is_correct INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_answers_question ON answers(question_id, position);

CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quiz_id INTEGER NULL,
    host_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pin TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'lobby',
    current_question INTEGER NOT NULL DEFAULT -1,
    question_starts_ms INTEGER NULL,
    question_ends_ms INTEGER NULL,
    state_version INTEGER NOT NULL DEFAULT 1,
    quiz_title TEXT NOT NULL,
    quiz_snapshot TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    finished_at INTEGER NULL,
    skipped TEXT NOT NULL DEFAULT '',
    show_on_devices INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_games_pin ON games(pin, status);
CREATE INDEX IF NOT EXISTS idx_games_host ON games(host_id, created_at);

CREATE TABLE IF NOT EXISTS players (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    nickname TEXT NOT NULL COLLATE NOCASE,
    avatar TEXT NULL,
    rejoin_token_hash TEXT NOT NULL,
    score INTEGER NOT NULL DEFAULT 0,
    is_kicked INTEGER NOT NULL DEFAULT 0,
    joined_at INTEGER NOT NULL,
    last_seen_at INTEGER NOT NULL,
    UNIQUE (game_id, nickname)
);
CREATE INDEX IF NOT EXISTS idx_players_token ON players(rejoin_token_hash);

CREATE TABLE IF NOT EXISTS responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    player_id INTEGER NOT NULL REFERENCES players(id) ON DELETE CASCADE,
    question_no INTEGER NOT NULL,
    answer_idx INTEGER NOT NULL,
    answer_value REAL NULL,
    answer_text TEXT NULL,
    response_ms INTEGER NOT NULL,
    is_correct INTEGER NOT NULL,
    points INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    UNIQUE (player_id, question_no)
);
CREATE INDEX IF NOT EXISTS idx_responses_game_q ON responses(game_id, question_no);

CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_resets_token ON password_resets(token_hash);

CREATE TABLE IF NOT EXISTS settings (
    skey TEXT NOT NULL PRIMARY KEY,
    svalue TEXT NULL
);

CREATE TABLE IF NOT EXISTS rate_limits (
    rkey TEXT NOT NULL PRIMARY KEY,
    hits INTEGER NOT NULL,
    window_start INTEGER NOT NULL
);
