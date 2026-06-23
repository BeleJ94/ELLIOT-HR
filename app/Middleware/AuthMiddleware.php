<?php

namespace App\Middleware;

use App\Core\Auth;

class AuthMiddleware
{
    public function handle(): bool
    {
        if (Auth::check()) {
            return true;
        }

        if ($this->expectsJson()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Session expiree. Veuillez vous reconnecter.',
                'redirect' => path_url('/login'),
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        header('Location: ' . url('/login'));
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
