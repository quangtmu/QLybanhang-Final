<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? 'list');
$id = (int) ($_GET['id'] ?? 0);

try {
    PermissionMiddleware::requireUserType(USER_TYPE_USER, true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => BuyerProductModel::listProducts($_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        if ($id <= 0) {
            responseJson(['success' => false, 'message' => 'Thieu sản phẩm.'], 422);
        }

        $product = BuyerProductModel::detail($id);

        if (!$product) {
            responseJson(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        responseJson(['success' => true, 'data' => $product]);
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
