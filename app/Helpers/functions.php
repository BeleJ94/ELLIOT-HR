<?php

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
        $path = CONFIG_PATH . '/' . $file . '.php';

        if (!is_file($path)) {
            return $default;
        }

        $config = require $path;

        return $item === null ? $config : ($config[$item] ?? $default);
    }
}

if (!function_exists('load_env')) {
    function load_env(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);

            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            $value = trim($value);
            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        $lower = strtolower($value);

        switch ($lower) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
            default:
                return $value;
        }
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('is_local_app_url')) {
    function is_local_app_url(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return in_array(strtolower((string) $host), ['localhost', '127.0.0.1', '::1'], true);
    }
}

if (!function_exists('current_request_base_url')) {
    function current_request_base_url(): string
    {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $host = trim(explode(',', (string) $host)[0]);

        if ($host === '') {
            return '';
        }

        $https = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        $scheme = strtolower(trim(explode(',', (string) $https)[0]));

        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');

        if ($basePath === '/') {
            $basePath = '';
        }

        return rtrim($scheme . '://' . $host . $basePath, '/');
    }
}

if (!function_exists('current_request_base_path')) {
    function current_request_base_path(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');

        return $basePath === '/' ? '' : $basePath;
    }
}

if (!function_exists('is_secure_request')) {
    function is_secure_request(): bool
    {
        $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

        return $proto === 'https'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    }
}

if (!function_exists('app_base_url')) {
    function app_base_url(): string
    {
        $configured = rtrim((string) config('app.url', 'auto'), '/');
        $detected = current_request_base_url();
        $currentHost = strtolower((string) parse_url($detected, PHP_URL_HOST));

        if ($configured === '' || strtolower($configured) === 'auto') {
            return $detected;
        }

        if ($detected !== '' && is_local_app_url($configured) && !in_array($currentHost, ['localhost', '127.0.0.1', '::1'], true)) {
            return $detected;
        }

        return $configured;
    }
}

if (!function_exists('path_url')) {
    function path_url(string $path = ''): string
    {
        $basePath = current_request_base_path();
        $path = '/' . ltrim($path, '/');

        return $basePath . ($path === '/' ? '' : $path);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = app_base_url();
        $path = '/' . ltrim($path, '/');

        return $base . ($path === '/' ? '' : $path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('public/' . ltrim($path, '/'));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('is_active')) {
    function is_active(string $path): bool
    {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($scriptDir !== '/' && strpos($current, $scriptDir) === 0) {
            $current = substr($current, strlen($scriptDir));
        }

        return '/' . trim($current, '/') === '/' . trim($path, '/');
    }
}

if (!function_exists('active_class')) {
    function active_class(string $path, string $class = 'active'): string
    {
        return is_active($path) ? $class : '';
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $class = 'icon'): string
    {
        $icons = [
            'building' => '<path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M9 8h4"/><path d="M9 12h4"/><path d="M9 16h4"/>',
            'dashboard' => '<path d="M4 13a8 8 0 1 1 16 0"/><path d="M12 13l4-4"/><path d="M7 13h.01"/><path d="M17 13h.01"/><path d="M12 5v.01"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><path d="M3 10h18"/><path d="M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2"/>',
            'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
            'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
            'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.52a2 2 0 0 1-1 1.72l-.15.1a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.52a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
            'logout' => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 19V5a2 2 0 0 0-2-2h-4"/>',
            'menu' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
            'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
            'mail' => '<path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/>',
            'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
            'alert' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M12 12v.01"/><path d="M3 12a20 20 0 0 0 18 0"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'wallet' => '<path d="M4 5h14a2 2 0 0 1 2 2v12H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2"/><path d="M16 13h4"/><path d="M16 9h4"/>',
            'chart' => '<path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19H2"/>',
            'layers' => '<path d="m12 2 9 5-9 5-9-5 9-5"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>',
            'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.42 1.42"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
            'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/>',
            'filter' => '<path d="M4 5h16"/><path d="M7 12h10"/><path d="M10 19h4"/>',
            'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
            'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8"/><path d="m15 8 2 2"/><path d="m17 6 2 2"/>',
        ];

        $paths = $icons[$name] ?? $icons['dashboard'];

        return '<svg class="' . e($class) . '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }
}
