<?php

declare(strict_types=1);

namespace Nanofin\Models;

use Nanofin\Core\Database;
use PDO;

/**
 * Data-access layer for the `auth_tokens` table (magic links).
 *
 * The raw token is NEVER stored.  Only its SHA-256 hash is persisted.
 * Tokens are single-use and short-lived (default: 15 minutes).
 *
 * @phpstan-type TokenRow array{
 *   id: int,
 *   user_id: int,
 *   token_hash: string,
 *   expires_at: string,
 *   used_at: string|null,
 *   created_at: string,
 * }
 */
final class AuthTokenModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Create ────────────────────────────────────────────────────

    /**
     * Generate a new magic-link token for a user.
     * Returns the raw (unhashed) token — the only moment it is available.
     *
     * @param int $ttlSeconds Token lifetime in seconds (default: 15 min)
     */
    public function generate(int $userId, int $ttlSeconds = 900): string
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $stmt = $this->db->prepare(
            'INSERT INTO auth_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expiresAt]);

        return $rawToken;
    }

    // ── Reads ─────────────────────────────────────────────────────

    /**
     * Find a valid (unused, unexpired) token row from the raw token.
     * Returns null if the token is invalid, expired, or already used.
     *
     * @return TokenRow|null
     */
    public function findValid(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $this->db->prepare(
            "SELECT * FROM auth_tokens
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > datetime('now')"
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    // ── Consume ───────────────────────────────────────────────────

    /**
     * Mark a token as used (single-use enforcement).
     */
    public function consume(int $tokenId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE auth_tokens SET used_at = datetime('now') WHERE id = ?"
        );
        $stmt->execute([$tokenId]);
    }

    // ── Cleanup ───────────────────────────────────────────────────

    /**
     * Delete all expired or used tokens.
     * Call periodically (e.g. on each login request) to keep the table clean.
     */
    public function purgeExpired(): void
    {
        $this->db->exec(
            "DELETE FROM auth_tokens
             WHERE used_at IS NOT NULL
                OR expires_at <= datetime('now')"
        );
    }
}
