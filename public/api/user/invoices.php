<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? 'list');
$id = (int) ($_GET['id'] ?? 0);

try {
    $user = PermissionMiddleware::requireModule(MODULE_INVOICES, 'view', true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => InvoiceModel::paginateForActor($user, $_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        responseJson(['success' => true, 'data' => requireUserInvoice($id, $user)]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function requireUserInvoice(int $id, array $actor): array
{
    if ($id <= 0) {
        responseJson(['success' => false, 'message' => 'Thieu invoice id.'], 422);
    }

    $invoice = InvoiceModel::detailForActor($id, $actor);

    if (!$invoice) {
        responseJson(['success' => false, 'message' => 'Không tìm thấy hóa đơn.'], 404);
    }

    return $invoice;
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
