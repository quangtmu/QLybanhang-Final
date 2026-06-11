<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_TAGS);
$errors = [];
$success = flash_success();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $formAction = (string) ($_POST['form_action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        try {
            if ($formAction === 'create') {
                requireCatalogPermission($user, MODULE_TAGS, 'create');
                AdminCatalogModel::createTag($_POST);
                $_SESSION['flash_success'] = 'Đã tạo tag.';
                header('Location: /admin/tags.php');
                exit;
            }

            if ($formAction === 'update') {
                requireCatalogPermission($user, MODULE_TAGS, 'update');
                AdminCatalogModel::updateTag($id, $_POST);
                $_SESSION['flash_success'] = 'Đã cập nhật tag.';
                header('Location: /admin/tags.php');
                exit;
            }

            if ($formAction === 'activate' || $formAction === 'deactivate') {
                requireCatalogPermission($user, MODULE_TAGS, 'update');
                AdminCatalogModel::setTagActive($id, $formAction === 'activate');
                $_SESSION['flash_success'] = 'Đã cập nhật trạng thái tag.';
                header('Location: /admin/tags.php');
                exit;
            }

            if ($formAction === 'delete') {
                requireCatalogPermission($user, MODULE_TAGS, 'delete');
                AdminCatalogModel::deleteTag($id);
                $_SESSION['flash_success'] = 'Đã xóa tag.';
                header('Location: /admin/tags.php');
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
$tags = AdminCatalogModel::tags($filters);

$csrfToken = AuthController::csrfToken();
$canCreate = PermissionMiddleware::can($user, MODULE_TAGS, 'create');
$canUpdate = PermissionMiddleware::can($user, MODULE_TAGS, 'update');
$canDelete = PermissionMiddleware::can($user, MODULE_TAGS, 'delete');

function requireCatalogPermission(array $user, string $module, string $action): void
{
    if (!PermissionMiddleware::can($user, $module, $action)) {
        throw new RuntimeException('Bạn không có quyền thực hiện thao tác này.');
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tags</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
    <style>
        .tag-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
        }
        .tag-pill::before {
            content: "#";
            opacity: 0.5;
            margin-right: 2px;
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
                <h1 style="margin: 0;">Danh sách Tags (Từ khóa)</h1>
                <?php if ($canCreate): ?>
                    <button type="button" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; border-radius: 6px; padding: 8px 16px; font-weight: 500; font-size: 14px; cursor: pointer;" onclick="openTagModal('create')">+ Tạo tag mới</button>
                <?php endif; ?>
            </div>
            
            <form method="get" class="filter-row filter-row-large">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm tag...">
                <select name="is_active">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1" <?= $filters['is_active'] === '1' ? 'selected' : '' ?>>Đang bật</option>
                    <option value="0" <?= $filters['is_active'] === '0' ? 'selected' : '' ?>>Đang tắt</option>
                </select>
                <button type="submit">Lọc</button>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tag (Từ khóa)</th>
                            <th>Mã màu HEX</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$tags): ?>
                            <tr><td colspan="5" class="empty">Chưa có tag nào.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tags as $tag): ?>
                                <?php 
                                    $bgColor = $tag['color_hex'] ?: '#e2e8f0'; 
                                    $textColor = $tag['color_hex'] ? '#ffffff' : '#475569';
                                    $nodeData = htmlspecialchars(json_encode($tag));
                                ?>
                                <tr>
                                    <td><?= (int) $tag['id'] ?></td>
                                    <td>
                                        <span class="tag-pill" style="background-color: <?= htmlspecialchars($bgColor) ?>; color: <?= $textColor ?>;">
                                            <?= htmlspecialchars($tag['name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($tag['color_hex']): ?>
                                            <span style="display: inline-block; width: 16px; height: 16px; background-color: <?= htmlspecialchars($tag['color_hex']) ?>; border-radius: 4px; vertical-align: middle; margin-right: 6px;"></span>
                                            <?= htmlspecialchars($tag['color_hex']) ?>
                                        <?php else: ?>
                                            <span class="muted">Trống</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= (int) $tag['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>">
                                            <?= (int) $tag['is_active'] === 1 ? 'Đang bật' : 'Đang tắt' ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <?php if ($canUpdate): ?>
                                            <button type="button" class="btn btn-sm btn-outline" onclick='openTagModal("update", <?= $nodeData ?>)'>Sửa</button>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $tag['id'] ?>">
                                                <input type="hidden" name="form_action" value="<?= (int) $tag['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                                                <button type="submit" class="btn btn-sm btn-outline"><?= (int) $tag['is_active'] === 1 ? 'Tắt' : 'Bật' ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa tag này không?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $tag['id'] ?>">
                                                <input type="hidden" name="form_action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline">Xóa</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Tag Modal -->
        <div id="tag-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
            <div style="max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                <button type="button" onclick="document.getElementById('tag-modal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary);">&times;</button>
                <h3 id="tag-modal-title" style="margin-top: 0; margin-bottom: 20px;">Tạo Tag (Từ khóa)</h3>
                
                <form method="post" class="admin-form js-validate" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="form_action" id="tag-form-action" value="create">
                    <input type="hidden" name="id" id="tag-id" value="">

                    <div class="form-grid" style="grid-template-columns: 1fr;">
                        <label>Tên tag <?= UiHelper::requiredMark() ?>
                            <input type="text" name="name" id="tag-name" required placeholder="Ví dụ: hot, sale, new...">
                        </label>
                        <label>Mã màu (Hex)
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="color" id="tag-color-picker" style="width: 40px; height: 40px; padding: 0; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer;" oninput="document.getElementById('tag-color-hex').value = this.value;">
                                <input type="text" name="color_hex" id="tag-color-hex" placeholder="#RRGGBB (Tùy chọn)" style="flex: 1;" oninput="document.getElementById('tag-color-picker').value = this.value.length === 7 ? this.value : '#000000';">
                            </div>
                        </label>
                        <label>Trạng thái
                            <select name="is_active" id="tag-is-active">
                                <option value="1">Đang bật</option>
                                <option value="0">Đang tắt</option>
                            </select>
                        </label>
                    </div>
                    
                    <div class="actions" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #eee; padding-top: 15px; margin-top: 20px;">
                        <button type="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;" onclick="document.getElementById('tag-modal').style.display='none'">Hủy</button>
                        <button type="submit" id="tag-submit-btn" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">Lưu tag</button>
                    </div>
                </form>
            </div>
        </div>

    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
    <script>
        function openTagModal(action, data = null) {
            document.getElementById('tag-modal').style.display = 'block';
            document.getElementById('tag-form-action').value = action;
            
            if (action === 'update' && data) {
                document.getElementById('tag-modal-title').innerText = 'Sửa tag: ' + data.name;
                document.getElementById('tag-submit-btn').innerText = 'Cập nhật tag';
                document.getElementById('tag-id').value = data.id;
                document.getElementById('tag-name').value = data.name;
                
                const hex = data.color_hex || '';
                document.getElementById('tag-color-hex').value = hex;
                if (hex.match(/^#[0-9a-fA-F]{6}$/)) {
                    document.getElementById('tag-color-picker').value = hex;
                } else {
                    document.getElementById('tag-color-picker').value = '#000000';
                }
                
                document.getElementById('tag-is-active').value = data.is_active;
            } else {
                document.getElementById('tag-modal-title').innerText = 'Tạo tag mới';
                document.getElementById('tag-submit-btn').innerText = 'Tạo tag';
                document.getElementById('tag-id').value = '';
                document.getElementById('tag-name').value = '';
                document.getElementById('tag-color-hex').value = '';
                document.getElementById('tag-color-picker').value = '#000000';
                document.getElementById('tag-is-active').value = 1;
            }
        }
    </script>
</body>
</html>
