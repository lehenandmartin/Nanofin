-- ── Migration 001 : initial schema ──────────────────────────────

-- ── users ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    username              TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password              TEXT    NOT NULL,                           -- bcrypt hash
    role                  TEXT    NOT NULL DEFAULT 'user'             -- 'admin' | 'user'
                                  CHECK (role IN ('admin', 'user')),
    content_access        TEXT    NOT NULL DEFAULT 'both'             -- 'movies' | 'shows' | 'both'
                                  CHECK (content_access IN ('movies', 'shows', 'both')),
    email                 TEXT    NOT NULL DEFAULT '',                -- single address; '' = none
    session_token         TEXT    NULL,                               -- NULL = no active forced-revocation
    force_password_change INTEGER NOT NULL DEFAULT 0,                 -- 1 = must change password at next login
    last_activity         TEXT    NULL,                               -- ISO-8601 datetime
    created_at            TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users (username COLLATE NOCASE);

-- ── settings ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);

INSERT OR IGNORE INTO settings (key, value) VALUES
    ('site_title',           'Nanofin'),
    ('public_mode',          '0'),
    ('default_locale',       'en'),
    ('default_sort',         'added'),
    ('grid_rows',            '4'),
    ('poster_cache_days',    '7'),
    ('timezone',             'UTC'),
    ('jellyfin_url',         ''),
    ('jellyfin_api_key',     ''),
    ('smtp_host',            ''),
    ('smtp_port',            '587'),
    ('smtp_user',            ''),
    ('smtp_password',        ''),
    ('smtp_from',            ''),
    ('allow_password_reset', '0'),
    ('allow_magic_link',     '0'),
    ('smtp_ok',              '0'),
    ('smtp_last_error',      '');

-- ── downloads ──────────────────────────────────────────────────
-- user_id is nullable to support public mode (unauthenticated downloads).
CREATE TABLE IF NOT EXISTS downloads (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id       INTEGER NULL REFERENCES users (id) ON DELETE SET NULL,
    item_id       TEXT    NOT NULL,          -- Jellyfin item ID
    item_title    TEXT    NOT NULL,
    item_type     TEXT    NOT NULL           -- 'movie' | 'episode'
                          CHECK (item_type IN ('movie', 'episode')),
    downloaded_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_downloads_user_id       ON downloads (user_id);
CREATE INDEX IF NOT EXISTS idx_downloads_downloaded_at ON downloads (downloaded_at);

-- ── auth_tokens ────────────────────────────────────────────────
-- Used for magic-link authentication.
-- Tokens are stored hashed — the raw token is only ever in the email link.
-- Single-use, 15-minute TTL (enforced in AuthTokenModel).
CREATE TABLE IF NOT EXISTS auth_tokens (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    token_hash TEXT    NOT NULL UNIQUE,   -- hash('sha256', raw_token)
    expires_at TEXT    NOT NULL,          -- ISO-8601 datetime
    used_at    TEXT    NULL,              -- NULL = not yet used
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_auth_tokens_user_id    ON auth_tokens (user_id);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_token_hash ON auth_tokens (token_hash);
CREATE INDEX IF NOT EXISTS idx_auth_tokens_expires_at ON auth_tokens (expires_at);

-- ── login_attempts ─────────────────────────────────────────────
-- Track failed login attempts per IP for brute-force protection.
-- Max 5 failures / IP / 15 min. Purged on successful login.
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    ip           TEXT    NOT NULL,
    attempted_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time
    ON login_attempts (ip, attempted_at);
