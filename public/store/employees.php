<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_STORE_EMPLOYEES);
$errors = [];
$success = flash_success();
$csrfToken = AuthController::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            $formAction = (string) ($_POST['form_action'] ?? '');

            if ($formAction === 'create') {
                if (!PermissionMiddleware::can($user, MODULE_STORE_EMPLOYEES, 'create')) {
                    throw new RuntimeException('Bạn không có quyền tao nhân viên.');
                }

                StoreEmployeeModel::create($user, $_POST);
                $_SESSION['flash_success'] = 'Đã tao nhân viên shop.';
                header('Location: /store/employees.php');
                exit;
            }

            if ($formAction === 'permissions') {
                if (!PermissionMiddleware::can($user, MODULE_STORE_EMPLOYEES, 'update')) {
                    throw new RuntimeException('Bạn không có quyền sua quyền nhân viên.');
                }

                StoreEmployeeModel::updatePermissions($user, (int) ($_POST['employee_id'] ?? 0), $_POST['permissions'] ?? []);
                $_SESSION['flash_success'] = 'Đã cập nhật quyền nhân viên.';
                header('Location: /store/employees.php');
                exit;
            }

            if (in_array($formAction, ['activate', 'deactivate'], true)) {
                if (!PermissionMiddleware::can($user, MODULE_STORE_EMPLOYEES, 'update')) {
                    throw new RuntimeException('Bạn không có quyền cập nhật nhân viên.');
                }

                StoreEmployeeModel::setActive($user, (int) ($_POST['employee_id'] ?? 0), $formAction === 'activate');
                $_SESSION['flash_success'] = $formAction === 'activate' ? 'Đã mo nhân viên.' : 'Đã khoa nhân viên.';
                header('Location: /store/employees.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$storeId = StoreEmployeeModel::storeIdForActor($user);
$employees = StoreEmployeeModel::listForStore($storeId);
$modules = StoreEmployeeModel::modules();
$actions = StoreEmployeeModel::actions();
$canCreate = PermissionMiddleware::can($user, MODULE_STORE_EMPLOYEES, 'create');
$canUpdate = PermissionMiddleware::can($user, MODULE_STORE_EMPLOYEES, 'update');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhân viên shop</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
    <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260609-12">
</head>
<body class="portal-page store-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_store_nav.php"; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $message): ?>
                    <p><?= htmlspecialchars((string) $message) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="portal-panel">
            <h1>Nhân viên shop</h1>
            <p class="muted">Quản lý tài khoản nhân viên và phạm vi thao tác trong kênh bán hàng.</p>
        </section>

        <?php if ($canCreate): ?>
            <section class="portal-panel store-collapsible-panel">
                <details>
                    <summary>Tạo nhân viên shop</summary>
                <form method="post" class="admin-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="form_action" value="create">
                    <div class="form-grid">
                        <label>Username
                            <input type="text" name="username" required>
                        </label>
                        <label>Email
                            <input type="email" name="email" required>
                        </label>
                        <label>Mật khẩu tạm
                            <input type="password" name="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
                        </label>
                        <label>Họ tên
                            <input type="text" name="full_name" required>
                        </label>
                        <label>Số điện thoại
                            <input type="text" name="phone">
                        </label>
                    </div>
                    <h2>Quyền ban đầu</h2>
                    <?= renderStorePermissionGrid($modules, $actions, []) ?>
                    <div class="actions">
                        <button type="submit">Tạo nhân viên</button>
                    </div>
                </form>
                </details>
            </section>
        <?php endif; ?>

        <section class="portal-panel">
            <h2>Danh sách nhân viên</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Trạng thái</th>
                            <th>Phân quyền</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$employees): ?>
                            <tr><td colspan="4" class="empty">Chưa có nhân viên shop.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                                    <span><?= htmlspecialchars($employee['email']) ?></span>
                                    <span>@<?= htmlspecialchars($employee['username']) ?></span>
                                </td>
                                <td>
                                    <span class="badge"><?= (int) $employee['is_active'] === 1 ? 'active' : 'inactive' ?></span>
                                    <?php if ($employee['last_login_at']): ?>
                                        <span>Login: <?= htmlspecialchars($employee['last_login_at']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canUpdate): ?>
                                        <form method="post" class="admin-form compact-permission-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="form_action" value="permissions">
                                            <input type="hidden" name="employee_id" value="<?= (int) $employee['employee_id'] ?>">
                                            <?= renderStorePermissionGrid($modules, $actions, $employee['permissions_data']) ?>
                                            <div class="actions">
                                                <button type="submit">Lưu quyền</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <?= renderPermissionSummary($employee['permissions_data'], $modules) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <?php if ($canUpdate): ?>
                                        <form method="post" class="actions">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="employee_id" value="<?= (int) $employee['employee_id'] ?>">
                                            <input type="hidden" name="form_action" value="<?= (int) $employee['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                                            <button type="submit"><?= (int) $employee['is_active'] === 1 ? 'Khóa' : 'Mở' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="/assets/js/global.js"></script>
</body>
</html>

<?php

function renderStorePermissionGrid(array $modules, array $actions, array $selected): string
{
    $actionLabels = [
        'view' => 'Xem',
        'create' => 'Thêm mới',
        'update' => 'Sửa',
        'delete' => 'Xóa',
        'export' => 'Xuất file',
        'approve' => 'Duyệt'
    ];

    $html = '<div class="permission-grid"><table class="data-table compact-table" style="text-align: center;"><thead><tr><th style="text-align: left;">Chức năng</th>';

    foreach ($actions as $action) {
        $label = $actionLabels[$action] ?? $action;
        $html .= '<th style="text-align: center;">' . htmlspecialchars($label) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($modules as $moduleKey => $moduleLabel) {
        $html .= '<tr><td style="text-align: left; font-weight: 500;">' . htmlspecialchars($moduleLabel) . '</td>';

        foreach ($actions as $action) {
            $checked = !empty($selected[$moduleKey][$action]) ? 'checked' : '';
            $html .= '<td style="text-align: center;"><input type="checkbox" name="permissions[' . htmlspecialchars($moduleKey) . '][' . htmlspecialchars($action) . ']" value="1" ' . $checked . ' style="cursor: pointer; width: 16px; height: 16px;"></td>';
        }

        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
}

function renderPermissionSummary(array $permissions, array $modules): string
{
    $parts = [];

    foreach ($permissions as $moduleKey => $modulePermissions) {
        if (!isset($modules[$moduleKey]) || !is_array($modulePermissions)) {
            continue;
        }

        $parts[] = htmlspecialchars($modules[$moduleKey] . ': ' . implode(', ', array_keys(array_filter($modulePermissions))));
    }

    return $parts ? implode('<br>', $parts) : '<span class="muted">Chưa có quyền.</span>';
}
