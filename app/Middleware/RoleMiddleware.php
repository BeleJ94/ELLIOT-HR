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

        $permission = $this->requestPermission();
        $override = $permission !== null ? Auth::permissionOverride($permission) : null;

        if ($override === true || ($override === null && ($roles === [] || Auth::hasRole($roles)))) {
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

    private function requestPermission(): ?string
    {
        $path = trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
        $basePath = trim(current_request_base_path(), '/');
        if ($basePath !== '' && ($path === $basePath || strpos($path, $basePath . '/') === 0)) {
            $path = ltrim(substr($path, strlen($basePath)), '/');
        }
        $segment = explode('/', $path)[0] ?? '';
        $map = [
            'employees' => 'employees.manage', 'departments' => 'employees.manage', 'positions' => 'employees.manage',
            'contracts' => 'contracts.manage', 'attendance' => 'attendance.manage', 'leaves' => 'leaves.manage',
            'medical' => 'medical.manage', 'trainings' => 'trainings.manage', 'payroll' => 'payroll.manage',
            'declarations' => 'declarations.manage',
        ];
        return $map[$segment] ?? null;
    }

    private function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return stripos($accept, 'application/json') !== false
            || strtolower($requestedWith) === 'xmlhttprequest';
    }
}
