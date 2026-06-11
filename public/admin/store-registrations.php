<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_STORES);
$errors = [];
$success = flash_success();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $formAction = (string) ($_POST['form_action'] ?? '');
        $requestId = (int) ($_POST['request_id'] ?? 0);

        try {
            if (!PermissionMiddleware::can($user, MODULE_STORES, 'approve')) {
                throw new RuntimeException('Bạn không có quyền duyệt đơn mở shop.');
            }

            if ($formAction === 'approve') {
                $result = StoreRegistrationModel::approve($requestId, (int) $user['id'], trim((string) ($_POST['admin_note'] ?? '')) ?: null);
                $_SESSION['flash_success'] = 'Đã duyệt đơn và tạo tài khoản shop: ' . $result['username'];
                header('Location: /admin/store-registrations.php');
                exit;
            }

            if ($formAction === 'reject') {
                StoreRegistrationModel::reject($requestId, (int) $user['id'], (string) ($_POST['admin_note'] ?? ''));
                $_SESSION['flash_success'] = 'Đã tu choi đơn mở shop.';
                header('Location: /admin/store-registrations.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
];
$result = StoreRegistrationModel::paginateAdmin($filters);
$items = $result['items'];
$pagination = $result['pagination'];
$csrfToken = AuthController::csrfToken();
$canApprove = PermissionMiddleware::can($user, MODULE_STORES, 'approve');

function displayStatus(string $status): string {
    return match($status) {
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        default => $status
    };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duyệt đơn mở shop</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body class="portal-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_admin_nav.php"; ?>

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
            <h1>Duyệt đơn mở shop</h1>
            <form method="get" class="filter-row">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm kiếm...">
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (StoreRegistrationModel::requestStatuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                            <?= htmlspecialchars(displayStatus($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" title="Lọc dữ liệu">Lọc</button>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?= UiHelper::sortLink('created_at', 'ID', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                            <th><?= UiHelper::sortLink('store_name', 'Tên Shop', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                            <th>Email Shop</th>
                            <th>Tài khoản Shop</th>
                            <th>Người gửi</th>
                            <th>Email người gửi</th>
                            <th>Hồ sơ</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$items): ?>
                            <tr><td colspan="9" class="empty">Chưa có đơn mở shop.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= (int) $item['id'] ?></td>
                                <td><strong><?= htmlspecialchars($item['store_name']) ?></strong></td>
                                <td><?= htmlspecialchars($item['gmail']) ?></td>
                                <td><?= htmlspecialchars((string) ($item['store_username'] ?: '-')) ?></td>
                                <td><?= htmlspecialchars($item['full_name']) ?></td>
                                <td><?= htmlspecialchars($item['requester_email']) ?></td>
                                <td>
                                    <span>CCCD: <?= htmlspecialchars($item['cccd']) ?></span>
                                    <span>Loại: <?= htmlspecialchars($item['product_category']) ?></span>
                                    <?php if ($item['business_license_url']): ?>
                                        <span><?= htmlspecialchars($item['business_license_url']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge"><?= htmlspecialchars(displayStatus($item['status'])) ?></span>
                                    <?php if ($item['reviewed_at']): ?>
                                        <span><?= htmlspecialchars($item['reviewed_at']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <?php if ($item['status'] === 'pending' && $canApprove): ?>
                                        <form method="post" class="inline-decision-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="request_id" value="<?= (int) $item['id'] ?>">
                                            <textarea name="admin_note" rows="2" placeholder="Ghi chú admin"></textarea>
                                            <div class="actions">
                                                <button type="submit" name="form_action" value="approve">Duyệt</button>
                                                <button type="submit" name="form_action" value="reject">Từ chối</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) ($item['admin_note'] ?? '')) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
        </section>
    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
</body>
</html>
