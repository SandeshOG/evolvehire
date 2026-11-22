<?php
declare(strict_types=1);

namespace Evolve\Repository;

/** Staff/owner accounts and password resets. */
final class UserRepo
{
    public static function byEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function byId(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function insert(string $email, string $name, string $hash, string $role, int $mustChange = 0): void
    {
        db()->prepare('INSERT INTO users (email, name, password_hash, role, must_change_password) VALUES (?, ?, ?, ?, ?)')
            ->execute([$email, substr($name, 0, 120), $hash, $role, $mustChange]);
    }

    public static function upsertStaff(string $email, string $name, string $hash): void
    {
        if (\Evolve\Core\Db::isSqlite()) {
            db()->prepare('INSERT INTO users (email, name, password_hash, role) VALUES (?, ?, ?, \'staff\')
                ON CONFLICT(email) DO UPDATE SET name = excluded.name, password_hash = excluded.password_hash,
                role = \'staff\', must_change_password = 0')
                ->execute([$email, substr($name, 0, 120), $hash]);
        } else {
            db()->prepare('INSERT INTO users (email, name, password_hash, role) VALUES (?, ?, ?, \'staff\')
                ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = \'staff\', must_change_password = 0')
                ->execute([$email, substr($name, 0, 120), $hash]);
        }
    }

    public static function setPassword(int $id, string $hash, int $mustChange = 0): void
    {
        db()->prepare('UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ?')
            ->execute([$hash, $mustChange, $id]);
    }

    public static function setMustChange(int $id, int $flag): void
    {
        db()->prepare('UPDATE users SET must_change_password = ? WHERE id = ?')->execute([$flag, $id]);
    }

    public static function remove(int $id): void
    {
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public static function all(): array
    {
        return db()->query('SELECT id, email, name, role, must_change_password, created_at FROM users ORDER BY role = \'owner\' DESC, id')->fetchAll();
    }

    public static function countOwners(): int
    {
        return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function requestReset(string $email): void
    {
        $token = bin2hex(random_bytes(16));
        db()->prepare('INSERT INTO password_resets (email, token_hash, status, expires_at) VALUES (?, ?, \'open\', ?)')
            ->execute([$email, hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
    }

    public static function openResets(): array
    {
        $driver = \Evolve\Core\Db::isSqlite();
        $now = $driver ? "datetime('now')" : 'NOW()';
        return db()->query("SELECT id, email, status, created_at FROM password_resets WHERE status = 'open' AND (expires_at IS NULL OR expires_at > $now) ORDER BY id DESC LIMIT 50")->fetchAll();
    }

    public static function resolveReset(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM password_resets WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function closeReset(int $id): void
    {
        db()->prepare("UPDATE password_resets SET status = 'done' WHERE id = ?")->execute([$id]);
    }
}
