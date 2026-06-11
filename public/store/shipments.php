<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_SHIPMENTS);
$errors = [];
$success = flash_success();
$csrfToken = AuthController::csrfToken();
$storeId = StoreEmployeeModel::storeIdForActor($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            $action = (string) ($_POST['form_action'] ?? '');
            if ($action === 'create') {
                StoreOrderModel::requireOrder($user, (int) ($_POST['order_id'] ?? 0));
                $shipmentId = ShipmentModel::create($_POST, (int) $user['id'], (string) $user['user_type']);
                $proofImage = uploadShipmentProofImage();
                if ($proofImage !== null) {
                    ShipmentModel::attachProofImage($shipmentId, $proofImage, (int) $user['id'], (string) $user['user_type']);
                }
                $_SESSION['flash_success'] = 'Đã tạo vận đơn.';
            } elseif ($action === 'status') {
                requireStoreShipment((int) ($_POST['shipment_id'] ?? 0), $storeId);
                ShipmentModel::updateStatus((int) ($_POST['shipment_id'] ?? 0), (string) ($_POST['status'] ?? ''), (string) ($_POST['note'] ?? ''), (int) $user['id'], (string) $user['user_type']);
                $proofImage = uploadShipmentProofImage();
                if ($proofImage !== null) {
                    ShipmentModel::attachProofImage((int) ($_POST['shipment_id'] ?? 0), $proofImage, (int) $user['id'], (string) $user['user_type']);
                }
                $_SESSION['flash_success'] = 'Đã cập nhật trạng thái vận đơn.';
            } else {
                throw new RuntimeException('Thao tác không hợp lệ.');
            }
            header('Location: /store/shipments.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'order_status' => $_GET['order_status'] ?? '',
    'store_id' => $storeId,
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = ShipmentModel::paginate($filters);
$shipments = $result['items'];
$pagination = $result['pagination'];
$ordersWithoutShipment = array_values(array_filter(ShipmentModel::ordersWithoutShipment('', 100), fn (array $order): bool => (int) ($order['store_id'] ?? 0) === $storeId));
$prefillOrderId = (int) ($_GET['order_id'] ?? 0);
$canCreate = PermissionMiddleware::can($user, MODULE_SHIPMENTS, 'create');
$canUpdate = PermissionMiddleware::can($user, MODULE_SHIPMENTS, 'update');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vận đơn shop</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
    <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260609-12">
</head>
<body class="portal-page store-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_store_nav.php"; ?>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $message): ?><p><?= htmlspecialchars((string) $message) ?></p><?php endforeach; ?></div><?php endif; ?>

        <section class="portal-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h1 style="margin: 0 0 5px 0;">Điều phối vận đơn</h1>
                    <p class="muted" style="margin: 0;">Luồng vận hành: tạo vận đơn, chờ lấy hàng, đang vận chuyển, đang giao cho khách, giao thành công.</p>
                </div>
                <?php if ($canCreate): ?>
                    <button type="button" style="background: #0f766e; color: #ffffff; border: 1px solid #0f766e; border-radius: 6px; padding: 8px 16px; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" onmouseover="this.style.background='#115e59'" onmouseout="this.style.background='#0f766e'" onclick="document.getElementById('create-shipment-modal').style.display='block'">
                        <i class="bi bi-plus-lg"></i>
                        Tạo vận đơn
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($canCreate): ?>
                <div id="create-shipment-modal" class="modal" style="display: <?= $prefillOrderId ? 'block' : 'none' ?>; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
                    <div style="max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                        <button type="button" onclick="closeCreateModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary);">&times;</button>
                        <h2 style="margin-top: 0; margin-bottom: 20px;">Tạo vận đơn mới</h2>
                        <form method="post" class="admin-form js-validate" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="create">
                            <div class="shipment-form-grid">
                                <label>
                                    <span>Chọn đơn hàng <?= UiHelper::requiredMark() ?></span>
                                    <select name="order_id" required style="max-width: 100%; font-size: 13px;">
                                        <option value="">Chọn đơn</option>
                                        <?php foreach ($ordersWithoutShipment as $order): ?>
                                            <?php 
                                                $optionText = $order['order_code'] . ' - ' . UiHelper::statusLabel((string) $order['status']) . ' - ' . $order['buyer_email'];
                                                if (mb_strlen($optionText) > 65) {
                                                    $optionText = mb_substr($optionText, 0, 62) . '...';
                                                }
                                            ?>
                                            <option value="<?= (int) $order['id'] ?>" <?= $prefillOrderId === (int) $order['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($optionText) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                
                                <label>
                                    <span>Đơn vị vận chuyển</span>
                                    <input type="text" name="carrier_name" placeholder="VD: GHN, GHTK">
                                </label>
                                <label>
                                    <span>Ngày giao dự kiến</span>
                                    <input type="date" name="estimated_date">
                                </label>
                                <label>
                                    <span>Tên shipper</span>
                                    <input type="text" name="shipper_name">
                                </label>
                                <label>
                                    <span>SDT shipper</span>
                                    <input type="text" name="shipper_phone">
                                </label>
                                <label style="grid-column: 1 / -1;">
                                    <span>Ảnh vận đơn / biên nhận</span>
                                    <input type="file" name="proof_image" accept="image/jpeg,image/png,image/webp,image/gif" data-preview-target="shipment-proof-preview">
                                    <span class="field-note">Upload ảnh (tối đa 5MB)</span>
                                </label>
                            </div>
                            <div class="image-preview image-preview-wide" id="shipment-proof-preview" style="margin-bottom: 20px; padding: 20px; text-align: center; border: 1px dashed #cfd7df; border-radius: 6px; color: #596270;"><span>Chưa chọn ảnh</span></div>
                            <div class="actions" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                                <button type="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px;" onclick="closeCreateModal()">Đóng</button>
                                <button type="submit" style="background: #0f766e; color: #ffffff; border: 1px solid #0f766e; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px;" <?= !$ordersWithoutShipment ? 'disabled' : '' ?>>Lưu vận đơn</button>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                    function closeCreateModal() {
                        const urlParams = new URLSearchParams(window.location.search);
                        if (urlParams.has('order_id')) {
                            window.location.href = '/store/orders.php';
                        } else {
                            document.getElementById('create-shipment-modal').style.display = 'none';
                        }
                    }
                </script>
            <?php endif; ?>
        </section>

        <section class="portal-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">Danh sách vận đơn</h2>
                <div class="muted">Tổng: <?= (int) $pagination['total'] ?> vận đơn</div>
            </div>
            <form method="get" class="filter-row">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Mã vận đơn, mã đơn, buyer">
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (ShipmentModel::statuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars(UiHelper::statusLabel($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="sort_by">
                    <option value="created_at" <?= ($filters['sort_by'] ?? '') === 'created_at' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="estimated_date" <?= ($filters['sort_by'] ?? '') === 'estimated_date' ? 'selected' : '' ?>>Dự kiến giao</option>
                </select>
                <select name="sort_dir">
                    <option value="DESC" <?= ($filters['sort_dir'] ?? 'DESC') === 'DESC' ? 'selected' : '' ?>>Giảm dần</option>
                    <option value="ASC" <?= ($filters['sort_dir'] ?? '') === 'ASC' ? 'selected' : '' ?>>Tăng dần</option>
                </select>
                <button type="submit">Lọc</button>
            </form>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Mã vận đơn</th><th>ĐVVC / Shipper</th><th>Đơn hàng</th><th>Buyer</th><th>Trạng thái VĐ</th><th>Trạng thái đơn</th><th>Dự kiến giao</th><th>Timeline</th><th>Thao tác</th></tr></thead>
                    <tbody>
                        <?php if (!$shipments): ?><tr><td colspan="9" class="empty">Chưa có vận đơn.</td></tr><?php endif; ?>
                        <?php $modalsHtml = ''; ?>
                        <?php foreach ($shipments as $shipment): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars((string) $shipment['tracking_code']) ?></strong>
                                    <span style="display:block; font-size:11px; color:#94a3b8;">ID: <?= (int) $shipment['id'] ?></span>
                                </td>
                                <td>
                                    <span style="display:block; font-weight:600;"><?= htmlspecialchars((string) ($shipment['carrier_name'] ?: '—')) ?></span>
                                    <?php if (!empty($shipment['shipper_name'])): ?>
                                        <span style="display:block; font-size:12px; color:#64748b;"><?= htmlspecialchars($shipment['shipper_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($shipment['shipper_phone'])): ?>
                                        <span style="display:block; font-size:12px; color:#64748b;"><?= htmlspecialchars($shipment['shipper_phone']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($shipment['order_code']) ?></strong></td>
                                <td><span style="font-size:13px;"><?= htmlspecialchars($shipment['buyer_email']) ?></span></td>
                                <td>
                                    <span class="badge <?= htmlspecialchars(UiHelper::statusClass((string) $shipment['current_status'])) ?>"><?= htmlspecialchars(UiHelper::statusLabel((string) $shipment['current_status'])) ?></span>
                                    <?php if (!empty($shipment['proof_image_url'])): ?>
                                        <a class="shipment-proof-link" href="<?= htmlspecialchars(StorageService::publicUrl((string) $shipment['proof_image_url'])) ?>" target="_blank" rel="noopener" style="display:inline-block; margin-top:4px;"><img src="<?= htmlspecialchars(StorageService::publicUrl((string) $shipment['proof_image_url'])) ?>" alt="Ảnh VĐ" style="width:32px; height:32px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0;"></a>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= htmlspecialchars(UiHelper::statusClass((string) $shipment['order_status'])) ?>"><?= htmlspecialchars(UiHelper::statusLabel((string) $shipment['order_status'])) ?></span></td>
                                <td><span style="font-size:13px; white-space:nowrap;"><?= htmlspecialchars((string) ($shipment['estimated_date'] ?: '—')) ?></span></td>
                                <td>
                                    <?php $latestLog = $shipment['logs'][0] ?? null; ?>
                                    <?php if ($latestLog): ?>
                                        <div style="position: relative; display: inline-block; cursor: help;" onmouseenter="this.querySelector('.timeline-tooltip').style.display='block'" onmouseleave="this.querySelector('.timeline-tooltip').style.display='none'">
                                            <strong style="font-size:13px;"><?= htmlspecialchars(UiHelper::statusLabel((string) $latestLog['status'])) ?></strong><br>
                                            <span class="muted" style="font-size: 12px;"><?= htmlspecialchars($latestLog['created_at']) ?></span>
                                            <?php if (count($shipment['logs']) > 1): ?>
                                                <br><span style="font-size: 11px; color: #0f766e;">+<?= count($shipment['logs']) - 1 ?> bước</span>
                                            <?php endif; ?>
                                            <div class="timeline-tooltip" style="display:none; position:absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 10px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); width: 280px; z-index: 10; border: 1px solid #dbe4f0; text-align: left;">
                                                <h4 style="margin-top: 0; margin-bottom: 10px; font-size: 13px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Lịch sử vận đơn</h4>
                                                <ul style="list-style: none; padding: 0; margin: 0; font-size: 12px;">
                                                    <?php foreach ($shipment['logs'] as $log): ?>
                                                        <li style="margin-bottom: 8px; border-bottom: 1px dashed #eee; padding-bottom: 8px;">
                                                            <strong><?= htmlspecialchars(UiHelper::statusLabel((string) $log['status'])) ?></strong><br>
                                                            <span class="muted"><?= htmlspecialchars($log['created_at']) ?></span>
                                                            <?php if ($log['note']): ?><br><span>Ghi chú: <?= htmlspecialchars($log['note']) ?></span><?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="vertical-align: middle; display: flex; gap: 6px;">
                                    <button type="button" style="padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; background: #ecfdf5; color: #0f766e; border: 1px solid transparent; border-radius: 5px; transition: all 0.2s;" onmouseover="this.style.background='#d1fae5'" onmouseout="this.style.background='#ecfdf5'" title="Xem chi tiết" onclick="document.getElementById('shipment-detail-modal-<?= (int) $shipment['id'] ?>').style.display='block'">
                                        <i class="bi bi-eye"></i> Chi tiết
                                    </button>
                                    <a href="/store/print_order.php?id=<?= (int) $shipment['order_id'] ?>" target="_blank" style="padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; background: #f0fdf4; color: #166534; border: 1px solid transparent; border-radius: 5px; transition: all 0.2s; text-decoration: none;" onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'" title="In đơn hàng">
                                        <i class="bi bi-printer"></i> In đơn
                                    </a>
                                </td>
                            </tr>
                            <?php ob_start(); ?>
                            <div id="shipment-detail-modal-<?= (int) $shipment['id'] ?>" class="modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
                                <div style="max-width: 700px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                                    <button type="button" onclick="document.getElementById('shipment-detail-modal-<?= (int) $shipment['id'] ?>').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary);">&times;</button>
                                    <h3 style="margin-top: 0; margin-bottom: 20px;">Chi tiết vận đơn #<?= (int) $shipment['id'] ?></h3>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                        <div style="border: 1px solid #dbe4f0; border-radius: 8px; padding: 15px;">
                                            <h4 style="margin-top: 0;">Thông tin chung</h4>
                                            <p><strong>Mã VĐ:</strong> <?= htmlspecialchars((string) $shipment['tracking_code']) ?></p>
                                            <p><strong>ĐVVC:</strong> <?= htmlspecialchars((string) ($shipment['carrier_name'] ?: 'Chưa có')) ?></p>
                                            <p><strong>Mã đơn hàng:</strong> <?= htmlspecialchars($shipment['order_code']) ?></p>
                                            <p><strong>Trạng thái HT:</strong> <span class="badge <?= htmlspecialchars(UiHelper::statusClass((string) $shipment['current_status'])) ?>"><?= htmlspecialchars(UiHelper::statusLabel((string) $shipment['current_status'])) ?></span></p>
                                            <p><strong>Dự kiến giao:</strong> <?= htmlspecialchars((string) ($shipment['estimated_date'] ?: '-')) ?></p>
                                        </div>
                                        <div style="border: 1px solid #dbe4f0; border-radius: 8px; padding: 15px;">
                                            <h4 style="margin-top: 0;">Giao hàng</h4>
                                            <p><strong>Shipper:</strong> <?= htmlspecialchars((string) ($shipment['shipper_name'] ?: 'Chưa có')) ?></p>
                                            <p><strong>SĐT:</strong> <?= htmlspecialchars((string) ($shipment['shipper_phone'] ?: '-')) ?></p>
                                            <p><strong>Khách hàng:</strong> <?= htmlspecialchars($shipment['buyer_email']) ?></p>
                                        </div>
                                    </div>

                                    <?php if ($canUpdate && !in_array($shipment['current_status'], [SHIPMENT_STATUS_DELIVERED, SHIPMENT_STATUS_CANCELLED], true)): ?>
                                        <div style="border: 1px solid #dbe4f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; background: #f8fafc;">
                                            <h4 style="margin-top: 0;">Cập nhật trạng thái</h4>
                                            <form method="post" class="admin-form js-validate" enctype="multipart/form-data" novalidate style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="form_action" value="status">
                                                <input type="hidden" name="shipment_id" value="<?= (int) $shipment['id'] ?>">
                                                
                                                <div style="grid-column: 1 / -1; display: grid; gap: 6px;">
                                                    <label style="display: block; margin: 0;">Trạng thái mới</label>
                                                    <select name="status" style="width: 100%; padding: 8px; border: 1px solid #dbe4f0; border-radius: 4px;">
                                                        <?php foreach (ShipmentModel::statuses() as $status): ?>
                                                            <option value="<?= htmlspecialchars($status) ?>" <?= $shipment['current_status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars(UiHelper::statusLabel($status)) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div style="grid-column: 1 / -1; display: grid; gap: 6px;">
                                                    <label style="display: block; margin: 0;">Ghi chú</label>
                                                    <textarea name="note" rows="2" style="width: 100%; padding: 8px; border: 1px solid #dbe4f0; border-radius: 4px;" placeholder="Ghi chú vận hành"></textarea>
                                                </div>
                                                <div style="grid-column: 1 / -1; display: grid; gap: 6px;">
                                                    <label style="display: block; margin: 0;">Ảnh xác nhận (nếu có)</label>
                                                    <input type="file" name="proof_image" accept="image/jpeg,image/png,image/webp,image/gif">
                                                </div>
                                                <div style="grid-column: 1 / -1; text-align: right; margin-top: 10px;">
                                                    <button type="submit" style="background: #0f766e; color: #ffffff; border: 1px solid #0f766e; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px;">Lưu cập nhật</button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <h4 style="margin-top: 0;">Lịch sử Timeline</h4>
                                    <ul style="list-style: none; padding: 0; margin: 0; max-height: 200px; overflow-y: auto; border: 1px solid #dbe4f0; border-radius: 8px; padding: 10px;">
                                        <?php foreach ($shipment['logs'] as $log): ?>
                                            <li style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                                                <strong><?= htmlspecialchars(UiHelper::statusLabel((string) $log['status'])) ?></strong> - <span class="muted"><?= htmlspecialchars($log['created_at']) ?></span>
                                                <?php if ($log['note']): ?><br><span style="color: #64748b;">Ghi chú: <?= htmlspecialchars($log['note']) ?></span><?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <?php $modalsHtml .= ob_get_clean(); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
        </section>
        
        <?= $modalsHtml ?? '' ?>
    </main>
    <script src="/assets/js/global.js"></script>
</body>
</html>
<?php

function requireStoreShipment(int $shipmentId, int $storeId): array
{
    $shipment = ShipmentModel::detail($shipmentId);
    if (!$shipment || (int) ($shipment['store_id'] ?? 0) !== $storeId) {
        throw new RuntimeException('Không tìm thấy vận đơn của shop.');
    }
    return $shipment;
}

function uploadShipmentProofImage(): ?string
{
    if (!isset($_FILES['proof_image']) || ($_FILES['proof_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $stored = StorageService::storeUploadedFile($_FILES['proof_image'], 'shipments', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ], 5 * 1024 * 1024, 'shipment');

    return (string) $stored['url'];
}
