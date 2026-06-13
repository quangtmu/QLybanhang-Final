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
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            $formAction = (string) ($_POST['form_action'] ?? '');

            if ($formAction === 'mark_read') {
                NotificationModel::markRead((int) ($_POST['notification_id'] ?? 0), $userId);
                $_SESSION['flash_success'] = 'Đã đánh dấu thông báo.';
                header('Location: /notifications.php');
                exit;
            }

            if ($formAction === 'mark_all_read') {
                NotificationModel::markAllRead($userId);
                $_SESSION['flash_success'] = 'Đã đánh dấu tất cả thông báo.';
                header('Location: /notifications.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'notification_type' => $_GET['notification_type'] ?? '',
    'is_read' => $_GET['is_read'] ?? '',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = NotificationModel::listForUser($userId, $filters);
$pagination = $result['pagination'];
$types = NotificationModel::notificationTypesForUser($userId);
$isAdminPortal = in_array($user['user_type'], [USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE], true);
$isStorePortal = in_array($user['user_type'], [USER_TYPE_STORE_APPROVED, USER_TYPE_STORE_EMPLOYEE], true);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo - OmniSales</title>
    <?php include __DIR__ . "/user/_tailwind_head.php"; ?>
    <?php if ($isStorePortal || $isAdminPortal): ?>
        <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
        <?php if ($isStorePortal): ?>
            <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260609-12">
        <?php endif; ?>
    <?php endif; ?>

</head>
<body class="<?= !$isAdminPortal && !$isStorePortal ? 'font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background pt-[64px] pb-20 lg:pb-8' : ($isStorePortal ? 'portal-page store-page' : 'portal-page') ?>">

    <?php if (!$isAdminPortal && !$isStorePortal): ?>
        <?php include __DIR__ . '/user/_tailwind_header.php'; ?>
    <?php elseif ($isAdminPortal): ?>
        <?php include BASE_PATH . '/public/admin/_admin_nav.php'; ?>
    <?php elseif ($isStorePortal): ?>
        <?php include __DIR__ . '/store/_store_nav.php'; ?>
    <?php endif; ?>

    <main class="<?= !$isAdminPortal && !$isStorePortal ? 'max-w-3xl mx-auto w-full px-4 mt-4 flex-grow' : 'portal-shell portal-wide' ?>">
        
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

            <!-- Shared Notifications UI -->
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">notifications</span>Thông báo
                    </h1>
                    <p class="text-xs text-on-surface-variant mt-0.5"><?= (int) $result['unread_count'] ?> thông báo chưa đọc</p>
                </div>
                <?php if ($result['unread_count'] > 0): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="form_action" value="mark_all_read">
                        <button type="submit" class="text-xs font-semibold text-primary hover:text-primary-container transition-colors flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">done_all</span>Đánh dấu tất cả
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <form method="get" class="bg-white rounded-xl border border-border-subtle p-3 mb-4 flex flex-wrap gap-2 items-center">
                <div class="flex-1 min-w-[200px] relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] text-outline">search</span>
                    <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm thông báo" class="w-full border border-border-subtle rounded-lg py-1.5 pl-9 pr-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all">
                </div>
                <select name="notification_type" class="border border-border-subtle rounded-lg px-3 py-1.5 text-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/10">
                    <option value="">Tất cả loại</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= $filters['notification_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="is_read" class="border border-border-subtle rounded-lg px-3 py-1.5 text-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/10">
                    <option value="">Tất cả trạng thái</option>
                    <option value="0" <?= $filters['is_read'] === '0' ? 'selected' : '' ?>>Chưa đọc</option>
                    <option value="1" <?= $filters['is_read'] === '1' ? 'selected' : '' ?>>Đã đọc</option>
                </select>
                <button type="submit" class="bg-primary text-white text-sm font-semibold px-4 py-1.5 rounded-lg hover:bg-primary-container transition-all shadow-sm">Lọc</button>
            </form>

            <div class="bg-white rounded-xl border border-border-subtle overflow-hidden">
                <?php if (!$result['items']): ?>
                    <div class="p-12 text-center flex flex-col items-center">
                        <span class="material-symbols-outlined text-[48px] text-outline-variant mb-3">notifications_paused</span>
                        <p class="text-on-surface-variant font-medium text-sm">Chưa có thông báo nào.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-border-subtle">
                        <?php foreach ($result['items'] as $notification): ?>
                            <?php
                            $data = is_array($notification['data']) ? $notification['data'] : [];
                            $url = (string) ($data['url'] ?? '');
                            $isUnread = (int) $notification['is_read'] === 0;
                            ?>
                            <div class="p-4 flex gap-4 transition-colors <?= $isUnread ? 'bg-primary/5' : 'hover:bg-surface-container-low/50' ?>">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center <?= $isUnread ? 'bg-primary text-white' : 'bg-surface-container-highest text-on-surface-variant' ?>">
                                    <span class="material-symbols-outlined text-[20px]"><?= $isUnread ? 'notifications_active' : 'notifications' ?></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full <?= $isUnread ? 'bg-primary text-white' : 'bg-surface-container-high text-on-surface-variant' ?>"><?= htmlspecialchars($notification['notification_type']) ?></span>
                                        <span class="text-xs text-on-surface-variant"><?= htmlspecialchars($notification['created_at']) ?></span>
                                    </div>
                                    <h3 class="text-sm font-bold text-on-surface mb-1"><?= htmlspecialchars($notification['title']) ?></h3>
                                    <?php if ($notification['content']): ?>
                                        <p class="text-sm text-on-surface-variant mb-2"><?= htmlspecialchars((string) $notification['content']) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center gap-2 mt-2">
                                        <?php if ($url !== ''): ?>
                                            <a href="<?= htmlspecialchars($url) ?>" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px]">link</span>Xem chi tiết
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($isUnread): ?>
                                            <form method="post" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="form_action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                                <button type="submit" class="text-xs font-semibold text-success hover:underline flex items-center gap-1 ml-3">
                                                    <span class="material-symbols-outlined text-[14px]">check</span>Đánh dấu đã đọc
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-4">
                <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
            </div>

    </main>

    <script src="/assets/js/global.js?v=20260609-3"></script>
</body>
</html>
