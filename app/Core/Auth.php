<?php

namespace App\Core;

use App\Models\User;
use Throwable;

class Auth
{
    public static function check(): bool
    {
        return Session::has('user');
    }

    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user['id'] ?? null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password'])) {
            self::log('login_failed', $user['company_id'] ?? null, $user['id'] ?? null, [
                'email' => $email,
            ]);
            return false;
        }

        $userModel->markLogin((int) $user['id']);
        self::login($user);
        self::log('login_success', $user['company_id'] ?? null, (int) $user['id'], [
            'email' => $email,
        ]);

        return true;
    }

    public static function login(array $user): void
    {
        unset($user['password'], $user['remember_token']);

        Session::regenerate();
        Session::put('user', $user);
    }

    public static function logout(): void
    {
        $user = self::user();

        if ($user) {
            self::log('logout', $user['company_id'] ?? null, (int) $user['id']);
        }

        Session::forget('user');
        Session::regenerate();
    }

    public static function hasRole(array $roles): bool
    {
        $user = self::user();

        if (!$user) {
            return false;
        }

        return in_array($user['role_slug'] ?? '', $roles, true)
            || in_array($user['role_name'] ?? '', $roles, true);
    }

    public static function permissionOverride(string $slug): ?bool
    {
        $user = self::user();
        if (!$user || empty($user['id'])) {
            return null;
        }
        if (($user['role_slug'] ?? '') === 'super-admin') {
            return true;
        }
        try {
            $effect = Database::query(
                'SELECT user_permissions.effect
                 FROM user_permissions
                 INNER JOIN permissions ON permissions.id = user_permissions.permission_id
                 WHERE user_permissions.user_id = :user_id AND permissions.slug = :slug
                 AND user_permissions.deleted_at IS NULL AND permissions.deleted_at IS NULL LIMIT 1',
                ['user_id' => (int) $user['id'], 'slug' => $slug]
            )->fetchColumn();
            return $effect === 'allow' ? true : ($effect === 'deny' ? false : null);
        } catch (Throwable $exception) {
            // Compatibilite pendant le deploiement precedant l'execution de la migration.
            error_log($exception->getMessage());
            return null;
        }
    }

    public static function log(string $action, ?int $companyId = null, ?int $userId = null, array $data = []): void
    {
        try {
            Database::query(
                'INSERT INTO audit_logs
                    (company_id, user_id, action, entity_type, entity_id, new_values, ip_address, user_agent, created_at)
                 VALUES
                    (:company_id, :user_id, :action, :entity_type, :entity_id, :new_values, :ip_address, :user_agent, NOW())',
                [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'action' => $action,
                    'entity_type' => 'auth',
                    'entity_id' => $userId,
                    'new_values' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }
}
