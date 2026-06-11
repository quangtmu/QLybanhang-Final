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
    $currentUser = PermissionMiddleware::requireModule(MODULE_USERS, actionForRequest($method, $action), true);

    if ($method === 'GET' && $action === 'list') {
        echo json_encode(['success' => true, 'data' => AdminUserModel::paginate($_GET)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET' && $action === 'permissions') {
        $target = requireTargetUser($id);
        requireSubAdminTarget($target);
        echo json_encode(['success' => true, 'data' => AdminUserModel::getPermissions($id)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'create-sub-admin') {
        $errors = validateSubAdminPayload($body);

        if ($errors !== []) {
            responseJson(['success' => false, 'errors' => $errors], 422);
        }

        $newId = AdminUserModel::createSubAdmin([
            'username' => trim((string) $body['username']),
            'email' => strtolower(trim((string) $body['email'])),
            'password' => (string) $body['password'],
            'full_name' => trim((string) $body['full_name']),
            'phone' => trim((string) ($body['phone'] ?? '')),
        ], (int) $currentUser['id']);

        if (!empty($body['permissions']) && is_array($body['permissions'])) {
            AdminUserModel::replacePermissions($newId, $body['permissions']);
        }

        echo json_encode(['success' => true, 'id' => $newId], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'lock') {
        $target = requireTargetUser($id);
        ensureCanMutateTarget($currentUser, $target);
        $newType = AdminUserModel::transitionForLock($target);

        if ($newType === null) {
            responseJson(['success' => false, 'message' => 'Trạng thái tài khoản này không thể khóa.'], 422);
        }

        AdminUserModel::setUserType($id, $newType);
        echo json_encode(['success' => true, 'user_type' => $newType], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'unlock') {
        $target = requireTargetUser($id);
        ensureCanMutateTarget($currentUser, $target);
        $newType = AdminUserModel::transitionForUnlock($target);

        if ($newType === null) {
            responseJson(['success' => false, 'message' => 'Trạng thái tài khoản này không thể mở khóa.'], 422);
        }

        AdminUserModel::setUserType($id, $newType);
        echo json_encode(['success' => true, 'user_type' => $newType], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'PUT' && $action === 'permissions') {
        $target = requireTargetUser($id);
        requireSubAdminTarget($target);
        ensureCanMutateTarget($currentUser, $target);

        if (!isset($body['permissions']) || !is_array($body['permissions'])) {
            responseJson(['success' => false, 'message' => 'Thieu danh sach quyền.'], 422);
        }

        AdminUserModel::replacePermissions($id, $body['permissions']);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    responseJson(['success' => false, 'message' => 'Endpoint không ton tai.'], 404);
} catch (Throwable $e) {
    responseJson(['success' => false, 'message' => APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.'], 500);
}

function actionForRequest(string $method, string $action): string
{
    if ($action === 'lock' || $action === 'unlock' || $action === 'permissions') {
        return $method === 'GET' ? 'view' : 'update';
    }

    return match ($method) {
        'POST' => 'create',
        'PUT', 'PATCH' => 'update',
        'DELETE' => 'delete',
        default => 'view',
    };
}

function validateSubAdminPayload(array $body): array
{
    $errors = [];
    $username = trim((string) ($body['username'] ?? ''));
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $password = (string) ($body['password'] ?? '');

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
        $errors[] = 'Username tu 3 ky tu, chi dung chu cai, so va dau gach duoi.';
    } elseif (UserModel::usernameExists($username)) {
        $errors[] = 'Username đã ton tai.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hop le.';
    } elseif (UserModel::emailExists($email)) {
        $errors[] = 'Email đã ton tai.';
    }

    if (trim((string) ($body['full_name'] ?? '')) === '') {
        $errors[] = 'Vui lòng nhap họ tên.';
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Mật khẩu toi thieu ' . PASSWORD_MIN_LENGTH . ' ky tu.';
    }

    return $errors;
}

function requireTargetUser(int $id): array
{
    if ($id <= 0) {
        responseJson(['success' => false, 'message' => 'Thieu user id.'], 422);
    }

    $target = UserModel::findById($id);

    if (!$target) {
        responseJson(['success' => false, 'message' => 'Không tìm thấy người dùng.'], 404);
    }

    return $target;
}

function requireSubAdminTarget(array $target): void
{
    if (!in_array($target['user_type'], [USER_TYPE_SUB_ADMIN_ACTIVE, USER_TYPE_SUB_ADMIN_INACTIVE], true)) {
        responseJson(['success' => false, 'message' => 'Chi quan ly quyền cho sub-admin.'], 422);
    }
}

function ensureCanMutateTarget(array $currentUser, array $target): void
{
    if ((int) $currentUser['id'] === (int) $target['id']) {
        responseJson(['success' => false, 'message' => 'Không thể tự thay đổi trạng thái/quyền của chính mình.'], 422);
    }

    if ($target['user_type'] === USER_TYPE_ADMIN) {
        responseJson(['success' => false, 'message' => 'Không thể khóa hoặc sửa quyền admin hệ thống.'], 422);
    }
}

function responseJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
