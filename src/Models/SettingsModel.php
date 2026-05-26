<?php

declare(strict_types=1);

namespace Nanofin\Models;

use Nanofin\Core\Database;
use PDO;

/**
 * Data-access layer for the `settings` key-value table.
 *
 * All values are stored as TEXT in SQLite.
 * Typed getters (getBool, getInt) handle casting.
 */
final class SettingsModel
{
    private PDO $db;

    /** @var array<string, string>|null Local cache to avoid redundant queries. */
    private ?array $cache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Reads ─────────────────────────────────────────────────────

    /** Return all settings as an associative array. */
    public function all(): array
    {
        if ($this->cache === null) {
            $rows        = $this->db->query('SELECT key, value FROM settings')->fetchAll();
            $this->cache = array_column($rows, 'value', 'key');
        }
        return $this->cache;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->all()[$key] ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->all()[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->all()[$key] ?? null;
        return $value !== null ? (int) $value : $default;
    }

    // ── Writes ────────────────────────────────────────────────────

    public function set(string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );
        $stmt->execute([$key, $value]);
        $this->cache = null; // invalidate cache
    }

    /**
     * Persist multiple key→value pairs in a single transaction.
     *
     * @param array<string, string> $data
     */
    public function setMany(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );

        $this->db->beginTransaction();
        try {
            foreach ($data as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->cache = null;
    }

    /** Flush the in-memory cache (call after external writes). */
    public function invalidate(): void
    {
        $this->cache = null;
    }
}
