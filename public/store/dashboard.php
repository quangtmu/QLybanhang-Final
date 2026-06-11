<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireUserType([USER_TYPE_STORE_APPROVED, USER_TYPE_STORE_EMPLOYEE]);
$success = flash_success();
$dashboard = DashboardModel::storeSummary($user);
$unreadNotifications = NotificationModel::unreadCount((int) $user['id']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Bảng điều khiển</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
    <link rel="stylesheet" href="/assets/css/store-buyer.css?v=20260609-12">
</head>
<body class="portal-page store-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_store_nav.php"; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <section class="portal-panel">
            <h1>Xin chào, <?= htmlspecialchars($user['full_name']) ?></h1>
            <p><?= htmlspecialchars((string) $dashboard['store']['store_name']) ?> có <?= (int) $unreadNotifications ?> thông báo chưa đọc.</p>
            <div class="store-quick-actions">
                <a href="/store/products.php">Quản lý sản phẩm</a>
                <a href="/store/orders.php">Xử lý đơn hàng</a>
                <a href="/store/shipments.php">Theo dõi vận đơn</a>
                <a href="/store/invoices.php">Xuất hóa đơn</a>
            </div>
        </section>

        <section class="dashboard-grid" style="margin-bottom: 30px;">
            <?= metricCard('Doanh thu đã giao', money($dashboard['revenue']['delivered_total'])) ?>
            <?= metricCard('Doanh thu tháng này', money($dashboard['revenue']['delivered_month'])) ?>
            <?= metricCard('Tổng đơn hàng', (string) $dashboard['orders']['total']) ?>
            <?= metricCard('Đơn đang xử lý', (string) $dashboard['orders']['open']) ?>
            <?= metricCard('Sản phẩm đã duyệt', (string) $dashboard['products']['approved']) ?>
            <?= metricCard('Nhân viên hoạt động', (string) $dashboard['employees']['active']) ?>
        </section>

        <section class="dashboard-columns">
            <div class="portal-panel">
                <h2>Trạng thái đơn</h2>
                <?= statusList($dashboard['orders']['by_status']) ?>
            </div>
            <div class="portal-panel">
                <h2>Trạng thái sản phẩm</h2>
                <?= statusList($dashboard['products']['by_status']) ?>
            </div>
        </section>

        <section class="portal-panel">
            <h2>Đơn gần đây</h2>
            <?= recentOrdersTable($dashboard['recent_orders']) ?>
        </section>

        <section class="portal-panel">
            <h2>Sản phẩm bán chạy</h2>
            <?= topProductsTable($dashboard['top_products']) ?>
        </section>
    </main>
    <script src="/assets/js/global.js"></script>
</body>
</html>
<?php

function metricCard(string $label, string $value): string
{
    return '<article class="metric-card"><span>' . htmlspecialchars($label) . '</span><strong>' . htmlspecialchars($value) . '</strong></article>';
}

function statusList(array $items): string
{
    $html = '<div class="status-list">';

    foreach ($items as $label => $value) {
        $html .= '<div><span>' . htmlspecialchars(UiHelper::statusLabel((string) $label)) . '</span><strong>' . (int) $value . '</strong></div>';
    }

    return $html . '</div>';
}

function recentOrdersTable(array $orders): string
{
    if (!$orders) {
        return '<p class="empty">Chưa có đơn hàng.</p>';
    }

    $rows = '';

    foreach ($orders as $order) {
        $rows .= '<tr>'
            . '<td>' . htmlspecialchars((string) $order['order_code']) . '</td>'
            . '<td>' . htmlspecialchars((string) $order['buyer_email']) . '</td>'
            . '<td><span class="badge ' . htmlspecialchars(UiHelper::statusClass((string) $order['status'])) . '">' . htmlspecialchars(UiHelper::statusLabel((string) $order['status'])) . '</span></td>'
            . '<td>' . money((float) $order['final_amount']) . '</td>'
            . '<td>' . htmlspecialchars((string) $order['created_at']) . '</td>'
            . '</tr>';
    }

    return '<div class="table-wrap"><table class="data-table"><thead><tr><th>Mã đơn</th><th>Buyer</th><th>Trạng thái</th><th>Giá trị</th><th>Ngày tạo</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

function topProductsTable(array $products): string
{
    if (!$products) {
        return '<p class="empty">Chưa có sản phẩm đã giao.</p>';
    }

    $rows = '';

    foreach ($products as $product) {
        $rows .= '<tr>'
            . '<td>' . htmlspecialchars((string) $product['product_name']) . '</td>'
            . '<td>' . (int) $product['quantity_total'] . '</td>'
            . '<td>' . money((float) $product['revenue_total']) . '</td>'
            . '</tr>';
    }

    return '<div class="table-wrap"><table class="data-table"><thead><tr><th>Sản phẩm</th><th>Đã bán</th><th>Doanh thu</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

function money(float $value): string
{
    return number_format($value, 0, ',', '.') . ' d';
}
