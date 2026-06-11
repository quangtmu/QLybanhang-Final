<?php
require_once __DIR__ . '/../../config/config.php';

use App\Models\User;
use App\Models\OrderModel;
use App\Models\ProductManagementModel;

$user = User::getLoggedInUser();
if (!$user) {
    die("Access denied");
}

$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    die("Missing order ID");
}

// In store, fetch the order. But we must ensure the order belongs to the store.
$orders = OrderModel::getStoreOrders($user, ['limit' => 1000]); // Fetch store's orders
$order = null;
foreach ($orders as $o) {
    if ((int)$o['id'] === (int)$orderId) {
        $order = $o;
        break;
    }
}

if (!$order) {
    die("Order not found or access denied");
}

// Fetch order items
$items = OrderModel::getItems((int)$orderId);

$printTitle = "In Vận Đơn - " . $order['order_code'];
require_once BASE_PATH . '/public/includes/print_order_template.php';
