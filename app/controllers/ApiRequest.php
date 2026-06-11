<?php

declare(strict_types=1);

class ApiRequest
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function action(string $default = 'list'): string
    {
        return (string) ($_GET['action'] ?? $default);
    }

    public static function id(string $key = 'id'): int
    {
        return (int) ($_GET[$key] ?? 0);
    }

    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if (trim($raw) === '') {
            return [];
        }

        $body = json_decode($raw, true);
        if (!is_array($body)) {
            throw new RuntimeException('JSON body không hop le.');
        }

        return $body;
    }
}
