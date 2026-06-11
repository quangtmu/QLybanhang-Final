<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';

try {
    $method = ApiRequest::method();
    $action = ApiRequest::action('list');
    $id = ApiRequest::id();
    $isMultipart = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
    $body = in_array($method, ['POST', 'PUT', 'PATCH'], true)
        ? ($isMultipart ? $_POST : ApiRequest::jsonBody())
        : [];
    $actor = PermissionMiddleware::requireModule(MODULE_SHIPMENTS, storeShipmentAction($method, $action), true);
    $storeId = StoreEmployeeModel::storeIdForActor($actor);

    if ($method === 'GET' && $action === 'list') {
        $_GET['store_id'] = $storeId;
        ApiResponse::success(['data' => ShipmentModel::paginate($_GET)]);
    }
    if ($method === 'GET' && $action === 'detail') {
        $shipment = ShipmentModel::detail($id);
        if (!$shipment || (int) ($shipment['store_id'] ?? 0) !== $storeId) ApiResponse::error('Không tìm thấy vận đơn của shop.', 404);
        ApiResponse::success(['data' => $shipment]);
    }
    if ($method === 'GET' && $action === 'orders') {
        ApiResponse::success(['data' => storeOrdersWithoutShipment($storeId, (string) ($_GET['search'] ?? ''), (int) ($_GET['limit'] ?? 50))]);
    }
    if ($method === 'POST' && $action === 'create') {
        StoreOrderModel::requireOrder($actor, (int) ($body['order_id'] ?? 0));
        if ($isMultipart) {
            $body['proof_image_url'] = uploadShipmentProofImage() ?? '';
        }
        $shipmentId = ShipmentModel::create($body, (int) $actor['id'], (string) $actor['user_type']);
        ApiResponse::success(['id' => $shipmentId], 201);
    }
    if ($method === 'POST' && $action === 'update') {
        requireStoreShipment($storeId, $id);
        ShipmentModel::updateInfo($id, $body, (int) $actor['id'], (string) $actor['user_type']);
        ApiResponse::success();
    }
    if ($method === 'POST' && $action === 'status') {
        requireStoreShipment($storeId, $id);
        ShipmentModel::updateStatus($id, (string) ($body['status'] ?? ''), (string) ($body['note'] ?? ''), (int) $actor['id'], (string) $actor['user_type']);
        $proofImage = $isMultipart ? uploadShipmentProofImage() : null;
        if ($proofImage !== null) {
            ShipmentModel::attachProofImage($id, $proofImage, (int) $actor['id'], (string) $actor['user_type']);
        }
        ApiResponse::success();
    }
    if ($method === 'POST' && $action === 'upload-proof') {
        requireStoreShipment($storeId, $id);
        $proofImage = uploadShipmentProofImage();
        if ($proofImage === null) {
            throw new RuntimeException('Vui lòng chọn ảnh vận đơn.');
        }
        ShipmentModel::attachProofImage($id, $proofImage, (int) $actor['id'], (string) $actor['user_type']);
        ApiResponse::success();
    }
    ApiResponse::error('Endpoint không ton tai.', 404);
} catch (Throwable $e) {
    ApiResponse::exception($e);
}

function storeShipmentAction(string $method, string $action): string
{
    if ($method === 'GET') return 'view';
    return match ($action) {
        'create' => 'create',
        'update', 'status', 'upload-proof' => 'update',
        default => 'view',
    };
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

function requireStoreShipment(int $storeId, int $shipmentId): array
{
    $shipment = ShipmentModel::detail($shipmentId);
    if (!$shipment || (int) ($shipment['store_id'] ?? 0) !== $storeId) {
        ApiResponse::error('Không tìm thấy vận đơn của shop.', 404);
    }
    return $shipment;
}

function storeOrdersWithoutShipment(int $storeId, string $search, int $limit): array
{
    $data = ShipmentModel::ordersWithoutShipment($search, $limit);
    return array_values(array_filter($data, fn (array $row): bool => (int) ($row['store_id'] ?? $storeId) === $storeId));
}
