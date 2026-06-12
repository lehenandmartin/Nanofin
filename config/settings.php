<?php

declare(strict_types=1);

// ── Application metadata ─────────────────────────────────────────
define('APP_VERSION',    '1.2.0');
define('APP_GITHUB_URL', 'https://github.com/lehenandmartin/Nanofin');

// ── Path constants ───────────────────────────────────────────────
define('ROOT_DIR',         dirname(__DIR__));
define('PUBLIC_DIR',       ROOT_DIR . '/public');
define('SRC_DIR',          ROOT_DIR . '/src');
define('TEMPLATES_DIR',    ROOT_DIR . '/templates');
define('TRANSLATIONS_DIR', ROOT_DIR . '/translations');
define('CACHE_DIR',        ROOT_DIR . '/cache');
define('DATA_DIR',         ROOT_DIR . '/data');
define('CONFIG_DIR',       ROOT_DIR . '/config');

define('DB_PATH',          DATA_DIR . '/nanofin.db');
define('POSTER_CACHE_DIR', CACHE_DIR . '/posters');
define('JELLYFIN_ID_CACHE',CACHE_DIR . '/user_id');

// ── Load .env if present (development only) ──────────────────────
$envFile = ROOT_DIR . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\"'");
        if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// ── PHP session configuration ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    // Prevent session IDs from leaking via URLs
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 7)); // 7 days

    // Mark the session cookie as Secure when the request arrives over HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }

    // Prevent browsers from caching authenticated pages
    session_cache_limiter('nocache');
}

// ── Ensure writable directories exist ───────────────────────────
foreach ([DATA_DIR, CACHE_DIR, POSTER_CACHE_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}
