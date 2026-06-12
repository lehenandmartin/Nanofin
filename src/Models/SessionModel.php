<?php

declare(strict_types=1);

namespace Nanofin\Models;

use Nanofin\Core\Database;
use PDO;

final class SessionModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(int $userId, string $token, string $ip, string $userAgent): int
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'INSERT INTO sessions (user_id, token, ip, user_agent, created_at, last_activity)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $token, $ip, $userAgent, $now, $now]);
        return (int) $this->db->lastInsertId();
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sessions WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** Find a session by ID, but only if it belongs to the given user. */
    public function findById(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sessions WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sessions WHERE user_id = ? ORDER BY last_activity DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function updateLastActivity(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE sessions SET last_activity = ? WHERE id = ?');
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function deleteByToken(string $token): void
    {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE token = ?');
        $stmt->execute([$token]);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function deleteByUser(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    /** Remove all sessions for a user except the given session ID (used for "end other sessions"). */
    public function deleteAllExceptSession(int $userId, int $keepSessionId): void
    {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE user_id = ? AND id != ?');
        $stmt->execute([$userId, $keepSessionId]);
    }

    /** Remove all sessions for every user except a given user (admin global revoke). */
    public function deleteAllExceptUser(int $exceptUserId): void
    {
        $stmt = $this->db->prepare('DELETE FROM sessions WHERE user_id != ?');
        $stmt->execute([$exceptUserId]);
    }

    /** Delete sessions whose created_at is older than $maxDays days. */
    public function deleteExpired(int $maxDays): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$maxDays} days"));
        $stmt   = $this->db->prepare('DELETE FROM sessions WHERE created_at < ?');
        $stmt->execute([$cutoff]);
    }

    /**
     * Parse a User-Agent string into a human-readable label.
     * Detection order matters: Edge / Opera / Chrome share the same token pool.
     */
    public static function parseUserAgent(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/')                                        => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')         => 'Opera',
            str_contains($ua, 'Chrome/')                                     => 'Chrome',
            str_contains($ua, 'Firefox/')                                    => 'Firefox',
            str_contains($ua, 'Safari/') && str_contains($ua, 'Version/')   => 'Safari',
            default                                                          => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')        => 'iOS',
            str_contains($ua, 'Android')                                     => 'Android',
            str_contains($ua, 'Windows')                                     => 'Windows',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')   => 'macOS',
            str_contains($ua, 'Linux')                                       => 'Linux',
            default                                                          => 'Unknown OS',
        };

        return $browser . ' on ' . $os;
    }
}
