<?php
// OmniSales Buyer Header - Vietnamese - Compact & Icon-focused
$cartCount = 0;
if (!empty($user) && class_exists('CartModel')) {
    $cart = CartModel::getForBuyer((int) $user['id']);
    $cartCount = $cart['summary']['total_quantity'] ?? 0;
}
?>
<!-- Compact Header -->
<nav class="bg-white/90 backdrop-blur-lg border-b border-border-subtle fixed top-0 w-full z-50 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
    <div class="max-w-[1280px] mx-auto px-4 md:px-6 h-14 flex items-center justify-between gap-3">
        
        <!-- Logo -->
        <a href="/user/home.php" class="flex items-center gap-2 text-primary font-bold text-base tracking-tight flex-shrink-0">
            <span class="material-symbols-outlined text-[22px]">storefront</span>
            <span class="hidden sm:inline">OmniSales</span>
        </a>

        <!-- Search -->
        <div class="hidden md:block flex-1 max-w-md mx-4">
            <form action="/user/products.php" method="GET" class="relative">
                <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" 
                       class="w-full bg-surface-container-low border border-border-subtle rounded-full py-2 pl-10 pr-4 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 text-sm transition-all"
                       placeholder="Tìm sản phẩm...">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
            </form>
        </div>

        <!-- Right Menu -->
        <div class="flex items-center gap-1">
            <!-- Nav Links (Desktop) -->
            <div class="hidden lg:flex items-center gap-0.5">
                <a href="/user/home.php" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Trang chủ">
                    <span class="material-symbols-outlined text-[20px]">home</span>
                </a>
                <a href="/user/products.php" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Sản phẩm">
                    <span class="material-symbols-outlined text-[20px]">grid_view</span>
                </a>
                <?php 
                $sellerLink = '/login.php';
                if (!empty($user)) {
                    $userType = $user['type'] ?? '';
                    if (in_array($userType, [USER_TYPE_STORE_APPROVED, USER_TYPE_STORE_EMPLOYEE], true)) {
                        $sellerLink = '/store/dashboard.php';
                    } else {
                        $sellerLink = '/user/store-registration.php';
                    }
                }
                ?>
                <a href="<?= htmlspecialchars($sellerLink) ?>" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Kênh Người Bán">
                    <span class="material-symbols-outlined text-[20px]">store</span>
                </a>
                <a href="/user/orders.php" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Đơn hàng">
                    <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                </a>
            </div>

            <div class="h-5 w-px bg-border-subtle hidden lg:block mx-1"></div>

            <!-- Actions -->
            <div class="flex items-center gap-0.5">
                <!-- Cart -->
                <a href="/user/cart.php" class="relative p-2 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Giỏ hàng">
                    <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 bg-error text-white text-[9px] font-bold w-[18px] h-[18px] rounded-full flex items-center justify-center shadow-sm">
                            <?= min($cartCount, 9) ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- Chat -->
                <a href="/chat.php" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Tin nhắn">
                    <span class="material-symbols-outlined text-[20px]">chat</span>
                </a>

                <!-- Notifications -->
                <a href="/notifications.php" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Thông báo">
                    <span class="material-symbols-outlined text-[20px]">notifications</span>
                </a>

                <!-- Mobile search -->
                <a href="/user/products.php" class="md:hidden p-2 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Tìm kiếm">
                    <span class="material-symbols-outlined text-[20px]">search</span>
                </a>

                <?php if (empty($user)): ?>
                    <a href="/login.php" class="ml-1 px-3 py-1.5 text-xs font-semibold text-on-surface-variant hover:text-primary border border-border-subtle rounded-lg hover:bg-primary/5 transition-all">Đăng nhập</a>
                    <a href="/register.php" class="px-3 py-1.5 text-xs font-semibold bg-primary text-white rounded-lg hover:bg-primary-container transition-all shadow-sm">Đăng ký</a>
                <?php else: ?>
                    <!-- User Menu -->
                    <div class="relative group cursor-pointer ml-1">
                        <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center text-primary font-bold text-xs hover:bg-primary/20 transition-all ring-2 ring-transparent hover:ring-primary/20">
                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                        </div>
                        
                        <!-- Dropdown -->
                        <div class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-lg border border-border-subtle opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                            <div class="p-3 border-b border-border-subtle bg-surface-container-low/50">
                                <div class="font-bold text-sm truncate text-on-surface"><?= htmlspecialchars((string)$user['username']) ?></div>
                                <div class="text-xs text-on-surface-variant mt-0.5 truncate"><?= htmlspecialchars((string)($user['email'] ?? '')) ?></div>
                            </div>
                            <div class="py-1">
                                <a href="/profile.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-on-surface hover:bg-surface-container-low hover:text-primary transition-all">
                                    <span class="material-symbols-outlined text-[18px]">person</span> Hồ sơ
                                </a>
                                <a href="/user/orders.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-on-surface hover:bg-surface-container-low hover:text-primary transition-all">
                                    <span class="material-symbols-outlined text-[18px]">receipt_long</span> Đơn hàng
                                </a>
                                <a href="/user/invoices.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-on-surface hover:bg-surface-container-low hover:text-primary transition-all">
                                    <span class="material-symbols-outlined text-[18px]">description</span> Hóa đơn
                                </a>
                                <div class="h-px bg-border-subtle my-1"></div>
                                <a href="/logout.php" class="flex items-center gap-2.5 px-3 py-2 text-sm text-error hover:bg-error/5 transition-all">
                                    <span class="material-symbols-outlined text-[18px]">logout</span> Đăng xuất
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Bottom Nav -->
<nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-border-subtle z-40 safe-bottom">
    <div class="flex items-center justify-around h-14 max-w-lg mx-auto">
        <a href="/user/home.php" class="flex flex-col items-center gap-0.5 text-on-surface-variant hover:text-primary transition-colors py-1 px-3">
            <span class="material-symbols-outlined text-[20px]">home</span>
            <span class="text-[10px] font-semibold">Trang chủ</span>
        </a>
        <a href="/user/products.php" class="flex flex-col items-center gap-0.5 text-on-surface-variant hover:text-primary transition-colors py-1 px-3">
            <span class="material-symbols-outlined text-[20px]">grid_view</span>
            <span class="text-[10px] font-semibold">Sản phẩm</span>
        </a>
        <a href="/user/orders.php" class="flex flex-col items-center gap-0.5 text-on-surface-variant hover:text-primary transition-colors py-1 px-3">
            <span class="material-symbols-outlined text-[20px]">receipt_long</span>
            <span class="text-[10px] font-semibold">Đơn hàng</span>
        </a>
        <a href="/profile.php" class="flex flex-col items-center gap-0.5 text-on-surface-variant hover:text-primary transition-colors py-1 px-3">
            <span class="material-symbols-outlined text-[20px]">person</span>
            <span class="text-[10px] font-semibold">Tài khoản</span>
        </a>
    </div>
</nav>
