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
    $admin = PermissionMiddleware::requireModule(MODULE_BANNERS, bannerAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => BannerModel::all($_GET)]);
    }

    if ($method === 'POST' && $action === 'create') {
        $bannerId = BannerModel::create($_POST, $_FILES['image'] ?? [], (int) $admin['id']);
        responseJson(['success' => true, 'id' => $bannerId]);
    }

    if ($method === 'POST' && $action === 'update') {
        BannerModel::update($id, $_POST, $_FILES['image'] ?? null);
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'activate') {
        BannerModel::setActive($id, true);
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'deactivate') {
        BannerModel::setActive($id, false);
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'sort') {
        BannerModel::updatePositions($body['positions'] ?? []);
        responseJson(['success' => true]);
    }

    if ($method === 'DELETE' && $action === 'delete') {
        BannerModel::delete($id);
        responseJson(['success' => true]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function bannerAction(string $method, string $action): string
{
    return match ($action) {
        'create' => 'create',
        'update', 'activate', 'deactivate', 'sort' => 'update',
        'delete' => 'delete',
        default => 'view',
    };
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
