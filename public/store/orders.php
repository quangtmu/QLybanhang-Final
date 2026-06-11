<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_ORDERS);
$errors = [];
$success = flash_success();
$csrfToken = AuthController::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            $action = (string) ($_POST['form_action'] ?? '');
            $orderId = (int) ($_POST['order_id'] ?? 0);

            if ($action === 'confirm') {
                StoreOrderModel::confirm($user, $orderId, (string) ($_POST['note'] ?? ''));
                $_SESSION['flash_success'] = 'Đã xac nhan đơn hàng.';
            } elseif ($action === 'processing') {
                StoreOrderModel::startProcessing($user, $orderId, (string) ($_POST['note'] ?? ''));
                $_SESSION['flash_success'] = 'Đã chuyen don sang dang xu ly.';
            } elseif ($action === 'cancel') {
                StoreOrderModel::cancel($user, $orderId, (string) ($_POST['reason'] ?? ''));
                $_SESSION['flash_success'] = 'Đã huy đơn hàng.';
            } elseif ($action === 'create_manual') {
                StoreOrderModel::createManual($user, manualOrderPayload($_POST));
                $_SESSION['flash_success'] = 'Đã tao đơn hàng thu cong.';
            } else {
                throw new RuntimeException('Thao tác không hop le.');
            }

            header('Location: /store/orders.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'buyer_id' => $_GET['buyer_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_dir' => $_GET['sort_dir'] ?? 'DESC',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = StoreOrderModel::paginate($user, $filters);
$orders = $result['items'];
$pagination = $result['pagination'];
$canCreate = PermissionMiddleware::can($user, MODULE_ORDERS, 'create');
$canUpdate = PermissionMiddleware::can($user, MODULE_ORDERS, 'update');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng shop</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
    <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260611-17">
    <link rel="stylesheet" href="/assets/css/store-orders.css?v=20260611-17">
</head>
<body class="portal-page store-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_store_nav.php"; ?>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php foreach ($errors as $message): ?><p><?= htmlspecialchars((string) $message) ?></p><?php endforeach; ?></div>
        <?php endif; ?>

        <section class="portal-panel">
            <h1>Quản lý đơn hàng shop</h1>
            <form method="get" class="filter-row filter-row-large">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Mã đơn, buyer">
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (OrderModel::orderStatuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars(UiHelper::statusLabel($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="buyer_id" value="<?= htmlspecialchars((string) $filters['buyer_id']) ?>" placeholder="Buyer ID">
                <input type="date" name="date_from" value="<?= htmlspecialchars((string) $filters['date_from']) ?>">
                <input type="date" name="date_to" value="<?= htmlspecialchars((string) $filters['date_to']) ?>">
                <select name="sort_by">
                    <option value="created_at" <?= ($filters['sort_by'] ?? '') === 'created_at' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="total_amount" <?= ($filters['sort_by'] ?? '') === 'total_amount' ? 'selected' : '' ?>>Giá trị</option>
                </select>
                <select name="sort_dir">
                    <option value="DESC" <?= ($filters['sort_dir'] ?? 'DESC') === 'DESC' ? 'selected' : '' ?>>Giảm dần</option>
                    <option value="ASC" <?= ($filters['sort_dir'] ?? '') === 'ASC' ? 'selected' : '' ?>>Tăng dần</option>
                </select>
                <button type="submit">Lọc</button>
            </form>
        </section>

        <?php if ($canCreate): ?>
            <button type="button" onclick="document.getElementById('create-manual-order-modal').style.display='block'" style="margin-bottom: 20px; background: #0f766e; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 14px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/></svg>
                Tạo đơn hàng thủ công
            </button>

            <?php
            $catalogProducts = [];
            $variantsByProduct = [];
            $storeId = StoreEmployeeModel::storeIdForActor($user);
            $db = getDB();
            $stmt = $db->prepare('SELECT id, name, base_price, stock_quantity FROM products WHERE store_id = :store_id AND deleted_at IS NULL AND status = "approved"');
            $stmt->execute([':store_id' => $storeId]);
            $catalogProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $productIds = array_column($catalogProducts, 'id');
            if ($productIds) {
                $inClause = implode(',', array_fill(0, count($productIds), '?'));
                $stmt = $db->prepare("SELECT id, product_id, type_label, color, size, price, stock_quantity FROM product_variants WHERE product_id IN ($inClause) AND is_active = 1");
                $stmt->execute($productIds);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
                    $variantsByProduct[$v['product_id']][] = $v;
                }
            }
            ?>

            <div id="create-manual-order-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
                <div style="width: 100%; max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative; box-sizing: border-box;">
                    <button type="button" onclick="document.getElementById('create-manual-order-modal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary);">&times;</button>
                    <h2 style="margin-top: 0; margin-bottom: 20px;">Tạo đơn hàng thủ công</h2>
                    
                    <form method="post" class="admin-form js-validate" id="manual-order-form" novalidate onsubmit="return serializeManualOrderItems()" style="width: 100%; box-sizing: border-box; overflow: hidden;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div style="display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; width: 100%;">
                            <label style="flex: 1 1 200px; font-size: 13px; font-weight: 700; display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                <span>Email buyer <span style="color:#dc2626;">*</span></span>
                                <input type="email" name="buyer_email" placeholder="buyer@example.com" required style="width: 100%; min-width: 0; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            </label>
                            <label style="flex: 1 1 200px; font-size: 13px; font-weight: 700; display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                <span>Người nhận <span style="color:#dc2626;">*</span></span>
                                <input type="text" name="receiver_name" required style="width: 100%; min-width: 0; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            </label>
                            <label style="flex: 1 1 200px; font-size: 13px; font-weight: 700; display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                <span>SĐT nhận <span style="color:#dc2626;">*</span></span>
                                <input type="text" name="receiver_phone" required style="width: 100%; min-width: 0; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            </label>
                            <label style="flex: 2 1 400px; font-size: 13px; font-weight: 700; display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                <span>Địa chỉ giao <span style="color:#dc2626;">*</span></span>
                                <input type="text" name="address_line" required style="width: 100%; min-width: 0; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            </label>
                            <label style="flex: 1 1 200px; font-size: 13px; font-weight: 700; display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                <span>Phí ship</span>
                                <input type="number" name="shipping_fee" min="0" value="0" style="width: 100%; min-width: 0; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                            </label>
                            <label style="flex: 1 1 100%; font-size: 13px; font-weight: 700; display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                <span>Ghi chú</span>
                                <textarea name="note" rows="2" style="width: 100%; min-width: 0; box-sizing: border-box; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; resize: vertical;"></textarea>
                            </label>
                        </div>

                        <div style="width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-bottom: 24px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h3 style="margin: 0; font-size: 16px;">Sản phẩm <span style="color:#dc2626;">*</span></h3>
                                <button type="button" onclick="addManualOrderItem()" style="padding: 6px 12px; font-size: 12px; font-weight: 600; border-radius: 4px; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; cursor: pointer;">+ Thêm dòng</button>
                            </div>
                            
                            <div style="width: 100%; overflow-x: auto;">
                                <table class="data-table" style="margin-bottom: 0; width: 100%; table-layout: fixed;">
                                <thead>
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th>Biến thể</th>
                                        <th style="width: 100px;">Số lượng</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="manual-order-items-container">
                                </tbody>
                                </table>
                            </div>
                        </div>

                        <div style="text-align: right; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                            <button type="button" style="padding: 8px 20px; font-size: 14px; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #475569; cursor: pointer; margin-right: 8px;" onclick="document.getElementById('create-manual-order-modal').style.display='none'">Hủy</button>
                            <button type="submit" style="padding: 8px 24px; font-size: 14px; font-weight: 600; border-radius: 6px; border: none; background: #0f766e; color: #fff; cursor: pointer;">Tạo đơn ngay</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                const catalogProducts = <?= json_encode($catalogProducts, JSON_UNESCAPED_UNICODE) ?>;
                const variantsByProduct = <?= json_encode($variantsByProduct, JSON_UNESCAPED_UNICODE) ?>;

                function getProductOptionsHTML() {
                    let html = '<option value="">-- Chọn sản phẩm --</option>';
                    catalogProducts.forEach(p => {
                        html += `<option value="${p.id}">${p.name.replace(/</g, "&lt;").replace(/>/g, "&gt;")} (Tồn: ${p.stock_quantity})</option>`;
                    });
                    return html;
                }

                function addManualOrderItem() {
                    const container = document.getElementById('manual-order-items-container');
                    const tr = document.createElement('tr');
                    tr.className = 'manual-item-row';
                    
                    tr.innerHTML = `
                        <td>
                            <select class="item-product-select" onchange="handleProductChange(this)" required style="width: 100%; max-width: 100%; text-overflow: ellipsis; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
                                ${getProductOptionsHTML()}
                            </select>
                        </td>
                        <td>
                            <select class="item-variant-select" style="width: 100%; max-width: 100%; text-overflow: ellipsis; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
                                <option value="">-- Không có --</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="item-quantity" min="1" value="1" required style="width: 100%; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px;">
                        </td>
                        <td style="text-align: center;">
                            <button type="button" onclick="this.closest('tr').remove()" style="color: #ef4444; background: none; border: none; cursor: pointer; font-size: 16px;" title="Xóa dòng">&times;</button>
                        </td>
                    `;
                    container.appendChild(tr);
                }

                function handleProductChange(selectElem) {
                    const productId = selectElem.value;
                    const variantSelect = selectElem.closest('tr').querySelector('.item-variant-select');
                    
                    variantSelect.innerHTML = '<option value="">-- Không có --</option>';
                    if (!productId) return;

                    const variants = variantsByProduct[productId] || [];
                    if (variants.length > 0) {
                        let options = '<option value="">-- Chọn biến thể --</option>';
                        variants.forEach(v => {
                            let label = [];
                            if (v.type_label) label.push(v.type_label);
                            if (v.color) label.push(v.color);
                            if (v.size) label.push(v.size);
                            options += `<option value="${v.id}">${label.join(' - ').replace(/</g, "&lt;").replace(/>/g, "&gt;")} (Tồn: ${v.stock_quantity})</option>`;
                        });
                        variantSelect.innerHTML = options;
                        variantSelect.required = true;
                    } else {
                        variantSelect.required = false;
                    }
                }

                function serializeManualOrderItems() {
                    const rows = document.querySelectorAll('.manual-item-row');
                    if (rows.length === 0) {
                        alert('Vui lòng thêm ít nhất 1 sản phẩm.');
                        return false;
                    }

                    const items = [];
                    let hasError = false;

                    rows.forEach(row => {
                        const pid = row.querySelector('.item-product-select').value;
                        const vid = row.querySelector('.item-variant-select').value;
                        const qty = row.querySelector('.item-quantity').value;
                        
                        if (!pid) {
                            hasError = true;
                        } else {
                            items.push(`${pid}|${vid}|${qty}`);
                        }
                    });

                    if (hasError) {
                        alert('Vui lòng chọn sản phẩm cho tất cả các dòng.');
                        return false;
                    }

                    document.getElementById('hidden_items_text').value = items.join('\\n');
                    return true;
                }

                document.addEventListener('DOMContentLoaded', () => {
                    addManualOrderItem();
                });
            </script>
        <?php endif; ?>

        <section class="portal-panel">
            <h2>Danh sách đơn</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Mã đơn</th><th>Ngày tạo</th><th>Buyer</th><th>Tạm tính</th><th>Thành tiền</th><th>Trạng thái</th><th>Sản phẩm</th><th>Thao tác</th></tr></thead>
                    <tbody>
                        <?php if (!$orders): ?><tr><td colspan="8" class="empty">Chưa có đơn hàng.</td></tr><?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($order['order_code']) ?></strong>
                                    <span style="display:block; font-size:11px; color:#94a3b8;">ID: <?= (int) $order['id'] ?></span>
                                </td>
                                <td><span style="font-size:13px; white-space:nowrap;"><?= htmlspecialchars($order['created_at']) ?></span></td>
                                <td>
                                    <span style="display:block; font-weight:600; font-size:13px;"><?= htmlspecialchars($order['buyer_name']) ?></span>
                                    <span style="display:block; font-size:12px; color:#64748b;"><?= htmlspecialchars($order['buyer_email']) ?></span>
                                </td>
                                <td><span style="font-size:13px; color:#64748b;"><?= money((float) $order['total_amount']) ?></span></td>
                                <td><strong style="font-size:13px; color:#0f172a;"><?= money((float) $order['final_amount']) ?></strong></td>
                                <td>
                                    <span class="badge <?= htmlspecialchars(UiHelper::statusClass((string) $order['status'])) ?>"><?= htmlspecialchars(UiHelper::statusLabel((string) $order['status'])) ?></span>
                                    <?php if ($order['cancel_reason']): ?>
                                        <span style="display:block; font-size:11px; color:#b91c1c; margin-top:4px;"><?= htmlspecialchars($order['cancel_reason']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($order['order_items'] as $item): ?>
                                        <span style="display:block; font-size:12px; line-height:1.6;"><?= htmlspecialchars($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="order-actions-cell">
                                    <?php if ($canUpdate): ?>
                                        <div class="order-act">
                                            <div class="order-act__btns">
                                                <?php if ($order['status'] === ORDER_STATUS_PENDING): ?>
                                                    <?= actionForm($csrfToken, (int) $order['id'], 'confirm', '<i class="bi bi-check2-circle"></i> Xác nhận', 'confirm') ?>
                                                <?php endif; ?>
                                                <?php if (in_array($order['status'], [ORDER_STATUS_PENDING, ORDER_STATUS_CONFIRMED], true)): ?>
                                                    <?= actionForm($csrfToken, (int) $order['id'], 'processing', '<i class="bi bi-box-seam"></i> Đóng gói', 'pack') ?>
                                                <?php endif; ?>
                                                <?php if (!in_array($order['status'], [ORDER_STATUS_CANCELLED, ORDER_STATUS_REFUNDED, ORDER_STATUS_DELIVERED], true)): ?>
                                                    <a href="/store/shipments.php?order_id=<?= (int) $order['id'] ?>" class="order-act__btn order-act__btn--ship"><i class="bi bi-truck"></i> Vận đơn</a>
                                                <?php endif; ?>
                                                <a href="/store/print_order.php?id=<?= (int) $order['id'] ?>" target="_blank" class="order-act__btn order-act__btn--ship" style="background: #e0f2fe; color: #0f766e; border-color: #bae6fd;"><i class="bi bi-printer"></i> In đơn</a>
                                                <?php if (in_array($order['status'], [ORDER_STATUS_CANCELLED, ORDER_STATUS_REFUNDED, ORDER_STATUS_DELIVERED], true)): ?>
                                                    <span class="order-act__done"><i class="bi bi-check2"></i> Hoàn tất</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (in_array($order['status'], [ORDER_STATUS_PENDING, ORDER_STATUS_CONFIRMED], true)): ?>
                                                <form method="post" class="order-act__cancel">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                    <input type="hidden" name="form_action" value="cancel">
                                                    <input type="text" name="reason" placeholder="Lý do hủy" required class="order-act__cancel-input">
                                                    <button type="submit" class="order-act__btn order-act__btn--cancel"><i class="bi bi-x-circle"></i> Hủy</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?><span class="order-act__done">Chỉ xem</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
        </section>
    </main>
    <script src="/assets/js/global.js"></script>
</body>
</html>
<?php

function manualOrderPayload(array $post): array
{
    return [
        'buyer_email' => $post['buyer_email'] ?? '',
        'items' => itemsFromText((string) ($post['items_text'] ?? '')),
        'receiver_name' => $post['receiver_name'] ?? '',
        'receiver_phone' => $post['receiver_phone'] ?? '',
        'address_line' => $post['address_line'] ?? '',
        'shipping_fee' => $post['shipping_fee'] ?? 0,
        'note' => $post['note'] ?? '',
    ];
}

function itemsFromText(string $text): array
{
    $items = [];
    foreach (preg_split('/\r\n|\r|\n/', trim($text)) ?: [] as $line) {
        $parts = array_map('trim', explode('|', trim($line)));
        if (!$parts || ($parts[0] ?? '') === '') continue;
        $items[] = ['product_id' => (int) ($parts[0] ?? 0), 'variant_id' => ($parts[1] ?? '') === '' ? null : (int) $parts[1], 'quantity' => (int) ($parts[2] ?? 1)];
    }
    return $items;
}

function actionForm(string $csrfToken, int $orderId, string $action, string $label, string $modifier = ''): string
{
    $cls = 'order-act__btn order-act__btn--' . htmlspecialchars($modifier);
    return '<form method="post" style="margin:0;"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '"><input type="hidden" name="order_id" value="' . $orderId . '"><input type="hidden" name="form_action" value="' . htmlspecialchars($action) . '"><button type="submit" class="' . $cls . '">' . $label . '</button></form>';
}

function money(float $value): string
{
    return number_format($value, 0, ',', '.') . ' d';
}
