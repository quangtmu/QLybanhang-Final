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
    PermissionMiddleware::requireModule(MODULE_TAGS, catalogActionForRequest($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => AdminCatalogModel::tags($_GET)]);
    }

    if ($method === 'GET' && $action === 'detail') {
        responseJson(['success' => true, 'data' => requireTagId($id)]);
    }

    if ($method === 'POST' && $action === 'create') {
        $newId = AdminCatalogModel::createTag($body);
        responseJson(['success' => true, 'id' => $newId], 201);
    }

    if ($method === 'PUT' && $action === 'update') {
        requireTagId($id);
        AdminCatalogModel::updateTag($id, $body);
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && ($action === 'activate' || $action === 'deactivate')) {
        AdminCatalogModel::setTagActive($id, $action === 'activate');
        responseJson(['success' => true]);
    }

    if ($method === 'DELETE' && $action === 'delete') {
        AdminCatalogModel::deleteTag($id);
        responseJson(['success' => true]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function requireTagId(int $id): array
{
    if ($id <= 0) {
        responseJson(['success' => false, 'message' => 'Thieu tag id.'], 422);
    }

    $tag = AdminCatalogModel::findTag($id);

    if (!$tag) {
        responseJson(['success' => false, 'message' => 'Không tìm thấy tag.'], 404);
    }

    return $tag;
}

function catalogActionForRequest(string $method, string $action): string
{
    if ($action === 'activate' || $action === 'deactivate') {
        return 'update';
    }

    return match ($method) {
        'POST' => 'create',
        'PUT', 'PATCH' => 'update',
        'DELETE' => 'delete',
        default => 'view',
    };
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
