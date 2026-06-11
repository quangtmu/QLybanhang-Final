<?php
require_once __DIR__ . '/../../config/config.php';

$user = AuthMiddleware::requireLogin();
if ($user['user_type'] !== USER_TYPE_ADMIN) {
    die("Access denied");
}

$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    die("Missing order ID");
}

$order = AdminOrderModel::detail((int)$orderId);
if (!$order) {
    die("Order not found");
}

// Fetch order items (since AdminOrderModel::detail already includes items)
$items = $order['order_items'] ?? [];

$printTitle = "In Vận Đơn - " . $order['order_code'];
require_once BASE_PATH . '/public/includes/print_order_template.php';
