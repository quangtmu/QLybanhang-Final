<?php

declare(strict_types=1);

spl_autoload_register(function (string $className): void {
    $className = ltrim($className, '\\');
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';

    $searchPaths = [
        BASE_PATH . '/app/models/' . $classPath,
        BASE_PATH . '/app/controllers/' . $classPath,
        BASE_PATH . '/app/middleware/' . $classPath,
    ];

    foreach ($searchPaths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});
