#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Migration runner — CLI interface.
 *
 * Usage:
 *   php migrate.php           — run all pending migrations
 *   php migrate.php --status  — list applied / pending migrations
 *   php migrate.php --fresh   — DROP everything, clear cache, re-run (dev only!)
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/settings.php';

use Nanofin\Core\MigrationRunner;

$flags = array_slice($argv ?? [], 1);

// ── --fresh ───────────────────────────────────────────────────────
if (in_array('--fresh', $flags, true)) {
    MigrationRunner::fresh();
    clearCache();
    exit(0);
}

// ── --status ──────────────────────────────────────────────────────
if (in_array('--status', $flags, true)) {
    $rows = MigrationRunner::status();
    echo str_pad('Migration', 50) . " Status\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($rows as $row) {
        $status = $row['applied'] ? '✓ applied' : '· pending';
        echo str_pad($row['filename'], 50) . " $status\n";
    }
    exit(0);
}

// ── Run pending ───────────────────────────────────────────────────
try {
    $ran = MigrationRunner::runPending(verbose: true);
    echo $ran > 0
        ? "\n✓  $ran migration(s) applied.\n"
        : "\n✓  Nothing to do — database is up to date.\n";
} catch (\Throwable $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// ── Helpers ───────────────────────────────────────────────────────

function clearCache(): void
{
    // Poster images
    $posters = glob(POSTER_CACHE_DIR . '/*') ?: [];
    $count   = 0;
    foreach ($posters as $file) {
        if (is_file($file)) {
            unlink($file);
            $count++;
        }
    }
    echo "✓  $count poster(s) deleted from cache.\n";

    // Jellyfin user ID cache
    if (file_exists(JELLYFIN_ID_CACHE)) {
        unlink(JELLYFIN_ID_CACHE);
        echo "✓  Jellyfin user ID cache cleared.\n";
    }
}
