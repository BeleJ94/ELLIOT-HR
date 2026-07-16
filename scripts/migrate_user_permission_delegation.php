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

$migration = BASE_PATH . '/database/migrations/033_create_user_permission_delegation.sql';
$sql = file_get_contents($migration);
if ($sql === false) {
    fwrite(STDERR, "Migration introuvable.\n");
    exit(1);
}

try {
    $config = require CONFIG_PATH . '/database.php';
    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $config['driver'], $config['host'], $config['port'], $config['database'], $config['charset']);
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
    echo "Migration des délégations et permissions utilisateur appliquée.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration impossible : " . $exception->getMessage() . "\n");
    exit(1);
}
