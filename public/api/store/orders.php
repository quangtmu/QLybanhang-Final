<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

try {
    $method = ApiRequest::method();
    $action = ApiRequest::action('list');
    $id = ApiRequest::id();
    $body = in_array($method, ['POST', 'PUT', 'PATCH'], true) ? ApiRequest::jsonBody() : [];
    $actor = PermissionMiddleware::requireModule(MODULE_ORDERS, storeOrderAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        ApiResponse::success(['data' => StoreOrderModel::paginate($actor, $_GET)]);
    }
    if ($method === 'GET' && $action === 'detail') {
        $order = StoreOrderModel::detail($actor, $id);
        if (!$order) ApiResponse::error('Không tìm thấy đơn hàng của shop.', 404);
        ApiResponse::success(['data' => $order]);
    }
    if ($method === 'POST' && $action === 'create-manual') {
        $orderId = StoreOrderModel::createManual($actor, $body);
        ApiResponse::success(['id' => $orderId], 201);
    }
    if ($method === 'POST' && $action === 'confirm') {
        StoreOrderModel::confirm($actor, $id, (string) ($body['note'] ?? ''));
        ApiResponse::success();
    }
    if ($method === 'POST' && $action === 'processing') {
        StoreOrderModel::startProcessing($actor, $id, (string) ($body['note'] ?? ''));
        ApiResponse::success();
    }
    if ($method === 'POST' && $action === 'cancel') {
        StoreOrderModel::cancel($actor, $id, (string) ($body['reason'] ?? ''));
        ApiResponse::success();
    }
    ApiResponse::error('Endpoint không ton tai.', 404);
} catch (Throwable $e) {
    ApiResponse::exception($e);
}

function storeOrderAction(string $method, string $action): string
{
    if ($method === 'GET') return 'view';
    return match ($action) {
        'create-manual' => 'create',
        'confirm', 'processing', 'cancel' => 'update',
        default => 'view',
    };
}
