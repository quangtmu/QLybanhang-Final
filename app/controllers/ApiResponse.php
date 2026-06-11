<?php

declare(strict_types=1);

class ApiResponse
{
    public static function json(array $payload, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(array $data = [], int $statusCode = 200): never
    {
        self::json(['success' => true] + $data, $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): never
    {
        self::json(['success' => false, 'message' => $message] + $extra, $statusCode);
    }

    public static function exception(Throwable $e): never
    {
        $statusCode = $e instanceof RuntimeException ? 422 : 500;
        $message = APP_DEBUG ? $e->getMessage() : ($statusCode === 500 ? 'Lỗi hệ thống.' : $e->getMessage());
        self::error($message, $statusCode);
    }
}
