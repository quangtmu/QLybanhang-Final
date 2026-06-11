<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_PRODUCTS);
$errors = [];
$success = flash_success();
$csrfToken = AuthController::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            if (!PermissionMiddleware::can($user, MODULE_PRODUCTS, 'approve')) {
                throw new RuntimeException('Bạn không có quyền duyệt sản phẩm.');
            }

            $id = (int) ($_POST['id'] ?? 0);
            $formAction = (string) ($_POST['form_action'] ?? '');

            if ($formAction === 'approve') {
                ProductManagementModel::approve($id, (int) $user['id']);
                $_SESSION['flash_success'] = 'Đã duyệt sản phẩm.';
                header('Location: /admin/products.php');
                exit;
            }

            if ($formAction === 'reject') {
                ProductManagementModel::reject($id, (int) $user['id'], (string) ($_POST['reason'] ?? ''));
                $_SESSION['flash_success'] = 'Đã tu choi sản phẩm.';
                header('Location: /admin/products.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? 'pending_review',
    'store_id' => $_GET['store_id'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_dir' => $_GET['sort_dir'] ?? 'DESC',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = ProductManagementModel::paginateForAdmin($filters);
$products = $result['items'];
$pagination = $result['pagination'];
$canApprove = PermissionMiddleware::can($user, MODULE_PRODUCTS, 'approve');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duyệt sản phẩm</title>
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
                <?php foreach ($errors as $message): ?><p><?= htmlspecialchars((string) $message) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="portal-panel">
            <h1>Duyệt sản phẩm</h1>
            <form method="get" class="filter-row">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm kiếm...">
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (ProductManagementModel::statuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars(UiHelper::statusLabel($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" title="Lọc dữ liệu" class="btn-icon" style="padding: 8px 12px;"><i class="bi bi-funnel"></i></button>
            </form>
        </section>

        <section class="portal-panel">
            <h2>Danh sách sản phẩm</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr>
                        <th>Sản phẩm</th>
                        <th><?= UiHelper::sortLink('created_at', 'Ngày tạo', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                        <th>Mã</th>
                        <th>Danh mục</th>
                        <th>Biến thể</th>
                        <th>Shop</th>
                        <th>Shop ID</th>
                        <th><?= UiHelper::sortLink('base_price', 'Giá', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                        <th><?= UiHelper::sortLink('view_count', 'Lượt xem', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                        <th><?= UiHelper::sortLink('sold_count', 'Đã bán', $filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'DESC', $_GET) ?></th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr></thead>
                    <tbody>
                        <?php if (!$products): ?><tr><td colspan="12" class="empty">Không có sản phẩm nào cần duyệt.</td></tr><?php endif; ?>
                        <?php foreach ($products as $product): ?>
                            <?php $detail = ProductManagementModel::detailForAdmin((int) $product['id']); ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($product['created_at']) ?></td>
                                <td><?= htmlspecialchars($product['product_code']) ?></td>
                                <td><?= htmlspecialchars((string) $product['category_name']) ?></td>
                                <td><?= !empty($detail['variants']) ? count($detail['variants']) : 0 ?></td>
                                <td><strong><?= htmlspecialchars((string) ($product['store_name'] ?: 'Shop')) ?></strong></td>
                                <td>#<?= (int) $product['store_id'] ?></td>
                                <td><?= money((float) $product['base_price']) ?></td>
                                <td><?= (int) $product['view_count'] ?></td>
                                <td><?= (int) $product['sold_count'] ?></td>
                                <td><span class="badge <?= htmlspecialchars(UiHelper::statusClass((string) $product['status'])) ?>"><?= htmlspecialchars(UiHelper::statusLabel((string) $product['status'])) ?></span><?php if ($product['reject_reason']): ?><span style="display:block; margin-top:4px; font-size:12px; color:#ef4444;"><?= htmlspecialchars($product['reject_reason']) ?></span><?php endif; ?></td>
                                <td class="actions">
                                    <button type="button" class="btn-icon" title="Xem chi tiết" onclick="document.getElementById('modal-<?= (int)$product['id'] ?>').style.display='block'" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; color: #334155; background: #fff;"><i class="bi bi-eye"></i></button>
                                    
                                    <div id="modal-<?= (int)$product['id'] ?>" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px; text-align: left;">
                                        <div class="portal-panel" style="max-width: 800px; margin: 0 auto; position: relative;">
                                            <button type="button" onclick="document.getElementById('modal-<?= (int)$product['id'] ?>').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">&times;</button>
                                            <h2 style="margin-top: 0; padding-right: 30px;">Chi tiết sản phẩm: <?= htmlspecialchars($product['name']) ?></h2>
                                            
                                            <div style="margin-top:20px; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
                                                <?php
                                                $adminImages = [];
                                                if (!empty($detail['main_image_url'])) {
                                                    $adminImages[] = (string) $detail['main_image_url'];
                                                }
                                                foreach (($detail['images_data'] ?? []) as $imageUrl) {
                                                    if (is_string($imageUrl) && $imageUrl !== '') {
                                                        $adminImages[] = $imageUrl;
                                                    }
                                                }
                                                $adminImages = array_values(array_unique($adminImages));
                                                ?>
                                                <?php if ($adminImages): ?>
                                                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:15px;">
                                                        <?php foreach ($adminImages as $imageUrl): ?>
                                                            <a href="<?= htmlspecialchars(StorageService::publicUrl((string) $imageUrl)) ?>" target="_blank" rel="noopener" style="display:block; width:80px; height:80px; border:1px solid #cbd5e1; border-radius:8px; overflow:hidden; background:#fff;">
                                                                <img src="<?= htmlspecialchars(StorageService::publicUrl((string) $imageUrl)) ?>" alt="Ảnh sản phẩm" style="width:100%; height:100%; object-fit:cover;">
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                                    <div>
                                                        <p><strong>Mã SP:</strong> <?= htmlspecialchars($product['product_code']) ?></p>
                                                        <p><strong>Shop:</strong> <?= htmlspecialchars((string) $product['store_name']) ?> (#<?= (int) $product['store_id'] ?>)</p>
                                                        <p><strong>Giá cơ bản:</strong> <span style="color:#e11d48; font-weight:bold;"><?= money((float) $product['base_price']) ?></span></p>
                                                    </div>
                                                    <div>
                                                        <p><strong>Cân nặng:</strong> <?= htmlspecialchars((string) ($detail['weight'] ?? '0')) ?> <?= htmlspecialchars((string) ($detail['weight_unit'] ?? 'g')) ?></p>
                                                        <p><strong>Kích thước:</strong> <?= htmlspecialchars((string) ($detail['length'] ?? '0')) ?> x <?= htmlspecialchars((string) ($detail['width'] ?? '0')) ?> x <?= htmlspecialchars((string) ($detail['height'] ?? '0')) ?> cm</p>
                                                        <p><strong>Đề xuất:</strong> <?= !empty($detail['is_recommended']) ? '<span style="color:#059669; font-weight:bold;">Có</span>' : 'Không' ?></p>
                                                    </div>
                                                </div>
                                                
                                                <div class="product-review-description" style="margin-bottom: 15px;">
                                                    <strong>Mô tả:</strong>
                                                    <?php if (!empty($detail['description'])): ?>
                                                        <div class="rich-content" style="background: #fff; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 8px; max-height: 200px; overflow-y: auto;"><?= UiHelper::richTextHtml((string) $detail['description']) ?></div>
                                                    <?php else: ?>
                                                        <p class="muted">Không có mô tả</p>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($detail['variants'])): ?>
                                                    <div><strong>Biến thể:</strong>
                                                        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 8px; max-height: 150px; overflow-y: auto;">
                                                            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                                                                <thead><tr style="border-bottom: 1px solid #e2e8f0; background: #f1f5f9;"><th style="padding: 8px;">Phân loại</th><th style="padding: 8px;">Giá</th><th style="padding: 8px;">Tồn kho</th></tr></thead>
                                                                <tbody>
                                                                <?php foreach ($detail['variants'] as $v): ?>
                                                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                                                        <td style="padding: 8px;">Màu <?= htmlspecialchars((string) ($v['color'] ?? '')) ?>, Size <?= htmlspecialchars((string) ($v['size'] ?? '')) ?></td>
                                                                        <td style="padding: 8px; color: #e11d48; font-weight: bold;"><?= money((float) $v['price']) ?></td>
                                                                        <td style="padding: 8px;"><?= (int) $v['stock_quantity'] ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($canApprove && $product['status'] === PRODUCT_STATUS_PENDING_REVIEW): ?>
                                                <div style="margin-top: 20px; display: flex; gap: 20px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                                                    <form method="post" style="flex: 1;">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                                        <button type="submit" name="form_action" value="approve" style="width: 100%; background: #10b981; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; font-weight: bold; display: flex; justify-content: center; align-items: center; gap: 8px;"><i class="bi bi-check-circle"></i>Duyệt sản phẩm</button>
                                                    </form>
                                                    <form method="post" style="flex: 2; display: flex; gap: 10px;">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                                        <input type="text" name="reason" placeholder="Nhập lý do từ chối..." required style="flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
                                                        <button type="submit" name="form_action" value="reject" style="background: #ef4444; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; display: flex; justify-content: center; align-items: center; gap: 8px;"><i class="bi bi-x-circle"></i>Từ chối</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
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
<?php

function money(float $value): string
{
    return number_format($value, 0, ',', '.') . ' d';
}
