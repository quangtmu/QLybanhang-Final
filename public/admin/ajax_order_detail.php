<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

$user = PermissionMiddleware::requireModule(MODULE_ORDERS);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$orderId = (int) ($_GET['id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

try {
    $order = AdminOrderModel::detail($orderId);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    echo json_encode(['order' => $order]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => APP_DEBUG ? $e->getMessage() : 'Server error']);
}
