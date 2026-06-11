<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

try {
    $dbOk = false;
    try {
        getDB()->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable) {
        $dbOk = false;
    }

    ApiResponse::success([
        'data' => [
            'app' => APP_NAME,
            'version' => APP_VERSION,
            'env' => APP_ENV,
            'debug' => APP_DEBUG,
            'database' => $dbOk ? 'ok' : 'unavailable',
            'storage_provider' => env('STORAGE_PROVIDER', STORAGE_PROVIDER),
            'time' => date('c'),
        ],
    ]);
} catch (Throwable $e) {
    ApiResponse::exception($e);
}
