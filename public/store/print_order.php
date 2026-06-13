<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

$user = PermissionMiddleware::requireModule(MODULE_ORDERS);

$orderId = (int) ($_GET['id'] ?? 0);
if (!$orderId) {
    die("Missing order ID");
}

$order = StoreOrderModel::detail($user, $orderId);

if (!$order) {
    die("Order not found or access denied");
}

// Fetch order items
$items = $order['order_items'] ?? [];

$printTitle = "In Vận Đơn - " . $order['order_code'];
require_once BASE_PATH . '/public/includes/print_order_template.php';
