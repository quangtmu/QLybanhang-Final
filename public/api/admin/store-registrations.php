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
    $admin = PermissionMiddleware::requireModule(MODULE_STORES, storeRegistrationAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => StoreRegistrationModel::paginateAdmin($_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        responseJson(['success' => true, 'data' => requireRequest($id)]);
    }

    if ($method === 'POST' && $action === 'approve') {
        requireRequest($id);
        $result = StoreRegistrationModel::approve($id, (int) $admin['id'], trim((string) ($body['admin_note'] ?? '')) ?: null);
        responseJson(['success' => true, 'data' => $result]);
    }

    if ($method === 'POST' && $action === 'reject') {
        requireRequest($id);
        $result = StoreRegistrationModel::reject($id, (int) $admin['id'], (string) ($body['admin_note'] ?? ''));
        responseJson(['success' => true, 'data' => $result]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function storeRegistrationAction(string $method, string $action): string
{
    if ($action === 'approve' || $action === 'reject') {
        return 'approve';
    }

    return match ($method) {
        'POST' => 'update',
        default => 'view',
    };
}

function requireRequest(int $id): array
{
    if ($id <= 0) {
        responseJson(['success' => false, 'message' => 'Thieu request id.'], 422);
    }

    $request = StoreRegistrationModel::find($id);

    if (!$request) {
        responseJson(['success' => false, 'message' => 'Không tìm thấy đơn mở shop.'], 404);
    }

    return $request;
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
