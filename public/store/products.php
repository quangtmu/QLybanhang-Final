<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_PRODUCTS);
$fieldErrors = [];
$generalErrors = [];
$success = flash_success();
$csrfToken = AuthController::csrfToken();
$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? ProductManagementModel::detailForStore($user, $editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $generalErrors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            $formAction = (string) ($_POST['form_action'] ?? '');
            $id = (int) ($_POST['id'] ?? 0);

            if ($formAction === 'create') {
                requireStoreProductPermission($user, 'create');
                ProductManagementModel::createForStore($user, productPayloadFromPost($_POST));
                $_SESSION['flash_success'] = (empty($_POST['save_draft']) && !empty($_POST['submit_for_review'])) ? 'Đã tạo và gửi sản phẩm cho admin duyệt.' : 'Đã tạo bản nháp sản phẩm.';
                header('Location: /store/products.php');
                exit;
            }

            if ($formAction === 'update') {
                requireStoreProductPermission($user, 'update');
                ProductManagementModel::updateForStore($user, $id, productPayloadFromPost($_POST));
                $_SESSION['flash_success'] = (empty($_POST['save_draft']) && !empty($_POST['submit_for_review'])) ? 'Đã cập nhật và gửi sản phẩm cho admin duyệt.' : 'Đã cập nhật bản nháp sản phẩm.';
                header('Location: /store/products.php');
                exit;
            }

            if ($formAction === 'submit') {
                requireStoreProductPermission($user, 'update');
                ProductManagementModel::submitForReview($user, $id);
                $_SESSION['flash_success'] = 'Đã gửi sản phẩm cho admin duyệt.';
                header('Location: /store/products.php');
                exit;
            }

            if ($formAction === 'archive') {
                requireStoreProductPermission($user, 'update');
                ProductManagementModel::archiveForStore($user, $id);
                $_SESSION['flash_success'] = 'Đã lưu trữ sản phẩm.';
                header('Location: /store/products.php');
                exit;
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $decoded = json_decode($msg, true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                $fieldErrors = $decoded;
            } else {
                $generalErrors[] = APP_DEBUG ? $msg : 'Lỗi hệ thống.';
            }
        }
    }
}

$formProduct = $editing ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($fieldErrors) || !empty($generalErrors))) {
    $formProduct = array_merge($formProduct, $_POST);
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'created_at',
    'sort_dir' => $_GET['sort_dir'] ?? 'DESC',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = ProductManagementModel::paginateForStore($user, $filters);
$products = $result['items'];
$pagination = $result['pagination'];
$categories = AdminCatalogModel::categories(['is_active' => 1]);
$tags = AdminCatalogModel::tags(['is_active' => 1]);
$canCreate = PermissionMiddleware::can($user, MODULE_PRODUCTS, 'create');
$canUpdate = PermissionMiddleware::can($user, MODULE_PRODUCTS, 'update');

// Stats Query
$db = getDB();
$storeId = (int)$user['id'];
$stats = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status IN ('draft', 'archived', 'rejected') THEN 1 ELSE 0 END) as draft
    FROM products WHERE store_id = {$storeId}")->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản phẩm shop</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
    <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260611-17">
    <link rel="stylesheet" href="/assets/css/store-orders.css?v=20260611-17">
</head>
<body class="portal-page store-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_store_nav.php"; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($generalErrors): ?>
            <div class="alert alert-error">
                <?php foreach ($generalErrors as $message): ?><p><?= htmlspecialchars((string) $message) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- TOP PANEL: Page Title & Primary Action -->
        <section class="portal-panel">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin: 0 0 5px 0; color: #0f172a;">Quản lý sản phẩm</h1>
                    <p class="muted" style="margin: 0;">Luồng vận hành: thêm sản phẩm mới, cập nhật giá/tồn kho, duyệt, lưu trữ.</p>
                </div>
                <?php if ($canCreate): ?>
                    <button id="btn-add-product" style="background: #0f766e; color: #ffffff; border: 1px solid #0f766e; border-radius: 6px; padding: 8px 16px; font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;" onmouseover="this.style.background='#115e59'" onmouseout="this.style.background='#0f766e'">
                        <i class="bi bi-plus-lg"></i> Thêm sản phẩm
                    </button>
                <?php endif; ?>
            </div>
        </section>

        <!-- BOTTOM PANEL: Stats, Filters & Grid -->
        <section class="portal-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0; color: #0f172a;">Danh sách sản phẩm</h2>
                <div class="muted">Tổng: <?= number_format((int)($stats['total'] ?? 0)) ?> sản phẩm</div>
            </div>

            <!-- Stats Cards -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px;">
                <div style="background: #f8fafc; padding: 12px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="color: #64748b; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Tổng SP</div>
                    <div style="font-size: 20px; font-weight: 700; color: #0f172a;"><?= number_format((int)($stats['total'] ?? 0)) ?></div>
                </div>
                <div style="background: #f0fdf4; padding: 12px 14px; border-radius: 8px; border: 1px solid #dcfce3;">
                    <div style="color: #059669; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Đang bán</div>
                    <div style="font-size: 20px; font-weight: 700; color: #0f172a;"><?= number_format((int)($stats['active'] ?? 0)) ?></div>
                </div>
                <div style="background: #fffbeb; padding: 12px 14px; border-radius: 8px; border: 1px solid #fef08a;">
                    <div style="color: #d97706; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Chờ duyệt</div>
                    <div style="font-size: 20px; font-weight: 700; color: #0f172a;"><?= number_format((int)($stats['pending'] ?? 0)) ?></div>
                </div>
                <div style="background: #fef2f2; padding: 12px 14px; border-radius: 8px; border: 1px solid #fecaca;">
                    <div style="color: #dc2626; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-bottom: 2px;">Lưu trữ / Nháp</div>
                    <div style="font-size: 20px; font-weight: 700; color: #0f172a;"><?= number_format((int)($stats['draft'] ?? 0)) ?></div>
                </div>
            </div>

            <!-- Search & Filters Bar -->
            <form method="get" class="filter-row" style="margin-bottom: 20px;">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm kiếm theo tên hoặc mã sản phẩm...">
                
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (ProductManagementModel::statuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars(UiHelper::statusLabel($status)) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="sort_by">
                    <option value="created_at" <?= ($filters['sort_by'] ?? '') === 'created_at' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="base_price" <?= ($filters['sort_by'] ?? '') === 'base_price' ? 'selected' : '' ?>>Giá bán</option>
                    <option value="view_count" <?= ($filters['sort_by'] ?? '') === 'view_count' ? 'selected' : '' ?>>Lượt xem</option>
                    <option value="sold_count" <?= ($filters['sort_by'] ?? '') === 'sold_count' ? 'selected' : '' ?>>Đã bán</option>
                </select>
                
                <select name="sort_dir">
                    <option value="DESC" <?= ($filters['sort_dir'] ?? 'DESC') === 'DESC' ? 'selected' : '' ?>>Giảm dần</option>
                    <option value="ASC" <?= ($filters['sort_dir'] ?? '') === 'ASC' ? 'selected' : '' ?>>Tăng dần</option>
                </select>

                <button type="submit">Lọc</button>
            </form>

        <?php if (($editing && $canUpdate) || (!$editing && $canCreate)): ?>
            <?php $modalOpen = $editing || (!empty($fieldErrors) || !empty($generalErrors)); ?>
            <div id="product-modal" style="<?= $modalOpen ? 'display:block;' : 'display:none;' ?> position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
                <section class="portal-panel" style="max-width: 1700px; width: 95%; margin: 0 auto; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px; padding-bottom: 10px;">
                        <h2 style="margin: 0;"><?= $editing ? 'Sửa sản phẩm' : 'Tạo sản phẩm' ?></h2>
                        <button type="button" onclick="document.getElementById('product-modal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer; color:#64748b;">&times;</button>
                    </div>
                    <?php if (!$categories): ?>
                        <div class="alert alert-error">Chưa có danh mục đang bật. Cần admin tạo danh mục trước khi shop tạo sản phẩm.</div>
                    <?php else: ?>
                        <?php 
                        $step2Errors = ['base_price', 'main_image', 'gallery_images', 'variants', 'discount_price', 'weight', 'volume', 'length', 'width', 'height'];
                        $startStep = 1;
                        if (!empty($fieldErrors)) {
                            foreach ($step2Errors as $err) {
                                if (isset($fieldErrors[$err])) {
                                    $startStep = 2;
                                    break;
                                }
                            }
                        }
                        ?>
                        <script>
                            window.ALL_CATEGORIES = <?= json_encode($categories) ?>;
                            window.START_STEP = <?= $startStep ?>;
                            window.ALL_TAGS = <?= json_encode($tags) ?>;
                            window.SELECTED_TAG_IDS = <?= json_encode(array_map('intval', array_column($formProduct['tags'] ?? [], 'id'))) ?>;
                        </script>
                        <form id="product-form" method="post" class="admin-form js-validate" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="<?= $editing ? 'update' : 'create' ?>">
                            <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
                            <input type="hidden" name="existing_main_image_url" value="<?= htmlspecialchars((string) ($formProduct['main_image_url'] ?? '')) ?>">
                            <input type="hidden" name="existing_images" value="<?= htmlspecialchars((string) ($formProduct['images'] ?? '')) ?>">
                            
                            <!-- Stepper Header -->
                            <div class="stepper-header" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                                <div class="step-indicator active" data-step="1" style="flex:1; text-align:center; padding:10px; border-radius:4px; font-weight:bold; cursor:pointer; background:#e0f2fe; color:#0284c7;">1. Thông tin cơ bản</div>
                                <div class="step-indicator" data-step="2" style="flex:1; text-align:center; padding:10px; border-radius:4px; font-weight:bold; cursor:pointer; color:#64748b;">2. Giá, Hình ảnh & Phân loại</div>
                            </div>

                            <!-- Step 1: Basic Info -->
                            <div class="form-step active" data-step="1">
                                <div style="display: flex; flex-direction: column; gap: 20px; background: #f8fafc; padding: 24px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                    <input type="hidden" name="product_code" value="<?= htmlspecialchars((string) ($formProduct['product_code'] ?? '')) ?>">
                                    
                                    <label style="display:block;"><span class="label-text" style="font-weight:600; color:#0f172a; margin-bottom:8px; display:block;">Tên sản phẩm <span style="color:#dc2626">*</span></span>
                                        <input type="text" name="name" class="<?= isset($fieldErrors['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars((string) ($formProduct['name'] ?? '')) ?>" placeholder="Nhập tên sản phẩm" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:6px;" required>
                                    </label>

                                    <!-- 3-Level Category Selects -->
                                    <div>
                                        <span class="label-text" style="font-weight:600; color:#0f172a; margin-bottom:8px; display:block;">Danh mục <span style="color:#dc2626">*</span></span>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                                            <select id="cat_large" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;"><option>Chọn danh mục lớn</option></select>
                                            <select id="cat_medium" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;"><option>Chọn danh mục vừa</option></select>
                                            <select id="cat_small" name="category_id" class="<?= isset($fieldErrors['category_id']) ? 'is-invalid' : '' ?>" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:6px; background:#fff;" required>
                                                <?php if ($editing || !empty($formProduct['category_id'])): ?>
                                                    <option value="<?= (int) ($formProduct['category_id'] ?? 0) ?>" selected>Đã chọn ID <?= (int) ($formProduct['category_id'] ?? 0) ?></option>
                                                <?php else: ?>
                                                    <option value="">Chọn danh mục nhỏ</option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <label style="display:block;"><span class="label-text" style="font-weight:600; color:#0f172a; margin-bottom:8px; display:block;">Số lượng tồn kho (nếu không có phân loại)</span>
                                        <input type="number" name="stock_quantity" min="0" value="<?= htmlspecialchars((string) ($formProduct['stock_quantity'] ?? '0')) ?>" style="width:100%; max-width: 300px; padding:10px 14px; border:1px solid #cbd5e1; border-radius:6px;">
                                    </label>

                                    <div class="product-description-field">
                                        <span class="label-text" style="font-weight:600; color:#0f172a; margin-bottom:8px; display:block;">Mô tả sản phẩm</span>
                                        <div class="rich-editor" data-rich-editor style="border:1px solid #cbd5e1; border-radius:6px; background:#fff; overflow:hidden;">
                                            <div class="rich-editor-toolbar" aria-label="Công cụ định dạng mô tả" style="background:#f1f5f9; padding:8px; border-bottom:1px solid #e2e8f0;">
                                                <select data-rich-block aria-label="Kiểu đoạn"><option value="p">Đoạn thường</option><option value="h2">Tiêu đề lớn</option><option value="h3">Tiêu đề vừa</option><option value="h4">Tiêu đề nhỏ</option></select>
                                                <select data-rich-size aria-label="Cỡ chữ"><option value="">Cỡ chữ</option><option value="14px">Nhỏ</option><option value="16px">Thường</option><option value="20px">Lớn</option><option value="26px">Rất lớn</option></select>
                                                <button type="button" data-rich-command="bold" title="Bôi đậm"><i class="bi bi-type-bold"></i></button>
                                                <button type="button" data-rich-command="italic" title="In nghiêng"><i class="bi bi-type-italic"></i></button>
                                                <button type="button" data-rich-command="underline" title="Gạch chân"><i class="bi bi-type-underline"></i></button>
                                                <span class="rich-editor-divider"></span>
                                                <button type="button" data-rich-command="insertUnorderedList" title="Danh sách gạch đầu dòng"><i class="bi bi-list-ul"></i></button>
                                                <button type="button" data-rich-command="insertOrderedList" title="Danh sách đánh số"><i class="bi bi-list-ol"></i></button>
                                            </div>
                                            <div class="rich-editor-surface" contenteditable="true" data-rich-surface aria-label="Mô tả sản phẩm" style="padding:15px; min-height:150px;"><?= UiHelper::richTextHtml((string) ($formProduct['description'] ?? '')) ?: '<p></p>' ?></div>
                                            <textarea name="description" data-rich-input hidden><?= htmlspecialchars((string) ($formProduct['description'] ?? '')) ?></textarea>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="label-text" style="font-weight:600; color:#0f172a; margin-bottom:8px; display:block;">Thẻ (Tags)</span>
                                        <div style="position: relative;">
                                            <input type="text" id="smart-tag-input" placeholder="Nhập thẻ, cách nhau bằng dấu phẩy" autocomplete="off" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                            <div id="smart-tag-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 4px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); z-index: 10; max-height: 200px; overflow-y: auto;"></div>
                                        </div>
                                        <div id="smart-tag-chips" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;"></div>
                                        <div id="smart-tag-hidden-inputs"></div>
                                    </div>
                                    
                                    <div>
                                        <span class="label-text" style="font-weight:600; color:#0f172a; margin-bottom:12px; display:block;">Cấu hình hiển thị thông tin vận chuyển trên app người mua</span>
                                        <div style="display: flex; gap: 24px; align-items: center;">
                                            <label style="cursor:pointer; display: flex; align-items: center; gap: 8px;"><input type="checkbox" id="check-weight" <?= !empty($formProduct['weight']) ? 'checked' : '' ?>> Cân nặng</label>
                                            <label style="cursor:pointer; display: flex; align-items: center; gap: 8px;"><input type="checkbox" id="check-dims" <?= (!empty($formProduct['length']) || !empty($formProduct['width']) || !empty($formProduct['height'])) ? 'checked' : '' ?>> Kích thước (D x R x C)</label>
                                            <label style="cursor:pointer; display: flex; align-items: center; gap: 8px;"><input type="checkbox" id="check-volume" <?= !empty($formProduct['volume']) ? 'checked' : '' ?>> Thể tích (m3/l)</label>
                                        </div>
                                        
                                        <!-- Hidden Shipping Inputs -->
                                        <div id="wrap-weight" style="display: <?= !empty($formProduct['weight']) ? 'flex' : 'none' ?>; gap: 10px; align-items: flex-end; margin-top: 16px;">
                                            <label style="flex: 2;">Cân nặng <input type="number" name="weight" min="0" step="0.01" value="<?= htmlspecialchars((string) ($formProduct['weight'] ?? '')) ?>" style="width: 100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; mt:4px;"></label>
                                            <label style="flex: 1;">Đơn vị <select name="weight_unit" style="width: 100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;"><option value="g" <?= ($formProduct['weight_unit'] ?? 'g') === 'g' ? 'selected' : '' ?>>g</option><option value="kg" <?= ($formProduct['weight_unit'] ?? '') === 'kg' ? 'selected' : '' ?>>kg</option></select></label>
                                        </div>
                                        <div id="wrap-dims" style="display: <?= (!empty($formProduct['length']) || !empty($formProduct['width']) || !empty($formProduct['height'])) ? 'flex' : 'none' ?>; gap: 10px; align-items: flex-end; margin-top: 16px;">
                                            <label style="flex: 1;">Dài (cm) <input type="number" id="input-length" name="length" min="0" step="0.01" value="<?= htmlspecialchars((string) ($formProduct['length'] ?? '')) ?>" style="width: 100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;"></label>
                                            <label style="flex: 1;">Rộng (cm) <input type="number" id="input-width" name="width" min="0" step="0.01" value="<?= htmlspecialchars((string) ($formProduct['width'] ?? '')) ?>" style="width: 100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;"></label>
                                            <label style="flex: 1;">Cao (cm) <input type="number" id="input-height" name="height" min="0" step="0.01" value="<?= htmlspecialchars((string) ($formProduct['height'] ?? '')) ?>" style="width: 100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;"></label>
                                            <label style="flex: 1;">Thể tích (Preview) <input type="text" id="calculated-volume-preview" readonly placeholder="0 m3" style="width: 100%; padding:8px; background: #f1f5f9; color: #0f766e; border: 1px dashed #cbd5e1; border-radius:6px;"></label>
                                        </div>
                                        <div id="wrap-volume" style="display: <?= !empty($formProduct['volume']) ? 'flex' : 'none' ?>; gap: 10px; align-items: flex-end; margin-top: 16px;">
                                            <label style="flex: 2;">Thể tích <input type="number" id="input-volume" name="volume" min="0" step="0.0001" value="<?= htmlspecialchars((string) ($formProduct['volume'] ?? '')) ?>" style="width: 100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;"></label>
                                            <label style="flex: 1;">Đơn vị <select name="volume_unit" id="input-volume-unit" style="width: 100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;"><option value="m3" <?= ($formProduct['volume_unit'] ?? 'm3') === 'm3' ? 'selected' : '' ?>>m3</option><option value="l" <?= ($formProduct['volume_unit'] ?? '') === 'l' ? 'selected' : '' ?>>lít (l)</option><option value="ml" <?= ($formProduct['volume_unit'] ?? '') === 'ml' ? 'selected' : '' ?>>ml</option></select></label>
                                        </div>
                                        <script>
                                            document.getElementById('check-weight').addEventListener('change', function() { document.getElementById('wrap-weight').style.display = this.checked ? 'flex' : 'none'; });
                                            document.getElementById('check-dims').addEventListener('change', function() { document.getElementById('wrap-dims').style.display = this.checked ? 'flex' : 'none'; });
                                            document.getElementById('check-volume').addEventListener('change', function() { document.getElementById('wrap-volume').style.display = this.checked ? 'flex' : 'none'; });
                                        </script>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Price and Images -->
                            <div class="form-step" data-step="2" style="display:none; background:#f8fafc; padding:24px; border-radius:8px; border:1px solid #e2e8f0;">
                                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
                                    
                                    <!-- Left Column: Images -->
                                    <div class="step2-left" style="display: flex; flex-direction: column; gap: 20px;">
                                        <div style="background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 20px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                                <h3 style="margin:0; font-size: 15px; color:#0f172a; font-weight:600;"><i class="bi bi-image" style="color:#0f766e; margin-right:6px;"></i> Hình ảnh sản phẩm <span style="color:#dc2626">*</span></h3>
                                                <span style="font-size: 13px; color: #64748b;" id="image-count-text">0/9 ảnh</span>
                                            </div>
                                            
                                            <!-- Main Image Box -->
                                            <label style="display:block; background: #f1f5f9; border-radius: 8px; height: 220px; position: relative; display: flex; align-items: center; justify-content: center; flex-direction: column; cursor: pointer; border: 1px dashed #cbd5e1; overflow:hidden;" id="main-image-preview-box">
                                                <span style="position: absolute; top: 12px; left: 12px; background: #e0f2fe; color: #0284c7; font-size: 11px; padding: 4px 8px; border-radius: 4px; font-weight: 600; z-index:2;">Ảnh chính</span>
                                                <img src="<?= !empty($formProduct['main_image_url']) ? StorageService::publicUrl((string) $formProduct['main_image_url']) : '' ?>" style="display:<?= empty($formProduct['main_image_url']) ? 'none' : 'block' ?>; width:100%; height:100%; object-fit:contain; position:absolute; inset:0; z-index:1;" id="main-image-img">
                                                <div style="text-align:center; position:relative; z-index:2; <?= !empty($formProduct['main_image_url']) ? 'opacity:0;' : '' ?>" id="main-image-placeholder">
                                                    <i class="bi bi-image" style="font-size: 32px; color: #94a3b8; margin-bottom: 8px; display:block;"></i>
                                                    <span style="font-size: 13px; color: #64748b; font-weight: 500;">Tải ảnh chính</span>
                                                </div>
                                                <input type="file" name="main_image" id="upload-main" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
                                            </label>
                                            
                                            <!-- Thumbnails grid -->
                                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px;" id="gallery-grid">
                                                <label class="gallery-upload-btn" style="background: #f8fafc; border-radius: 8px; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px dashed #cbd5e1; flex-direction:column; position:relative;">
                                                    <i class="bi bi-plus-lg" style="font-size: 20px; color: #94a3b8; margin-bottom:4px;"></i>
                                                    <span style="font-size: 10px; color: #94a3b8; position:absolute; bottom:8px; background:#fff; padding:2px 6px; border-radius:4px; border:1px solid #e2e8f0;">Ảnh phụ</span>
                                                    <input type="file" name="gallery_images[]" id="upload-gallery" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none;">
                                                </label>
                                                <!-- Gallery previews rendered by store-products.js -->
                                            </div>
                                            <div style="font-size: 12px; color: #64748b; margin-top: 16px; line-height:1.5;">Định dạng JPEG, PNG. Dung lượng tối đa 5MB. Kích thước tỷ lệ 1:1 hoặc 16:9.</div>
                                        </div>
                                    </div>

                                    <!-- Right Column: Price & Variants -->
                                    <div class="step2-right" style="display: flex; flex-direction: column; gap: 20px;">
                                        <!-- Thông tin giá -->
                                        <div style="background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 20px;">
                                            <h3 style="margin:0 0 16px 0; font-size: 15px; color:#0f172a; font-weight:600;"><i class="bi bi-cash-stack" style="color:#0f766e; margin-right:6px;"></i> Thông tin giá</h3>
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                                <label style="display:block;"><span class="label-text" style="font-size:13px; font-weight:600; color:#0f172a; margin-bottom:6px; display:block;">Giá cơ bản <span style="color:#dc2626">*</span></span>
                                                    <div style="position:relative;">
                                                        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b;">đ</span>
                                                        <input type="text" class="js-price-input <?= isset($fieldErrors['base_price']) ? 'is-invalid' : '' ?>" data-hidden-target="hidden_base_price" value="<?= htmlspecialchars((string) ($formProduct['base_price'] ?? '')) ?>" style="width:100%; padding:10px 14px 10px 30px; border:1px solid #cbd5e1; border-radius:6px;" required>
                                                    </div>
                                                    <input type="hidden" id="hidden_base_price" name="base_price" value="<?= htmlspecialchars((string) ($formProduct['base_price'] ?? '')) ?>">
                                                </label>
                                                <label style="display:block;"><span class="label-text" style="font-size:13px; font-weight:600; color:#0f172a; margin-bottom:6px; display:block;">Giá sau giảm</span>
                                                    <div style="position:relative;">
                                                        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#64748b;">đ</span>
                                                        <input type="text" class="js-price-input <?= isset($fieldErrors['discount_price']) ? 'is-invalid' : '' ?>" data-hidden-target="hidden_discount_price" value="<?= htmlspecialchars((string) ($formProduct['discount_price'] ?? '')) ?>" style="width:100%; padding:10px 14px 10px 30px; border:1px solid #cbd5e1; border-radius:6px;">
                                                    </div>
                                                    <input type="hidden" id="hidden_discount_price" name="discount_price" value="<?= htmlspecialchars((string) ($formProduct['discount_price'] ?? '')) ?>">
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- Phân loại hàng (Biến thể) -->
                                        <div style="background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 20px;">
                                            <h3 style="margin:0 0 16px 0; font-size: 15px; color:#0f172a; font-weight:600;"><i class="bi bi-diagram-2" style="color:#0f766e; margin-right:6px;"></i> Phân loại hàng (Biến thể)</h3>
                                            <div id="variant-generator-app"></div>
                                            <textarea name="variants_text" style="display:none;"><?= isset($formProduct['variants']) && is_array($formProduct['variants']) ? htmlspecialchars(variantsText($formProduct['variants'])) : '' ?></textarea>
                                            <input type="hidden" name="variants_json" id="hidden-variants-data">
                                        </div>
                                        
                                        <!-- Additional Settings -->
                                        <style>
                                            .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
                                            .toggle-switch input { opacity: 0; width: 0; height: 0; }
                                            .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
                                            .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                                            .toggle-switch input:checked + .toggle-slider { background-color: #0ea5e9; }
                                            .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
                                            .toggle-switch.warning input:checked + .toggle-slider { background-color: #d97706; }
                                        </style>
                                        <div style="display: flex; flex-direction: column; gap: 12px;">
                                            <label style="padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 15px; background: #fff;">
                                                <div>
                                                    <strong style="display: block; color: #0f172a; font-size: 14px; margin-bottom: 2px;">Sản phẩm đề xuất (Recommended)</strong>
                                                    <span style="font-size: 13px; color: #64748b; font-weight: normal;">Ưu tiên hiển thị ở danh mục "Gợi ý cho bạn" tại giao diện người mua.</span>
                                                </div>
                                                <div class="toggle-switch">
                                                    <input type="checkbox" name="is_recommended" value="1" <?= ($formProduct['is_recommended'] ?? 0) ? 'checked' : '' ?>>
                                                    <span class="toggle-slider"></span>
                                                </div>
                                            </label>
                                            <label style="padding: 15px; border: 1px solid #fde68a; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 15px; background: #fffbeb;">
                                                <div>
                                                    <strong style="display: block; color: #92400e; font-size: 14px; margin-bottom: 2px;">Gửi admin duyệt sau khi lưu</strong>
                                                    <span style="font-size: 13px; color: #b45309; font-weight: normal;">Sản phẩm cần được Admin duyệt trước khi hiển thị trên trang mua sắm.</span>
                                                </div>
                                                <div class="toggle-switch warning">
                                                    <input type="checkbox" id="send-approval-checkbox" name="submit_for_review" value="1">
                                                    <span class="toggle-slider"></span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stepper Actions -->
                            <div class="actions stepper-actions" style="margin-top: 20px; display:flex; justify-content: flex-end; gap: 10px; background:#fff; padding:16px 24px; border-top:1px solid #e2e8f0; border-radius:0 0 8px 8px;">
                                <button type="button" class="btn-prev-step btn-outline" style="display:none; margin-right: auto;"><i class="bi bi-arrow-left"></i> Quay lại thông tin cơ bản</button>
                                <button type="button" id="btn-cancel-product" class="btn-outline">Đóng</button>
                                <button type="submit" name="save_draft" value="1" class="btn-submit-step btn-outline" style="display:none;">Lưu nháp</button>
                                <button type="button" class="btn-next-step btn-primary">Tiếp tục <i class="bi bi-arrow-right"></i></button>
                                <button type="submit" class="btn-submit-step btn-primary" style="display:none;"><i class="bi bi-upload"></i> <?= $editing ? 'Hoàn tất & Đăng bán' : 'Hoàn tất & Đăng bán' ?></button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <?php if (!$products): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: #fff; border-radius: 12px; border: 1px dashed #cbd5e1;">
                        <i class="bi bi-box-seam" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px; display: block;"></i>
                        <h3 style="margin: 0 0 8px 0; color: #334155; font-size: 16px;">Chưa có sản phẩm nào</h3>
                        <p style="color: #64748b; margin: 0; font-size: 14px;">Bạn chưa upload sản phẩm nào hoặc không tìm thấy kết quả phù hợp.</p>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($products as $product): ?>
                    <?php
                    $detail = ProductManagementModel::detailForStore($user, (int) $product['id']);
                    $galleryCount = count($detail['images_data'] ?? []);
                    $imageUrl = !empty($product['main_image_url']) ? htmlspecialchars(StorageService::publicUrl((string) $product['main_image_url'])) : 'https://placehold.co/400x300/f1f5f9/94a3b8?text=Chưa+có+ảnh';
                    $statusClass = htmlspecialchars(UiHelper::statusClass((string) $product['status']));
                    $statusLabel = htmlspecialchars(UiHelper::statusLabel((string) $product['status']));
                    $statusColor = '';
                    $statusBg = '';
                    switch ($product['status']) {
                        case 'approved': $statusColor = '#059669'; $statusBg = '#d1fae5'; break;
                        case 'pending_review': $statusColor = '#d97706'; $statusBg = '#fef3c7'; break;
                        case 'rejected': $statusColor = '#dc2626'; $statusBg = '#fee2e2'; break;
                        default: $statusColor = '#475569'; $statusBg = '#f1f5f9'; break;
                    }
                    ?>
                    <div style="background: #fff; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 12px -3px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.04)'">
                        <!-- Preview Image -->
                        <div style="position: relative; padding-top: 100%; background: #f8fafc;">
                            <img src="<?= $imageUrl ?>" alt="<?= htmlspecialchars((string) $product['name']) ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                            <div style="position: absolute; top: 0; left: 0; background: <?= $statusBg ?>; color: <?= $statusColor ?>; padding: 4px 10px; border-radius: 10px 0 10px 0; font-size: 10px; font-weight: 700;">
                                <?= $statusLabel ?>
                            </div>
                            <?php if ($galleryCount > 0): ?>
                                <div style="position: absolute; bottom: 0; right: 0; background: rgba(0,0,0,0.6); color: #fff; padding: 4px 8px; border-radius: 10px 0 10px 0; font-size: 10px; font-weight: 600; display: flex; align-items: center; gap: 3px; backdrop-filter: blur(4px);">
                                    <i class="bi bi-images"></i> +<?= $galleryCount ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Metadata -->
                        <div style="padding: 12px 10px; flex: 1; display: flex; flex-direction: column;">
                            <h3 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 600; color: #0f172a; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>
                            <div style="font-size: 15px; font-weight: 700; color: #0f766e; margin-bottom: auto;">
                                <?= UiHelper::money($product['base_price']) ?>
                            </div>
                            
                            <?php if ($product['reject_reason']): ?>
                                <div style="margin-top: 6px; padding: 4px 6px; background: #fee2e2; border-radius: 4px; color: #b91c1c; font-size: 10px; line-height: 1.3;">
                                    <strong>Từ chối:</strong> <?= htmlspecialchars($product['reject_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div style="padding: 8px; border-top: 1px solid #f1f5f9; background: #f8fafc; display: flex; gap: 6px; align-items: center;">
                            <?php if ($canUpdate): ?>
                                <a href="/store/products.php?edit=<?= (int) $product['id'] ?>" style="flex: 1; text-align: center; padding: 6px; border-radius: 5px; color: #475569; background: #f1f5f9; font-size: 11px; font-weight: 600; text-decoration: none; border: 1px solid transparent; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                                    <i class="bi bi-pencil-square"></i> Sửa
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($canUpdate && in_array($product['status'], [PRODUCT_STATUS_DRAFT, PRODUCT_STATUS_REJECTED], true)): ?>
                                <form method="post" style="margin: 0; flex: 1; display: flex;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                    <input type="hidden" name="form_action" value="submit">
                                    <button type="submit" style="width: 100%; text-align: center; padding: 6px; border-radius: 5px; color: #0284c7; background: #e0f2fe; font-size: 11px; font-weight: 600; border: 1px solid transparent; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#bae6fd'" onmouseout="this.style.background='#e0f2fe'">
                                        <i class="bi bi-send"></i> Gửi
                                    </button>
                                </form>
                            <?php elseif ($canUpdate && $product['status'] !== PRODUCT_STATUS_ARCHIVED): ?>
                                <form method="post" style="margin: 0; flex: 1; display: flex;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                    <input type="hidden" name="form_action" value="archive">
                                    <button type="submit" style="width: 100%; text-align: center; padding: 6px; border-radius: 5px; color: #dc2626; background: #fee2e2; font-size: 11px; font-weight: 600; border: 1px solid transparent; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                        <i class="bi bi-eye-slash"></i> Dừng
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
        </section>
    </main>
    <script src="/assets/js/global.js?v=20260611-17"></script>
    <script src="/assets/js/rich-editor.js?v=20260611-17"></script>
    <script src="/assets/js/store-products.js?v=20260611-17"></script>
</body>
</html>
<?php

function requireStoreProductPermission(array $user, string $action): void
{
    if (!PermissionMiddleware::can($user, MODULE_PRODUCTS, $action)) {
        throw new RuntimeException('Bạn không có quyền thao tác sản phẩm.');
    }
}

function productPayloadFromPost(array $post): array
{
    $uploads = uploadProductImages($post);

    $variants = [];
    if (!empty($post['variants_json'])) {
        $parsed = json_decode($post['variants_json'], true);
        if (is_array($parsed)) {
            foreach ($parsed as $v) {
                $variants[] = [
                    'type_label' => 'Màu/Kích cỡ',
                    'color' => $v['color'] ?? '',
                    'size' => $v['size'] ?? '',
                    'sku' => $v['sku'] ?? '',
                    'price' => (float) ($v['price'] ?? 0),
                    'stock_quantity' => (int) ($v['stock_quantity'] ?? 0),
                    'restock_wait_days' => (int) ($v['restock_wait_days'] ?? 0),
                    'image_url' => trim((string) ($v['image_url'] ?? '')),
                    'is_active' => 1,
                ];
            }
        }
    } else {
        $variants = variantsFromText((string) ($post['variants_text'] ?? ''));
    }

    return [
        'product_code' => $post['product_code'] ?? '',
        'category_id' => $post['category_id'] ?? '',
        'name' => $post['name'] ?? '',
        'description' => UiHelper::sanitizeRichText((string) ($post['description'] ?? '')),
        'base_price' => $post['base_price'] ?? 0,
        'stock_quantity' => $post['stock_quantity'] ?? 0,
        'main_image_url' => $uploads['main_image_url'],
        'images' => $uploads['images'],
        'weight' => $post['weight'] ?? '',
        'tag_ids' => $post['tag_ids'] ?? [],
        'variants' => $variants,
        'submit_for_review' => empty($post['save_draft']) && !empty($post['submit_for_review']),
        'is_recommended' => !empty($post['is_recommended']),
    ];
}

function uploadProductImages(array $post): array
{
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $mainImageUrl = trim((string) ($post['existing_main_image_url'] ?? ''));
    $images = json_decode((string) ($post['existing_images'] ?? '[]'), true);
    $images = is_array($images) ? array_values(array_filter($images, 'is_string')) : [];

    if (isset($_FILES['main_image']) && ($_FILES['main_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $stored = StorageService::storeUploadedFile($_FILES['main_image'], 'products', $allowed, 5 * 1024 * 1024, 'product');
        $mainImageUrl = (string) $stored['url'];
    }

    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'] ?? null)) {
        $uploaded = [];
        foreach ($_FILES['gallery_images']['name'] as $index => $name) {
            if (($_FILES['gallery_images']['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name' => $name,
                'type' => $_FILES['gallery_images']['type'][$index] ?? '',
                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$index] ?? '',
                'error' => $_FILES['gallery_images']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['gallery_images']['size'][$index] ?? 0,
            ];
            $stored = StorageService::storeUploadedFile($file, 'products/gallery', $allowed, 5 * 1024 * 1024, 'product-gallery');
            $uploaded[] = (string) $stored['url'];
        }
        if ($uploaded) {
            $images = array_values(array_unique(array_merge($images, $uploaded)));
            if ($mainImageUrl === '') {
                $mainImageUrl = $uploaded[0];
            }
        }
    }

    return [
        'main_image_url' => $mainImageUrl,
        'images' => $images,
    ];
}

function variantsFromText(string $text): array
{
    $variants = [];
    foreach (preg_split('/\r\n|\r|\n/', trim($text)) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', explode('|', $line));
        $variants[] = [
            'type_label' => 'Mau/Kich co',
            'color' => $parts[0] ?? '',
            'size' => $parts[1] ?? '',
            'sku' => $parts[2] ?? '',
            'price' => (float) ($parts[3] ?? 0),
            'stock_quantity' => (int) ($parts[4] ?? 0),
            'image_url' => $parts[5] ?? '',
            'restock_wait_days' => (int) ($parts[6] ?? 0),
            'is_active' => 1,
        ];
    }
    return $variants;
}

function variantsText(array $variants): string
{
    $lines = [];
    foreach ($variants as $variant) {
        $lines[] = implode('|', [
            $variant['color'] ?? '',
            $variant['size'] ?? '',
            $variant['sku'] ?? '',
            $variant['price'] ?? '',
            $variant['stock_quantity'] ?? '',
            $variant['image_url'] ?? '',
            $variant['restock_wait_days'] ?? '',
        ]);
    }
    return implode("\n", $lines);
}

function money(float $value): string
{
    return UiHelper::money($value);
}
