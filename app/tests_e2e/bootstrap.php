<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Tests\\E2E\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if (str_starts_with($relative, 'Support\\')) {
        $relativePath = '_support/' . str_replace('\\', '/', substr($relative, strlen('Support\\'))) . '.php';
    } else {
        $relativePath = str_replace('\\', '/', $relative) . '.php';
    }

    $file = __DIR__ . '/' . $relativePath;
    if (is_file($file)) {
        require_once $file;
    }
});
