<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_USER);
$buyerId = (int) $user['id'];
$errors = [];
$success = flash_success();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        $formAction = (string) ($_POST['form_action'] ?? '');
        $orderId = (int) ($_POST['order_id'] ?? 0);

        try {
            if ($formAction === 'checkout') {
                $orders = OrderModel::create($buyerId, $_POST);
                $_SESSION['flash_success'] = 'Đã đặt ' . count($orders) . ' đơn hàng thành công!';
                header('Location: /user/orders.php?tab=manage');
                exit;
            }

            if ($formAction === 'cancel') {
                OrderModel::cancel($buyerId, $orderId, (string) ($_POST['cancel_reason'] ?? ''));
                $_SESSION['flash_success'] = 'Đã hủy đơn hàng.';
                header('Location: /user/orders.php?tab=manage');
                exit;
            }

            if ($formAction === 'received') {
                OrderModel::markReceived($buyerId, $orderId);
                $_SESSION['flash_success'] = 'Đã xác nhận nhận hàng.';
                header('Location: /user/orders.php?tab=manage');
                exit;
            }

            if ($formAction === 'review') {
                ReviewModel::save(
                    $buyerId,
                    (int) ($_POST['order_item_id'] ?? 0),
                    (int) ($_POST['rating'] ?? 5),
                    (string) ($_POST['comment'] ?? '')
                );
                $_SESSION['flash_success'] = 'Đã lưu đánh giá sản phẩm.';
                header('Location: /user/orders.php?tab=manage');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? '',
    'sort_dir' => $_GET['sort_dir'] ?? '',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$cart = CartModel::getForBuyer($buyerId);
$result = OrderModel::paginateForBuyer($buyerId, $filters);
$orders = $result['items'];
$pagination = $result['pagination'];
$csrfToken = AuthController::csrfToken();
$activeOrderTab = (($_GET['tab'] ?? '') === 'checkout' || isset($_GET['checkout'])) ? 'checkout' : 'manage';
$checkoutContext = buildCheckoutContext($cart, $_GET);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="flex-grow pt-[72px] pb-20 lg:pb-8">
        <div class="max-w-5xl mx-auto px-4 md:px-6">

        <?php if ($success): ?>
            <div class="bg-success/10 text-success p-3 rounded-lg font-medium text-sm mt-3 flex items-center gap-2 animate-fade-in">
                <span class="material-symbols-outlined text-[18px]">check_circle</span><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="bg-error/10 text-error p-3 rounded-lg text-sm mt-3">
                <?php foreach ($errors as $message): ?>
                    <p class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><?= htmlspecialchars((string) $message) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Tab Switcher -->
        <div class="flex gap-2 mt-4 mb-4">
            <a href="/user/orders.php?tab=manage" class="flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold transition-all <?= $activeOrderTab === 'manage' ? 'bg-primary text-white shadow-sm' : 'bg-white border border-border-subtle text-on-surface-variant hover:text-primary hover:border-primary/30' ?>">
                <span class="material-symbols-outlined text-[16px]">receipt_long</span>Đơn hàng
            </a>
            <a href="/user/orders.php?tab=checkout" class="flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold transition-all <?= $activeOrderTab === 'checkout' ? 'bg-primary text-white shadow-sm' : 'bg-white border border-border-subtle text-on-surface-variant hover:text-primary hover:border-primary/30' ?>">
                <span class="material-symbols-outlined text-[16px]">shopping_cart_checkout</span>Thanh toán
            </a>
        </div>

        <?php if ($activeOrderTab === 'checkout'): ?>
        <!-- Checkout Section -->
        <section class="bg-white rounded-xl border border-border-subtle p-5 mb-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-lg font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">shopping_cart_checkout</span>Đặt hàng
                    </h1>
                    <p class="text-xs text-on-surface-variant mt-0.5">Đơn tách theo từng shop</p>
                </div>
                <a class="text-xs text-primary font-semibold flex items-center gap-1 hover:underline" href="/user/cart.php">
                    <span class="material-symbols-outlined text-[16px]">shopping_cart</span>Giỏ hàng
                </a>
            </div>
            <?php if (!$checkoutContext['items']): ?>
                <div class="text-center py-10">
                    <span class="material-symbols-outlined text-[40px] text-outline-variant mb-2">shopping_bag</span>
                    <p class="text-on-surface-variant text-sm font-semibold">Chưa có sản phẩm để đặt</p>
                    <a class="inline-flex items-center gap-1 mt-3 text-primary text-xs font-semibold" href="/user/products.php">
                        <span class="material-symbols-outlined text-[16px]">search</span>Tìm sản phẩm
                    </a>
                </div>
            <?php else: ?>
                <?= renderCheckoutCart($checkoutContext) ?>
                <form method="post" class="mt-4 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="form_action" value="checkout">
                    <?= renderCheckoutHiddenInputs($checkoutContext) ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface">Người nhận <span class="text-error">*</span></span>
                            <input type="text" name="receiver_name" value="<?= htmlspecialchars($user['full_name']) ?>" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface">Điện thoại <span class="text-error">*</span></span>
                            <input type="tel" name="receiver_phone" value="<?= htmlspecialchars((string) ($user['phone'] ?? '')) ?>" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface">Địa chỉ <span class="text-error">*</span></span>
                            <input type="text" name="address_line" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface-variant">Phường/Xã</span>
                            <input type="text" name="ward" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface-variant">Quận/Huyện</span>
                            <input type="text" name="district" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface-variant">Tỉnh/Thành</span>
                            <input type="text" name="province" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                        </label>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="bg-surface-container-low rounded-xl p-3 border border-border-subtle">
                            <p class="text-xs font-bold text-on-surface mb-2">Vận chuyển</p>
                            <label class="flex items-center gap-2 text-sm mb-1 cursor-pointer"><input type="radio" name="shipping_method" value="standard" checked class="text-primary"> Tiêu chuẩn</label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="radio" name="shipping_method" value="express" class="text-primary"> Nhanh</label>
                        </div>
                        <div class="bg-surface-container-low rounded-xl p-3 border border-border-subtle">
                            <p class="text-xs font-bold text-on-surface mb-2">Thanh toán</p>
                            <label class="flex items-center gap-2 text-sm mb-1 cursor-pointer"><input type="radio" name="payment_method" value="cod" checked class="text-primary"> COD</label>
                            <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="radio" name="payment_method" value="bank_transfer" class="text-primary"> Chuyển khoản</label>
                        </div>
                    </div>
                    <label class="block">
                        <span class="text-xs font-bold text-on-surface-variant">Ghi chú</span>
                        <textarea name="note" rows="2" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Ghi chú cho shop..."></textarea>
                    </label>
                    <button type="submit" class="w-full sm:w-auto bg-primary text-white font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-primary-container transition-all shadow-sm flex items-center justify-center gap-2 active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>Đặt hàng
                    </button>
                </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($activeOrderTab === 'manage'): ?>
        <section>
            <!-- Status Filter Tabs -->
            <div class="flex gap-1.5 overflow-x-auto no-scrollbar mb-3">
                <a href="/user/orders.php?tab=manage" class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= $filters['status'] === '' ? 'bg-primary text-white shadow-sm' : 'bg-white border border-border-subtle text-on-surface-variant hover:text-primary hover:border-primary/30' ?>">Tất cả</a>
                <?php foreach ([ORDER_STATUS_PENDING, ORDER_STATUS_PROCESSING, ORDER_STATUS_DELIVERING, ORDER_STATUS_DELIVERED, ORDER_STATUS_CANCELLED] as $statusTab): ?>
                    <a href="/user/orders.php?tab=manage&status=<?= htmlspecialchars($statusTab) ?>" class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= $filters['status'] === $statusTab ? 'bg-primary text-white shadow-sm' : 'bg-white border border-border-subtle text-on-surface-variant hover:text-primary hover:border-primary/30' ?>">
                        <?= htmlspecialchars(UiHelper::statusLabel($statusTab)) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search -->
            <div class="mb-4">
                <form method="get" class="flex items-center bg-white rounded-xl border border-border-subtle px-3 py-2 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/10 transition-all">
                    <input type="hidden" name="tab" value="manage">
                    <input type="hidden" name="status" value="<?= htmlspecialchars((string) $filters['status']) ?>">
                    <span class="material-symbols-outlined text-[18px] text-outline mr-2">search</span>
                    <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm theo tên shop, mã đơn, sản phẩm..." class="flex-1 outline-none text-sm bg-transparent border-none focus:ring-0 p-0">
                </form>
            </div>

            <?= renderOrders($orders, $csrfToken, $buyerId) ?>
            
            <div class="mt-4">
                <?php include BASE_PATH . '/public/includes/pagination.php'; ?>
            </div>
        </section>
        <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-border-subtle mt-auto">
        <div class="max-w-container-max mx-auto px-4 py-5">
            <div class="flex flex-col md:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">storefront</span>
                    <span class="font-bold text-sm text-on-surface">OmniSales</span>
                </div>
                <nav class="flex flex-wrap gap-4 text-xs text-on-surface-variant">
                    <a class="hover:text-primary transition-colors" href="/user/support.php">Hỗ trợ</a>
                    <a class="hover:text-primary transition-colors" href="/user/policy-shipping.php">Giao hàng</a>
                    <a class="hover:text-primary transition-colors" href="/user/policy-return.php">Đổi trả</a>
                    <a class="hover:text-primary transition-colors" href="/user/policy-terms.php">Điều khoản</a>
                </nav>
                <span class="text-[11px] text-on-surface-variant">© 2026 OmniSales</span>
            </div>
        </div>
    </footer>

    <script src="/assets/js/global.js?v=20260609-3"></script>
</body>
</html>

<?php

function buildCheckoutContext(array $cart, array $query): array
{
    $buyNowProductId = (int) ($query['buy_now_product_id'] ?? 0);

    if ($buyNowProductId > 0) {
        $item = buildBuyNowCheckoutItem($buyNowProductId, (int) ($query['variant_id'] ?? 0), (int) ($query['quantity'] ?? 1));

        return [
            'source' => 'buy_now',
            'items' => $item ? [$item] : [],
            'summary' => checkoutSummary($item ? [$item] : []),
        ];
    }

    $selectedIds = normalizeCheckoutIds($query['selected_cart_item_ids'] ?? []);
    $availableItems = array_values(array_filter($cart['items'], fn (array $item): bool => !empty($item['is_available'])));

    if (!$selectedIds) {
        $selectedIds = array_map(fn (array $item): int => (int) $item['id'], $availableItems);
    }

    $selectedLookup = array_fill_keys($selectedIds, true);
    $items = array_values(array_filter($availableItems, fn (array $item): bool => isset($selectedLookup[(int) $item['id']])));

    return [
        'source' => 'cart',
        'selected_cart_item_ids' => $selectedIds,
        'items' => $items,
        'summary' => checkoutSummary($items),
    ];
}

function buildBuyNowCheckoutItem(int $productId, int $variantId, int $quantity): ?array
{
    $product = BuyerProductModel::detail($productId);
    if (!$product) {
        return null;
    }

    $quantity = max(1, $quantity);
    $variant = null;

    if (!empty($product['variants'])) {
        foreach ($product['variants'] as $candidate) {
            if ((int) $candidate['id'] === $variantId) {
                $variant = $candidate;
                break;
            }
        }

        if (!$variant && (int) ($product['has_variants'] ?? 0) === 1) {
            return null;
        }
    }

    $unitPrice = $variant ? (float) $variant['price'] : (float) $product['base_price'];

    return [
        'id' => 0,
        'product_id' => (int) $product['id'],
        'variant_id' => $variant ? (int) $variant['id'] : null,
        'product_name' => (string) $product['name'],
        'product_code' => (string) $product['product_code'],
        'main_image_url' => (string) ($product['main_image_url'] ?? ''),
        'store_name' => (string) ($product['store_name'] ?: 'Shop'),
        'type_label' => $variant['type_label'] ?? '',
        'color' => $variant['color'] ?? '',
        'size' => $variant['size'] ?? '',
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'subtotal' => $unitPrice * $quantity,
        'is_available' => true,
    ];
}

function normalizeCheckoutIds($value): array
{
    $values = is_array($value) ? $value : [$value];
    $ids = [];

    foreach ($values as $candidate) {
        $id = (int) $candidate;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function checkoutSummary(array $items): array
{
    $quantity = 0;
    $total = 0.0;

    foreach ($items as $item) {
        $quantity += (int) $item['quantity'];
        $total += (float) $item['subtotal'];
    }

    return [
        'item_count' => count($items),
        'available_quantity' => $quantity,
        'total' => $total,
    ];
}

function renderCheckoutHiddenInputs(array $checkout): string
{
    if (($checkout['source'] ?? '') === 'buy_now') {
        $item = $checkout['items'][0] ?? null;
        if (!$item) {
            return '';
        }

        $productId = (int) $item['product_id'];
        $variantId = $item['variant_id'] !== null ? (int) $item['variant_id'] : '';
        $quantity = (int) $item['quantity'];

        return <<<HTML
                    <input type="hidden" name="items[0][product_id]" value="{$productId}">
                    <input type="hidden" name="items[0][variant_id]" value="{$variantId}">
                    <input type="hidden" name="items[0][quantity]" value="{$quantity}">
HTML;
    }

    $html = '';
    foreach (($checkout['selected_cart_item_ids'] ?? []) as $itemId) {
        $html .= '<input type="hidden" name="selected_cart_item_ids[]" value="' . (int) $itemId . '">' . "\n";
    }

    return $html;
}

function renderCheckoutCart(array $checkout): string
{
    $html = '<div class="space-y-2 mb-3">';

    foreach ($checkout['items'] as $item) {
        $name = htmlspecialchars((string) $item['product_name']);
        $store = htmlspecialchars((string) ($item['store_name'] ?: 'Shop'));
        $variant = htmlspecialchars(trim(implode(' / ', array_filter([
            $item['type_label'] ?? '',
            $item['color'] ?? '',
            $item['size'] ?? '',
        ]))) ?: 'Mặc định');
        $quantity = (int) $item['quantity'];
        $subtotal = number_format((float) $item['subtotal'], 0, ',', '.');
        $image = htmlspecialchars((string) ($item['main_image_url'] ?? ''));
        $imageHtml = $image !== '' ? '<img src="' . $image . '" alt="' . $name . '" class="w-full h-full object-cover rounded-lg">' : '<div class="w-full h-full flex items-center justify-center bg-surface-container-highest rounded-lg"><span class="material-symbols-outlined text-outline-variant">image</span></div>';

        $html .= <<<HTML
        <div class="flex items-center gap-3 p-3 bg-surface-container-low rounded-xl border border-border-subtle">
            <div class="w-14 h-14 flex-shrink-0 overflow-hidden">{$imageHtml}</div>
            <div class="flex-1 min-w-0">
                <p class="text-[11px] text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">store</span>{$store}</p>
                <p class="text-sm font-semibold text-on-surface truncate">{$name}</p>
                <p class="text-xs text-on-surface-variant">{$variant} · SL {$quantity}</p>
            </div>
            <span class="text-sm font-bold text-primary flex-shrink-0">{$subtotal}đ</span>
        </div>
HTML;
    }

    $total = number_format((float) $checkout['summary']['total'], 0, ',', '.');
    $html .= '</div>';
    $html .= '<div class="flex justify-between items-center p-3 bg-primary/5 rounded-xl border border-primary/10"><span class="text-sm font-bold text-on-surface">Tổng tạm tính</span><span class="text-lg font-bold text-primary">' . $total . 'đ</span></div>';

    return $html;
}

function renderOrders(array $orders, string $csrfToken, int $buyerId): string
{
    if (!$orders) {
        return '<div class="bg-white p-12 flex flex-col items-center justify-center rounded-xl border border-border-subtle text-center">
            <span class="material-symbols-outlined text-[48px] text-outline-variant mb-3">receipt_long</span>
            <p class="text-on-surface-variant text-sm font-semibold">Chưa có đơn hàng</p>
        </div>';
    }

    $html = '<div class="space-y-3">';

    foreach ($orders as $order) {
        $orderCode = htmlspecialchars((string) $order['order_code']);
        $store = htmlspecialchars((string) ($order['store_name'] ?: 'Shop'));
        $statusLabel = htmlspecialchars(UiHelper::statusLabel((string) $order['status']));
        $finalAmount = number_format((float) $order['final_amount'], 0, ',', '.');
        $orderId = (int) $order['id'];
        
        // Status styling
        $statusClasses = 'text-secondary bg-secondary/10';
        $statusIcon = 'local_shipping';
        if ($order['status'] === ORDER_STATUS_DELIVERED) {
            $statusClasses = 'text-success bg-success/10';
            $statusIcon = 'check_circle';
        } elseif ($order['status'] === ORDER_STATUS_CANCELLED) {
            $statusClasses = 'text-error bg-error/10';
            $statusIcon = 'cancel';
        } elseif ($order['status'] === ORDER_STATUS_PENDING) {
            $statusClasses = 'text-buyer-orange bg-buyer-orange/10';
            $statusIcon = 'schedule';
        }

        $html .= <<<HTML
        <article class="bg-white rounded-xl border border-border-subtle overflow-hidden hover-elevate">
            <!-- Header -->
            <div class="px-4 py-3 flex justify-between items-center border-b border-border-subtle bg-surface-container-low/30">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant">store</span>
                    <strong class="text-sm text-on-surface">{$store}</strong>
                </div>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold {$statusClasses}">
                    <span class="material-symbols-outlined text-[14px]">{$statusIcon}</span>{$statusLabel}
                </span>
            </div>

            <!-- Items - clickable -->
            <a href="/user/order-detail.php?id={$orderId}" class="block px-4 py-2 hover:bg-surface-container-low/30 transition-colors">
HTML;

        foreach ($order['items'] as $item) {
            $name = htmlspecialchars((string) $item['product_name']);
            $variant = htmlspecialchars(trim(implode(', ', array_filter([
                $item['type_label'] ?? '',
                $item['color'] ?? '',
                $item['size'] ?? '',
            ]))) ?: '');
            $price = number_format((float) $item['unit_price'], 0, ',', '.');
            $quantity = (int) $item['quantity'];
            
            $imageUrl = !empty($item['main_image_url']) ? htmlspecialchars((string) $item['main_image_url']) : 'https://placehold.co/60x60/e2e8f0/64748b?text=SP';

            $html .= <<<HTML
                <div class="flex py-2 border-b border-dashed border-border-subtle last:border-0 gap-3 items-center">
                    <div class="w-14 h-14 flex-shrink-0 border border-border-subtle rounded-lg overflow-hidden bg-white">
                        <img src="{$imageUrl}" alt="" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm text-on-surface line-clamp-1 font-medium">{$name}</h3>
                        <p class="text-xs text-on-surface-variant mt-0.5">{$variant} · x{$quantity}</p>
                    </div>
                    <span class="text-sm font-bold text-on-surface flex-shrink-0">{$price}₫</span>
                </div>
HTML;
        }

        $html .= <<<HTML
            </a>

            <!-- Footer -->
            <div class="px-4 py-3 bg-surface-container-low/20 border-t border-border-subtle flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-on-surface-variant">Tổng:</span>
                    <span class="text-lg font-bold text-primary">{$finalAmount}₫</span>
                </div>
                
                <div class="flex flex-wrap gap-2">
HTML;
        
        if ($order['status'] === ORDER_STATUS_PENDING) {
            $html .= <<<HTML
                    <form method="post" class="inline-block">
                        <input type="hidden" name="csrf_token" value="{$csrfToken}">
                        <input type="hidden" name="form_action" value="cancel">
                        <input type="hidden" name="order_id" value="{$orderId}">
                        <input type="hidden" name="cancel_reason" value="Người mua hủy đơn">
                        <button type="submit" class="px-3 py-1.5 bg-white border border-border-subtle text-on-surface text-xs font-semibold rounded-lg hover:bg-surface-container transition-all flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">close</span>Hủy
                        </button>
                    </form>
                    <a href="/chat.php" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-container transition-all flex items-center gap-1 shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">chat</span>Liên hệ
                    </a>
HTML;
        } elseif (in_array($order['status'], [ORDER_STATUS_SHIPPED, ORDER_STATUS_DELIVERING], true)) {
            $html .= <<<HTML
                    <form method="post" class="inline-block">
                        <input type="hidden" name="csrf_token" value="{$csrfToken}">
                        <input type="hidden" name="form_action" value="received">
                        <input type="hidden" name="order_id" value="{$orderId}">
                        <button type="submit" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-container transition-all flex items-center gap-1 shadow-sm">
                            <span class="material-symbols-outlined text-[14px]">check</span>Đã nhận
                        </button>
                    </form>
                    <a href="/chat.php" class="px-3 py-1.5 bg-white border border-border-subtle text-on-surface text-xs font-semibold rounded-lg hover:bg-surface-container transition-all flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">chat</span>Liên hệ
                    </a>
HTML;
        } elseif ($order['status'] === ORDER_STATUS_DELIVERED) {
            // Get first product for rebuy link
            $firstProductId = (int)($order['items'][0]['product_id'] ?? 0);
            $html .= <<<HTML
                    <a href="/user/product-detail.php?id={$firstProductId}" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-container transition-all flex items-center gap-1 shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">replay</span>Mua lại
                    </a>
                    <a href="/user/order-detail.php?id={$orderId}" class="px-3 py-1.5 bg-buyer-orange/10 text-buyer-orange border border-buyer-orange/30 text-xs font-semibold rounded-lg hover:bg-buyer-orange/20 transition-all flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">star</span>Đánh giá
                    </a>
                    <a href="/chat.php" class="px-3 py-1.5 bg-white border border-border-subtle text-on-surface text-xs font-semibold rounded-lg hover:bg-surface-container transition-all flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">chat</span>Liên hệ
                    </a>
HTML;
        } else {
            $html .= <<<HTML
                    <a href="/chat.php" class="px-3 py-1.5 bg-white border border-border-subtle text-on-surface text-xs font-semibold rounded-lg hover:bg-surface-container transition-all flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">chat</span>Liên hệ
                    </a>
HTML;
        }

        $html .= <<<HTML
                </div>
            </div>
        </article>
HTML;
    }

    $html .= '</div>';
    return $html;
}
