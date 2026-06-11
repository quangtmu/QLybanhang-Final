<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? '');
$itemId = (int) ($_GET['item_id'] ?? ($_GET['id'] ?? 0));
$body = json_decode(file_get_contents('php://input') ?: '[]', true);

if (!is_array($body)) {
    $body = [];
}

try {
    $user = PermissionMiddleware::requireUserType(USER_TYPE_USER, true);
    $buyerId = (int) $user['id'];

    if ($method === 'GET') {
        responseJson(['success' => true, 'data' => CartModel::getForBuyer($buyerId)]);
    }

    if ($method === 'POST') {
        CartModel::addItem($buyerId, $body);
        responseJson(['success' => true, 'data' => CartModel::getForBuyer($buyerId)], 201);
    }

    if ($method === 'PUT') {
        if ($itemId <= 0) {
            responseJson(['success' => false, 'message' => 'Thieu cart item.'], 422);
        }

        CartModel::updateItem($buyerId, $itemId, (int) ($body['quantity'] ?? 0));
        responseJson(['success' => true, 'data' => CartModel::getForBuyer($buyerId)]);
    }

    if ($method === 'DELETE' && $action === 'clear') {
        CartModel::clear($buyerId);
        responseJson(['success' => true, 'data' => CartModel::getForBuyer($buyerId)]);
    }

    if ($method === 'DELETE') {
        if ($itemId <= 0) {
            responseJson(['success' => false, 'message' => 'Thieu cart item.'], 422);
        }

        CartModel::removeItem($buyerId, $itemId);
        responseJson(['success' => true, 'data' => CartModel::getForBuyer($buyerId)]);
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
