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
    $admin = PermissionMiddleware::requireModule(MODULE_INVOICES, invoiceAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => InvoiceModel::paginateForActor($admin, $_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        responseJson(['success' => true, 'data' => requireInvoice($id, $admin)]);
    }

    if ($method === 'GET' && $action === 'orders') {
        responseJson(['success' => true, 'data' => InvoiceModel::invoiceableOrdersForActor($admin, (string) ($_GET['search'] ?? ''), (int) ($_GET['limit'] ?? 60))]);
    }

    if ($method === 'POST' && $action === 'generate') {
        $invoiceId = InvoiceModel::generateForActor((int) ($body['order_id'] ?? 0), $admin);
        responseJson(['success' => true, 'id' => $invoiceId]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function invoiceAction(string $method, string $action): string
{
    return $action === 'generate' ? 'create' : 'view';
}

function requireInvoice(int $id, array $actor): array
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
