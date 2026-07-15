<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

require APP_PATH . '/Helpers/functions.php';

load_env(BASE_PATH . '/.env');

spl_autoload_register(static function (string $className): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $className, strlen($prefix)) !== 0) {
        return;
    }

    $file = APP_PATH . '/' . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Router;
use App\Core\Session;

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8025';
$_SERVER['SERVER_NAME'] = '127.0.0.1';

Session::start();
Session::put('user', [
    'id' => 2,
    'company_id' => 1,
    'employee_id' => 1,
    'first_name' => 'Admin',
    'last_name' => 'RH Demo',
    'email' => 'admin@demo.test',
    'role_name' => 'Admin RH',
    'role_slug' => 'admin-rh',
    'status' => 'active',
]);

$routes = [
    '/medical' => 'Prises en charge medicales',
    '/medical/requests' => 'Demandes & bons',
    '/medical/dependents' => 'Ayants droit',
    '/medical/providers' => 'Prestataires medicaux',
    '/medical/settings' => 'Politique medicale',
];

$router = new Router();
require BASE_PATH . '/routes/web.php';

foreach ($routes as $route => $needle) {
    $_SERVER['REQUEST_URI'] = $route;

    ob_start();
    $router->dispatch('GET', $route);
    $html = ob_get_clean();

    if (strpos($html, $needle) === false || strpos($html, 'medical-module-tabs') === false) {
        throw new RuntimeException('Le rendu de ' . $route . ' ne contient pas les reperes attendus.');
    }
}

echo 'Medical render smoke OK (' . count($routes) . " routes)\n";
