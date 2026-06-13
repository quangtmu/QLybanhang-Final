<?php

declare(strict_types=1);

$adminNavPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$adminNavTab = (string) ($_GET['tab'] ?? '');
if ($adminNavPath === '/admin/users.php' && $adminNavTab === '') {
    $adminNavTab = 'buyer';
}

if (!function_exists('adminNavLinkClass')) {
    function adminNavLinkClass(string $href, string $currentPath, string $currentTab): string
    {
        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        if ($path !== $currentPath) {
            return '';
        }

        parse_str((string) (parse_url($href, PHP_URL_QUERY) ?: ''), $query);
        if (isset($query['tab']) && (string) $query['tab'] !== $currentTab) {
            return '';
        }

        return ' is-active';
    }
}

$pendingStoresCount = (int) getDB()->query("SELECT COUNT(*) FROM store_registration_requests WHERE status = 'pending'")->fetchColumn();
$pendingProductsCount = (int) getDB()->query("SELECT COUNT(*) FROM products WHERE status = 'pending_review' AND deleted_at IS NULL")->fetchColumn();
$pendingOrdersCount = (int) getDB()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

if (!function_exists('adminNavBadge')) {
    function adminNavBadge(int $count): string
    {
        if ($count <= 0) {
            return '';
        }
        return ' <span style="background: #ef4444; color: white; border-radius: 9999px; padding: 2px 6px; font-size: 11px; font-weight: bold; margin-left: auto; line-height: 1; display: inline-block;">' . $count . '</span>';
    }
}
?>
<nav class="topbar admin-topbar" data-static-nav="admin">
    <strong>
        <img src="/assets/images/admin_icon.png" alt="" class="portal-logo" style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover;">
        Quản trị hệ thống
    </strong>
    <span class="admin-nav-list">
        <a href="/admin/dashboard.php" class="<?= adminNavLinkClass('/admin/dashboard.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>

        <a href="/admin/users.php?tab=buyer" class="<?= adminNavLinkClass('/admin/users.php?tab=buyer', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-person"></i> Quản lý người mua</a>
        <a href="/admin/users.php?tab=store" class="<?= adminNavLinkClass('/admin/users.php?tab=store', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-shop"></i> Quản lý store</a>
        <a href="/admin/store-registrations.php" class="<?= adminNavLinkClass('/admin/store-registrations.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-shop-window"></i> Duyệt đơn mở shop<?= adminNavBadge($pendingStoresCount) ?></a>
        <a href="/admin/users.php?tab=admin" class="<?= adminNavLinkClass('/admin/users.php?tab=admin', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-shield-lock"></i> Quản lý Sub-admin</a>

        <a href="/admin/orders.php" class="<?= adminNavLinkClass('/admin/orders.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-receipt"></i> Danh sách đơn hàng<?= adminNavBadge($pendingOrdersCount) ?></a>
        <a href="/admin/shipments.php" class="<?= adminNavLinkClass('/admin/shipments.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-truck"></i> Vận đơn</a>
        <a href="/admin/invoices.php" class="<?= adminNavLinkClass('/admin/invoices.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-file-earmark-text"></i> Xử lý hóa đơn</a>

        <a href="/admin/products.php" class="<?= adminNavLinkClass('/admin/products.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-box-seam"></i> Yêu cầu duyệt sản phẩm<?= adminNavBadge($pendingProductsCount) ?></a>
        <a href="/admin/categories.php" class="<?= adminNavLinkClass('/admin/categories.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-diagram-3"></i> Quản lý danh mục</a>
        <a href="/admin/tags.php" class="<?= adminNavLinkClass('/admin/tags.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-tags"></i> Quản lý tags</a>

        <a href="/admin/banners.php" class="<?= adminNavLinkClass('/admin/banners.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-megaphone"></i> Quản lý Banner</a>
        <a href="/admin/flash-sales.php" class="<?= adminNavLinkClass('/admin/flash-sales.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-lightning-charge"></i> Quản lý Flash Sale</a>
        <a href="/admin/vouchers.php" class="<?= adminNavLinkClass('/admin/vouchers.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-ticket-perforated"></i> Quản lý Mã giảm giá</a>

        <?php $unreadAdminNotifications = isset($user['id']) && class_exists('NotificationModel') ? NotificationModel::unreadCount((int) $user['id']) : 0; ?>
        <a href="/notifications.php" class="<?= adminNavLinkClass('/notifications.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-bell"></i> Thông báo<?= adminNavBadge($unreadAdminNotifications) ?></a>
        <a href="/chat.php" class="<?= adminNavLinkClass('/chat.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-chat-dots"></i> Tin nhắn</a>
        <a href="/profile.php" class="<?= adminNavLinkClass('/profile.php', $adminNavPath, $adminNavTab) ?>"><i class="bi bi-person-circle"></i> Hồ sơ</a>
        <a href="/logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
    </span>
</nav>
