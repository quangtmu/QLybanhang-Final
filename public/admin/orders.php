<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_ORDERS);
$errors = [];
$success = flash_success();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            if (!PermissionMiddleware::can($user, MODULE_ORDERS, 'update')) {
                throw new RuntimeException('Bạn không có quyền can thiep đơn hàng.');
            }

            $formAction = $_POST['form_action'] ?? '';
            
            if ($formAction === 'cancel_order') {
                $reason = trim($_POST['cancel_reason'] ?? 'Admin huy don theo yeu cau');
                AdminOrderModel::cancelByAdmin(
                    (int) ($_POST['order_id'] ?? 0),
                    (int) $user['id'],
                    $reason
                );
                $_SESSION['flash_success'] = 'Đã hủy đơn hàng.';
                header('Location: /admin/orders.php');
                exit;
            } elseif ($formAction === 'urge_order') {
                $orderId = (int) ($_POST['order_id'] ?? 0);
                $orderInfo = AdminOrderModel::detail($orderId);
                if ($orderInfo) {
                    require_once BASE_PATH . '/app/Models/ChatModel.php';
                    $room = ChatModel::roomForOrder($orderId, $user);
                    
                    $addr = $orderInfo['shipping_address_data'] ?? [];
                    $phone = $addr['receiver_phone'] ?? $addr['phone'] ?? 'Không có SĐT';
                    $receiver = $addr['receiver_name'] ?? $addr['name'] ?? $addr['full_name'] ?? 'Không rõ';
                    $itemsStr = '';
                    foreach ($orderInfo['order_items'] as $it) {
                        $itemsStr .= "- " . $it['product_name'] . " (SL: " . $it['quantity'] . ")\n";
                    }
                    
                    $message = "⚠️ ADMIN YÊU CẦU HỐI \n";
                    $message .= "---------------------------------------\n";
                    $message .= "Mã đơn: " . $orderInfo['order_code'] . "\n";
                    $message .= "Ngày đặt: " . $orderInfo['created_at'] . "\n";
                    $message .= "Trạng thái HT: " . UiHelper::statusLabel($orderInfo['status']) . "\n";
                    $message .= "Người nhận: " . $receiver . " - SĐT: " . $phone . "\n";
                    $message .= "Sản phẩm:\n" . trim($itemsStr);
                    
                    ChatModel::sendMessage((int) $room['id'], $user, $message);
                    header('Location: /chat.php?room_id=' . $room['id']);
                    exit;
                }
            } elseif ($formAction === 'update_order_info') {
                $orderId = (int) ($_POST['order_id'] ?? 0);
                $newAddress = [
                    'full_name' => trim((string) ($_POST['ship_name'] ?? '')),
                    'phone' => trim((string) ($_POST['ship_phone'] ?? '')),
                    'address' => trim((string) ($_POST['ship_address'] ?? ''))
                ];
                AdminOrderModel::updateShippingAddress($orderId, $newAddress);
                
                $newStatus = trim((string) ($_POST['order_status'] ?? ''));
                if ($newStatus) {
                    AdminOrderModel::updateOrderStatus($orderId, $newStatus, (int) $user['id']);
                }

                $_SESSION['flash_success'] = 'Đã cập nhật thông tin đơn hàng.';
                header('Location: /admin/orders.php');
                exit;
            } elseif ($formAction === 'update_order_items') {
                $orderId = (int) ($_POST['order_id'] ?? 0);
                $itemsData = $_POST['items'] ?? [];
                if (is_array($itemsData)) {
                    AdminOrderModel::updateOrderItems($orderId, $itemsData);
                }
                $_SESSION['flash_success'] = 'Đã cập nhật sản phẩm đơn hàng.';
                header('Location: /admin/orders.php');
                exit;
            } else {
                // Inline cancel handle
                AdminOrderModel::cancelByAdmin(
                    (int) ($_POST['order_id'] ?? 0),
                    (int) $user['id'],
                    (string) ($_POST['cancel_reason'] ?? '')
                );
                $_SESSION['flash_success'] = 'Đã huy đơn hàng.';
                header('Location: /admin/orders.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'store_id' => $_GET['store_id'] ?? '',
    'buyer_id' => $_GET['buyer_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_dir' => $_GET['sort_dir'] ?? 'DESC',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = AdminOrderModel::paginate($filters);
$orders = $result['items'];
$pagination = $result['pagination'];
$stores = AdminOrderModel::storesForFilter();
$csrfToken = AuthController::csrfToken();
$canCancel = PermissionMiddleware::can($user, MODULE_ORDERS, 'update');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng</title>
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
            <h1>Quản lý đơn hàng</h1>
            <form method="get" class="filter-row filter-row-large">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm kiếm...">
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (OrderModel::orderStatuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
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

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Đơn</th>
                            <th>Order ID</th>
                            <th><?= UiHelper::sortLink('created_at', 'Ngày tạo', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                            <th>Tên Buyer</th>
                            <th>Buyer ID</th>
                            <th>Email</th>
                            <th>Tên Shop</th>
                            <th>Shop ID</th>
                            <th><?= UiHelper::sortLink('total_amount', 'Giá trị', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                            <th>Tạm tính</th>
                            <th>Trạng thái</th>
                            <th>Sản phẩm</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$orders): ?>
                            <tr><td colspan="13" class="empty">Chưa có đơn hàng.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <strong class="text-truncate" style="display:block; max-width:120px;" title="<?= htmlspecialchars($order['order_code']) ?>"><?= htmlspecialchars($order['order_code']) ?></strong>
                                </td>
                                <td>#<?= (int) $order['id'] ?></td>
                                <td><?= htmlspecialchars($order['created_at']) ?></td>
                                <td><span class="text-truncate" title="<?= htmlspecialchars($order['buyer_name']) ?>"><?= htmlspecialchars($order['buyer_name']) ?></span></td>
                                <td>#<?= (int) $order['buyer_id'] ?></td>
                                <td><span class="text-truncate" title="<?= htmlspecialchars($order['buyer_email']) ?>"><?= htmlspecialchars($order['buyer_email']) ?></span></td>
                                <td>
                                    <span class="text-truncate" title="<?= htmlspecialchars((string) ($order['store_name'] ?: $order['store_email'])) ?>">
                                        <?= htmlspecialchars((string) ($order['store_name'] ?: $order['store_email'])) ?>
                                    </span>
                                </td>
                                <td>#<?= (int) $order['store_id'] ?></td>
                                <td>
                                    <strong class="price-cell"><?= UiHelper::money($order['final_amount']) ?></strong>
                                </td>
                                <td><?= UiHelper::money($order['total_amount']) ?></td>
                                <td>
                                    <span class="badge <?= htmlspecialchars(UiHelper::statusClass((string) $order['status'])) ?>"><?= htmlspecialchars(UiHelper::statusLabel((string) $order['status'])) ?></span>
                                </td>
                                <td>
                                    <?php foreach ($order['order_items'] as $item): ?>
                                        <span><?= htmlspecialchars($item['product_name']) ?> x <?= (int) $item['quantity'] ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="actions">
                                    <div style="display: flex; gap: 8px; align-items: flex-start;">
                                        <button type="button" class="btn-action-outline" title="Xem chi tiết" onclick="openOrderModal(<?= (int) $order['id'] ?>)">
                                            <i class="bi bi-eye"></i> Xem
                                        </button>
                                        <?php if ($canCancel && !in_array($order['status'], [ORDER_STATUS_DELIVERED, ORDER_STATUS_REFUNDED, ORDER_STATUS_CANCELLED], true)): ?>
                                            <div style="position: relative;" class="cancel-popover-container">
                                                <button type="button" class="btn-danger-outline" onclick="this.nextElementSibling.style.display='block'">
                                                    <i class="bi bi-x-circle"></i> Hủy
                                                </button>
                                                <div class="cancel-popup" style="display:none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: white; padding: 12px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 50; border: 1px solid #dbe4f0; width: 220px; text-align: left;">
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                        <textarea name="cancel_reason" rows="2" placeholder="Lý do hủy..." required style="width: 100%; margin-bottom: 8px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; font-family: inherit;"></textarea>
                                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                            <button type="button" class="btn-secondary" onclick="this.closest('.cancel-popup').style.display='none'">Đóng</button>
                                                            <button type="submit" class="btn-danger-outline">Xác nhận</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
        </section>
        
        <!-- Order Detail Modal -->
        <div id="order-detail-modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
            <section class="portal-panel" style="max-width: 1200px; margin: 0 auto; position: relative; padding: 30px;">
                <button type="button" onclick="closeOrderModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary);">&times;</button>
                <h2 style="margin-top: 0; margin-bottom: 20px;">Chi tiết đơn hàng</h2>
                
                <div id="modal-loading">Đang tải...</div>
                
                <div id="modal-content" style="display:none;">
                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="urge_order">
                            <input type="hidden" name="order_id" id="modal-order-id-urge">
                            <button type="submit" style="padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; background: #fffbeb; color: #d97706; border: 1px solid transparent; border-radius: 5px; transition: all 0.2s;" onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='#fffbeb'">
                                <i class="bi bi-clock-history"></i> Hối đơn
                            </button>
                        </form>
                        <a id="modal-btn-print" href="#" target="_blank" style="padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; background: #f0fdf4; color: #166534; border: 1px solid transparent; border-radius: 5px; transition: all 0.2s; text-decoration: none; margin-right: 5px; margin-left: 5px;" onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
                            <i class="bi bi-printer"></i> In đơn
                        </a>
                        <div style="position: relative; display: inline-block;">
                            <button type="button" style="padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; background: #fef2f2; color: #dc2626; border: 1px solid transparent; border-radius: 5px; transition: all 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'" onclick="document.getElementById('cancel-popup').style.display='block'">
                                <i class="bi bi-x-circle"></i> Hủy đơn
                            </button>
                            <div id="cancel-popup" style="display:none; position: absolute; top: 100%; left: 0; margin-top: 10px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 101; border: 1px solid #dbe4f0; width: 300px;">
                                <h4 style="margin-top: 0; margin-bottom: 15px;">Lý do hủy đơn</h4>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="form_action" value="cancel_order">
                                    <input type="hidden" name="order_id" id="modal-order-id-cancel">
                                    <select name="cancel_reason" required style="width: 100%; margin-bottom: 15px; padding: 8px; border: 1px solid #dbe4f0; border-radius: 4px;">
                                        <option value="">-- Chọn lý do --</option>
                                        <option value="Khách hàng yêu cầu hủy">Khách hàng yêu cầu hủy</option>
                                        <option value="Sản phẩm hết hàng">Sản phẩm hết hàng</option>
                                        <option value="Sai thông tin giao hàng">Sai thông tin giao hàng</option>
                                        <option value="Không liên lạc được khách">Không liên lạc được khách</option>
                                        <option value="Khác">Lý do khác...</option>
                                    </select>
                                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                        <button type="button" class="btn btn-outline" onclick="document.getElementById('cancel-popup').style.display='none'">Đóng</button>
                                        <button type="submit" class="btn btn-danger">Xác nhận hủy</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <button type="button" style="padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; border-radius: 5px; transition: all 0.2s; margin-left: 5px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'" onclick="toggleEditInfo()">
                            <i class="bi bi-pencil-square"></i> Chỉnh sửa thông tin
                        </button>
                    </div>

                    <form method="post" id="form-edit-info-inline" style="margin-bottom: 20px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="form_action" value="update_order_info">
                        <input type="hidden" name="order_id" id="modal-order-id-info">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <h4>Thông tin chung</h4>
                                <p><strong>Mã đơn:</strong> <span id="modal-order-code"></span></p>
                                <p><strong>Ngày tạo:</strong> <span id="modal-created-at"></span></p>
                                <p><strong>Giá trị:</strong> <span id="modal-final-amount" class="price-cell"></span></p>
                                <p><strong>Trạng thái HT:</strong> 
                                    <span id="modal-status" class="info-view"></span>
                                    <select name="order_status" id="modal-order-status" class="info-edit" style="display:none; width: 100%; border: 1px solid #dbe4f0; padding: 4px; border-radius: 4px; margin-top: 4px;">
                                        <?php foreach (OrderModel::orderStatuses() as $st): ?>
                                            <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars(UiHelper::statusLabel($st)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                                <p id="modal-cancel-reason-container" style="display:none; color: #ef4444;">
                                    <strong>Lý do hủy:</strong> <span id="modal-cancel-reason"></span>
                                </p>
                            </div>
                            <div>
                                <h4>Khách hàng</h4>
                                <p style="margin-bottom: 8px;"><strong>Tên:</strong> 
                                    <span id="modal-buyer-name" class="info-view"></span>
                                    <input type="text" name="ship_name" id="modal-ship-name" class="info-edit" style="display:none; width: 100%; border: 1px solid #dbe4f0; padding: 4px; border-radius: 4px; margin-top: 4px;" required>
                                </p>
                                <p style="margin-bottom: 8px;"><strong>Email:</strong> <span id="modal-buyer-email"></span></p>
                                <p style="margin-bottom: 8px;"><strong>SĐT:</strong> 
                                    <span id="modal-view-phone" class="info-view"></span>
                                    <input type="text" name="ship_phone" id="modal-ship-phone" class="info-edit" style="display:none; width: 100%; border: 1px solid #dbe4f0; padding: 4px; border-radius: 4px; margin-top: 4px;" required>
                                </p>
                                <p style="margin-bottom: 8px;"><strong>Địa chỉ:</strong> 
                                    <span id="modal-view-address" class="info-view"></span>
                                    <textarea name="ship_address" id="modal-ship-address" rows="2" class="info-edit" style="display:none; width: 100%; border: 1px solid #dbe4f0; padding: 4px; border-radius: 4px; margin-top: 4px;" required></textarea>
                                </p>
                                <p style="margin-bottom: 8px;"><strong>Store:</strong> <span id="modal-store-name"></span></p>
                            </div>
                        </div>
                        <button type="submit" id="btn-save-info" class="btn btn-primary" style="display:none; margin-top: 15px;">Lưu thông tin cập nhật</button>
                    </form>

                    <form method="post" class="admin-form" style="margin-bottom: 20px; padding: 15px; border: 1px solid #dbe4f0; border-radius: 8px; overflow-x: auto;">
                        <h4>Sản phẩm (Chỉ sửa thông tin hiển thị)</h4>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="form_action" value="update_order_items">
                        <input type="hidden" name="order_id" id="modal-order-id-items">
                        <table class="data-table" style="width: 100%; margin-bottom: 10px; min-width: 700px;">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Mã SP</th>
                                    <th>Loại</th>
                                    <th>Màu sắc</th>
                                    <th>Size</th>
                                    <th style="width: 60px;">SL</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="modal-order-items"></tbody>
                        </table>
                        <button type="submit" id="btn-save-items" class="btn btn-primary" style="display:none;">Lưu thay đổi sản phẩm</button>
                    </form>


                </div>
            </section>
        </div>
    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
    <script>
    function openOrderModal(id) {
        document.getElementById('order-detail-modal').style.display = 'block';
        document.getElementById('modal-btn-print').href = '#';
        document.getElementById('modal-loading').style.display = 'block';
        document.getElementById('modal-content').style.display = 'none';

        fetch('/admin/ajax_order_detail.php?id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    closeOrderModal();
                    return;
                }
                const o = data.order;
                document.getElementById('modal-order-code').textContent = o.order_code;
                document.getElementById('modal-created-at').textContent = o.created_at;
                document.getElementById('modal-final-amount').textContent = new Intl.NumberFormat('vi-VN', {style: 'currency', currency: 'VND'}).format(o.final_amount);
                document.getElementById('modal-status').textContent = o.status;
                
                if (o.cancel_reason) {
                    document.getElementById('modal-cancel-reason-container').style.display = 'block';
                    document.getElementById('modal-cancel-reason').textContent = o.cancel_reason;
                } else {
                    document.getElementById('modal-cancel-reason-container').style.display = 'none';
                }
                
                document.getElementById('modal-buyer-name').textContent = o.buyer_name || '';
                document.getElementById('modal-buyer-email').textContent = o.buyer_email || '';
                document.getElementById('modal-store-name').textContent = o.store_name || o.store_email || '';
                
                // Populate items
                const tbody = document.getElementById('modal-order-items');
                tbody.innerHTML = '';
                if (o.order_items && o.order_items.length > 0) {
                    o.order_items.forEach(item => {
                        const tr = document.createElement('tr');
                        const pName = item.product_name ? item.product_name.replace(/"/g, '&quot;') : '';
                        const pCode = item.product_code ? item.product_code.replace(/"/g, '&quot;') : '';
                        const tLabel = item.type_label ? item.type_label.replace(/"/g, '&quot;') : '';
                        const color = item.color ? item.color.replace(/"/g, '&quot;') : '';
                        const size = item.size ? item.size.replace(/"/g, '&quot;') : '';
                        const qty = item.quantity || 1;
                        
                        tr.innerHTML = `
                            <td>
                                <span>${pName || 'Trống'}</span>
                                <input type="text" name="items[${item.id}][product_name]" value="${pName}" style="display:none; width:100%; border:1px solid #dbe4f0; padding:4px;">
                            </td>
                            <td>
                                <span>${pCode || 'Trống'}</span>
                                <input type="text" name="items[${item.id}][product_code]" value="${pCode}" style="display:none; width:100%; border:1px solid #dbe4f0; padding:4px;">
                            </td>
                            <td>
                                <span>${tLabel || 'Trống'}</span>
                                <input type="text" name="items[${item.id}][type_label]" value="${tLabel}" style="display:none; width:100%; border:1px solid #dbe4f0; padding:4px;">
                            </td>
                            <td>
                                <span>${color || 'Trống'}</span>
                                <input type="text" name="items[${item.id}][color]" value="${color}" style="display:none; width:100%; border:1px solid #dbe4f0; padding:4px;">
                            </td>
                            <td>
                                <span>${size || 'Trống'}</span>
                                <input type="text" name="items[${item.id}][size]" value="${size}" style="display:none; width:100%; border:1px solid #dbe4f0; padding:4px;">
                            </td>
                            <td>
                                <span>${qty}</span>
                                <input type="number" name="items[${item.id}][quantity]" value="${qty}" min="1" style="display:none; width:100%; border:1px solid #dbe4f0; padding:4px;" required>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-sm btn-outline" onclick="toggleEditItem(this)" title="Sửa">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="7">Không có sản phẩm</td></tr>';
                }

                document.getElementById('btn-save-items').style.display = 'none';

                // Populate forms
                document.getElementById('modal-order-id-urge').value = o.id;
                document.getElementById('modal-order-id-cancel').value = o.id;
                document.getElementById('modal-order-id-items').value = o.id;
                document.getElementById('modal-order-id-info').value = o.id;
                
                document.getElementById('modal-order-status').value = o.status;
                
                let addr = o.shipping_address_data || {};
                let phone = addr.receiver_phone || addr.phone || '';
                let address = [addr.address_line || addr.address || addr.street || '', addr.ward || '', addr.district || '', addr.province || ''].filter(Boolean).join(', ');
                document.getElementById('modal-ship-name').value = addr.receiver_name || addr.name || addr.full_name || '';
                document.getElementById('modal-ship-phone').value = phone;
                document.getElementById('modal-ship-address').value = address;
                
                document.getElementById('modal-view-phone').textContent = phone || 'Trống';
                document.getElementById('modal-view-address').textContent = address || 'Trống';

                // Reset views
                const views = document.querySelectorAll('.info-view');
                const edits = document.querySelectorAll('.info-edit');
                views.forEach(v => v.style.display = 'inline');
                edits.forEach(e => e.style.display = 'none');
                document.getElementById('btn-save-info').style.display = 'none';

                document.getElementById('modal-loading').style.display = 'none';
                document.getElementById('modal-content').style.display = 'block';
                document.getElementById('modal-btn-print').href = '/admin/print_order.php?id=' + o.id;
            })
            .catch(err => {
                alert('Lỗi khi tải thông tin đơn hàng');
                closeOrderModal();
            });
    }

    function closeOrderModal() {
        document.getElementById('order-detail-modal').style.display = 'none';
    }

    function toggleEditItem(btn) {
        const tr = btn.closest('tr');
        const spans = tr.querySelectorAll('span');
        const inputs = tr.querySelectorAll('input');
        
        spans.forEach(s => s.style.display = s.style.display === 'none' ? 'inline' : 'none');
        inputs.forEach(i => i.style.display = i.style.display === 'none' ? 'inline-block' : 'none');
        
        document.getElementById('btn-save-items').style.display = 'inline-block';
    }

    function toggleEditInfo() {
        const views = document.querySelectorAll('.info-view');
        const edits = document.querySelectorAll('.info-edit');
        const btn = document.getElementById('btn-save-info');
        
        views.forEach(v => v.style.display = v.style.display === 'none' ? 'inline' : 'none');
        edits.forEach(e => e.style.display = e.style.display === 'none' ? 'inline-block' : 'none');
        btn.style.display = btn.style.display === 'none' ? 'inline-block' : 'none';
    }
    </script>
</body>
</html>
<?php

function orderAddressText(array $address): string
{
    $parts = array_filter([
        $address['name'] ?? $address['full_name'] ?? null,
        $address['phone'] ?? null,
        $address['address'] ?? $address['street'] ?? null,
        $address['ward'] ?? null,
        $address['district'] ?? null,
        $address['province'] ?? $address['city'] ?? null,
    ], fn ($value): bool => trim((string) $value) !== '');

    return $parts ? implode(', ', array_map('strval', $parts)) : 'Chưa có địa chỉ.';
}
