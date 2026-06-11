<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireModule(MODULE_USERS);
$errors = [];
$success = flash_success();

const USER_ROLE_MAP = [
    'admin' => 'Admin',
    'sub_admin_active' => 'Hoạt động',
    'sub_admin_inactive' => 'Tạm khóa',
    'store_pending' => 'Chờ duyệt',
    'store_approved' => 'Hoạt động',
    'store_rejected' => 'Từ chối',
    'store_suspended' => 'Bị khóa',
    'store_employee' => 'Nhân viên',
    'user' => 'Người mua',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $formAction = (string) ($_POST['form_action'] ?? '');

        try {
            if ($formAction === 'create_store') {
                requireAdminOnly($user);
                $createErrors = validateStoreForm($_POST);

                if ($createErrors === []) {
                    $db = getDB();
                    $db->beginTransaction();
                    try {
                        $storeName = trim((string) $_POST['store_name']);
                        $fullName = trim((string) $_POST['full_name']);
                        $username = trim((string) $_POST['username']);
                        $email = strtolower(trim((string) $_POST['email']));
                        $phone = trim((string) ($_POST['phone'] ?? ''));
                        $password = (string) $_POST['password'];

                        // Create user
                        $userStmt = $db->prepare(
                            "INSERT INTO users (
                                uuid,
                                username,
                                email,
                                password_hash,
                                full_name,
                                phone,
                                user_type,
                                is_first_login,
                                email_verified_at,
                                created_by
                            ) VALUES (
                                UUID(),
                                :username,
                                :email,
                                :password_hash,
                                :full_name,
                                :phone,
                                'store_approved',
                                1,
                                NOW(),
                                :created_by
                            )"
                        );
                        $userStmt->execute([
                            ':username' => $username,
                            ':email' => $email,
                            ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                            ':full_name' => $fullName,
                            ':phone' => $phone ?: null,
                            ':created_by' => (int) $user['id'],
                        ]);
                        $storeUserId = (int) $db->lastInsertId();

                        // Create store profile
                        $storeSlug = AdminCatalogModel::slugify($storeName);
                        $slugCandidate = $storeSlug;
                        $counter = 2;
                        while (true) {
                            $checkSlug = $db->prepare('SELECT id FROM store_profiles WHERE store_slug = :slug LIMIT 1');
                            $checkSlug->execute([':slug' => $slugCandidate]);
                            if (!$checkSlug->fetch()) {
                                break;
                            }
                            $slugCandidate = $storeSlug . '-' . $counter;
                            $counter++;
                        }

                        $profileStmt = $db->prepare(
                            'INSERT INTO store_profiles (
                                user_id,
                                store_name,
                                store_slug,
                                approved_at,
                                approved_by
                            ) VALUES (
                                :user_id,
                                :store_name,
                                :store_slug,
                                NOW(),
                                :approved_by
                            )'
                        );
                        $profileStmt->execute([
                            ':user_id' => $storeUserId,
                            ':store_name' => $storeName,
                            ':store_slug' => $slugCandidate,
                            ':approved_by' => (int) $user['id'],
                        ]);

                        $db->commit();
                        $_SESSION['flash_success'] = 'Đã tạo cửa hàng và tài khoản chủ shop thành công.';
                        header('Location: /admin/users.php?tab=store');
                        exit;
                    } catch (Throwable $e) {
                        $db->rollBack();
                        throw $e;
                    }
                }

                $errors = $createErrors;
            }

            if ($formAction === 'create_sub_admin') {
                requireAdminOnly($user);
                $createErrors = validateAdminSubAdminForm($_POST);

                if ($createErrors === []) {
                    $newId = AdminUserModel::createSubAdmin([
                        'username' => trim((string) $_POST['username']),
                        'email' => strtolower(trim((string) $_POST['email'])),
                        'password' => (string) $_POST['password'],
                        'full_name' => trim((string) $_POST['full_name']),
                        'phone' => trim((string) ($_POST['phone'] ?? '')),
                    ], (int) $user['id']);

                    AdminUserModel::replacePermissions($newId, permissionsFromPost($_POST));
                    $_SESSION['flash_success'] = 'Đã tao sub-admin va gan quyền.';
                    header('Location: /admin/users.php?tab=admin');
                    exit;
                }

                $errors = $createErrors;
            }

            if ($formAction === 'lock' || $formAction === 'unlock') {
                $target = UserModel::findById((int) ($_POST['user_id'] ?? 0));

                if (!$target) {
                    $errors[] = 'Không tìm thấy người dùng.';
                } elseif ((int) $target['id'] === (int) $user['id'] || $target['user_type'] === USER_TYPE_ADMIN) {
                    $errors[] = 'Không thể khóa/mở khóa tài khoản này.';
                } else {
                    $newType = $formAction === 'lock'
                        ? AdminUserModel::transitionForLock($target)
                        : AdminUserModel::transitionForUnlock($target);

                    if ($newType === null) {
                        $errors[] = 'Trạng thái tài khoản này không thể thay đổi.';
                    } else {
                        AdminUserModel::setUserType((int) $target['id'], $newType);
                        $_SESSION['flash_success'] = 'Đã cập nhật trạng thái tài khoản.';
                        
                        $redirectTab = 'buyer';
                        if (in_array($target['user_type'], [USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE, USER_TYPE_SUB_ADMIN_INACTIVE], true)) {
                            $redirectTab = 'admin';
                        } elseif (in_array($target['user_type'], [USER_TYPE_STORE_APPROVED, USER_TYPE_STORE_PENDING, USER_TYPE_STORE_REJECTED, USER_TYPE_STORE_SUSPENDED, USER_TYPE_STORE_EMPLOYEE], true)) {
                            $redirectTab = 'store';
                        }
                        
                        header('Location: /admin/users.php?tab=' . $redirectTab);
                        exit;
                    }
                }
            }

            if ($formAction === 'update_permissions') {
                requireAdminOnly($user);
                $target = UserModel::findById((int) ($_POST['user_id'] ?? 0));

                if (!$target) {
                    $errors[] = 'Không tìm thấy sub-admin.';
                } elseif (!in_array($target['user_type'], [USER_TYPE_SUB_ADMIN_ACTIVE, USER_TYPE_SUB_ADMIN_INACTIVE], true)) {
                    $errors[] = 'Chi co the gan quyền cho sub-admin.';
                } elseif ((int) $target['id'] === (int) $user['id']) {
                    $errors[] = 'Không thể tự sửa quyền của chính mình.';
                } else {
                    AdminUserModel::replacePermissions((int) $target['id'], permissionsFromPost($_POST));
                    $_SESSION['flash_success'] = 'Đã cập nhật quyền sub-admin.';
                    header('Location: /admin/users.php?tab=admin&edit_permissions=' . (int) $target['id']);
                    exit;
                }
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$tab = $_GET['tab'] ?? 'buyer';
if (!in_array($tab, ['buyer', 'store', 'admin'], true)) {
    $tab = 'buyer';
}

$allowedTypes = [];
if ($tab === 'buyer') {
    $allowedTypes = [
        USER_TYPE_USER,
        USER_TYPE_USER_BANNED,
    ];
} elseif ($tab === 'admin') {
    $allowedTypes = [
        USER_TYPE_ADMIN,
        USER_TYPE_SUB_ADMIN_ACTIVE,
        USER_TYPE_SUB_ADMIN_INACTIVE,
    ];
} else {
    $allowedTypes = [
        USER_TYPE_STORE_APPROVED,
        USER_TYPE_STORE_PENDING,
        USER_TYPE_STORE_REJECTED,
        USER_TYPE_STORE_SUSPENDED,
        USER_TYPE_STORE_EMPLOYEE,
    ];
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100], true)) {
    $limit = 20;
}

$sortBy = in_array($_GET['sort_by'] ?? '', ['created_at', 'full_name', 'login_count'], true) ? $_GET['sort_by'] : 'created_at';
$sortDir = in_array(strtoupper($_GET['sort_dir'] ?? ''), ['ASC', 'DESC'], true) ? strtoupper($_GET['sort_dir']) : 'DESC';

$filters = [
    'page' => $_GET['page'] ?? 1,
    'limit' => $limit,
    'search' => $_GET['search'] ?? '',
    'sort_by' => $sortBy,
    'sort_dir' => $sortDir,
];

$selectedUserType = $_GET['user_type'] ?? '';
if ($selectedUserType !== '' && in_array($selectedUserType, $allowedTypes, true)) {
    $filters['user_type'] = $selectedUserType;
} else {
    $filters['user_types'] = $allowedTypes;
}

$result = AdminUserModel::paginate($filters);
$items = $result['items'];
$pagination = $result['pagination'];
$editPermissionsId = (int) ($_GET['edit_permissions'] ?? 0);
$permissionRows = $editPermissionsId > 0 ? AdminUserModel::getPermissions($editPermissionsId) : [];
$permissionMap = permissionMap($permissionRows);
$csrfToken = AuthController::csrfToken();

$fieldErrors = [];
$generalErrors = [];
foreach ($errors as $key => $val) {
    if (is_int($key)) {
        $generalErrors[] = $val;
    } else {
        $fieldErrors[$key] = $val;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
    <style>
        .tab-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--ui-border);
            padding-bottom: 8px;
        }
        .tab-item {
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            color: var(--ui-secondary);
            padding-bottom: 8px;
            position: relative;
            transition: all 0.2s ease;
        }
        .tab-item.active {
            color: var(--ui-primary);
        }
        .tab-item.active::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--ui-primary);
            border-radius: 2px;
        }
        .page-link {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid var(--ui-border);
            border-radius: var(--ui-radius-control);
            color: var(--ui-secondary);
            text-decoration: none;
            background: var(--ui-surface);
            transition: all 0.2s ease;
        }
        .page-link:hover {
            border-color: var(--ui-primary);
            color: var(--ui-primary);
        }
        .page-link.active {
            background: var(--ui-primary);
            color: #fff;
            border-color: var(--ui-primary);
        }
    </style>
</head>
<body class="portal-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_admin_nav.php"; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($generalErrors): ?>
            <div class="alert alert-error">
                <?php foreach ($generalErrors as $message): ?>
                    <p><?= htmlspecialchars((string) $message) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="portal-panel">
            <h1 style="margin-bottom: 20px;">Quản lý người dùng</h1>

            <!-- Tab Switcher -->
            <div class="tab-container">
                <a href="?tab=buyer&limit=<?= $limit ?>" class="tab-item <?= $tab === 'buyer' ? 'active' : '' ?>">Khách hàng</a>
                <a href="?tab=store&limit=<?= $limit ?>" class="tab-item <?= $tab === 'store' ? 'active' : '' ?>">Store</a>
                <a href="?tab=admin&limit=<?= $limit ?>" class="tab-item <?= $tab === 'admin' ? 'active' : '' ?>">Sub-admin</a>
            </div>

            <!-- Top Action (Add Store Form) -->
            <?php if ($tab === 'store' && $user['user_type'] === USER_TYPE_ADMIN): ?>
                <button type="button" id="btn-open-store-modal" class="btn-primary" style="margin-bottom: 20px;">
                    Thêm cửa hàng mới
                </button>

                <div id="add-store-modal" style="<?= (!empty($fieldErrors) || !empty($generalErrors)) && ($_POST['form_action'] ?? '') === 'create_store' ? 'display:block;' : 'display:none;' ?> position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
                    <section class="portal-panel" style="max-width: 1200px; margin: 0 auto; position: relative;">
                        <button type="button" id="btn-close-store-modal" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary); line-height: 1;">&times;</button>
                        <h2 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: var(--ui-bold);">Tạo cửa hàng và chủ shop mới</h2>
                        
                        <?php if (!empty($generalErrors) && ($_POST['form_action'] ?? '') === 'create_store'): ?>
                            <div class="alert alert-error" style="margin-bottom: 15px;">
                                <?php foreach ($generalErrors as $msg): ?><p><?= htmlspecialchars($msg) ?></p><?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="admin-form" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="create_store">
                            <div class="form-grid">
                                <label>
                                    <span class="label-text">Tên cửa hàng <span class="required-mark">*</span></span>
                                    <input type="text" name="store_name" class="<?= isset($fieldErrors['store_name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>" required autocomplete="off">
                                    <?php if (isset($fieldErrors['store_name'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['store_name']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span class="label-text">Họ tên chủ shop <span class="required-mark">*</span></span>
                                    <input type="text" name="full_name" class="<?= isset($fieldErrors['full_name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autocomplete="off">
                                    <?php if (isset($fieldErrors['full_name'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['full_name']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span class="label-text">Username <span class="required-mark">*</span></span>
                                    <input type="text" name="username" class="<?= isset($fieldErrors['username']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="new-password">
                                    <?php if (isset($fieldErrors['username'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['username']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span class="label-text">Email <span class="required-mark">*</span></span>
                                    <input type="email" name="email" class="<?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="off">
                                    <?php if (isset($fieldErrors['email'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['email']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span>Số điện thoại</span>
                                    <input type="tel" name="phone" class="<?= isset($fieldErrors['phone']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" autocomplete="off">
                                    <?php if (isset($fieldErrors['phone'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['phone']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span class="label-text">Mật khẩu tạm <span class="required-mark">*</span></span>
                                    <input type="password" name="password" class="<?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>" required autocomplete="new-password">
                                    <?php if (isset($fieldErrors['password'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['password']) ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
                                <button type="button" id="btn-cancel-store" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: var(--ui-radius-control); cursor: pointer;">Đóng</button>
                                <button type="submit" style="padding: 8px 16px; border-radius: var(--ui-radius-control);">Tạo cửa hàng</button>
                            </div>
                        </form>
                    </section>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const modal = document.getElementById('add-store-modal');
                        const btnOpen = document.getElementById('btn-open-store-modal');
                        const btnClose = document.getElementById('btn-close-store-modal');
                        const btnCancel = document.getElementById('btn-cancel-store');

                        if (btnOpen && modal) {
                            btnOpen.addEventListener('click', () => {
                                modal.style.display = 'block';
                            });
                        }
                        
                        const closeModal = () => {
                            if (modal) modal.style.display = 'none';
                        };

                        if (btnClose) btnClose.addEventListener('click', closeModal);
                        if (btnCancel) btnCancel.addEventListener('click', closeModal);
                    });
                </script>
            <?php endif; ?>

            <!-- Top Action (Add Sub-Admin Form) -->
            <?php if ($tab === 'admin' && $user['user_type'] === USER_TYPE_ADMIN): ?>
                <button type="button" id="btn-open-admin-modal" class="btn-primary" style="margin-bottom: 20px;">
                    Thêm sub-admin
                </button>

                <div id="add-admin-modal" style="<?= (!empty($fieldErrors) || !empty($generalErrors)) && ($_POST['form_action'] ?? '') === 'create_sub_admin' ? 'display:block;' : 'display:none;' ?> position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; overflow-y: auto; padding: 40px 20px;">
                    <section class="portal-panel" style="max-width: 1200px; margin: 0 auto; position: relative;">
                        <button type="button" id="btn-close-admin-modal" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ui-secondary); line-height: 1;">&times;</button>
                        <h2 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: var(--ui-bold);">Tạo sub-admin mới</h2>
                        
                        <?php if (!empty($generalErrors) && ($_POST['form_action'] ?? '') === 'create_sub_admin'): ?>
                            <div class="alert alert-error" style="margin-bottom: 15px;">
                                <?php foreach ($generalErrors as $msg): ?><p><?= htmlspecialchars($msg) ?></p><?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="admin-form" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="form_action" value="create_sub_admin">
                            <div class="form-grid">
                                <label>
                                    <span class="label-text">Họ tên <span class="required-mark">*</span></span>
                                    <input type="text" name="full_name" class="<?= isset($fieldErrors['full_name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required autocomplete="off">
                                    <?php if (isset($fieldErrors['full_name'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['full_name']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span class="label-text">Username <span class="required-mark">*</span></span>
                                    <input type="text" name="username" class="<?= isset($fieldErrors['username']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="new-password">
                                    <?php if (isset($fieldErrors['username'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['username']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span class="label-text">Email <span class="required-mark">*</span></span>
                                    <input type="email" name="email" class="<?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="off">
                                    <?php if (isset($fieldErrors['email'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['email']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span>Số điện thoại</span>
                                    <input type="tel" name="phone" class="<?= isset($fieldErrors['phone']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" autocomplete="off">
                                    <?php if (isset($fieldErrors['phone'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['phone']) ?></span>
                                    <?php endif; ?>
                                </label>
                                <label>
                                    <span class="label-text">Mật khẩu <span class="required-mark">*</span></span>
                                    <input type="password" name="password" class="<?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>" required autocomplete="new-password">
                                    <?php if (isset($fieldErrors['password'])): ?>
                                        <span class="field-error"><?= htmlspecialchars($fieldErrors['password']) ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                            
                            <h3 style="margin-top: 20px; font-size: 16px;">Phân quyền ban đầu</h3>
                            <?= renderPermissionGrid([]) ?>
                            
                            <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
                                <button type="button" id="btn-cancel-admin" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: var(--ui-radius-control); cursor: pointer;">Đóng</button>
                                <button type="submit" style="padding: 8px 16px; border-radius: var(--ui-radius-control);">Tạo sub-admin</button>
                            </div>
                        </form>
                    </section>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const modal = document.getElementById('add-admin-modal');
                        const btnOpen = document.getElementById('btn-open-admin-modal');
                        const btnClose = document.getElementById('btn-close-admin-modal');
                        const btnCancel = document.getElementById('btn-cancel-admin');

                        if (btnOpen && modal) {
                            btnOpen.addEventListener('click', () => {
                                modal.style.display = 'block';
                            });
                        }
                        
                        const closeModal = () => {
                            if (modal) modal.style.display = 'none';
                        };

                        if (btnClose) btnClose.addEventListener('click', closeModal);
                        if (btnCancel) btnCancel.addEventListener('click', closeModal);
                    });
                </script>
            <?php endif; ?>

            <form method="get" class="filter-row">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="limit" value="<?= $limit ?>">
                <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm tên, email, username, phone">
                <select name="user_type">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach ($allowedTypes as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= $selectedUserType === $type ? 'selected' : '' ?>>
                            <?= htmlspecialchars(USER_ROLE_MAP[$type] ?? $type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-primary">Lọc</button>
            </form>

            <!-- Total Users pushed above table and aligned to the right -->
            <div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 10px;">
                <span class="muted" style="font-weight: 600; font-size: 14px; color: var(--ui-secondary);">Tổng số lượng user: <?= (int) $pagination['total'] ?></span>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?= UiHelper::sortLink('full_name', 'Họ tên', $sortBy, $sortDir, $_GET) ?></th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Trạng thái</th>
                            <th>Online</th>
                            <th><?= UiHelper::sortLink('login_count', 'Lần đăng nhập', $sortBy, $sortDir, $_GET) ?></th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$items): ?>
                            <tr><td colspan="7" class="empty">Chưa có người dùng nào.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($item['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($item['email']) ?></td>
                                <td>@<?= htmlspecialchars($item['username']) ?></td>
                                <td><span class="badge badge-muted"><?= htmlspecialchars(USER_ROLE_MAP[$item['user_type']] ?? $item['user_type']) ?></span></td>
                                <td><?= (int) $item['is_online'] === 1 ? 'Online' : 'Offline' ?></td>
                                <td><?= (int) $item['login_count'] ?></td>
                                <td class="actions">
                                    <div style="display: flex; gap: 8px;">
                                        <?php if (AdminUserModel::transitionForLock($item)): ?>
                                            <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn KHÓA tài khoản này?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="form_action" value="lock">
                                                <input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>">
                                                <button type="submit" class="btn-danger-outline">Khóa</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (AdminUserModel::transitionForUnlock($item)): ?>
                                            <form method="post" onsubmit="return confirm('Bạn có chắc chắn muốn MỞ KHÓA tài khoản này?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="form_action" value="unlock">
                                                <input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>">
                                                <button type="submit" class="btn-action-outline">Mở khóa</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($item['user_type'], [USER_TYPE_SUB_ADMIN_ACTIVE, USER_TYPE_SUB_ADMIN_INACTIVE], true)): ?>
                                            <a href="/admin/users.php?tab=<?= htmlspecialchars($tab) ?>&edit_permissions=<?= (int) $item['id'] ?>" class="btn-action-outline">Quyền</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Custom Pagination with Page Numbers and Limit Selector -->
            <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
        </section>

        <?php if ($editPermissionsId > 0 && $user['user_type'] === USER_TYPE_ADMIN): ?>
            <section class="portal-panel" style="margin-top: 20px;">
                <h2>Cập nhật quyền sub-admin #<?= $editPermissionsId ?></h2>
                <form method="post" class="admin-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="form_action" value="update_permissions">
                    <input type="hidden" name="user_id" value="<?= $editPermissionsId ?>">
                    <?= renderPermissionGrid($permissionMap) ?>
                    <button type="submit">Lưu quyền</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
</body>
</html>

<?php

function requireAdminOnly(array $user): void
{
    if ($user['user_type'] !== USER_TYPE_ADMIN) {
        throw new RuntimeException('Chỉ admin hệ thống được thực hiện thao tác này.');
    }
}

function validateAdminSubAdminForm(array $data): array
{
    $errors = [];
    $username = trim((string) ($data['username'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
        $errors[] = 'Username từ 3 ký tự, chỉ dùng chữ cái, số và dấu gạch dưới.';
    } elseif (UserModel::usernameExists($username)) {
        $errors[] = 'Username đã tồn tại.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    } elseif (UserModel::emailExists($email)) {
        $errors[] = 'Email đã tồn tại.';
    }

    if (trim((string) ($data['full_name'] ?? '')) === '') {
        $errors[] = 'Vui lòng nhập họ tên.';
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Mật khẩu tối thiểu ' . PASSWORD_MIN_LENGTH . ' ký tự.';
    }

    return $errors;
}

function validateStoreForm(array $data): array
{
    $errors = [];
    $storeName = trim((string) ($data['store_name'] ?? ''));
    $username = trim((string) ($data['username'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');

    if ($storeName === '') {
        $errors['store_name'] = 'Vui lòng nhập tên cửa hàng.';
    }

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
        $errors['username'] = 'Username từ 3 ký tự, chỉ dùng chữ cái, số và dấu gạch dưới.';
    } elseif (UserModel::usernameExists($username)) {
        $errors['username'] = 'Username đã tồn tại.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ.';
    } elseif (UserModel::emailExists($email)) {
        $errors['email'] = 'Email đã tồn tại.';
    }

    if (trim((string) ($data['full_name'] ?? '')) === '') {
        $errors['full_name'] = 'Vui lòng nhập họ tên chủ cửa hàng.';
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Mật khẩu tối thiểu ' . PASSWORD_MIN_LENGTH . ' ký tự.';
    }

    return $errors;
}

function permissionsFromPost(array $data): array
{
    $permissions = [];

    foreach (AdminUserModel::moduleKeys() as $moduleKey) {
        $permissions[] = [
            'module_key' => $moduleKey,
            'can_view' => isset($data['permissions'][$moduleKey]['can_view']),
            'can_create' => isset($data['permissions'][$moduleKey]['can_create']),
            'can_update' => isset($data['permissions'][$moduleKey]['can_update']),
            'can_delete' => isset($data['permissions'][$moduleKey]['can_delete']),
            'can_export' => isset($data['permissions'][$moduleKey]['can_export']),
            'can_approve' => isset($data['permissions'][$moduleKey]['can_approve']),
        ];
    }

    return $permissions;
}

function permissionMap(array $rows): array
{
    $map = [];

    foreach ($rows as $row) {
        $map[$row['module_key']] = $row;
    }

    return $map;
}

function renderPermissionGrid(array $selected): string
{
    $actions = ['can_view' => 'Xem', 'can_create' => 'Thêm', 'can_update' => 'Sửa', 'can_delete' => 'Xóa', 'can_export' => 'Export', 'can_approve' => 'Duyệt'];
    ob_start();
    ?>
    <div class="permission-grid">
        <table class="data-table compact-table">
            <thead>
                <tr>
                    <th>Module</th>
                    <?php foreach ($actions as $label): ?>
                        <th><?= htmlspecialchars($label) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (AdminUserModel::moduleKeys() as $moduleKey): ?>
                    <tr>
                        <td><?= htmlspecialchars($moduleKey) ?></td>
                        <?php foreach ($actions as $action => $label): ?>
                            <td>
                                <input
                                    type="checkbox"
                                    name="permissions[<?= htmlspecialchars($moduleKey) ?>][<?= htmlspecialchars($action) ?>]"
                                    <?= !empty($selected[$moduleKey][$action]) ? 'checked' : '' ?>
                                >
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}
