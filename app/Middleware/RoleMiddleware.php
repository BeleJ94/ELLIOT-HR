<?php

namespace App\Middleware;

use App\Core\Auth;

class RoleMiddleware
{
    public function handle(...$roles): bool
    {
        if (!Auth::check()) {
            return (new AuthMiddleware())->handle();
        }

        if ($roles === [] || Auth::hasRole($roles)) {
            return true;
        }

        http_response_code(403);

        if ($this->expectsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Acces refuse pour ce role.',
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        echo 'Acces refuse';
        return false;
    }

    private function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return stripos($accept, 'application/json') !== false
            || strtolower($requestedWith) === 'xmlhttprequest';
    }
}
