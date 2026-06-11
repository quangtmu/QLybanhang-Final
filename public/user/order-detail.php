<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_USER);
$buyerId = (int) $user['id'];
$orderId = (int) ($_GET['id'] ?? 0);

if (!$orderId) {
    header('Location: /user/orders.php?tab=manage');
    exit;
}

$order = OrderModel::detail($buyerId, $orderId);

if (!$order) {
    $_SESSION['flash_error'] = 'Không tìm thấy đơn hàng.';
    header('Location: /user/orders.php?tab=manage');
    exit;
}

// Map order statuses to timeline steps
$steps = [
    ORDER_STATUS_PENDING => ['label' => 'Đã đặt', 'icon' => 'receipt', 'time' => ''],
    ORDER_STATUS_PROCESSING => ['label' => 'Xác nhận', 'icon' => 'verified', 'time' => ''],
    'waiting_pickup' => ['label' => 'Chờ lấy', 'icon' => 'inventory_2', 'time' => ''],
    ORDER_STATUS_DELIVERING => ['label' => 'Đang giao', 'icon' => 'local_shipping', 'time' => ''],
    ORDER_STATUS_DELIVERED => ['label' => 'Hoàn tất', 'icon' => 'check_circle', 'time' => ''],
];

// Extract times from logs
$logs = $order['logs'] ?? [];
foreach ($logs as $log) {
    if ($log['new_status'] === ORDER_STATUS_PENDING) $steps[ORDER_STATUS_PENDING]['time'] = $log['created_at'];
    if ($log['new_status'] === ORDER_STATUS_PROCESSING) $steps[ORDER_STATUS_PROCESSING]['time'] = $log['created_at'];
    if ($log['new_status'] === ORDER_STATUS_SHIPPED) $steps['waiting_pickup']['time'] = $log['created_at'];
    if ($log['new_status'] === ORDER_STATUS_DELIVERING) $steps[ORDER_STATUS_DELIVERING]['time'] = $log['created_at'];
    if ($log['new_status'] === ORDER_STATUS_DELIVERED) $steps[ORDER_STATUS_DELIVERED]['time'] = $log['created_at'];
}
if (!$steps[ORDER_STATUS_PENDING]['time']) {
    $steps[ORDER_STATUS_PENDING]['time'] = $order['created_at'];
}

$currentStatus = $order['status'];
$stepKeys = array_keys($steps);
$currentIndex = array_search($currentStatus, $stepKeys);
if ($currentIndex === false) {
    if (in_array($currentStatus, [ORDER_STATUS_CANCELLED, ORDER_STATUS_REFUNDED])) {
        $currentIndex = -1;
    } else if ($currentStatus === ORDER_STATUS_SHIPPED) {
        $currentIndex = 2;
    } else {
        $currentIndex = 0;
    }
}
$csrfToken = AuthController::csrfToken();

$reviewsLookup = [];
if ($currentStatus === ORDER_STATUS_DELIVERED) {
    $stmt = getDB()->prepare('SELECT order_item_id, rating, comment FROM product_reviews WHERE order_id = :order_id');
    $stmt->execute([':order_id' => $orderId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rev) {
        $reviewsLookup[(int)$rev['order_item_id']] = $rev;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn #<?= htmlspecialchars($order['order_code']) ?> - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="bg-background min-h-screen text-on-surface font-body-md pt-[64px] pb-20 lg:pb-8">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="max-w-4xl mx-auto px-4 mt-4">
        
        <!-- Back + Order Code -->
        <div class="flex items-center justify-between mb-4">
            <a href="/user/orders.php?tab=manage" class="text-on-surface-variant hover:text-primary flex items-center text-xs font-semibold transition-colors">
                <span class="material-symbols-outlined text-[18px] mr-0.5">arrow_back</span>Đơn hàng
            </a>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-on-surface-variant font-mono">#<?= htmlspecialchars($order['order_code']) ?></span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-bold text-[11px] <?php
                    if ($currentIndex === -1) echo 'bg-error/10 text-error';
                    elseif ($currentStatus === ORDER_STATUS_DELIVERED) echo 'bg-success/10 text-success';
                    elseif ($currentStatus === ORDER_STATUS_PENDING) echo 'bg-buyer-orange/10 text-buyer-orange';
                    else echo 'bg-primary/10 text-primary';
                ?>">
                    <?= htmlspecialchars(UiHelper::statusLabel($currentStatus)) ?>
                </span>
            </div>
        </div>

        <!-- Timeline -->
        <div class="bg-white rounded-xl border border-border-subtle p-4 md:p-6 mb-3">
            <?php if ($currentIndex === -1): ?>
                <div class="text-center py-4">
                    <span class="material-symbols-outlined text-error text-[32px] mb-1">cancel</span>
                    <p class="text-error font-bold text-sm">Đơn hàng đã bị hủy</p>
                </div>
            <?php else: ?>
                <div class="relative flex justify-between items-start mx-auto max-w-lg">
                    <!-- Background line -->
                    <div class="absolute top-5 left-[10%] right-[10%] h-0.5 bg-border-subtle z-0"></div>
                    <!-- Active line -->
                    <?php $progressWidth = ($currentIndex / max(1, count($steps) - 1)) * 80; ?>
                    <div class="absolute top-5 left-[10%] h-0.5 bg-success z-0 transition-all duration-500" style="width: <?= $progressWidth ?>%;"></div>

                    <?php $i = 0; foreach ($steps as $key => $step): ?>
                        <?php
                            $isActive = $i <= $currentIndex;
                            $isCurrent = $i === $currentIndex;
                        ?>
                        <div class="relative z-10 flex flex-col items-center flex-1">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mb-1.5 shadow-sm <?= $isActive ? 'bg-success text-white' : 'bg-white border-2 border-border-subtle text-on-surface-variant' ?> <?= $isCurrent ? 'ring-2 ring-success/30' : '' ?>">
                                <span class="material-symbols-outlined text-[18px] <?= $isActive ? 'fill' : '' ?>"><?= $step['icon'] ?></span>
                            </div>
                            <span class="text-[11px] font-bold text-center <?= $isActive ? 'text-on-surface' : 'text-on-surface-variant' ?>"><?= $step['label'] ?></span>
                            <span class="text-[10px] text-on-surface-variant text-center mt-0.5"><?= $step['time'] ? date('H:i d/m', strtotime($step['time'])) : '' ?></span>
                        </div>
                    <?php $i++; endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-2 mb-3">
            <?php if ($currentStatus === ORDER_STATUS_PENDING): ?>
                <form method="post" action="/user/orders.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="form_action" value="cancel">
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                    <input type="hidden" name="cancel_reason" value="Người mua hủy đơn">
                    <button type="submit" class="px-3 py-1.5 bg-white border border-border-subtle text-on-surface text-xs font-semibold rounded-lg hover:bg-surface-container transition-all flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">close</span>Hủy đơn
                    </button>
                </form>
            <?php endif; ?>
            <?php if (in_array($currentStatus, [ORDER_STATUS_SHIPPED, ORDER_STATUS_DELIVERING], true)): ?>
                <form method="post" action="/user/orders.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="form_action" value="received">
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                    <button type="submit" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-container transition-all flex items-center gap-1 shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">check</span>Đã nhận
                    </button>
                </form>
            <?php endif; ?>
            <a href="/chat.php" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-container transition-all flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-[14px]">chat</span>Liên hệ shop
            </a>
        </div>

        <!-- Address -->
        <div class="bg-white px-4 py-4 rounded-xl border border-border-subtle mb-3">
            <h2 class="text-sm font-bold text-on-surface mb-2 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-primary">location_on</span>Địa chỉ nhận
            </h2>
            <?php
                $address = $order['shipping_address_data'] ?? [];
                $addressStr = implode(', ', array_filter([$address['address_line'] ?? '', $address['ward'] ?? '', $address['district'] ?? '', $address['province'] ?? '']));
            ?>
            <div class="text-sm text-on-surface-variant">
                <span class="font-bold text-on-surface"><?= htmlspecialchars($order['buyer_name'] ?? 'Khách hàng') ?></span>
                <br><span class="text-xs"><?= htmlspecialchars($order['shipping_address'] ?? $addressStr) ?></span>
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-xl border border-border-subtle mb-3 overflow-hidden">
            <div class="px-4 py-3 flex items-center gap-2 border-b border-border-subtle bg-surface-container-low/30">
                <span class="material-symbols-outlined text-[16px] text-on-surface-variant">store</span>
                <strong class="text-sm text-on-surface"><?= htmlspecialchars($order['store_name']) ?></strong>
            </div>

            <div class="px-4 py-2">
                <?php foreach ($order['items'] as $item): ?>
                    <?php
                        $name = htmlspecialchars((string) $item['product_name']);
                        $variant = htmlspecialchars(trim(implode(', ', array_filter([$item['type_label'] ?? '', $item['color'] ?? '', $item['size'] ?? '']))) ?: '');
                        $price = number_format((float) $item['unit_price'], 0, ',', '.');
                        $quantity = (int) $item['quantity'];
                        $imageUrl = !empty($item['main_image_url']) ? htmlspecialchars((string) $item['main_image_url']) : 'https://placehold.co/60x60/e2e8f0/64748b?text=SP';
                    ?>
                    <div class="flex py-2.5 border-b border-dashed border-border-subtle last:border-0 gap-3 items-center">
                        <div class="w-14 h-14 flex-shrink-0 border border-border-subtle rounded-lg overflow-hidden">
                            <img src="<?= $imageUrl ?>" alt="" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm text-on-surface line-clamp-1 font-medium"><?= $name ?></h3>
                            <p class="text-xs text-on-surface-variant mt-0.5"><?= $variant ?> · x<?= $quantity ?></p>
                        </div>
                        <span class="text-sm font-bold text-on-surface flex-shrink-0"><?= $price ?>₫</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary -->
            <div class="bg-surface-container-low/30 border-t border-border-subtle">
                <div class="px-4 py-3">
                    <div class="flex justify-end">
                        <div class="w-full sm:w-1/2 md:w-2/5 space-y-2 text-sm">
                            <div class="flex justify-between text-on-surface-variant">
                                <span>Tiền hàng</span>
                                <span class="text-on-surface font-medium"><?= number_format((float) $order['total_amount'], 0, ',', '.') ?>₫</span>
                            </div>
                            <div class="flex justify-between text-on-surface-variant">
                                <span>Phí ship</span>
                                <span class="text-on-surface font-medium"><?= number_format((float) $order['shipping_fee'], 0, ',', '.') ?>₫</span>
                            </div>
                            <div class="flex justify-between text-on-surface-variant">
                                <span>Giảm giá</span>
                                <span class="text-on-surface font-medium">-<?= number_format((float) $order['discount_amount'], 0, ',', '.') ?>₫</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-border-subtle">
                                <span class="font-bold text-on-surface">Thành tiền</span>
                                <span class="text-xl font-bold text-primary"><?= number_format((float) $order['final_amount'], 0, ',', '.') ?>₫</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 flex items-center justify-between text-xs text-on-surface-variant border-t border-border-subtle bg-surface-container-low/50">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">payments</span>Thanh toán</span>
                    <span class="font-bold text-on-surface">COD</span>
                </div>
            </div>
        </div>

        <!-- Review Section (only for delivered orders) -->
        <?php if ($currentStatus === ORDER_STATUS_DELIVERED && !empty($order['items'])): ?>
        <div class="bg-white rounded-xl border border-border-subtle p-4 mb-4">
            <h3 class="text-sm font-bold text-on-surface mb-3 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-buyer-orange">star</span>Đánh giá sản phẩm
            </h3>
            <div class="space-y-3">
                <?php foreach ($order['items'] as $item): ?>
                    <?php $existingReview = $reviewsLookup[(int)$item['id']] ?? null; ?>
                    <?php if ($existingReview): ?>
                        <div class="bg-surface-container-low rounded-xl p-3 border border-border-subtle">
                            <p class="text-sm font-semibold text-on-surface mb-2"><?= htmlspecialchars((string)$item['product_name']) ?></p>
                            <div class="flex items-center gap-1 mb-2 text-buyer-orange">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="material-symbols-outlined text-[14px] <?= $i <= $existingReview['rating'] ? 'fill' : 'text-outline-variant' ?>">star</span>
                                <?php endfor; ?>
                            </div>
                            <?php if ($existingReview['comment']): ?>
                                <p class="text-sm text-on-surface-variant italic">"<?= htmlspecialchars($existingReview['comment']) ?>"</p>
                            <?php endif; ?>
                            <div class="mt-2 text-xs text-success font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span> Đã đánh giá
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/user/orders.php" class="bg-surface-container-low rounded-xl p-3 border border-border-subtle">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="form_action" value="review">
                            <input type="hidden" name="order_item_id" value="<?= (int)$item['id'] ?>">
                            <p class="text-sm font-semibold text-on-surface mb-2"><?= htmlspecialchars((string)$item['product_name']) ?></p>
                            <div class="flex items-center gap-3 mb-2">
                                <select name="rating" class="border border-border-subtle rounded-lg px-2 py-1 text-sm bg-white">
                                    <option value="5">⭐⭐⭐⭐⭐</option>
                                    <option value="4">⭐⭐⭐⭐</option>
                                    <option value="3">⭐⭐⭐</option>
                                    <option value="2">⭐⭐</option>
                                    <option value="1">⭐</option>
                                </select>
                            </div>
                            <textarea name="comment" rows="2" placeholder="Nhận xét..." class="w-full border border-border-subtle rounded-lg px-3 py-2 text-sm mb-2 focus:border-primary focus:ring-2 focus:ring-primary/10"></textarea>
                            <button type="submit" class="px-3 py-1.5 bg-buyer-orange text-white text-xs font-semibold rounded-lg hover:bg-buyer-orange/90 transition-all flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">send</span>Gửi đánh giá
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</body>
</html>
