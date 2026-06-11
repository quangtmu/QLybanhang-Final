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
    $admin = PermissionMiddleware::requireModule(MODULE_ORDERS, adminOrderAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => AdminOrderModel::paginate($_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        responseJson(['success' => true, 'data' => requireAdminOrder($id)]);
    }

    if ($method === 'POST' && $action === 'cancel') {
        requireAdminOrder($id);
        AdminOrderModel::cancelByAdmin($id, (int) $admin['id'], (string) ($body['reason'] ?? ''));
        responseJson(['success' => true]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function adminOrderAction(string $method, string $action): string
{
    if ($action === 'cancel') {
        return 'update';
    }

    return 'view';
}

function requireAdminOrder(int $id): array
{
    if ($id <= 0) {
        responseJson(['success' => false, 'message' => 'Thieu order id.'], 422);
    }

    $order = AdminOrderModel::detail($id);

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
