<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

try {
    $method = ApiRequest::method();
    $action = ApiRequest::action('list');
    $id = ApiRequest::id();
    $body = in_array($method, ['POST', 'PUT', 'PATCH'], true) ? ApiRequest::jsonBody() : [];
    $admin = PermissionMiddleware::requireModule(MODULE_PRODUCTS, adminProductAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        ApiResponse::success(['data' => ProductManagementModel::paginateForAdmin($_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        $product = ProductManagementModel::detailForAdmin($id);
        if (!$product) {
            ApiResponse::error('Không tìm thấy sản phẩm.', 404);
        }
        ApiResponse::success(['data' => $product]);
    }

    if ($method === 'POST' && $action === 'approve') {
        ProductManagementModel::approve($id, (int) $admin['id']);
        ApiResponse::success();
    }

    if ($method === 'POST' && $action === 'reject') {
        ProductManagementModel::reject($id, (int) $admin['id'], (string) ($body['reason'] ?? ''));
        ApiResponse::success();
    }

    ApiResponse::error('Endpoint không ton tai.', 404);
} catch (Throwable $e) {
    ApiResponse::exception($e);
}

function adminProductAction(string $method, string $action): string
{
    if ($method === 'GET') {
        return 'view';
    }
    return match ($action) {
        'approve', 'reject' => 'approve',
        default => 'view',
    };
}
