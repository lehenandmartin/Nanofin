<?php

declare(strict_types=1);

namespace Nanofin\Models;

use Nanofin\Core\Database;
use PDO;

/**
 * Data-access layer for the `downloads` table.
 *
 * @phpstan-type DownloadRow array{
 *   id: int,
 *   user_id: int|null,
 *   item_id: string,
 *   item_title: string,
 *   item_type: string,
 *   downloaded_at: string,
 * }
 */
final class DownloadModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Reads ─────────────────────────────────────────────────────

    /**
     * Return all download logs, optionally filtered by user_id, with the
     * associated username joined from the users table.
     *
     * @return array<array<string, mixed>>
     */
    public function all(?int $userId = null, int $limit = 200, int $offset = 0): array
    {
        if ($userId !== null) {
            $stmt = $this->db->prepare(
                'SELECT d.*, u.username
                 FROM downloads d
                 LEFT JOIN users u ON u.id = d.user_id
                 WHERE d.user_id = ?
                 ORDER BY d.downloaded_at DESC
                 LIMIT ? OFFSET ?'
            );
            $stmt->execute([$userId, $limit, $offset]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT d.*, u.username
                 FROM downloads d
                 LEFT JOIN users u ON u.id = d.user_id
                 ORDER BY d.downloaded_at DESC
                 LIMIT ? OFFSET ?'
            );
            $stmt->execute([$limit, $offset]);
        }

        return $stmt->fetchAll();
    }

    public function count(?int $userId = null): int
    {
        if ($userId !== null) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM downloads WHERE user_id = ?'
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = $this->db->query('SELECT COUNT(*) FROM downloads');
        }

        return (int) $stmt->fetchColumn();
    }

    // ── Writes ────────────────────────────────────────────────────

    /**
     * Log a download event.
     */
    public function log(
        ?int   $userId,
        string $itemId,
        string $itemTitle,
        string $itemType,  // 'movie' | 'episode'
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO downloads (user_id, item_id, item_title, item_type, downloaded_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $itemId, $itemTitle, $itemType, date('Y-m-d H:i:s')]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Delete all download logs (admin action).
     */
    public function clearAll(): void
    {
        $this->db->exec('DELETE FROM downloads');
    }

    /**
     * Delete logs for a specific user (called when the user is deleted, as a
     * supplement to the ON DELETE SET NULL FK — this removes the orphan rows).
     */
    public function clearForUser(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM downloads WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * Return the N most-recent download entries for the dashboard widget.
     *
     * @return array<array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, u.username
             FROM downloads d
             LEFT JOIN users u ON u.id = d.user_id
             ORDER BY d.downloaded_at DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Return per-user download counts, ordered by most active.
     *
     * @return array<array{username: string|null, total: int}>
     */
    public function statsPerUser(): array
    {
        return $this->db->query(
            'SELECT u.username, COUNT(d.id) AS total
             FROM downloads d
             LEFT JOIN users u ON u.id = d.user_id
             GROUP BY d.user_id
             ORDER BY total DESC'
        )->fetchAll();
    }
}
