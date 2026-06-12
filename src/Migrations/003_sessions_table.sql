CREATE TABLE IF NOT EXISTS sessions (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token         TEXT    NOT NULL UNIQUE,
    user_agent    TEXT    NOT NULL DEFAULT '',
    ip            TEXT    NOT NULL DEFAULT '',
    created_at    TEXT    NOT NULL,
    last_activity TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_token   ON sessions(token);

INSERT OR IGNORE INTO settings (key, value) VALUES ('session_max_days', '30');
