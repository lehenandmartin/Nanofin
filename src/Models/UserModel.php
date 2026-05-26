<?php

declare(strict_types=1);

namespace Nanofin\Models;

use Nanofin\Core\Database;
use PDO;

final class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? COLLATE NOCASE');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? COLLATE NOCASE');
        $stmt->execute([strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM users ORDER BY created_at ASC')->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function adminExists(): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        if ($excludeUserId !== null) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ? COLLATE NOCASE AND id != ?');
            $stmt->execute([strtolower(trim($email)), $excludeUserId]);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ? COLLATE NOCASE');
            $stmt->execute([strtolower(trim($email))]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $username, string $passwordHash, string $role = 'user', string $contentAccess = 'both', string $email = ''): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (username, password, role, content_access, email) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $passwordHash, $role, $contentAccess, strtolower(trim($email))]);
        return (int) $this->db->lastInsertId();
    }

    public function updateUsername(int $id, string $username): void
    {
        $stmt = $this->db->prepare('UPDATE users SET username = ? WHERE id = ?');
        $stmt->execute([$username, $id]);
    }

    public function updateEmail(int $id, string $email): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email = ? WHERE id = ?');
        $stmt->execute([strtolower(trim($email)), $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    public function setForcePasswordChange(int $id, bool $force): void
    {
        $stmt = $this->db->prepare('UPDATE users SET force_password_change = ? WHERE id = ?');
        $stmt->execute([$force ? 1 : 0, $id]);
    }

    public function updateRole(int $id, string $role): void
    {
        $stmt = $this->db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $id]);
    }

    public function updateContentAccess(int $id, string $contentAccess): void
    {
        $stmt = $this->db->prepare('UPDATE users SET content_access = ? WHERE id = ?');
        $stmt->execute([$contentAccess, $id]);
    }

    public function updateLastActivity(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_activity = ? WHERE id = ?');
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function setSessionToken(int $id, ?string $token): void
    {
        $stmt = $this->db->prepare('UPDATE users SET session_token = ? WHERE id = ?');
        $stmt->execute([$token, $id]);
    }

    public function revokeAllSessionsExcept(int $exceptUserId): void
    {
        $stmt = $this->db->prepare("UPDATE users SET session_token = hex(randomblob(16)) WHERE id != ?");
        $stmt->execute([$exceptUserId]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
}
