<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_CATEGORIES);
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
                requireCatalogPermission($user, MODULE_CATEGORIES, 'create');
                AdminCatalogModel::createCategory($_POST);
                $_SESSION['flash_success'] = 'Đã tạo danh mục.';
                header('Location: /admin/categories.php');
                exit;
            }

            if ($formAction === 'update') {
                requireCatalogPermission($user, MODULE_CATEGORIES, 'update');
                AdminCatalogModel::updateCategory($id, $_POST);
                $_SESSION['flash_success'] = 'Đã cập nhật danh mục.';
                header('Location: /admin/categories.php');
                exit;
            }

            if ($formAction === 'activate' || $formAction === 'deactivate') {
                requireCatalogPermission($user, MODULE_CATEGORIES, 'update');
                AdminCatalogModel::setCategoryActive($id, $formAction === 'activate');
                $_SESSION['flash_success'] = 'Đã cập nhật trạng thái danh mục.';
                header('Location: /admin/categories.php');
                exit;
            }

            if ($formAction === 'delete') {
                requireCatalogPermission($user, MODULE_CATEGORIES, 'delete');
                AdminCatalogModel::deleteCategory($id);
                $_SESSION['flash_success'] = 'Đã xóa danh mục.';
                header('Location: /admin/categories.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$categories = AdminCatalogModel::categories();
$tree = AdminCatalogModel::categoryTree();
$csrfToken = AuthController::csrfToken();
$canCreate = PermissionMiddleware::can($user, MODULE_CATEGORIES, 'create');
$canUpdate = PermissionMiddleware::can($user, MODULE_CATEGORIES, 'update');
$canDelete = PermissionMiddleware::can($user, MODULE_CATEGORIES, 'delete');

function requireCatalogPermission(array $user, string $module, string $action): void
{
    if (!PermissionMiddleware::can($user, $module, $action)) {
        throw new RuntimeException('Bạn không có quyền thực hiện thao tác này.');
    }
}

function categoryLevelLabel(string $level): string
{
    return match ($level) {
        CATEGORY_LEVEL_LARGE => 'Danh mục lớn',
        CATEGORY_LEVEL_MEDIUM => 'Danh mục con',
        CATEGORY_LEVEL_SMALL => 'Nhánh chi tiết',
        default => $level,
    };
}

function getNextLevel(string $currentLevel): ?string
{
    if ($currentLevel === CATEGORY_LEVEL_LARGE) return CATEGORY_LEVEL_MEDIUM;
    if ($currentLevel === CATEGORY_LEVEL_MEDIUM) return CATEGORY_LEVEL_SMALL;
    return null;
}

// Generate the specific tree HTML
function renderCategoryTreeHTML(array $nodes, int $depth, string $csrfToken, bool $canCreate, bool $canUpdate, bool $canDelete): string
{
    $html = '';
    
    // Large Categories are wrapper blocks
    if ($depth === 0) {
        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $name = htmlspecialchars($node['name']);
            $slug = htmlspecialchars($node['slug']);
            $isActive = (int) $node['is_active'] === 1;
            $statusLabel = $isActive ? 'Đang bật' : 'Đang tắt';
            $statusClass = $isActive ? 'badge-success' : 'badge-muted';
            $nextLevel = getNextLevel($node['level']);
            $nodeData = htmlspecialchars(json_encode($node));
            
            $hasChildren = !empty($node['children']);
            $toggleIcon = $hasChildren ? '<span class="toggle-icon" style="cursor: pointer; display: inline-block; width: 24px; text-align: center; color: #1769e0; font-size: 14px;" onclick="toggleNode(this, \'node-children-' . $id . '\')">▼</span>' : '<span style="display: inline-block; width: 24px;"></span>';

            $html .= "<div class='tree-block' style='margin-bottom: 20px; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);'>";
            $html .= "<div class='tree-large-header' style='background: #f4f8ff; padding: 12px 20px 12px 10px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #bcd2f3; border-left: 4px solid var(--ui-primary);'>";
            $html .= "    <div style='display: flex; align-items: center;'>";
            $html .= "        {$toggleIcon}";
            $html .= "        <strong style='font-size: 16px; color: var(--ui-primary);'>{$name}</strong>";
            $html .= "        <span class='badge' style='margin-left: 10px; font-size: 11px; background: #e0f2fe; color: var(--ui-primary); border-color: #bae6fd;'>Danh mục lớn</span>";
            $html .= "        <span class='badge {$statusClass}' style='font-size: 11px;'>{$statusLabel}</span>";
            $html .= "        <span class='muted' style='font-size: 13px; margin-left: 10px; color: var(--ui-secondary);'>/{$slug}</span>";
            $html .= "    </div>";
            $html .= "    <div class='actions'>";
            if ($canCreate && $nextLevel) {
                $html .= "<button type='button' class='btn-action-outline' style='margin-right: 5px;' onclick='openCategoryModal(\"create\", null, {$id}, \"{$nextLevel}\")'>+ Cấp con</button>";
            }
            if ($canUpdate) {
                $html .= "<button type='button' class='btn-action-outline' style='margin-right: 5px;' onclick='openCategoryModal(\"update\", {$nodeData})'>Sửa</button>";
            }
            if ($canDelete) {
                $html .= "<form method='post' style='display:inline;' onsubmit='return confirm(\"Bạn có chắc muốn xóa danh mục này không?\");'><input type='hidden' name='csrf_token' value='{$csrfToken}'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='form_action' value='delete'><button type='submit' class='btn-danger-outline'>Xóa</button></form>";
            }
            $html .= "    </div>";
            $html .= "</div>";

            if ($hasChildren) {
                $html .= "<div id='node-children-{$id}' class='tree-children' style='padding: 15px 20px 15px 40px; background: #f8fafc;'>";
                $html .= renderCategoryTreeHTML($node['children'], 1, $csrfToken, $canCreate, $canUpdate, $canDelete);
                $html .= "</div>";
            }
            $html .= "</div>";
        }
    } else {
        $html .= "<ul class='tree-list' style='list-style: none; padding: 0; margin: 0; border-left: 2px solid #cbd5e1;'>";
        foreach ($nodes as $index => $node) {
            $id = (int) $node['id'];
            $name = htmlspecialchars($node['name']);
            $slug = htmlspecialchars($node['slug']);
            $level = $node['level'];
            $levelLabel = categoryLevelLabel($level);
            $isActive = (int) $node['is_active'] === 1;
            $statusLabel = $isActive ? 'Đang bật' : 'Đang tắt';
            $statusClass = $isActive ? 'badge-success' : 'badge-muted';
            $nextLevel = getNextLevel($level);
            $nodeData = htmlspecialchars(json_encode($node));
            
            $isLast = ($index === count($nodes) - 1);
            $fontSize = $depth === 1 ? '15px' : '14px';
            $textColor = $depth === 1 ? '#0f172a' : '#334155';
            $fontWeight = $depth === 1 ? '600' : '500';
            $bgColor = $depth === 1 ? '#f1f5f9' : '#ffffff';
            $borderColor = $depth === 1 ? '#e2e8f0' : '#f1f5f9';

            $hasChildren = !empty($node['children']);
            $toggleIcon = $hasChildren ? '<span class="toggle-icon" style="cursor: pointer; display: inline-block; width: 24px; text-align: center; color: #475569; font-size: 13px;" onclick="toggleNode(this, \'node-children-' . $id . '\')">▼</span>' : '<span style="display: inline-block; width: 24px;"></span>';

            // The tree line item
            $html .= "<li class='tree-item' style='position: relative; padding-left: 25px; margin-bottom: 12px;'>";
            
            // Connectors
            $html .= "<div style='position: absolute; top: 16px; left: 0; width: 20px; height: 2px; background: #cbd5e1;'></div>";
            if ($isLast) {
                // Hide vertical line continuing down
                $html .= "<div style='position: absolute; top: 18px; bottom: -12px; left: -2px; width: 4px; background: #f8fafc;'></div>";
            }
            
            $html .= "<div style='display: flex; justify-content: space-between; align-items: center; background: {$bgColor}; padding: 8px 12px 8px 0; border: 1px solid {$borderColor}; border-radius: 6px;'>";
            $html .= "    <div style='display: flex; align-items: center;'>";
            $html .= "        {$toggleIcon}";
            $html .= "        <strong style='font-size: {$fontSize}; font-weight: {$fontWeight}; color: {$textColor};'>{$name}</strong>";
            $html .= "        <span class='badge' style='margin-left: 8px; font-size: 10px; background: #e2e8f0; color: #475569; border-color: #cbd5e1;'>{$levelLabel}</span>";
            $html .= "        <span class='badge {$statusClass}' style='font-size: 10px;'>{$statusLabel}</span>";
            $html .= "        <span class='muted' style='font-size: 12px; margin-left: 8px;'>/{$slug}</span>";
            $html .= "    </div>";
            
            $html .= "    <div class='actions' style='opacity: 0.8;'>";
            if ($canCreate && $nextLevel) {
                $html .= "<button type='button' class='btn-action-outline' style='font-size: 11px; padding: 4px 8px; margin-right: 4px;' onclick='openCategoryModal(\"create\", null, {$id}, \"{$nextLevel}\")'>+ Cấp con</button>";
            }
            if ($canUpdate) {
                $html .= "<button type='button' class='btn-action-outline' style='font-size: 11px; padding: 4px 8px; margin-right: 4px;' onclick='openCategoryModal(\"update\", {$nodeData})'>Sửa</button>";
            }
            if ($canDelete) {
                $html .= "<form method='post' style='display:inline;' onsubmit='return confirm(\"Bạn có chắc muốn xóa danh mục này không?\");'><input type='hidden' name='csrf_token' value='{$csrfToken}'><input type='hidden' name='id' value='{$id}'><input type='hidden' name='form_action' value='delete'><button type='submit' class='btn-danger-outline' style='font-size: 11px; padding: 4px 8px;'>Xóa</button></form>";
            }
            $html .= "    </div>";
            $html .= "</div>";

            if ($hasChildren) {
                $html .= "<div id='node-children-{$id}' style='margin-top: 10px;'>";
                $html .= renderCategoryTreeHTML($node['children'], $depth + 1, $csrfToken, $canCreate, $canUpdate, $canDelete);
                $html .= "</div>";
            }
            
            $html .= "</li>";
        }
        $html .= "</ul>";
    }

    return $html;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục</title>
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
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px;">
                <div>
                    <h1 style="margin: 0 0 5px 0;">Danh mục 3 cấp</h1>
                    <p class="muted" style="margin: 0 0 12px 0; font-size: 14px;">Cấu trúc chuẩn: Danh mục lớn > Danh mục con > Nhánh chi tiết.</p>
                    <div style="position: relative; width: 300px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#94a3b8" viewBox="0 0 16 16" style="position: absolute; left: 10px; top: 10px;">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                        <input type="search" placeholder="Tìm kiếm danh mục..." style="width: 100%; padding: 8px 12px 8px 32px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" oninput="filterCategoriesTree(this.value)">
                    </div>
                </div>
                <?php if ($canCreate): ?>
                    <button type="button" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; border-radius: 6px; padding: 8px 16px; font-weight: 500; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" onclick="openCategoryModal('create', null, 0, '<?= CATEGORY_LEVEL_LARGE ?>')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/></svg>
                        Thêm danh mục lớn
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!$tree): ?>
                <p class="empty" style="padding: 40px; text-align: center; border: 1px dashed #cbd5e1; border-radius: 8px;">Chưa có danh mục nào.</p>
            <?php else: ?>
                <div class="category-tree-container">
                    <?= renderCategoryTreeHTML($tree, 0, $csrfToken, $canCreate, $canUpdate, $canDelete) ?>
                </div>
            <?php endif; ?>
        </section>

        <div id="category-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; position: relative;">
                <button type="button" onclick="document.getElementById('category-modal').style.display='none'" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary);">&times;</button>
                <h3 id="category-modal-title" style="margin-top: 0; margin-bottom: 20px;">Tạo danh mục</h3>
                
                <form method="post" class="admin-form js-validate" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="form_action" id="category-form-action" value="create">
                    <input type="hidden" name="id" id="category-id" value="">

                    <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                        <label style="grid-column: 1 / -1;">Tên danh mục <?= UiHelper::requiredMark() ?>
                            <input type="text" name="name" id="category-name" required>
                        </label>
                        <label style="grid-column: 1 / -1;">Slug
                            <input type="text" name="slug" id="category-slug" placeholder="Tự sinh nếu để trống">
                        </label>
                        <label>Cấp <?= UiHelper::requiredMark() ?>
                            <select name="level" id="category-level" required style="width: 100%; box-sizing: border-box;">
                                <?php foreach (AdminCatalogModel::categoryLevels() as $level): ?>
                                    <option value="<?= htmlspecialchars($level) ?>">
                                        <?= htmlspecialchars(categoryLevelLabel($level)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Danh mục cha
                            <select name="parent_id" id="category-parent-id" style="width: 100%; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; box-sizing: border-box;">
                                <option value="0">Không có (Danh mục lớn)</option>
                                <?php foreach ($categories as $category): ?>
                                    <?php 
                                        $label = categoryLevelLabel((string) $category['level']) . ' - ' . $category['name'];
                                        if (mb_strlen($label) > 35) {
                                            $label = mb_substr($label, 0, 32) . '...';
                                        }
                                    ?>
                                    <option value="<?= (int) $category['id'] ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Icon URL
                            <input type="text" name="icon_url" id="category-icon-url" placeholder="https://...">
                        </label>
                        <label>Thứ tự
                            <input type="number" name="sort_order" id="category-sort-order" value="0">
                        </label>
                        <label style="grid-column: 1 / -1;">Trạng thái
                            <select name="is_active" id="category-is-active" style="width: 100%; box-sizing: border-box;">
                                <option value="1">Đang bật</option>
                                <option value="0">Đang tắt</option>
                            </select>
                        </label>
                    </div>
                    
                    <div class="actions" style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #eee; padding-top: 15px; margin-top: 20px;">
                        <button type="button" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;" onclick="document.getElementById('category-modal').style.display='none'">Hủy</button>
                        <button type="submit" id="category-submit-btn" style="background: #1769e0; color: #ffffff; border: 1px solid #1769e0; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">Lưu danh mục</button>
                    </div>
                </form>
            </div>
        </div>

    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
    <script>
        function openCategoryModal(action, data = null, parentId = 0, level = '') {
            document.getElementById('category-modal').style.display = 'block';
            document.getElementById('category-form-action').value = action;
            
            if (action === 'update' && data) {
                document.getElementById('category-modal-title').innerText = 'Sửa danh mục: ' + data.name;
                document.getElementById('category-submit-btn').innerText = 'Cập nhật danh mục';
                document.getElementById('category-id').value = data.id;
                document.getElementById('category-name').value = data.name;
                document.getElementById('category-slug').value = data.slug;
                document.getElementById('category-level').value = data.level;
                document.getElementById('category-parent-id').value = data.parent_id || 0;
                document.getElementById('category-icon-url').value = data.icon_url || '';
                document.getElementById('category-sort-order').value = data.sort_order || 0;
                document.getElementById('category-is-active').value = data.is_active;
            } else {
                document.getElementById('category-modal-title').innerText = 'Tạo danh mục mới';
                document.getElementById('category-submit-btn').innerText = 'Tạo danh mục';
                document.getElementById('category-id').value = '';
                document.getElementById('category-name').value = '';
                document.getElementById('category-slug').value = '';
                document.getElementById('category-level').value = level;
                document.getElementById('category-parent-id').value = parentId;
                document.getElementById('category-icon-url').value = '';
                document.getElementById('category-sort-order').value = 0;
                document.getElementById('category-is-active').value = 1;
            }
        }

        // Collapse/Expand node children
        function toggleNode(iconElement, childrenContainerId) {
            const container = document.getElementById(childrenContainerId);
            if (!container) return;
            
            const isExpanded = iconElement.innerText === '▼';
            iconElement.innerText = isExpanded ? '▶' : '▼';
            container.style.display = isExpanded ? 'none' : 'block';
        }

        // Frontend search filter for categories
        function filterCategoriesTree(query) {
            const term = query.toLowerCase().trim();
            const blocks = document.querySelectorAll('.tree-block');
            
            // Reset state if empty
            if (term === '') {
                blocks.forEach(block => {
                    block.style.display = 'block';
                });
                document.querySelectorAll('.tree-large-header, .tree-item > div:first-of-type').forEach(item => {
                    item.style.opacity = '1';
                });
                return;
            }
            
            blocks.forEach(block => {
                const text = block.innerText.toLowerCase();
                if (text.includes(term)) {
                    block.style.display = 'block';
                    
                    // Expand all children so matched items are visible
                    const childrenContainers = block.querySelectorAll('[id^="node-children-"]');
                    childrenContainers.forEach(c => {
                        c.style.display = 'block';
                    });
                    const icons = block.querySelectorAll('.toggle-icon');
                    icons.forEach(i => i.innerText = '▼');
                    
                    // Fade out non-matching items
                    const items = block.querySelectorAll('.tree-large-header, .tree-item > div:first-of-type');
                    items.forEach(item => {
                        const itemText = item.innerText.toLowerCase();
                        if (itemText.includes(term)) {
                            item.style.opacity = '1';
                        } else {
                            item.style.opacity = '0.4';
                        }
                    });
                } else {
                    block.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
