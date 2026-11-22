<?php
declare(strict_types=1);

namespace Evolve\Service;

use Evolve\Core\Http;
use Evolve\Core\Session;
use Evolve\Core\ValidationError;
use Evolve\Repository\UserRepo;

/** Sign in/out, first-login password change, role checks. */
final class AuthService
{
    public static function login(string $email, string $password): array
    {
        Http::rateLimit('login', 8, 900);
        $email = strtolower($email);
        $row = UserRepo::byEmail($email);
        if (!$row || !password_verify($password, $row['password_hash'])) {
            Http::audit('login_fail', $email);
            throw new ValidationError('Email or password is wrong.');
        }
        Session::start();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'name' => (string) $row['name'],
            'role' => (string) $row['role'],
            'must_change' => (int) $row['must_change_password'] === 1,
        ];
        Http::audit('login', $email);
        return $_SESSION['user'];
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function requireRole(string ...$roles): array
    {
        Session::start();
        $u = $_SESSION['user'] ?? null;
        if (!$u || !in_array($u['role'], $roles, true)) {
            header('Location: ' . url('/gate'));
            exit;
        }
        if ($u['must_change'] && path() !== '/change-password') {
            redirect('/change-password');
        }
        return $u;
    }

    public static function changeOwnPassword(int $userId, string $current, string $new): void
    {
        $row = UserRepo::byId($userId);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            throw new ValidationError('The current password is wrong.');
        }
        if (strlen($new) < 12) {
            throw new ValidationError('The new password must be at least 12 characters.');
        }
        UserRepo::setPassword($userId, password_hash($new, PASSWORD_DEFAULT), 0);
        Session::start();
        $_SESSION['user']['must_change'] = false;
        Http::audit('password_change', (string) $row['email']);
    }
}
