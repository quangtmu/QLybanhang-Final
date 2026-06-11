<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_BANNERS);
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
                if (!PermissionMiddleware::can($user, MODULE_BANNERS, 'create')) {
                    throw new RuntimeException('Bạn không có quyền tạo banner.');
                }

                BannerModel::create($_POST, $_FILES['image'] ?? [], (int) $user['id']);
                $_SESSION['flash_success'] = 'Đã tạo banner.';
                header('Location: /admin/banners.php');
                exit;
            }

            if ($formAction === 'update') {
                if (!PermissionMiddleware::can($user, MODULE_BANNERS, 'update')) {
                    throw new RuntimeException('Bạn không có quyền sửa banner.');
                }

                BannerModel::update((int) ($_POST['banner_id'] ?? 0), $_POST, $_FILES['image'] ?? null);
                $_SESSION['flash_success'] = 'Đã cập nhật banner.';
                header('Location: /admin/banners.php');
                exit;
            }

            if ($formAction === 'sort') {
                if (!PermissionMiddleware::can($user, MODULE_BANNERS, 'update')) {
                    throw new RuntimeException('Bạn không có quyền sắp xếp banner.');
                }

                BannerModel::updatePositions($_POST['positions'] ?? []);
                $_SESSION['flash_success'] = 'Đã cập nhật thứ tự banner.';
                header('Location: /admin/banners.php');
                exit;
            }

            if (in_array($formAction, ['activate', 'deactivate'], true)) {
                if (!PermissionMiddleware::can($user, MODULE_BANNERS, 'update')) {
                    throw new RuntimeException('Bạn không có quyền cập nhật banner.');
                }

                BannerModel::setActive((int) ($_POST['banner_id'] ?? 0), $formAction === 'activate');
                $_SESSION['flash_success'] = $formAction === 'activate' ? 'Đã bật banner.' : 'Đã tắt banner.';
                header('Location: /admin/banners.php');
                exit;
            }

            if ($formAction === 'delete') {
                if (!PermissionMiddleware::can($user, MODULE_BANNERS, 'delete')) {
                    throw new RuntimeException('Bạn không có quyền xóa banner.');
                }

                BannerModel::delete((int) ($_POST['banner_id'] ?? 0));
                $_SESSION['flash_success'] = 'Đã xóa banner.';
                header('Location: /admin/banners.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'is_active' => $_GET['is_active'] ?? '',
];
$banners = BannerModel::all($filters);
$canCreate = PermissionMiddleware::can($user, MODULE_BANNERS, 'create');
$canUpdate = PermissionMiddleware::can($user, MODULE_BANNERS, 'update');
$canDelete = PermissionMiddleware::can($user, MODULE_BANNERS, 'delete');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý banner</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
    <style>
        .banner-thumbnail {
            width: 120px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .banner-info-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .banner-detail-image {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .detail-row {
            display: flex;
            border-bottom: 1px solid #f1f5f9;
            padding: 10px 0;
        }
        .detail-label {
            width: 120px;
            color: #64748b;
            font-weight: 500;
        }
        .detail-value {
            flex: 1;
            color: #0f172a;
            font-weight: 500;
        }
    </style>
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
                <h1 style="margin: 0;">Quản lý banner</h1>
                <div style="display: flex; gap: 10px;">
                    <?php if ($canUpdate && $banners): ?>
                        <button type="button" style="background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'" onclick="document.getElementById('sort-modal').style.display='block'">Sắp xếp thứ tự</button>
                    <?php endif; ?>
                    <?php if ($canCreate): ?>
                        <button type="button" class="btn" style="background: #1769e0; color: #ffffff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500;" onclick="openBannerModal('create')">+ Tạo banner mới</button>
                    <?php endif; ?>
                </div>
            </div>

            <form method="get" class="filter-row filter-row-large">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm banner theo tiêu đề...">
                <select name="is_active">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1" <?= $filters['is_active'] === '1' ? 'selected' : '' ?>>Đang hiển thị</option>
                    <option value="0" <?= $filters['is_active'] === '0' ? 'selected' : '' ?>>Đang ẩn</option>
                </select>
                <button type="submit">Lọc</button>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hình ảnh</th>
                            <th>Tiêu đề / Link</th>
                            <th>Kích thước</th>
                            <th>Hiển thị (Từ - Đến)</th>
                            <th>Thứ tự</th>
                            <th>Trạng thái</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$banners): ?>
                            <tr><td colspan="7" class="empty">Chưa có banner nào.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($banners as $banner): ?>
                            <?php 
                                $nodeData = htmlspecialchars(json_encode($banner)); 
                                $dateStr = '';
                                if ($banner['display_from'] || $banner['display_to']) {
                                    $from = $banner['display_from'] ? date('d/m/Y H:i', strtotime($banner['display_from'])) : 'Luôn hiện';
                                    $to = $banner['display_to'] ? date('d/m/Y H:i', strtotime($banner['display_to'])) : 'Mãi mãi';
                                    $dateStr = "{$from}<br>đến<br>{$to}";
                                } else {
                                    $dateStr = '<span class="muted">Không giới hạn</span>';
                                }
                            ?>
                            <tr>
                                <td>
                                    <img class="banner-thumbnail" src="<?= htmlspecialchars($banner['image_url']) ?>" alt="Banner">
                                </td>
                                <td>
                                    <div class="banner-info-group">
                                        <strong style="color: #1e293b; font-size: 15px;"><?= htmlspecialchars($banner['title']) ?></strong>
                                        <?php if ($banner['link_url']): ?>
                                            <a href="<?= htmlspecialchars($banner['link_url']) ?>" target="_blank" style="color: #3b82f6; font-size: 13px; text-decoration: none;">🔗 <?= htmlspecialchars($banner['link_url']) ?></a>
                                        <?php else: ?>
                                            <span class="muted" style="font-size: 13px;">Không có link</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="color: #475569;"><?= (int) $banner['width'] ?> x <?= (int) $banner['height'] ?></td>
                                <td style="color: #475569; font-size: 13px;"><?= $dateStr ?></td>
                                <td><strong><?= (int) $banner['position'] ?></strong></td>
                                <td>
                                    <span class="badge <?= (int) $banner['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>">
                                        <?= (int) $banner['is_active'] === 1 ? 'Đang bật' : 'Đang tắt' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="actions" style="justify-content: flex-end; margin: 0;">
                                        <button type="button" class="btn btn-sm btn-outline"onclick='openDetailModal(<?= $nodeData ?>)'>Chi tiết</button>
                                        
                                        <?php if ($canUpdate): ?>
                                            <button type="button" class="btn btn-sm btn-outline" onclick='openBannerModal("update", <?= $nodeData ?>)'>Sửa</button>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="banner_id" value="<?= (int) $banner['id'] ?>">
                                                <input type="hidden" name="form_action" value="<?= (int) $banner['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                                                <button type="submit" class="btn btn-sm btn-outline"><?= (int) $banner['is_active'] === 1 ? 'Tắt' : 'Bật' ?></button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($canDelete): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa banner này không?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="banner_id" value="<?= (int) $banner['id'] ?>">
                                                <input type="hidden" name="form_action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline">Xóa</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Banner Form Modal (Create/Edit) -->
        <div id="banner-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
            <div style="max-width: 1200px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                <button type="button" onclick="document.getElementById('banner-modal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">&times;</button>
                <h3 id="banner-modal-title" style="margin-top: 0; margin-bottom: 20px;">Tạo Banner</h3>
                
                <form method="post" enctype="multipart/form-data" class="admin-form js-validate" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="form_action" id="banner-form-action" value="create">
                    <input type="hidden" name="banner_id" id="banner-id" value="">

                    <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                        <label style="grid-column: 1 / -1;">Tiêu đề <?= UiHelper::requiredMark() ?>
                            <input type="text" name="title" id="banner-title" required placeholder="Tên để nhận diện banner">
                        </label>
                        <label style="grid-column: 1 / -1;">Đường dẫn Link (Tùy chọn)
                            <input type="text" name="link_url" id="banner-link" placeholder="VD: /products?category=sale">
                        </label>
                        <label>Thứ tự sắp xếp
                            <input type="number" name="position" id="banner-position" value="0">
                        </label>
                        <label>Trạng thái
                            <select name="is_active" id="banner-is-active">
                                <option value="1">Đang hiển thị</option>
                                <option value="0">Đang ẩn</option>
                            </select>
                        </label>
                        <label>Bắt đầu hiển thị từ
                            <input type="datetime-local" name="display_from" id="banner-display-from">
                        </label>
                        <label>Kết thúc hiển thị đến
                            <input type="datetime-local" name="display_to" id="banner-display-to">
                        </label>
                        <label style="grid-column: 1 / -1;"><span class="label-text">Ảnh banner <span id="banner-image-required" class="required-mark">*</span></span>
                            <input type="file" name="image" id="banner-image" accept="image/png,image/jpeg,image/webp">
                            <span class="field-note" id="banner-image-note">Hỗ trợ JPG, PNG, WEBP. Kích thước tùy chọn.</span>
                        </label>
                    </div>
                    
                    <div class="actions" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #eee; padding-top: 15px; margin-top: 20px;">
                        <button type="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;" onclick="document.getElementById('banner-modal').style.display='none'">Hủy</button>
                        <button type="submit" id="banner-submit-btn" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">Lưu banner</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Banner Details Modal -->
        <div id="detail-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 100; overflow-y: auto; padding: 40px 20px;">
            <div style="max-width: 650px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                <button type="button" onclick="document.getElementById('detail-modal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">&times;</button>
                <h3 style="margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">Chi tiết Banner</h3>
                
                <img id="detail-image" class="banner-detail-image" src="" alt="Banner Full Image">
                
                <div class="detail-row">
                    <div class="detail-label">Tiêu đề:</div>
                    <div class="detail-value" id="detail-title"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Link chuyển hướng:</div>
                    <div class="detail-value" id="detail-link"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Kích thước gốc:</div>
                    <div class="detail-value" id="detail-size"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Thứ tự hiển thị:</div>
                    <div class="detail-value" id="detail-position"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Trạng thái:</div>
                    <div class="detail-value" id="detail-status"></div>
                </div>
                <div class="detail-row" style="border-bottom: none;">
                    <div class="detail-label">Thời gian hiển thị:</div>
                    <div class="detail-value" id="detail-time"></div>
                </div>
            </div>
        </div>

        <!-- Sort Modal -->
        <div id="sort-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
            <div style="max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                <button type="button" onclick="document.getElementById('sort-modal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b;">&times;</button>
                <h3 style="margin-top: 0; margin-bottom: 20px;">Sắp xếp thứ tự Banner</h3>
                <form method="post" class="admin-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="form_action" value="sort">
                    
                    <p class="muted" style="font-size: 13px; margin-bottom: 15px;">Điền số thứ tự (nhỏ nhất sẽ hiện đầu tiên).</p>
                    
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;">
                        <?php foreach ($banners as $banner): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?= htmlspecialchars($banner['image_url']) ?>" style="width: 40px; height: 20px; object-fit: cover; border-radius: 3px;">
                                    <span style="font-size: 14px; font-weight: 500;"><?= htmlspecialchars($banner['title']) ?></span>
                                </div>
                                <input type="number" name="positions[<?= (int) $banner['id'] ?>]" value="<?= (int) $banner['position'] ?>" style="width: 70px; padding: 4px 8px; text-align: center;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('sort-modal').style.display='none'">Hủy</button>
                        <button type="submit" class="btn" style="background: #1769e0; color: #fff;">Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>

    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
    <script>
        function openBannerModal(action, data = null) {
            document.getElementById('banner-modal').style.display = 'block';
            document.getElementById('banner-form-action').value = action;
            
            const imageInput = document.getElementById('banner-image');
            const imageRequired = document.getElementById('banner-image-required');
            const imageNote = document.getElementById('banner-image-note');
            
            if (action === 'update' && data) {
                document.getElementById('banner-modal-title').innerText = 'Sửa banner: ' + data.title;
                document.getElementById('banner-submit-btn').innerText = 'Cập nhật banner';
                document.getElementById('banner-id').value = data.id;
                document.getElementById('banner-title').value = data.title;
                document.getElementById('banner-link').value = data.link_url || '';
                document.getElementById('banner-position').value = data.position;
                document.getElementById('banner-is-active').value = data.is_active;
                
                document.getElementById('banner-display-from').value = data.display_from ? data.display_from.substring(0, 16) : '';
                document.getElementById('banner-display-to').value = data.display_to ? data.display_to.substring(0, 16) : '';
                
                imageInput.removeAttribute('required');
                imageRequired.style.display = 'none';
                imageNote.innerText = 'Để trống nếu không muốn thay đổi ảnh.';
            } else {
                document.getElementById('banner-modal-title').innerText = 'Tạo banner mới';
                document.getElementById('banner-submit-btn').innerText = 'Tạo banner';
                document.getElementById('banner-id').value = '';
                document.getElementById('banner-title').value = '';
                document.getElementById('banner-link').value = '';
                document.getElementById('banner-position').value = 0;
                document.getElementById('banner-is-active').value = 1;
                document.getElementById('banner-display-from').value = '';
                document.getElementById('banner-display-to').value = '';
                
                imageInput.setAttribute('required', 'required');
                imageRequired.style.display = 'inline';
                imageNote.innerText = 'Hỗ trợ JPG, PNG, WEBP. Kích thước tùy chọn.';
            }
        }
        
        function openDetailModal(data) {
            document.getElementById('detail-modal').style.display = 'block';
            document.getElementById('detail-image').src = data.image_url;
            document.getElementById('detail-title').innerText = data.title;
            
            if (data.link_url) {
                document.getElementById('detail-link').innerHTML = `<a href="${data.link_url}" target="_blank" style="color: #3b82f6;">${data.link_url}</a>`;
            } else {
                document.getElementById('detail-link').innerHTML = '<span class="muted">Không có</span>';
            }
            
            document.getElementById('detail-size').innerText = `${data.width} x ${data.height} pixels`;
            document.getElementById('detail-position').innerText = data.position;
            document.getElementById('detail-status').innerHTML = data.is_active === 1 ? '<span style="color: #10b981; font-weight: bold;">Đang hiển thị</span>' : '<span style="color: #94a3b8;">Đang ẩn</span>';
            
            let timeStr = '';
            if (data.display_from || data.display_to) {
                timeStr = `Từ: ${data.display_from ? data.display_from : 'Luôn hiện'}<br>Đến: ${data.display_to ? data.display_to : 'Mãi mãi'}`;
            } else {
                timeStr = 'Không giới hạn thời gian (Luôn hiển thị)';
            }
            document.getElementById('detail-time').innerHTML = timeStr;
        }
    </script>
</body>
</html>
