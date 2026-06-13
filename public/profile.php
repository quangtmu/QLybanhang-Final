<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once BASE_PATH . '/includes/flash.php';
// Need buyer UI helper for Tailwind
require_once __DIR__ . '/user/_buyer_ui.php';

$user = AuthMiddleware::requireLogin();
AuthMiddleware::requireFirstLoginChange($user);

$errors = [];
$success = flash_success();
$csrfToken = AuthController::csrfToken();
$profile = UserModel::findById((int) $user['id']) ?: $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
            $result = AuthController::changePassword(
                (int) $user['id'],
                (string) ($_POST['current_password'] ?? ''),
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['new_password_confirmation'] ?? '')
            );
            if ($result['success']) {
                $_SESSION['flash_success'] = 'Đã đổi mật khẩu thành công.';
                header('Location: /profile.php');
                exit;
            } else {
                $errors[] = $result['message'];
            }
        } else {
            try {
                UserModel::updateProfile((int) $user['id'], (string) ($_POST['full_name'] ?? ''), $_POST['phone'] ?? null);
                $_SESSION['flash_success'] = 'Đã cập nhật hồ sơ.';
                header('Location: /profile.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
            }
        }
    }
}

$profile = UserModel::findById((int) $user['id']) ?: $profile;
$isAdminPortal = in_array($user['user_type'], [USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE], true);
$isStorePortal = in_array($user['user_type'], [USER_TYPE_STORE_APPROVED, USER_TYPE_STORE_EMPLOYEE], true);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ sơ cá nhân - OmniSales</title>
    <?php if (!$isAdminPortal && !$isStorePortal): ?>
        <?php include __DIR__ . '/user/_tailwind_head.php'; ?>
    <?php else: ?>
        <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
        <?php if ($isStorePortal): ?>
            <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260609-12">
        <?php else: ?>
            <style>
                .portal-page { background-color: #f1f5f9; color: #1e293b; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"; }
                .portal-shell { max-width: 1280px; margin: 0 auto; padding: 20px; }
                .portal-panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 20px; }
                .admin-form { display: flex; flex-direction: column; gap: 15px; }
                .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                label { display: flex; flex-direction: column; gap: 5px; font-weight: bold; }
                input { padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
                button { background: #3b82f6; color: white; padding: 8px 15px; border-radius: 4px; border: none; cursor: pointer; }
            </style>
        <?php endif; ?>
    <?php endif; ?>
</head>
<body class="<?= !$isAdminPortal && !$isStorePortal ? 'font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background pt-[64px] pb-20 lg:pb-8' : ($isStorePortal ? 'portal-page store-page' : 'portal-page') ?>">

    <?php if (!$isAdminPortal && !$isStorePortal): ?>
        <?php include __DIR__ . '/user/_tailwind_header.php'; ?>
    <?php elseif ($isAdminPortal): ?>
        <?php include __DIR__ . '/admin/_admin_nav.php'; ?>
    <?php elseif ($isStorePortal): ?>
        <?php include __DIR__ . '/store/_store_nav.php'; ?>
    <?php endif; ?>

    <main class="<?= !$isAdminPortal && !$isStorePortal ? 'max-w-3xl mx-auto w-full px-4 mt-4 flex-grow' : 'portal-shell' ?>">
        
        <?php if ($success): ?>
            <div class="<?= !$isAdminPortal && !$isStorePortal ? 'bg-success/10 text-success p-3 rounded-lg font-medium text-sm mb-3 flex items-center gap-2' : 'alert alert-success' ?>">
                <?php if (!$isAdminPortal && !$isStorePortal): ?><span class="material-symbols-outlined text-[18px]">check_circle</span><?php endif; ?>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="<?= !$isAdminPortal && !$isStorePortal ? 'bg-error/10 text-error p-3 rounded-lg text-sm mb-3' : 'alert alert-error' ?>">
                <?php foreach ($errors as $message): ?>
                    <p class="<?= !$isAdminPortal && !$isStorePortal ? 'flex items-center gap-1' : '' ?>">
                        <?php if (!$isAdminPortal && !$isStorePortal): ?><span class="material-symbols-outlined text-[16px]">error</span><?php endif; ?>
                        <?= htmlspecialchars((string) $message) ?>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$isAdminPortal && !$isStorePortal): ?>
            <!-- Buyer UI -->
            <div class="bg-white rounded-xl border border-border-subtle p-5 mb-4 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-r from-primary to-blue-400"></div>
                <div class="relative z-10 flex flex-col sm:flex-row items-center sm:items-end gap-4 mt-8">
                    <div class="w-24 h-24 rounded-full bg-white border-4 border-white shadow-sm flex items-center justify-center text-primary text-3xl font-bold uppercase overflow-hidden">
                        <?= htmlspecialchars(mb_substr($profile['full_name'], 0, 1)) ?>
                    </div>
                    <div class="text-center sm:text-left flex-1">
                        <h1 class="text-2xl font-bold text-on-surface"><?= htmlspecialchars($profile['full_name']) ?></h1>
                        <p class="text-sm text-on-surface-variant flex items-center justify-center sm:justify-start gap-1">
                            <span class="material-symbols-outlined text-[16px]">mail</span><?= htmlspecialchars($profile['email']) ?>
                        </p>
                    </div>
                    <button type="button" onclick="document.getElementById('password-modal').classList.remove('hidden'); document.getElementById('password-modal').classList.add('flex')" class="mt-4 sm:mt-0 px-4 py-2 bg-surface-container-low text-on-surface border border-border-subtle rounded-lg text-sm font-semibold hover:bg-surface-container-high transition-colors flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">lock_reset</span>Đổi mật khẩu
                    </button>
                </div>
                
                <?php 
                if (!$isAdminPortal && !$isStorePortal) {
                    require_once __DIR__ . '/../app/models/LoyaltyModel.php';
                    $loyaltyInfo = LoyaltyModel::getLoyaltyInfo((int) $user['id']);
                }
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-6 pt-4 border-t border-border-subtle">
                    <div class="flex items-center gap-3 p-3 bg-surface-container-low/50 rounded-lg border border-border-subtle/50">
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">alternate_email</span>
                        </div>
                        <div>
                            <p class="text-[11px] text-on-surface-variant font-bold uppercase tracking-wider">Username</p>
                            <p class="text-sm font-semibold text-on-surface">@<?= htmlspecialchars($profile['username']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-surface-container-low/50 rounded-lg border border-border-subtle/50">
                        <div class="w-10 h-10 rounded-full bg-buyer-orange/10 text-buyer-orange flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">shield_person</span>
                        </div>
                        <div>
                            <p class="text-[11px] text-on-surface-variant font-bold uppercase tracking-wider">Vai trò</p>
                            <p class="text-sm font-semibold text-on-surface"><?= htmlspecialchars(UiHelper::statusLabel((string) $profile['user_type'])) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-surface-container-low/50 rounded-lg border border-border-subtle/50">
                        <div class="w-10 h-10 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">workspace_premium</span>
                        </div>
                        <div>
                            <p class="text-[11px] text-on-surface-variant font-bold uppercase tracking-wider">Hạng thành viên</p>
                            <p class="text-sm font-semibold text-on-surface capitalize"><?= htmlspecialchars((string) ($loyaltyInfo['tier_level'] ?? 'bronze')) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-surface-container-low/50 rounded-lg border border-border-subtle/50">
                        <div class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">stars</span>
                        </div>
                        <div>
                            <p class="text-[11px] text-on-surface-variant font-bold uppercase tracking-wider">Điểm tích luỹ</p>
                            <p class="text-sm font-semibold text-on-surface"><?= number_format((float) ($loyaltyInfo['current_points'] ?? 0), 0, ',', '.') ?> điểm</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-border-subtle p-5 mb-6">
                <h2 class="text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">manage_accounts</span>Cập nhật thông tin
                </h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface">Họ tên <span class="text-error">*</span></span>
                            <input type="text" name="full_name" value="<?= htmlspecialchars((string) $profile['full_name']) ?>" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface">Số điện thoại</span>
                            <input type="tel" name="phone" value="<?= htmlspecialchars((string) ($profile['phone'] ?? '')) ?>" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                    </div>
                    <button type="submit" class="bg-primary text-white font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-primary-container transition-all shadow-sm flex items-center justify-center gap-2 active:scale-[0.98] w-full sm:w-auto">
                        <span class="material-symbols-outlined text-[18px]">save</span>Lưu hồ sơ
                    </button>
                </form>
            </div>
            
            <!-- Password Modal (Tailwind) -->
            <div id="password-modal" class="hidden fixed inset-0 z-50 items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('password-modal').classList.add('hidden'); document.getElementById('password-modal').classList.remove('flex')"></div>
                <div class="relative bg-white rounded-xl shadow-lg w-full max-w-sm overflow-hidden flex flex-col">
                    <div class="px-5 py-4 border-b border-border-subtle flex justify-between items-center bg-surface-container-low/30">
                        <h2 class="text-base font-bold text-on-surface">Đổi mật khẩu</h2>
                        <button type="button" class="text-on-surface-variant hover:text-error" onclick="document.getElementById('password-modal').classList.add('hidden'); document.getElementById('password-modal').classList.remove('flex')">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>
                    <form method="post" class="p-5 space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface">Mật khẩu hiện tại <span class="text-error">*</span></span>
                            <input type="password" name="current_password" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface">Mật khẩu mới <span class="text-error">*</span></span>
                            <input type="password" name="new_password" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface">Xác nhận mật khẩu mới <span class="text-error">*</span></span>
                            <input type="password" name="new_password_confirmation" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <div class="flex gap-2 mt-2 pt-2 border-t border-border-subtle">
                            <button type="button" onclick="document.getElementById('password-modal').classList.add('hidden'); document.getElementById('password-modal').classList.remove('flex')" class="flex-1 py-2 bg-surface-container-low text-on-surface font-semibold text-sm rounded-lg hover:bg-surface-container-high transition-colors">Hủy</button>
                            <button type="submit" class="flex-1 py-2 bg-primary text-white font-semibold text-sm rounded-lg hover:bg-primary-container transition-colors shadow-sm">Lưu mật khẩu</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- Admin/Store Legacy UI -->
            <section class="portal-panel" style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h1 style="margin: 0;">Hồ sơ cá nhân</h1>
                    <button type="button" style="background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'" onclick="document.getElementById('password-modal').style.display='flex'"><i class="bi bi-lock"></i> Đổi mật khẩu</button>
                </div>
                <div class="detail-grid profile-detail-grid" style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 30px; display: grid; gap: 10px;">
                    <div><strong>Email:</strong> <span><?= htmlspecialchars((string) $profile['email']) ?></span></div>
                    <div><strong>Username:</strong> <span>@<?= htmlspecialchars((string) $profile['username']) ?></span></div>
                    <div><strong>Vai trò:</strong> <span style="background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size: 12px;"><?= htmlspecialchars((string) $profile['user_type']) ?></span></div>
                </div>
            </section>

            <section class="portal-panel">
                <h2>Cập nhật thông tin</h2>
                <form method="post" class="admin-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="form-grid">
                        <label>Họ tên *
                            <input type="text" name="full_name" value="<?= htmlspecialchars((string) $profile['full_name']) ?>" required>
                        </label>
                        <label>Số điện thoại
                            <input type="tel" name="phone" value="<?= htmlspecialchars((string) ($profile['phone'] ?? '')) ?>">
                        </label>
                    </div>
                    <div class="actions" style="margin-top: 15px;">
                        <button type="submit" style="background: #0f766e; color: #ffffff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;" onmouseover="this.style.background='#115e59'" onmouseout="this.style.background='#0f766e'"><i class="bi bi-save"></i> Lưu hồ sơ</button>
                    </div>
                </form>
            </section>

            <!-- Password Modal (Legacy) -->
            <div id="password-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                <div style="background: white; padding: 25px; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                    <h2 style="margin-top: 0; margin-bottom: 20px;">Đổi mật khẩu</h2>
                    <form method="post" class="admin-form" style="display: flex; flex-direction: column; gap: 15px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <label style="display: flex; flex-direction: column; gap: 5px;">
                            Mật khẩu hiện tại
                            <input type="password" name="current_password" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </label>
                        <label style="display: flex; flex-direction: column; gap: 5px;">
                            Mật khẩu mới
                            <input type="password" name="new_password" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </label>
                        <label style="display: flex; flex-direction: column; gap: 5px;">
                            Xác nhận mật khẩu mới
                            <input type="password" name="new_password_confirmation" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </label>
                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                            <button type="submit" style="flex: 1; background: #3b82f6; color: white; padding: 8px; border: none; border-radius: 4px; cursor: pointer;">Lưu mật khẩu</button>
                            <button type="button" onclick="document.getElementById('password-modal').style.display='none'" style="flex: 1; background: #e2e8f0; color: #1e293b; padding: 8px; border: none; border-radius: 4px; cursor: pointer;">Hủy</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="/assets/js/global.js?v=20260609-3"></script>
</body>
</html>
