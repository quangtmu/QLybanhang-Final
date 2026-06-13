<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_ORDERS); // Using Orders module for now

require_once __DIR__ . '/../../app/models/VoucherModel.php';

$errors = [];
$success = flash_success();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'create') {
                VoucherModel::create($_POST, null); // Global voucher -> storeId = null
                $_SESSION['flash_success'] = 'Đã tạo mã khuyến mãi toàn sàn thành công.';
                header('Location: /admin/vouchers.php');
                exit;
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                VoucherModel::deleteStoreVoucher($id, null);
                $_SESSION['flash_success'] = 'Đã xoá mã khuyến mãi.';
                header('Location: /admin/vouchers.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Có lỗi xảy ra.';
        }
    }
}

// Get global vouchers (store_id IS NULL)
$stmt = getDB()->prepare('SELECT * FROM vouchers WHERE store_id IS NULL ORDER BY created_at DESC');
$stmt->execute();
$vouchers = $stmt->fetchAll();
$csrfToken = AuthController::csrfToken();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến mãi hệ thống - Admin</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611">
    <link rel="stylesheet" href="/assets/css/admin.css?v=20260611">
</head>
<body class="portal-page admin-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . '/_admin_nav.php'; ?>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php foreach ($errors as $message): ?><p><?= htmlspecialchars((string) $message) ?></p><?php endforeach; ?></div>
        <?php endif; ?>

        <section class="portal-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="margin: 0;">Quản lý mã khuyến mãi toàn sàn</h1>
                <button onclick="document.getElementById('modal-create').style.display='flex'" style="padding: 8px 16px; background: #0f766e; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">+ Thêm mã</button>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Mức giảm</th>
                            <th>Đơn tối thiểu</th>
                            <th>Sử dụng</th>
                            <th>Thời gian</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$vouchers): ?><tr><td colspan="6" class="empty">Chưa có mã khuyến mãi nào.</td></tr><?php endif; ?>
                        <?php foreach ($vouchers as $v): ?>
                            <tr>
                                <td><strong style="color: #0f766e;"><?= htmlspecialchars((string) $v['code']) ?></strong></td>
                                <td>
                                    <?= $v['discount_type'] === 'percent' ? (float) $v['discount_amount'] . '%' : number_format((float) $v['discount_amount']) . 'đ' ?>
                                    <?php if ($v['discount_type'] === 'percent' && $v['max_discount_amount'] > 0): ?>
                                        <br><span style="font-size: 11px; color: #64748b;">Tối đa <?= number_format((float) $v['max_discount_amount']) ?>đ</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format((float) $v['min_order_amount']) ?>đ</td>
                                <td><?= (int) $v['used_count'] ?> / <?= $v['usage_limit'] == 0 ? '∞' : (int) $v['usage_limit'] ?></td>
                                <td style="font-size: 12px;">
                                    <?= date('d/m/Y H:i', strtotime($v['start_date'])) ?><br>
                                    - <?= date('d/m/Y H:i', strtotime($v['end_date'])) ?>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Bạn có chắc muốn xoá mã này?');" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                        <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; text-decoration: underline;">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Create Modal -->
    <div id="modal-create" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; padding: 20px;">
        <div class="portal-panel" style="width: 100%; max-width: 500px; margin: 0; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 15px;">
                <h2 style="margin: 0; font-size: 18px;">Thêm mã khuyến mãi mới</h2>
                <button onclick="document.getElementById('modal-create').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create">
                
                <label><span>Mã voucher <span style="color: red">*</span></span>
                    <input type="text" name="code" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; text-transform: uppercase;">
                </label>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <label style="margin: 0;"><span>Loại giảm giá <span style="color: red">*</span></span>
                        <select name="discount_type" id="discount_type" onchange="toggleMaxDiscount()" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                            <option value="fixed">Số tiền (đ)</option>
                            <option value="percent">Phần trăm (%)</option>
                        </select>
                    </label>
                    <label style="margin: 0;"><span>Mức giảm <span style="color: red">*</span></span>
                        <input type="number" name="discount_amount" required min="1" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                    </label>
                </div>

                <label id="max_discount_container" style="display: none;"><span>Giảm tối đa (đ)</span>
                    <input type="number" name="max_discount_amount" min="0" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;" placeholder="Để trống nếu không giới hạn">
                </label>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <label style="margin: 0;"><span>Đơn tối thiểu (đ)</span>
                        <input type="number" name="min_order_amount" min="0" value="0" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                    </label>
                    <label style="margin: 0;"><span>Giới hạn sử dụng</span>
                        <input type="number" name="usage_limit" min="0" value="0" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;" placeholder="0 = Không giới hạn">
                    </label>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <label style="margin: 0;"><span>Từ ngày <span style="color: red">*</span></span>
                        <input type="datetime-local" name="start_date" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                    </label>
                    <label style="margin: 0;"><span>Đến ngày <span style="color: red">*</span></span>
                        <input type="datetime-local" name="end_date" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                    <button type="button" onclick="document.getElementById('modal-create').style.display='none'" style="padding: 8px 16px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">Hủy</button>
                    <button type="submit" style="padding: 8px 16px; background: #0f766e; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Lưu mã</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleMaxDiscount() {
            const type = document.getElementById('discount_type').value;
            const container = document.getElementById('max_discount_container');
            if (type === 'percent') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
    </script>
</body>
</html>
