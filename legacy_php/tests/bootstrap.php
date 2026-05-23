<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function loadEnvForTests(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

loadEnvForTests(__DIR__ . '/../.env');
require_once __DIR__ . '/../config/constants.php';

spl_autoload_register(static function($class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    if (count($parts) > 1 && isset($parts[0])) {
        $parts[0] = strtolower($parts[0]);
    }

    $path = __DIR__ . '/../src/' . implode('/', $parts) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

