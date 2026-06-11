<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($_GET['action'] ?? 'list');
$id = (int) ($_GET['id'] ?? 0);
$isMultipart = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
$body = $isMultipart ? $_POST : json_decode(file_get_contents('php://input') ?: '[]', true);

if (!is_array($body)) {
    $body = [];
}

try {
    $admin = PermissionMiddleware::requireModule(MODULE_SHIPMENTS, shipmentAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => ShipmentModel::paginate($_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        responseJson(['success' => true, 'data' => requireShipment($id)]);
    }

    if ($method === 'GET' && $action === 'orders') {
        $search = (string) ($_GET['search'] ?? '');
        $limit = (int) ($_GET['limit'] ?? 50);
        responseJson(['success' => true, 'data' => ShipmentModel::ordersWithoutShipment($search, $limit)]);
    }

    if ($method === 'POST' && $action === 'create') {
        if ($isMultipart) {
            $body['proof_image_url'] = uploadShipmentProofImage() ?? '';
        }
        $shipmentId = ShipmentModel::create($body, (int) $admin['id'], (string) $admin['user_type']);
        responseJson(['success' => true, 'id' => $shipmentId]);
    }

    if ($method === 'POST' && $action === 'update') {
        requireShipment($id);
        ShipmentModel::updateInfo($id, $body, (int) $admin['id'], (string) $admin['user_type']);
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'status') {
        requireShipment($id);
        ShipmentModel::updateStatus(
            $id,
            (string) ($body['status'] ?? ''),
            (string) ($body['note'] ?? ''),
            (int) $admin['id'],
            (string) $admin['user_type']
        );
        $proofImage = $isMultipart ? uploadShipmentProofImage() : null;
        if ($proofImage !== null) {
            ShipmentModel::attachProofImage($id, $proofImage, (int) $admin['id'], (string) $admin['user_type']);
        }
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'upload-proof') {
        requireShipment($id);
        $proofImage = uploadShipmentProofImage();
        if ($proofImage === null) {
            throw new RuntimeException('Vui lòng chọn ảnh vận đơn.');
        }
        ShipmentModel::attachProofImage($id, $proofImage, (int) $admin['id'], (string) $admin['user_type']);
        responseJson(['success' => true]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function shipmentAction(string $method, string $action): string
{
    if ($action === 'create') {
        return 'create';
    }

    if (in_array($action, ['update', 'status', 'upload-proof'], true)) {
        return 'update';
    }

    return 'view';
}

function uploadShipmentProofImage(): ?string
{
    if (!isset($_FILES['proof_image']) || ($_FILES['proof_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $stored = StorageService::storeUploadedFile($_FILES['proof_image'], 'shipments', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ], 5 * 1024 * 1024, 'shipment');

    return (string) $stored['url'];
}

function requireShipment(int $id): array
{
    if ($id <= 0) {
        responseJson(['success' => false, 'message' => 'Thieu shipment id.'], 422);
    }

    $shipment = ShipmentModel::detail($id);

    if (!$shipment) {
        responseJson(['success' => false, 'message' => 'Không tìm thấy vận đơn.'], 404);
    }

    return $shipment;
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
