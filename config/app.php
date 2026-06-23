<?php

return [
    'name' => env('APP_NAME', 'ELLIOT-HR'),
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', 'auto'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Africa/Lubumbashi'),
    'locale' => env('APP_LOCALE', 'fr'),
    'tabler_path' => env('TABLER_PATH', '/Applications/XAMPP/xamppfiles/htdocs/tabler-dev'),
];
