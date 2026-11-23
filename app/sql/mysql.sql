-- Naguut – Datenbankschema für MySQL 8 / MariaDB 10.6+
-- Zeitangaben als Unix-Zeitstempel (Sekunden) bzw. Millisekunden (*_ms).

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(80) NOT NULL,
    role VARCHAR(10) NOT NULL DEFAULT 'host',
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    verify_token_hash CHAR(64) NULL,
    verified_at INT UNSIGNED NULL,
    approved_at INT UNSIGNED NULL,
    expires_at INT UNSIGNED NULL,
    created_at INT UNSIGNED NOT NULL,
    last_login_at INT UNSIGNED NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quizzes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    cover_image VARCHAR(64) NULL,
    music VARCHAR(64) NULL,
    show_on_devices TINYINT(1) NOT NULL DEFAULT 0,
    category_id INT UNSIGNED NULL,
    tags VARCHAR(500) NOT NULL DEFAULT '',
    language VARCHAR(5) NOT NULL DEFAULT 'de',
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    created_at INT UNSIGNED NOT NULL,
    updated_at INT UNSIGNED NOT NULL,
    KEY idx_quizzes_user (user_id),
    KEY idx_quizzes_public (is_public),
    CONSTRAINT fk_quizzes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    type VARCHAR(5) NOT NULL,
    text TEXT NOT NULL,
    image VARCHAR(64) NULL,
    time_limit_s SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    points_factor TINYINT UNSIGNED NOT NULL DEFAULT 1,
    extra TEXT NULL,
    KEY idx_questions_quiz (quiz_id, position),
    CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    position TINYINT UNSIGNED NOT NULL,
    text VARCHAR(120) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_answers_question (question_id, position),
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NULL,
    host_id INT UNSIGNED NOT NULL,
    pin CHAR(6) NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'lobby',
    current_question SMALLINT NOT NULL DEFAULT -1,
    question_starts_ms BIGINT NULL,
    question_ends_ms BIGINT NULL,
    state_version INT UNSIGNED NOT NULL DEFAULT 1,
    quiz_title VARCHAR(150) NOT NULL,
    quiz_snapshot MEDIUMTEXT NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    updated_at INT UNSIGNED NOT NULL,
    finished_at INT UNSIGNED NULL,
    skipped VARCHAR(500) NOT NULL DEFAULT '',
    show_on_devices TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_games_pin (pin, status),
    KEY idx_games_host (host_id, created_at),
    CONSTRAINT fk_games_host FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS players (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    nickname VARCHAR(20) NOT NULL,
    avatar VARCHAR(20) NULL,
    rejoin_token_hash CHAR(64) NOT NULL,
    score INT NOT NULL DEFAULT 0,
    is_kicked TINYINT(1) NOT NULL DEFAULT 0,
    joined_at INT UNSIGNED NOT NULL,
    last_seen_at INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_players_nick (game_id, nickname),
    KEY idx_players_token (rejoin_token_hash),
    CONSTRAINT fk_players_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    question_no SMALLINT UNSIGNED NOT NULL,
    answer_idx TINYINT UNSIGNED NOT NULL,
    answer_value DOUBLE NULL,
    answer_text VARCHAR(255) NULL,
    response_ms INT UNSIGNED NOT NULL,
    is_correct TINYINT(1) NOT NULL,
    points INT NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_responses_player_q (player_id, question_no),
    KEY idx_responses_game_q (game_id, question_no),
    CONSTRAINT fk_responses_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_responses_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    KEY idx_resets_token (token_hash),
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(64) NOT NULL PRIMARY KEY,
    svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    rkey VARCHAR(190) NOT NULL PRIMARY KEY,
    hits INT UNSIGNED NOT NULL,
    window_start INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
