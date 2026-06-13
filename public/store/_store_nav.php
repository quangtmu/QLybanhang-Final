<?php

declare(strict_types=1);

$storeNavPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';

if (!function_exists('storeNavLinkClass')) {
    function storeNavLinkClass(string $href, string $currentPath): string
    {
        $path = parse_url($href, PHP_URL_PATH) ?: $href;

        return $path === $currentPath ? ' is-active' : '';
    }
}
?>
<nav class="topbar store-topbar" data-static-nav="store">
    <strong>
        <img src="/assets/images/store_icon.png" alt="" class="portal-logo">
        Kênh bán hàng
    </strong>
    <span>
        <a href="/store/dashboard.php" class="<?= storeNavLinkClass('/store/dashboard.php', $storeNavPath) ?>"><i class="bi bi-speedometer2"></i>Dashboard</a>
        <a href="/store/products.php" class="<?= storeNavLinkClass('/store/products.php', $storeNavPath) ?>"><i class="bi bi-box-seam"></i>Sản phẩm</a>
        <a href="/store/reviews.php" class="<?= storeNavLinkClass('/store/reviews.php', $storeNavPath) ?>"><i class="bi bi-star"></i>Đánh giá</a>
        <a href="/store/vouchers.php" class="<?= storeNavLinkClass('/store/vouchers.php', $storeNavPath) ?>"><i class="bi bi-tag"></i>Khuyến mãi</a>
        <a href="/store/orders.php" class="<?= storeNavLinkClass('/store/orders.php', $storeNavPath) ?>"><i class="bi bi-receipt"></i>Đơn hàng</a>
        <a href="/store/shipments.php" class="<?= storeNavLinkClass('/store/shipments.php', $storeNavPath) ?>"><i class="bi bi-truck"></i>Vận đơn</a>
        <a href="/store/invoices.php" class="<?= storeNavLinkClass('/store/invoices.php', $storeNavPath) ?>"><i class="bi bi-file-earmark-text"></i>Hóa đơn</a>
        <a href="/store/employees.php" class="<?= storeNavLinkClass('/store/employees.php', $storeNavPath) ?>"><i class="bi bi-people"></i>Nhân viên</a>
        <a href="/chat.php" class="<?= storeNavLinkClass('/chat.php', $storeNavPath) ?>"><i class="bi bi-chat-dots"></i>Tin nhắn</a>
        <a href="/notifications.php" class="<?= storeNavLinkClass('/notifications.php', $storeNavPath) ?>"><i class="bi bi-bell"></i>Thông báo</a>
        <a href="/profile.php" class="<?= storeNavLinkClass('/profile.php', $storeNavPath) ?>"><i class="bi bi-person-circle"></i>Hồ sơ</a>
        <a href="/logout.php"><i class="bi bi-box-arrow-right"></i>Đăng xuất</a>
    </span>
</nav>
