-- ── Migration 002 : Discord webhook settings ──────────────────

INSERT OR IGNORE INTO settings (key, value) VALUES
    ('discord_webhook_url',        ''),
    ('discord_notify_downloads',   '0'),
    ('discord_webhook_ok',         '0'),
    ('discord_webhook_last_error', '');
