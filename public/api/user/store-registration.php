<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? 'my');
$body = json_decode(file_get_contents('php://input') ?: '[]', true);

if (!is_array($body)) {
    $body = [];
}

try {
    $user = PermissionMiddleware::requireUserType(USER_TYPE_USER, true);

    if ($method === 'GET' && $action === 'my') {
        responseJson(['success' => true, 'data' => StoreRegistrationModel::myRequests((int) $user['id'])]);
    }

    if ($method === 'POST' && $action === 'submit') {
        $id = StoreRegistrationModel::submit((int) $user['id'], $body);
        responseJson(['success' => true, 'id' => $id], 201);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
