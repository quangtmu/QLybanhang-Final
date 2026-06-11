<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? 'list');
$id = (int) ($_GET['id'] ?? 0);
$body = json_decode(file_get_contents('php://input') ?: '[]', true);

if (!is_array($body)) {
    $body = [];
}

try {
    $user = PermissionMiddleware::requireUserType(USER_TYPE_USER, true);
    $buyerId = (int) $user['id'];

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => OrderModel::listForBuyer($buyerId, $_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        responseJson(['success' => true, 'data' => requireOrder($buyerId, $id)]);
    }

    if ($method === 'POST' && ($action === 'create' || $action === 'checkout')) {
        $orders = OrderModel::create($buyerId, $body);
        responseJson(['success' => true, 'data' => $orders], 201);
    }

    if ($method === 'POST' && $action === 'cancel') {
        requireOrder($buyerId, $id);
        OrderModel::cancel($buyerId, $id, (string) ($body['reason'] ?? ''));
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'received') {
        requireOrder($buyerId, $id);
        OrderModel::markReceived($buyerId, $id);
        responseJson(['success' => true]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function requireOrder(int $buyerId, int $id): array
{
    if ($id <= 0) {
        responseJson(['success' => false, 'message' => 'Thieu order id.'], 422);
    }

    $order = OrderModel::detail($buyerId, $id);

    if (!$order) {
        responseJson(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
    }

    return $order;
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
