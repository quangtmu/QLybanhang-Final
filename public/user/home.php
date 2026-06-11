<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_USER);
$success = flash_success();
$banners = BannerModel::active();
$categories = array_slice(BuyerProductModel::activeCategories(), 0, 10);
$products = BuyerProductModel::listProducts(['limit' => 8, 'sort' => 'sold']);
$featuredStores = BuyerProductModel::featuredStores(4);
?>
<!DOCTYPE html>
<html class="light" lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>OmniSales - Trang chủ</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background">

    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="flex-grow pt-[72px] pb-20 lg:pb-8">
        <?php if ($success): ?>
            <div class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-3 animate-fade-in">
                <div class="bg-success/10 text-success p-3 rounded-lg font-medium text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span><?= htmlspecialchars($success) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <?php
        $heroImage = !empty($banners[0]['image_url']) ? StorageService::publicUrl($banners[0]['image_url']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuBW7_EvCkvIdVjHLS65rpNos2fjYvSm1R9Ocqz892ewc1OWxN2-j5zMuP0990yZH8jrGNXBX-yhScCba5VxSPhziwTWStP6GYO-30wuVFxGIzGUI3OC3kw4u0vU2koaExO364jAfeWZU4KdoNZAiqRqsR8qMoKbsjtFHtJcV0FyVIhfIxkxXmkpm5jqdR9n3nHDVdut2i0uiHrHsoLql43AkQuUs0e6iquHu5FZuKBujzi1DdwlsjxzlqjQFU7EKA64Eny8aq5pyvpY';
        ?>
        <section class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-6">
            <div class="relative w-full h-[220px] md:h-[320px] rounded-2xl overflow-hidden group">
                <img alt="Hero Banner" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" src="<?= htmlspecialchars((string) $heroImage) ?>"/>
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                <div class="absolute bottom-0 left-0 w-full p-5 md:p-8 flex flex-col items-start max-w-xl">
                    <span class="bg-white/15 backdrop-blur-sm text-white font-semibold text-[11px] px-3 py-1 rounded-full mb-2 border border-white/20 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px] fill">star</span>Hàng Mới Về
                    </span>
                    <h1 class="text-white text-xl md:text-3xl font-bold mb-1 leading-tight">Nâng Tầm Phong Cách</h1>
                    <p class="text-white/80 text-xs md:text-sm mb-3 line-clamp-2">Bộ sưu tập cao cấp, thiết kế tối giản, chất lượng tối đa.</p>
                    <a href="/user/products.php" class="bg-primary text-white text-xs font-semibold px-5 py-2 rounded-full hover:bg-primary-container transition-all shadow-lg flex items-center gap-1.5">
                        Khám phá <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Category Chips -->
        <?php if (!empty($categories)): ?>
        <section class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-6">
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
                <a href="/user/products.php" class="flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-primary text-white text-xs font-semibold flex-shrink-0 shadow-sm transition-all hover:shadow-md">
                    <span class="material-symbols-outlined text-[16px]">apps</span>Tất cả
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="/user/products.php?category_id=<?= (int)$cat['id'] ?>" class="flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-white border border-border-subtle hover:border-primary/30 hover:bg-primary/5 text-on-surface hover:text-primary text-xs font-semibold flex-shrink-0 transition-all shadow-sm" title="<?= htmlspecialchars((string)$cat['name']) ?>">
                        <span class="material-symbols-outlined text-[14px]">category</span>
                        <?= htmlspecialchars((string)$cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Products Grid -->
        <section class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-8">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-on-surface">Gợi Ý Cho Bạn</h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Sản phẩm được chọn lọc</p>
                </div>
                <a class="hidden md:flex items-center gap-0.5 text-primary text-xs font-semibold hover:underline" href="/user/products.php">
                    Xem tất cả <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </a>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <?php foreach ($products['items'] as $product): ?>
                    <?php 
                    $imgUrl = !empty($product['main_image_url']) ? StorageService::publicUrl($product['main_image_url']) : 'https://placehold.co/400x500/e2e8f0/64748b?text=No+Image';
                    $hasDiscount = !empty($product['discount_price']) && $product['discount_price'] < $product['base_price'];
                    $displayPrice = $hasDiscount ? $product['discount_price'] : $product['base_price'];
                    $sold = (int)($product['sold_count'] ?? 0);
                    ?>
                    <div class="group bg-white rounded-xl overflow-hidden border border-border-subtle hover:shadow-md transition-all duration-300 relative flex flex-col h-full hover:-translate-y-0.5">
                        <?php if ($hasDiscount): ?>
                        <div class="absolute top-2 left-2 z-10">
                            <span class="bg-error text-white text-[10px] font-bold px-2 py-0.5 rounded-md">SALE</span>
                        </div>
                        <?php endif; ?>
                        
                        <a href="/user/product-detail.php?id=<?= (int)$product['id'] ?>" class="block">
                            <div class="aspect-square bg-surface-container-low overflow-hidden relative">
                                <img alt="<?= htmlspecialchars((string)$product['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?= htmlspecialchars((string)$imgUrl) ?>"/>
                                <?php if ($sold > 0): ?>
                                <div class="absolute bottom-2 left-2 bg-black/50 backdrop-blur-sm text-white text-[10px] font-semibold px-2 py-0.5 rounded-full flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[12px] fill">local_fire_department</span><?= $sold ?> đã bán
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                        
                        <div class="p-3 flex flex-col flex-grow">
                            <h3 class="text-xs font-semibold text-on-surface mb-1 line-clamp-2 leading-relaxed min-h-[32px]">
                                <a href="/user/product-detail.php?id=<?= (int)$product['id'] ?>" class="hover:text-primary transition-colors">
                                    <?= htmlspecialchars((string)$product['name']) ?>
                                </a>
                            </h3>
                            
                            <div class="mt-auto flex items-end justify-between pt-1">
                                <div class="flex flex-col">
                                    <?php if ($hasDiscount): ?>
                                    <span class="text-on-surface-variant line-through text-[10px]"><?= number_format((float)$product['base_price']) ?>đ</span>
                                    <?php endif; ?>
                                    <span class="text-sm font-bold text-primary"><?= number_format((float)$displayPrice) ?>đ</span>
                                </div>
                                <a href="/user/product-detail.php?id=<?= (int)$product['id'] ?>" class="w-8 h-8 bg-primary text-white rounded-lg flex items-center justify-center hover:bg-primary-container transition-all shadow-sm active:scale-95" title="Xem">
                                    <span class="material-symbols-outlined text-[16px]">shopping_cart</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <a class="md:hidden mt-3 w-full flex items-center justify-center gap-1 text-primary text-xs font-semibold py-2.5 bg-primary/5 border border-primary/20 rounded-xl" href="/user/products.php">
                Xem tất cả <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </section>

        <!-- Featured Stores -->
        <?php if (!empty($featuredStores)): ?>
        <section class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg md:text-xl font-bold text-on-surface">Shop Nổi Bật</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <?php foreach ($featuredStores as $store): ?>
                    <?php
                    $slug = rawurlencode((string)$store['store_slug']);
                    $storeName = htmlspecialchars((string)$store['store_name']);
                    $logoUrl = htmlspecialchars((string)($store['logo_url'] ?? ''));
                    ?>
                    <a href="/user/shop.php?slug=<?= $slug ?>" class="group flex items-center gap-3 p-3 bg-white border border-border-subtle rounded-xl hover:border-primary/30 hover:shadow-md transition-all">
                        <div class="w-11 h-11 rounded-xl overflow-hidden bg-primary/5 flex items-center justify-center flex-shrink-0">
                            <?php if ($logoUrl): ?>
                                <img src="<?= $logoUrl ?>" alt="<?= $storeName ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-primary text-[20px]">store</span>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-bold text-on-surface truncate group-hover:text-primary transition-colors"><?= $storeName ?></h3>
                            <div class="flex items-center gap-2 mt-0.5 text-[11px] text-on-surface-variant">
                                <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[12px]">inventory_2</span><?= (int)$store['product_count'] ?></span>
                                <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[12px] fill text-buyer-orange">star</span><?= number_format((float)($store['rating'] ?? 0), 1) ?></span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-border-subtle mt-auto">
        <div class="max-w-container-max mx-auto px-4 md:px-margin-desktop py-6">
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
