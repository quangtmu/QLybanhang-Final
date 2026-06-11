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
    $actor = PermissionMiddleware::requireModule(MODULE_PRODUCTS, storeProductAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        ApiResponse::success(['data' => ProductManagementModel::paginateForStore($actor, $_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        $product = ProductManagementModel::detailForStore($actor, $id);
        if (!$product) {
            ApiResponse::error('Không tìm thấy sản phẩm.', 404);
        }
        ApiResponse::success(['data' => $product]);
    }

    if ($method === 'POST' && $action === 'create') {
        if ($isMultipart) {
            $body = productApiPayloadWithUploads($body);
        }
        $productId = ProductManagementModel::createForStore($actor, $body);
        ApiResponse::success(['id' => $productId], 201);
    }

    if ($method === 'POST' && $action === 'upload-image') {
        $stored = uploadStoreProductImage($_FILES['image'] ?? null);
        ApiResponse::success(['data' => $stored], 201);
    }

    if ($method === 'PUT' && $action === 'update') {
        ProductManagementModel::updateForStore($actor, $id, $body);
        ApiResponse::success();
    }

    if ($method === 'POST' && $action === 'submit') {
        ProductManagementModel::submitForReview($actor, $id);
        ApiResponse::success();
    }

    if ($method === 'POST' && $action === 'archive') {
        ProductManagementModel::archiveForStore($actor, $id);
        ApiResponse::success();
    }

    ApiResponse::error('Endpoint không ton tai.', 404);
} catch (Throwable $e) {
    ApiResponse::exception($e);
}

function productApiPayloadWithUploads(array $body): array
{
    if (isset($_FILES['main_image']) && ($_FILES['main_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $stored = uploadStoreProductImage($_FILES['main_image']);
        $body['main_image_url'] = $stored['url'];
    }

    return $body;
}

function uploadStoreProductImage(?array $file): array
{
    if (!$file) {
        throw new RuntimeException('Vui lòng chọn ảnh sản phẩm.');
    }

    return StorageService::storeUploadedFile($file, 'products', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ], 5 * 1024 * 1024, 'product');
}

function storeProductAction(string $method, string $action): string
{
    if ($method === 'GET') {
        return 'view';
    }
    return match ($action) {
        'create', 'upload-image' => 'create',
        'update', 'submit', 'archive' => 'update',
        default => 'view',
    };
}
