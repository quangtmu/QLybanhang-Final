<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_SHIPMENTS);
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
                if (!PermissionMiddleware::can($user, MODULE_SHIPMENTS, 'create')) {
                    throw new RuntimeException('Bạn không có quyền tạo vận đơn.');
                }

                $shipmentId = ShipmentModel::create($_POST, (int) $user['id'], (string) $user['user_type']);
                $proofImage = uploadShipmentProofImage();
                if ($proofImage !== null) {
                    ShipmentModel::attachProofImage($shipmentId, $proofImage, (int) $user['id'], (string) $user['user_type']);
                }
                $_SESSION['flash_success'] = 'Đã tạo vận đơn.';
                header('Location: /admin/shipments.php');
                exit;
            }

            if ($formAction === 'status') {
                if (!PermissionMiddleware::can($user, MODULE_SHIPMENTS, 'update')) {
                    throw new RuntimeException('Bạn không có quyền cập nhật vận đơn.');
                }

                ShipmentModel::updateStatus(
                    (int) ($_POST['shipment_id'] ?? 0),
                    (string) ($_POST['status'] ?? ''),
                    (string) ($_POST['note'] ?? ''),
                    (int) $user['id'],
                    (string) $user['user_type']
                );
                $proofImage = uploadShipmentProofImage();
                if ($proofImage !== null) {
                    ShipmentModel::attachProofImage((int) ($_POST['shipment_id'] ?? 0), $proofImage, (int) $user['id'], (string) $user['user_type']);
                }
                $_SESSION['flash_success'] = 'Đã cập nhật trạng thái vận đơn.';
                header('Location: /admin/shipments.php');
                exit;
            }

            throw new RuntimeException('Thao tác không hợp lệ.');
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'order_status' => $_GET['order_status'] ?? '',
    'store_id' => $_GET['store_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_dir' => $_GET['sort_dir'] ?? 'DESC',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = ShipmentModel::paginate($filters);
$shipments = $result['items'];
$pagination = $result['pagination'];
$ordersWithoutShipment = ShipmentModel::ordersWithoutShipment('', 80);
$stores = AdminOrderModel::storesForFilter();
$canCreate = PermissionMiddleware::can($user, MODULE_SHIPMENTS, 'create');
$canUpdate = PermissionMiddleware::can($user, MODULE_SHIPMENTS, 'update');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý vận đơn</title>
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
                    <h1 style="margin: 0 0 5px 0;">Quản lý vận đơn</h1>
                </div>
                <?php if ($canCreate): ?>
                    <button type="button" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; border-radius: 6px; padding: 8px 16px; font-weight: 500; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" onclick="document.getElementById('create-shipment-modal').style.display='block'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/></svg>
                        Tạo vận đơn
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($canCreate): ?>
                <div id="create-shipment-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
                    <div style="max-width: 1000px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                        <button type="button" onclick="document.getElementById('create-shipment-modal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary);">&times;</button>
                        <h2 style="margin-top: 0; margin-bottom: 20px;">Tạo vận đơn mới</h2>
                        <form method="post" class="admin-form js-validate" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="create">
                            <div class="shipment-form-grid">
                                <label>
                                    <span>Chọn đơn hàng <?= UiHelper::requiredMark() ?></span>
                                    <select name="order_id" required style="max-width: 100%; font-size: 13px;">
                                        <option value="">Chọn đơn hàng</option>
                                        <?php foreach ($ordersWithoutShipment as $order): ?>
                                            <?php 
                                                $optionText = $order['order_code'] . ' - ' . UiHelper::statusLabel((string) $order['status']) . ' - ' . $order['buyer_email'] . ' - ' . $order['store_name'];
                                                if (mb_strlen($optionText) > 65) {
                                                    $optionText = mb_substr($optionText, 0, 62) . '...';
                                                }
                                            ?>
                                            <option value="<?= (int) $order['id'] ?>">
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
                                <button type="button" class="btn-secondary" onclick="document.getElementById('create-shipment-modal').style.display='none'">Đóng</button>
                                <button type="submit" class="btn-primary" <?= !$ordersWithoutShipment ? 'disabled' : '' ?>>Lưu vận đơn</button>
                            </div>
                            <?php if (!$ordersWithoutShipment): ?>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="portal-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">Danh sách vận đơn</h2>
                <div class="muted">Tổng: <?= (int) $pagination['total'] ?> vận đơn</div>
            </div>
            <form method="get" class="filter-row filter-row-large">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm kiếm...">
                <select name="status">
                    <option value="">Tất cả vận đơn</option>
                    <?php foreach (ShipmentModel::statuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                            <?= htmlspecialchars(UiHelper::statusLabel($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="order_status">
                    <option value="">Tất cả đơn hàng</option>
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
                <input type="date" name="date_from" value="<?= htmlspecialchars((string) $filters['date_from']) ?>">
                <input type="date" name="date_to" value="<?= htmlspecialchars((string) $filters['date_to']) ?>">
                <button type="submit" title="Lọc dữ liệu">Lọc</button>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vận đơn</th>
                            <th><?= UiHelper::sortLink('created_at', 'ID', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                            <th>Đơn vị</th>
                            <th>Đơn hàng</th>
                            <th>Trạng thái đơn</th>
                            <th>Buyer</th>
                            <th>Shop</th>
                            <th>Shipper</th>
                            <th>SDT</th>
                            <th><?= UiHelper::sortLink('estimated_date', 'Dự kiến', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                            <th>Trạng thái</th>
                            <th>Timeline</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$shipments): ?>
                            <tr><td colspan="13" class="empty">Chưa có vận đơn.</td></tr>
                        <?php endif; ?>
                        <?php $modalsHtml = ''; ?>
                        <?php foreach ($shipments as $shipment): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars((string) $shipment['tracking_code']) ?></strong>
                                </td>
                                <td>#<?= (int) $shipment['id'] ?></td>
                                <td><?= htmlspecialchars((string) ($shipment['carrier_name'] ?: 'Chưa có')) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($shipment['order_code']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars(UiHelper::statusLabel((string) $shipment['order_status'])) ?></td>
                                <td><?= htmlspecialchars($shipment['buyer_email']) ?></td>
                                <td><?= htmlspecialchars((string) ($shipment['store_name'] ?: $shipment['store_email'])) ?></td>
                                <td><?= htmlspecialchars((string) ($shipment['shipper_name'] ?: 'Chưa gán')) ?></td>
                                <td><?= htmlspecialchars((string) ($shipment['shipper_phone'] ?: '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($shipment['estimated_date'] ?: '-')) ?></td>
                                <td>
                                    <span class="badge <?= htmlspecialchars(UiHelper::statusClass((string) $shipment['current_status'])) ?>"><?= htmlspecialchars(UiHelper::statusLabel((string) $shipment['current_status'])) ?></span>
                                    <?php if (!empty($shipment['proof_image_url'])): ?>
                                        <a class="shipment-proof-link" href="<?= htmlspecialchars(StorageService::publicUrl((string) $shipment['proof_image_url'])) ?>" target="_blank" rel="noopener">
                                            <img src="<?= htmlspecialchars(StorageService::publicUrl((string) $shipment['proof_image_url'])) ?>" alt="Ảnh vận đơn">
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $latestLog = $shipment['logs'][0] ?? null; ?>
                                    <?php if ($latestLog): ?>
                                        <div style="position: relative; display: inline-block; cursor: help;" onmouseenter="showTimelineTooltip(this)" onmouseleave="hideTimelineTooltip(this)">
                                            <strong><?= htmlspecialchars(UiHelper::statusLabel((string) $latestLog['status'])) ?></strong><br>
                                            <span class="muted" style="font-size: 0.85em;"><?= htmlspecialchars($latestLog['created_at']) ?></span>
                                            <?php if (count($shipment['logs']) > 1): ?>
                                                <br><span style="font-size: 0.85em; color: #3b82f6;">... (<?= count($shipment['logs']) ?> log)</span>
                                            <?php endif; ?>
                                            <div class="timeline-tooltip" style="display:none; position:absolute; right: 0; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); width: 280px; z-index: 50; border: 1px solid #dbe4f0; text-align: left; max-height: 250px; overflow-y: auto;">
                                                <h4 style="margin-top: 0; margin-bottom: 10px; font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Chi tiết timeline</h4>
                                                <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9em;">
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
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="vertical-align: middle;">
                                    <button type="button" style="padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; background: #ecfdf5; color: #0f766e; border: 1px solid transparent; border-radius: 5px; transition: all 0.2s;" onmouseover="this.style.background='#d1fae5'" onmouseout="this.style.background='#ecfdf5'" title="Xem chi tiết" onclick="document.getElementById('shipment-detail-modal-<?= (int) $shipment['id'] ?>').style.display='block'">
                                        <i class="bi bi-eye"></i> Xem chi tiết
                                    </button>
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
                                            <p><strong>Shop:</strong> <?= htmlspecialchars((string) ($shipment['store_name'] ?: $shipment['store_email'])) ?></p>
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
                                                    <button type="submit" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">Lưu cập nhật</button>
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
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
    <script>
        function showTimelineTooltip(container) {
            const tooltip = container.querySelector('.timeline-tooltip');
            tooltip.style.top = '100%';
            tooltip.style.bottom = 'auto';
            tooltip.style.marginTop = '5px';
            tooltip.style.marginBottom = '0';
            tooltip.style.display = 'block';
            
            const rect = tooltip.getBoundingClientRect();
            if (rect.bottom > window.innerHeight - 20) {
                tooltip.style.top = 'auto';
                tooltip.style.bottom = '100%';
                tooltip.style.marginTop = '0';
                tooltip.style.marginBottom = '5px';
            }
        }

        function hideTimelineTooltip(container) {
            container.querySelector('.timeline-tooltip').style.display = 'none';
        }
    </script>
</body>
</html>
<?php

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
