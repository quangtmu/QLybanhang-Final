<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';
require_once BASE_PATH . '/includes/invoice_table.php';

$user = PermissionMiddleware::requireModule(MODULE_INVOICES);
$errors = [];
$success = flash_success();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            if (!PermissionMiddleware::can($user, MODULE_INVOICES, 'create')) {
                throw new RuntimeException('Bạn không có quyền xuat hóa đơn.');
            }

            InvoiceModel::generateForActor((int) ($_POST['order_id'] ?? 0), $user);
            $_SESSION['flash_success'] = 'Đã xuat hóa đơn PDF.';
            header('Location: /admin/invoices.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'order_status' => $_GET['order_status'] ?? '',
    'store_id' => $_GET['store_id'] ?? '',
    'buyer_id' => $_GET['buyer_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = InvoiceModel::paginateForActor($user, $filters);
$invoices = $result['items'];
$pagination = $result['pagination'];
$invoiceableOrders = InvoiceModel::invoiceableOrdersForActor($user, '', 80);
$stores = AdminOrderModel::storesForFilter();
$csrfToken = AuthController::csrfToken();
$canCreate = PermissionMiddleware::can($user, MODULE_INVOICES, 'create');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý hóa đơn</title>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h1 style="margin: 0 0 5px 0;">Quản lý hóa đơn</h1>
                </div>
                <?php if ($canCreate): ?>
                    <button type="button" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; border-radius: 6px; padding: 8px 16px; font-weight: 500; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" onclick="document.getElementById('create-invoice-modal').style.display='block'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/></svg>
                        Xuất hóa đơn
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($canCreate): ?>
                <div id="create-invoice-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
                    <div style="max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                        <button type="button" onclick="document.getElementById('create-invoice-modal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary);">&times;</button>
                        <h3 style="margin-top: 0; margin-bottom: 20px;">Xuất hóa đơn mới</h3>
                        <form method="post" class="admin-form js-validate" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <div class="shipment-form-grid" style="grid-template-columns: 1fr;">
                                <label>
                                    <span>Chọn đơn hàng <?= UiHelper::requiredMark() ?></span>
                                    <select name="order_id" required style="max-width: 100%; font-size: 13px;">
                                        <option value="">Chọn đơn hàng</option>
                                        <?php foreach ($invoiceableOrders as $order): ?>
                                            <?php 
                                                $optionText = $order['order_code'] . ' - ' . $order['status'] . ' - ' . $order['buyer_email'] . ' - ' . $order['store_name'];
                                                if (mb_strlen($optionText) > 55) {
                                                    $optionText = mb_substr($optionText, 0, 52) . '...';
                                                }
                                            ?>
                                            <option value="<?= (int) $order['id'] ?>">
                                                <?= htmlspecialchars($optionText) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <p class="muted" style="margin: 0; font-size: 13px;">Lưu ý: Hóa đơn PDF sẽ tự động tính thêm 8% VAT vào tổng giá trị đơn hàng.</p>
                            </div>
                            <div class="actions" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                                <button type="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;" onclick="document.getElementById('create-invoice-modal').style.display='none'">Đóng</button>
                                <button type="submit" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;" <?= !$invoiceableOrders ? 'disabled' : '' ?>>Xuất hóa đơn PDF</button>
                            </div>
                            <?php if (!$invoiceableOrders): ?>
                                <p class="muted" style="margin-top: 15px;">Hiện chưa có đơn hàng nào cần xuất hóa đơn.</p>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="portal-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">Danh sách hóa đơn</h2>
                <div class="muted">Tổng: <?= (int) $pagination['total'] ?> hóa đơn</div>
            </div>
            <form method="get" class="filter-row filter-row-large">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm kiếm...">
                <select name="order_status">
                    <option value="">Tất cả trạng thái đơn</option>
                    <?php foreach (OrderModel::orderStatuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['order_status'] === $status ? 'selected' : '' ?>>
                            <?= htmlspecialchars(UiHelper::statusLabel($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="store_id">
                    <option value="">Tất cả shop</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?= (int) $store['id'] ?>" <?= (string) $filters['store_id'] === (string) $store['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($store['store_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="buyer_id" value="<?= htmlspecialchars((string) $filters['buyer_id']) ?>" placeholder="Buyer ID">
                <input type="date" name="date_from" value="<?= htmlspecialchars((string) $filters['date_from']) ?>">
                <input type="date" name="date_to" value="<?= htmlspecialchars((string) $filters['date_to']) ?>">
                <button type="submit" title="Lọc dữ liệu">Lọc</button>
            </form>

            <?= renderInvoiceTable($invoices) ?>
            <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
        </section>
    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
</body>
</html>
