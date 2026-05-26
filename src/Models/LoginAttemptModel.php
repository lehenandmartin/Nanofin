<?php

declare(strict_types=1);

namespace Nanofin\Models;

use Nanofin\Core\Database;
use PDO;

/**
 * LoginAttemptModel — tracks failed login attempts for brute-force protection.
 *
 * Strategy: record every failed attempt keyed by client IP.
 * The login controller refuses the request if the IP has exceeded the
 * threshold within the rolling time window.
 *
 * Successful logins and stale records are purged to keep the table small.
 */
final class LoginAttemptModel
{
    private readonly PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    // ── Write ─────────────────────────────────────────────────────

    /**
     * Record a failed login attempt for the given IP address.
     */
    public function record(string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (ip) VALUES (?)'
        );
        $stmt->execute([$ip]);
    }

    /**
     * Remove all attempts for an IP (call on successful login).
     */
    public function clearForIp(string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM login_attempts WHERE ip = ?'
        );
        $stmt->execute([$ip]);
    }

    /**
     * Purge records older than $keepSeconds (default 1 hour).
     * Call occasionally to keep the table lean.
     */
    public function purgeOld(int $keepSeconds = 3600): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - $keepSeconds);
        $stmt   = $this->pdo->prepare(
            "DELETE FROM login_attempts WHERE attempted_at < ?"
        );
        $stmt->execute([$cutoff]);
    }

    // ── Read ──────────────────────────────────────────────────────

    /**
     * Count failed attempts from a given IP within the last $windowSeconds.
     * Default window: 15 minutes.
     */
    public function countRecent(string $ip, int $windowSeconds = 900): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
        $stmt   = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?'
        );
        $stmt->execute([$ip, $cutoff]);
        return (int) $stmt->fetchColumn();
    }
}
