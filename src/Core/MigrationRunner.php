<?php

declare(strict_types=1);

namespace Nanofin\Core;

use PDO;

/**
 * Runs SQLite migrations from src/Migrations/*.sql.
 *
 * Designed to be called both:
 *   - At web-request bootstrap (runPending, silent)
 *   - From the CLI migrate.php script (with output)
 */
final class MigrationRunner
{
    // ── Public API ────────────────────────────────────────────────

    /**
     * Apply every pending migration.
     * Already-applied migrations are skipped; safe to call on every boot.
     *
     * @throws \Throwable on migration failure
     */
    public static function runPending(bool $verbose = false): int
    {
        $pdo = Database::getInstance();
        self::ensureMigrationsTable($pdo);

        $files   = self::migrationFiles();
        $applied = self::appliedMigrations($pdo);
        $ran     = 0;

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            if ($verbose) {
                echo "Applying $name … ";
            }

            $sql = file_get_contents($file);
            $pdo->exec($sql);

            $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
            $stmt->execute([$name]);

            if ($verbose) {
                echo "✓\n";
            }

            $ran++;
        }

        return $ran;
    }

    /**
     * Drop all tables then re-apply every migration (CLI only).
     *
     * @throws \RuntimeException when called from web context
     */
    public static function fresh(): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new \RuntimeException('--fresh is only available from the CLI.');
        }

        $pdo = Database::getInstance();

        echo "⚠  Dropping all tables…\n";

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $pdo->exec('PRAGMA foreign_keys = OFF');
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
        }
        $pdo->exec('PRAGMA foreign_keys = ON');

        echo "✓  All tables dropped.\n\n";

        $ran = self::runPending(verbose: true);

        echo $ran > 0
            ? "\n✓  $ran migration(s) applied.\n"
            : "\n✓  Nothing to do — database is up to date.\n";
    }

    /**
     * Return the applied/pending status of every migration file.
     *
     * @return array<array{filename: string, applied: bool}>
     */
    public static function status(): array
    {
        $pdo = Database::getInstance();
        self::ensureMigrationsTable($pdo);

        $files   = self::migrationFiles();
        $applied = self::appliedMigrations($pdo);

        $result = [];
        foreach ($files as $file) {
            $name     = basename($file);
            $result[] = ['filename' => $name, 'applied' => in_array($name, $applied, true)];
        }

        return $result;
    }

    // ── Internals ─────────────────────────────────────────────────

    private static function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS migrations (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                filename   TEXT NOT NULL UNIQUE,
                applied_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL);
    }

    /** @return list<string> absolute paths, sorted */
    private static function migrationFiles(): array
    {
        $dir   = defined('ROOT_DIR') ? ROOT_DIR . '/src/Migrations' : dirname(__DIR__) . '/Migrations';
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        return $files;
    }

    /** @return list<string> already-applied filenames */
    private static function appliedMigrations(PDO $pdo): array
    {
        return $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    }
}
