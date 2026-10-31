<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/Security.php';

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/Helpers/View.php';
