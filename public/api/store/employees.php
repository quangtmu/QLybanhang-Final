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
    $actor = PermissionMiddleware::requireModule(MODULE_STORE_EMPLOYEES, storeEmployeeAction($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        responseJson(['success' => true, 'data' => StoreEmployeeModel::listForStore(StoreEmployeeModel::storeIdForActor($actor))]);
    }

    if ($method === 'GET' && $action === 'modules') {
        responseJson(['success' => true, 'data' => ['modules' => StoreEmployeeModel::modules(), 'actions' => StoreEmployeeModel::actions()]]);
    }

    if ($method === 'POST' && $action === 'create') {
        $employeeId = StoreEmployeeModel::create($actor, $body);
        responseJson(['success' => true, 'id' => $employeeId]);
    }

    if ($method === 'POST' && $action === 'permissions') {
        StoreEmployeeModel::updatePermissions($actor, $id, $body['permissions'] ?? []);
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'activate') {
        StoreEmployeeModel::setActive($actor, $id, true);
        responseJson(['success' => true]);
    }

    if ($method === 'POST' && $action === 'deactivate') {
        StoreEmployeeModel::setActive($actor, $id, false);
        responseJson(['success' => true]);
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function storeEmployeeAction(string $method, string $action): string
{
    return match ($action) {
        'create' => 'create',
        'permissions', 'activate', 'deactivate' => 'update',
        default => 'view',
    };
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
