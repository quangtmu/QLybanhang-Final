<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_ORDERS); // Using orders module as proxy for general sales management
$success = flash_success();
$errors = [];
$csrfToken = AuthController::csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'create') {
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'start_date' => $_POST['start_date'] ?? '',
                    'end_date' => $_POST['end_date'] ?? '',
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                FlashSaleModel::create($data);
                $_SESSION['flash_success'] = 'Đã tạo Flash Sale thành công.';
                header('Location: /admin/flash-sales.php');
                exit;
            } elseif ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'start_date' => $_POST['start_date'] ?? '',
                    'end_date' => $_POST['end_date'] ?? '',
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                FlashSaleModel::update($id, $data);
                $_SESSION['flash_success'] = 'Đã cập nhật Flash Sale.';
                header('Location: /admin/flash-sales.php');
                exit;
            } elseif ($action === 'add_product') {
                $fsId = (int)($_POST['flash_sale_id'] ?? 0);
                $productId = (int)($_POST['product_id'] ?? 0);
                $discountPrice = (float)($_POST['discount_price'] ?? 0);
                $stockQuantity = (int)($_POST['stock_quantity'] ?? 0);
                FlashSaleModel::addProduct($fsId, $productId, $discountPrice, $stockQuantity);
                $_SESSION['flash_success'] = 'Đã thêm/cập nhật sản phẩm vào Flash Sale.';
                header('Location: /admin/flash-sales.php?view=' . $fsId);
                exit;
            } elseif ($action === 'add_product_bulk') {
                $fsId = (int)($_POST['flash_sale_id'] ?? 0);
                $type = $_POST['target_type'] ?? 'product';
                $targetId = (int)($_POST['target_id'] ?? 0);
                $discountPercent = (float)($_POST['discount_percent'] ?? 0);
                $stockQuantity = (int)($_POST['stock_quantity'] ?? 0);
                $count = FlashSaleModel::addBulkProducts($fsId, $type, $targetId, $discountPercent, $stockQuantity);
                $_SESSION['flash_success'] = "Đã thêm thành công $count sản phẩm vào Flash Sale.";
                header('Location: /admin/flash-sales.php?view=' . $fsId);
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$viewId = (int)($_GET['view'] ?? 0);
$allFlashSales = FlashSaleModel::getAll();

$db = getDB();
$allCategories = $db->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$allStores = $db->query('SELECT user_id, store_name FROM store_profiles ORDER BY store_name ASC')->fetchAll();
$allProducts = $db->query("SELECT id, name FROM products WHERE status = 'approved' AND deleted_at IS NULL ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Flash Sale</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611">
</head>
<body class="portal-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . '/_admin_nav.php'; ?>
        
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-error"><?php foreach ($errors as $e) echo "<p>" . htmlspecialchars((string) $e) . "</p>"; ?></div>
        <?php endif; ?>

        <?php if ($viewId > 0): ?>
            <?php 
            $fs = FlashSaleModel::findById($viewId); 
            $fsProducts = FlashSaleModel::getProducts($viewId);
            if (!$fs) die('Flash Sale không tồn tại.');
            ?>
            <section class="portal-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h1 style="margin:0;">Sản phẩm trong Flash Sale: <?= htmlspecialchars((string) $fs['name']) ?></h1>
                        <p class="muted">Thời gian: <?= htmlspecialchars($fs['start_date']) ?> - <?= htmlspecialchars($fs['end_date']) ?></p>
                    </div>
                    <a href="/admin/flash-sales.php" style="color: #0f766e; text-decoration: none; font-weight: bold;">&larr; Quay lại danh sách</a>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                    <!-- Add Product Form -->
                    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <h2 style="margin-top:0; font-size: 16px;">Thêm sản phẩm hàng loạt</h2>
                        <form method="post" class="admin-form" id="fs-add-product-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="add_product_bulk">
                            <input type="hidden" name="flash_sale_id" value="<?= $viewId ?>">
                            
                            <label><span>Loại áp dụng</span>
                                <select name="target_type" id="target_type_select" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;" onchange="toggleTargetSelects()">
                                    <option value="product">Sản phẩm cụ thể</option>
                                    <option value="shop">Toàn bộ Cửa hàng</option>
                                    <option value="category">Toàn bộ Danh mục</option>
                                </select>
                            </label>
                            
                            <label id="wrap_product"><span>Chọn Sản phẩm</span>
                                <select name="target_id_product" id="select_product" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                    <?php foreach ($allProducts as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars((string)$p['name']) ?> (ID: <?= (int)$p['id'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            
                            <label id="wrap_shop" style="display: none;"><span>Chọn Cửa hàng</span>
                                <select name="target_id_shop" id="select_shop" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;" disabled>
                                    <?php foreach ($allStores as $s): ?>
                                        <option value="<?= (int)$s['user_id'] ?>"><?= htmlspecialchars((string)$s['store_name']) ?> (ID: <?= (int)$s['user_id'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label id="wrap_category" style="display: none;"><span>Chọn Danh mục</span>
                                <select name="target_id_category" id="select_category" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;" disabled>
                                    <?php foreach ($allCategories as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['name']) ?> (ID: <?= (int)$c['id'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            
                            <!-- Hidden input to hold the final target_id -->
                            <input type="hidden" name="target_id" id="final_target_id" value="">
                            
                            <script>
                                function toggleTargetSelects() {
                                    const type = document.getElementById('target_type_select').value;
                                    document.getElementById('wrap_product').style.display = type === 'product' ? 'block' : 'none';
                                    document.getElementById('wrap_shop').style.display = type === 'shop' ? 'block' : 'none';
                                    document.getElementById('wrap_category').style.display = type === 'category' ? 'block' : 'none';
                                    
                                    document.getElementById('select_product').disabled = type !== 'product';
                                    document.getElementById('select_shop').disabled = type !== 'shop';
                                    document.getElementById('select_category').disabled = type !== 'category';
                                }
                                
                                document.getElementById('fs-add-product-form').addEventListener('submit', function() {
                                    const type = document.getElementById('target_type_select').value;
                                    const val = document.getElementById('select_' + type).value;
                                    document.getElementById('final_target_id').value = val;
                                });
                            </script>
                            <label><span>Mức giảm giá (%)</span>
                                <input type="number" name="discount_percent" required min="1" max="99" placeholder="Ví dụ: 10, 20, 50" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </label>
                            <label><span>Số lượng bán (tồn kho áp dụng)</span>
                                <input type="number" name="stock_quantity" required min="1" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </label>
                            <button type="submit" style="background: #0f766e; color: #fff; padding: 10px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold;">Thêm vào Flash Sale</button>
                        </form>
                    </div>

                    <!-- Products Table -->
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Sản phẩm</th>
                                    <th>Cửa hàng</th>
                                    <th>Giá gốc</th>
                                    <th>Giá Flash Sale</th>
                                    <th>Số lượng</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$fsProducts): ?><tr><td colspan="6" class="empty">Chưa có sản phẩm nào.</td></tr><?php endif; ?>
                                <?php foreach ($fsProducts as $p): ?>
                                    <tr>
                                        <td><?= (int) $p['product_id'] ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <img src="<?= htmlspecialchars((string)$p['main_image_url']) ?>" style="width: 40px; height: 40px; border-radius: 4px; object-fit: cover;">
                                                <strong><?= htmlspecialchars((string)$p['product_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars((string)$p['store_name']) ?></td>
                                        <td><del style="color:#64748b;"><?= number_format((float) $p['base_price']) ?>đ</del></td>
                                        <td style="color:#dc2626; font-weight:bold;"><?= number_format((float) $p['discount_price']) ?>đ</td>
                                        <td><?= (int) $p['stock_quantity'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <!-- Flash Sale List -->
            <section class="portal-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h1 style="margin:0;">Quản lý Flash Sale</h1>
                    <button onclick="document.getElementById('modal-create').style.display='flex'" style="padding: 8px 16px; background: #0f766e; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">+ Tạo Flash Sale</button>
                </div>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tên chiến dịch</th>
                                <th>Bắt đầu</th>
                                <th>Kết thúc</th>
                                <th>Trạng thái</th>
                                <th>Sản phẩm</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$allFlashSales): ?><tr><td colspan="6" class="empty">Chưa có chiến dịch nào.</td></tr><?php endif; ?>
                            <?php foreach ($allFlashSales as $fs): ?>
                                <?php 
                                $status = (int)$fs['is_active'] ? '<span class="badge" style="background:#10b981; color:#fff;">Đang bật</span>' : '<span class="badge" style="background:#64748b; color:#fff;">Đã tắt</span>';
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string) $fs['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($fs['start_date']) ?></td>
                                    <td><?= htmlspecialchars($fs['end_date']) ?></td>
                                    <td><?= $status ?></td>
                                    <td><a href="/admin/flash-sales.php?view=<?= (int)$fs['id'] ?>" style="color: #0ea5e9; font-weight: bold; text-decoration: underline;">Quản lý sản phẩm</a></td>
                                    <td>
                                        <button onclick="editFs(<?= htmlspecialchars(json_encode($fs)) ?>)" style="background:none; border:none; color:#0f766e; text-decoration:underline; cursor:pointer;">Sửa</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Create / Edit Modal -->
            <div id="modal-create" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; padding: 20px;">
                <div class="portal-panel" style="width: 100%; max-width: 500px; margin: 0; max-height: 90vh; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 15px;">
                        <h2 style="margin: 0; font-size: 18px;" id="modal-title">Tạo chiến dịch Flash Sale</h2>
                        <button onclick="document.getElementById('modal-create').style.display='none'" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                    </div>
                    
                    <form method="post" class="admin-form" id="fs-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" id="form-action" value="create">
                        <input type="hidden" name="id" id="form-id" value="0">
                        
                        <label><span>Tên chiến dịch <span style="color: red">*</span></span>
                            <input type="text" name="name" id="form-name" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </label>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <label style="margin: 0;"><span>Từ ngày <span style="color: red">*</span></span>
                                <input type="datetime-local" name="start_date" id="form-start" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </label>
                            <label style="margin: 0;"><span>Đến ngày <span style="color: red">*</span></span>
                                <input type="datetime-local" name="end_date" id="form-end" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                            </label>
                        </div>

                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 15px;">
                            <input type="checkbox" name="is_active" id="form-active" value="1" checked> Kích hoạt chiến dịch
                        </label>

                        <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                            <button type="button" onclick="document.getElementById('modal-create').style.display='none'" style="padding: 8px 16px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">Hủy</button>
                            <button type="submit" style="padding: 8px 16px; background: #0f766e; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Lưu chiến dịch</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function editFs(fs) {
                document.getElementById('modal-title').textContent = 'Sửa chiến dịch';
                document.getElementById('form-action').value = 'update';
                document.getElementById('form-id').value = fs.id;
                document.getElementById('form-name').value = fs.name;
                document.getElementById('form-start').value = fs.start_date.replace(' ', 'T');
                document.getElementById('form-end').value = fs.end_date.replace(' ', 'T');
                document.getElementById('form-active').checked = parseInt(fs.is_active) === 1;
                document.getElementById('modal-create').style.display = 'flex';
            }
            </script>
        <?php endif; ?>
    </main>
</body>
</html>
