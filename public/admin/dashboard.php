<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireUserType([USER_TYPE_ADMIN, USER_TYPE_SUB_ADMIN_ACTIVE]);
$success = flash_success();
$dashboard = DashboardModel::adminSummary();
$unreadNotifications = NotificationModel::unreadCount((int) $user['id']);

const ORDER_STATUS_MAP = [
    'pending' => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'processing' => 'Đang xử lý',
    'shipped' => 'Đã giao ĐVVC',
    'delivering' => 'Đang giao hàng',
    'delivered' => 'Đã giao thành công',
    'cancelled' => 'Đã hủy',
    'refunding' => 'Đang hoàn tiền',
    'refunded' => 'Đã hoàn tiền'
];

const PRODUCT_STATUS_MAP = [
    'draft' => 'Bản nháp',
    'pending_review' => 'Chờ duyệt',
    'approved' => 'Đã duyệt',
    'rejected' => 'Từ chối',
    'archived' => 'Đã lưu trữ'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Bảng điều khiển</title>
    <link rel="stylesheet" href="/assets/css/global.css?v=20260611-17">
</head>
<body class="portal-page">
    <main class="portal-shell portal-wide">
        <?php include __DIR__ . "/_admin_nav.php"; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <section class="dashboard-grid" style="margin-bottom: 30px;">
            <?= metricCard('Doanh thu đã giao', money($dashboard['revenue']['delivered_total']), 'bi-cash-stack') ?>
            <?= metricCard('Doanh thu tháng này', money($dashboard['revenue']['delivered_month']), 'bi-graph-up-arrow') ?>
            <?= metricCard('Tổng đơn hàng', (string) $dashboard['orders']['total'], 'bi-bag-check') ?>
            <?= metricCard('Đơn đang xử lý', (string) $dashboard['orders']['open'], 'bi-hourglass-split') ?>
            <?= metricCard('Sản phẩm đã duyệt', (string) $dashboard['products']['approved'], 'bi-box-seam') ?>
            <?= metricCard('Tổng thành viên', (string) $dashboard['users']['total'], 'bi-people') ?>
            <?= metricCard('Cửa hàng hoạt động', (string) $dashboard['users']['store_approved'], 'bi-shop') ?>
            <?= metricCard('Khách mua hàng', (string) $dashboard['users']['buyers'], 'bi-person-badge') ?>
        </section>

        <section class="dashboard-columns">
            <div class="portal-panel">
                <h2>Trạng thái đơn</h2>
                <?= statusList($dashboard['orders']['by_status'], 'order') ?>
            </div>
            <div class="portal-panel">
                <h2>Trạng thái sản phẩm</h2>
                <?= statusList($dashboard['products']['by_status'], 'product') ?>
            </div>
        </section>

        <section class="portal-panel">
            <h2>Đơn gần đây</h2>
            <?= recentOrdersTable($dashboard['recent_orders'], true) ?>
        </section>

        <section class="portal-panel">
            <h2>Top shop theo doanh thu delivered</h2>
            <?= topStoresTable($dashboard['top_stores']) ?>
        </section>
    </main>
    <script src="/assets/js/global.js?v=admin-ui-20260608"></script>
</body>
</html>
<?php

function metricCard(string $label, string $value, string $iconClass = 'bi-bell'): string
{
    $icon = '<i class="bi ' . $iconClass . '" style="vertical-align: middle; margin-right: 6px; font-size: 1.1em;"></i>';
    return '<article class="metric-card"><span>' . $icon . htmlspecialchars($label) . '</span><strong>' . htmlspecialchars($value) . '</strong></article>';
}

function statusList(array $items, string $type = 'order'): string
{
    $html = '<div class="status-list">';
    $map = $type === 'order' ? ORDER_STATUS_MAP : PRODUCT_STATUS_MAP;

    foreach ($items as $label => $value) {
        $translatedLabel = $map[$label] ?? $label;
        $html .= '<div><span>' . htmlspecialchars((string) $translatedLabel) . '</span><strong>' . (int) $value . '</strong></div>';
    }

    return $html . '</div>';
}

function recentOrdersTable(array $orders, bool $showStore): string
{
    if (!$orders) {
        return '<p class="empty">Chưa có đơn hàng.</p>';
    }

    $storeColumn = $showStore ? '<th>Shop</th>' : '';
    $rows = '';

    foreach ($orders as $order) {
        $storeCell = $showStore ? '<td>' . htmlspecialchars((string) $order['store_name']) . '</td>' : '';
        $statusTranslated = ORDER_STATUS_MAP[$order['status']] ?? $order['status'];
        $rows .= '<tr>'
            . '<td>' . htmlspecialchars((string) $order['order_code']) . '</td>'
            . '<td>' . htmlspecialchars((string) $order['buyer_email']) . '</td>'
            . $storeCell
            . '<td><span class="badge">' . htmlspecialchars((string) $statusTranslated) . '</span></td>'
            . '<td>' . money((float) $order['final_amount']) . '</td>'
            . '<td>' . htmlspecialchars((string) $order['created_at']) . '</td>'
            . '</tr>';
    }

    return '<div class="table-wrap"><table class="data-table"><thead><tr><th>Mã đơn</th><th>Buyer</th>' . $storeColumn . '<th>Trạng thái</th><th>Giá trị</th><th>Ngày tạo</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

function topStoresTable(array $stores): string
{
    if (!$stores) {
        return '<p class="empty">Chưa có doanh thu đã giao.</p>';
    }

    $rows = '';

    foreach ($stores as $store) {
        $rows .= '<tr>'
            . '<td>' . htmlspecialchars((string) $store['store_name']) . '</td>'
            . '<td>' . (int) $store['orders_total'] . '</td>'
            . '<td>' . money((float) $store['revenue_total']) . '</td>'
            . '</tr>';
    }

    return '<div class="table-wrap"><table class="data-table"><thead><tr><th>Shop</th><th>Đơn đã giao</th><th>Doanh thu</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

function money(float $value): string
{
    return number_format($value, 0, ',', '.') . ' d';
}
