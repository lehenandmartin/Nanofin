<?php

declare(strict_types=1);

namespace Nanofin\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO wrapper for the SQLite database.
 *
 * Usage:
 *   $db = Database::getInstance();
 *   $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $path = defined('DB_PATH') ? DB_PATH : (dirname(__DIR__, 2) . '/data/nanofin.db');

        try {
            $pdo = new PDO('sqlite:' . $path, options: [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Performance pragmas
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA synchronous = NORMAL');

            self::$instance = $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Cannot open database: ' . $e->getMessage(), 0, $e);
        }

        return self::$instance;
    }

    /** Reset the instance (for testing purposes only). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
