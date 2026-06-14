<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_USER);
$cart = CartModel::getForBuyer((int) $user['id']);
?>
<!DOCTYPE html>
<html class="light" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Giỏ hàng - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background">
    
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="flex-grow pt-[72px] pb-20 lg:pb-8 w-full max-w-container-max mx-auto px-4 md:px-margin-desktop">
        <div id="cart-root">
            <?= renderCart($cart) ?>
        </div>
    </main>
    
    <script src="/assets/js/global.js?v=20260609-3"></script>
    <script src="/assets/js/buyer-cart.js?v=20260609-6"></script>
    
    <!-- Footer -->
    <footer class="bg-white border-t border-border-subtle mt-auto">
        <div class="max-w-container-max mx-auto px-4 md:px-margin-desktop py-5">
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
</body>
</html>
<?php

function renderCart(array $cart): string
{
    if (!$cart['items']) {  
        return '
        <div class="flex flex-col items-center justify-center py-16 bg-white rounded-xl border border-border-subtle text-center mt-4">
            <span class="material-symbols-outlined text-[48px] text-outline-variant mb-3">shopping_cart</span>
            <h2 class="text-lg font-bold text-on-surface mb-1">Giỏ hàng trống</h2>
            <p class="text-sm text-on-surface-variant mb-4">Chưa có sản phẩm nào.</p>
            <a href="/user/products.php" class="bg-primary text-white text-xs font-semibold px-5 py-2.5 rounded-lg hover:bg-primary-container transition-all shadow-sm flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">shopping_bag</span> Mua sắm ngay
            </a>
        </div>';
    }

    $groups = [];
    $totalCount = 0;
    foreach ($cart['items'] as $item) {
        $groups[(string) ($item['store_name'] ?: 'Shop')][] = $item;
        $totalCount += (int)$item['quantity'];
    }

    $html = '<div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mt-4">';
    
    // Header
    $html .= '<div class="col-span-1 lg:col-span-12 flex items-center justify-between">';
    $html .= '<div>';
    $html .= '<h1 class="text-xl font-bold text-on-background flex items-center gap-2"><span class="material-symbols-outlined text-[22px]">shopping_cart</span>Giỏ Hàng</h1>';
    $html .= '<p class="text-xs text-on-surface-variant mt-0.5">' . $totalCount . ' sản phẩm</p>';
    $html .= '</div></div>';

    // Left side items
    $html .= '<div class="col-span-1 lg:col-span-8 flex flex-col gap-3">';
    $html .= '<form id="cart-checkout-form" method="get" action="/user/orders.php" class="contents">';
    $html .= '<input type="hidden" name="tab" value="checkout">';
    
    foreach ($groups as $storeName => $items) {
        $html .= '<div class="bg-white border border-border-subtle rounded-xl p-4 flex flex-col gap-4 hover-elevate cart-store-card">';
        $html .= '<div class="flex items-center gap-2 border-b border-border-subtle pb-3">';
        $html .= '<input type="checkbox" class="cart-store-select w-4 h-4 text-primary rounded focus:ring-primary/50">';
        $html .= '<span class="material-symbols-outlined text-on-surface-variant text-[18px]">store</span>';
        $html .= '<h2 class="text-sm font-bold text-on-surface">' . htmlspecialchars($storeName) . '</h2>';
        $html .= '</div>';

        foreach ($items as $item) {
            $name = htmlspecialchars((string) $item['product_name']);
            $variant = htmlspecialchars(trim(implode(' / ', array_filter([
                $item['type_label'] ?? '',
                $item['color'] ?? '',
                $item['size'] ?? '',
            ]))) ?: 'Mặc định');
            $price = UiHelper::money($item['unit_price']);
            $subtotalRaw = (float) $item['subtotal'];
            $id = (int) $item['id'];
            $quantity = (int) $item['quantity'];
            $productId = (int) $item['product_id'];
            $image = htmlspecialchars((string) ($item['main_image_url'] ?? ''));
            $imageHtml = $image !== '' ? '<img src="' . StorageService::publicUrl($image) . '" alt="' . $name . '" class="w-full h-full object-cover">' : '<div class="w-full h-full flex items-center justify-center bg-surface-container-highest"><span class="material-symbols-outlined text-outline-variant text-[28px]">image</span></div>';
            $checked = $item['is_available'] ? ' checked' : '';
            $disabled = $item['is_available'] ? '' : ' disabled';

            $html .= <<<HTML
            <div class="flex gap-3 items-start cart-item-row" data-cart-item="{$id}" data-subtotal="{$subtotalRaw}" data-quantity="{$quantity}">
                <label class="flex items-center cursor-pointer mt-5">
                    <input type="checkbox" name="selected_cart_item_ids[]" value="{$id}" class="cart-item-checkbox w-4 h-4 text-primary rounded focus:ring-primary/50"{$checked}{$disabled}>
                </label>
                <div class="w-20 h-20 bg-surface-container-highest rounded-lg overflow-hidden flex-shrink-0">
                    <a href="/user/product-detail.php?id={$productId}">{$imageHtml}</a>
                </div>
                <div class="flex-grow min-w-0">
                    <h3 class="text-sm font-semibold text-on-surface line-clamp-1"><a href="/user/product-detail.php?id={$productId}" class="hover:text-primary transition-colors">{$name}</a></h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">{$variant}</p>
                    <div class="flex items-center justify-between mt-2">
                        <div class="flex items-center border border-border-subtle rounded-lg h-8 overflow-hidden">
                            <input class="cart-quantity w-12 bg-transparent border-none text-center text-xs font-semibold focus:ring-0 p-0" type="number" name="quantities[{$id}]" min="1" value="{$quantity}" data-item-id="{$id}">
                        </div>
                        <span class="text-sm font-bold text-on-surface">{$price}</span>
                    </div>
                </div>
                <button type="button" class="js-remove-cart text-on-surface-variant hover:text-error p-1 transition-colors cart-remove-button flex-shrink-0 mt-0.5" data-item-id="{$id}" title="Xoá">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            HTML;
        }
        $html .= '</div>';
    }
    $html .= '</form>';
    $html .= '</div>'; // End Left side

    // Order Summary (Right Sidebar)
    $totalPriceRaw = (float) ($cart['summary']['total'] ?? 0);
    $totalPrice = UiHelper::money($totalPriceRaw);
    
    $html .= <<<HTML
    <div class="col-span-1 lg:col-span-4">
        <div class="bg-white border border-border-subtle rounded-xl p-5 sticky top-[80px] hover-elevate">
            <h2 class="text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">receipt</span>Tổng đơn
            </h2>
            <div class="flex flex-col gap-3 text-sm text-on-surface-variant">
                <div class="flex justify-between">
                    <span>Tạm tính (<span id="cart-selected-count">0</span> SP)</span>
                    <span class="font-semibold text-on-surface" id="cart-selected-total">0đ</span>
                </div>
                <div class="flex justify-between">
                    <span>Phí vận chuyển</span>
                    <span class="text-on-surface-variant italic text-xs">Tính khi thanh toán</span>
                </div>
                <div class="border-t border-border-subtle pt-3 flex justify-between items-center">
                    <span class="font-bold text-on-surface">Tổng</span>
                    <span class="text-lg font-bold text-primary" id="cart-final-price">{$totalPrice}</span>
                </div>
            </div>
            <button type="submit" form="cart-checkout-form" id="cart-checkout-button" class="w-full mt-5 bg-primary text-white font-semibold text-sm py-3 rounded-xl hover:bg-primary-container transition-all flex justify-center items-center gap-2 shadow-sm active:scale-[0.98]">
                <span class="material-symbols-outlined text-[18px]">shopping_bag</span> Thanh toán
            </button>
            <p class="text-[11px] text-on-surface-variant text-center mt-3 flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-[12px]">lock</span>Bảo mật thanh toán
            </p>
        </div>
    </div>
    HTML;
    
    $html .= '</div>'; // End Grid

    return $html;
}
