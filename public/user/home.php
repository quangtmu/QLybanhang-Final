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
$activeFlashSale = class_exists('FlashSaleModel') ? FlashSaleModel::getActiveFlashSale() : null;
$fsProducts = $activeFlashSale ? FlashSaleModel::getProducts((int) $activeFlashSale['id']) : [];
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
        $activeBanners = !empty($banners) ? $banners : [[
            'image_url' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBW7_EvCkvIdVjHLS65rpNos2fjYvSm1R9Ocqz892ewc1OWxN2-j5zMuP0990yZH8jrGNXBX-yhScCba5VxSPhziwTWStP6GYO-30wuVFxGIzGUI3OC3kw4u0vU2koaExO364jAfeWZU4KdoNZAiqRqsR8qMoKbsjtFHtJcV0FyVIhfIxkxXmkpm5jqdR9n3nHDVdut2i0uiHrHsoLql43AkQuUs0e6iquHu5FZuKBujzi1DdwlsjxzlqjQFU7EKA64Eny8aq5pyvpY',
            'title' => 'Nâng Tầm Phong Cách',
            'link_url' => '/user/products.php',
            'description' => 'Bộ sưu tập cao cấp, thiết kế tối giản, chất lượng tối đa.'
        ]];
        ?>
        <section class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-6 relative">
            <div class="flex overflow-x-auto snap-x snap-mandatory no-scrollbar rounded-2xl w-full h-[220px] md:h-[320px] gap-4" style="scroll-behavior: smooth;" id="hero-slider">
                <?php foreach ($activeBanners as $banner): ?>
                    <?php 
                        $imgUrl = !empty($banner['image_url']) ? StorageService::publicUrl($banner['image_url']) : '';
                        $title = htmlspecialchars((string) ($banner['title'] ?? 'Nâng Tầm Phong Cách'));
                        $linkUrl = htmlspecialchars((string) (!empty($banner['link_url']) ? $banner['link_url'] : '/user/products.php'));
                    ?>
                    <div class="relative w-full flex-shrink-0 snap-center h-full group overflow-hidden rounded-2xl">
                        <img alt="<?= $title ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" src="<?= $imgUrl ?>"/>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                        <div class="absolute bottom-0 left-0 w-full p-5 md:p-8 flex flex-col items-start max-w-xl">
                            <span class="bg-white/15 backdrop-blur-sm text-white font-semibold text-[11px] px-3 py-1 rounded-full mb-2 border border-white/20 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px] fill">star</span>Khám Phá
                            </span>
                            <h1 class="text-white text-xl md:text-3xl font-bold mb-1 leading-tight"><?= $title ?></h1>
                            <?php if (!empty($banner['description'])): ?>
                            <p class="text-white/80 text-xs md:text-sm mb-3 line-clamp-2"><?= htmlspecialchars((string)$banner['description']) ?></p>
                            <?php endif; ?>
                            <a href="<?= $linkUrl ?>" class="bg-primary text-white text-xs font-semibold px-5 py-2 rounded-full hover:bg-primary-container transition-all shadow-lg flex items-center gap-1.5 mt-2">
                                Xem Ngay <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($activeBanners) > 1): ?>
                <button onclick="scrollHeroSlider(-1)" class="absolute left-6 md:left-8 top-1/2 -translate-y-1/2 w-8 h-8 md:w-10 md:h-10 bg-black/20 hover:bg-black/40 backdrop-blur-sm text-white rounded-full flex items-center justify-center transition-all z-10 shadow-md">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button onclick="scrollHeroSlider(1)" class="absolute right-6 md:right-8 top-1/2 -translate-y-1/2 w-8 h-8 md:w-10 md:h-10 bg-black/20 hover:bg-black/40 backdrop-blur-sm text-white rounded-full flex items-center justify-center transition-all z-10 shadow-md">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
                <script>
                    function scrollHeroSlider(direction) {
                        const slider = document.getElementById('hero-slider');
                        if (slider) {
                            const scrollAmount = slider.clientWidth + 16; // 16px gap
                            slider.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
                        }
                    }
                </script>
            <?php endif; ?>
        </section>

        <!-- Flash Sale Section -->
        <?php if ($activeFlashSale && !empty($fsProducts)): ?>
        <section class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-6">
            <div class="bg-gradient-to-r from-error to-orange-500 rounded-2xl p-4 md:p-6 text-white shadow-lg relative overflow-hidden">
                <!-- Background decoration -->
                <div class="absolute -right-10 -top-10 opacity-20">
                    <span class="material-symbols-outlined text-[120px] fill">bolt</span>
                </div>
                
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 relative z-10 gap-3">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl md:text-2xl font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined fill text-yellow-300">flash_on</span>
                            FLASH SALE
                        </h2>
                        <div class="flex items-center gap-1 bg-black/30 px-2 py-1 rounded-md text-sm font-mono font-bold" id="fs-countdown" data-endtime="<?= htmlspecialchars($activeFlashSale['end_date']) ?>">
                            <span class="bg-white text-error px-1.5 rounded" id="fs-h">00</span>:
                            <span class="bg-white text-error px-1.5 rounded" id="fs-m">00</span>:
                            <span class="bg-white text-error px-1.5 rounded" id="fs-s">00</span>
                        </div>
                    </div>
                    <a href="/user/products.php" class="text-white text-sm font-semibold hover:underline flex items-center gap-0.5">
                        Xem tất cả <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    </a>
                </div>

                <div class="flex overflow-x-auto gap-4 snap-x no-scrollbar pb-2 relative z-10">
                    <?php foreach ($fsProducts as $fsp): ?>
                        <?php 
                        $imgUrl = !empty($fsp['main_image_url']) ? StorageService::publicUrl($fsp['main_image_url']) : 'https://placehold.co/400x500/e2e8f0/64748b?text=No+Image';
                        $discountPercent = $fsp['base_price'] > 0 ? round((($fsp['base_price'] - $fsp['discount_price']) / $fsp['base_price']) * 100) : 0;
                        ?>
                        <div class="bg-white rounded-xl overflow-hidden w-[140px] md:w-[180px] flex-shrink-0 snap-start shadow-sm flex flex-col hover:-translate-y-1 transition-transform group">
                            <a href="<?= UiHelper::productUrl((int)$fsp['product_id']) ?>" class="block relative aspect-square">
                                <img src="<?= $imgUrl ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform" alt="">
                                <div class="absolute top-0 right-0 bg-[#fbbf24] text-error font-bold text-[11px] px-1.5 py-0.5 rounded-bl-lg">
                                    -<?= $discountPercent ?>%
                                </div>
                            </a>
                            <div class="p-2 flex flex-col flex-grow">
                                <div class="text-error font-bold text-sm md:text-base leading-tight mb-1"><?= number_format((float)$fsp['discount_price']) ?>đ</div>
                                <div class="text-on-surface-variant text-[10px] md:text-xs line-through mb-2"><?= number_format((float)$fsp['base_price']) ?>đ</div>
                                <div class="mt-auto relative bg-error-container h-4 rounded-full overflow-hidden flex items-center justify-center">
                                    <div class="absolute left-0 top-0 h-full bg-error rounded-full" style="width: 75%;"></div>
                                    <span class="text-white text-[9px] font-bold relative z-10 drop-shadow-md">Sắp hết</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <script>
                function updateCountdown() {
                    const el = document.getElementById('fs-countdown');
                    if (!el) return;
                    const endTime = new Date(el.dataset.endtime.replace(' ', 'T')).getTime();
                    const now = new Date().getTime();
                    const diff = endTime - now;
                    
                    if (diff < 0) {
                        el.innerHTML = '<span class="text-sm">Đã kết thúc</span>';
                        return;
                    }
                    
                    const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const s = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    document.getElementById('fs-h').innerText = h.toString().padStart(2, '0');
                    document.getElementById('fs-m').innerText = m.toString().padStart(2, '0');
                    document.getElementById('fs-s').innerText = s.toString().padStart(2, '0');
                }
                setInterval(updateCountdown, 1000);
                updateCountdown();
            </script>
        </section>
        <?php endif; ?>

        <!-- Gamification Banner -->
        <section class="max-w-container-max mx-auto px-4 md:px-margin-desktop mb-6 animate-fade-in-up">
            <button type="button" onclick="document.getElementById('lucky-wheel-modal').classList.remove('hidden'); document.getElementById('lucky-wheel-modal').classList.add('flex');" class="block w-full text-left bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-2xl p-4 shadow-md hover:shadow-lg transition-all hover:-translate-y-0.5 relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1/3 bg-white/10 skew-x-12 -translate-x-full group-hover:translate-x-[200%] transition-transform duration-1000"></div>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 md:w-14 md:h-14 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm shadow-inner flex-shrink-0 relative">
                        <span class="material-symbols-outlined text-[32px] md:text-[40px] text-yellow-300 drop-shadow-md group-hover:rotate-[360deg] transition-transform duration-1000">casino</span>
                    </div>
                    <div>
                        <h2 class="text-white text-lg md:text-xl font-bold mb-0.5 drop-shadow-sm">Vòng Quay May Mắn</h2>
                        <p class="text-white/90 text-xs md:text-sm">Quay trúng Voucher mua sắm và Điểm thưởng cực cháy!</p>
                    </div>
                    <div class="ml-auto bg-white text-fuchsia-600 px-3 py-1.5 rounded-full text-xs font-bold shadow-sm whitespace-nowrap">
                        Chơi ngay
                    </div>
                </div>
            </button>
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
                        
                        <a href="<?= UiHelper::productUrl((int)$product['id'], $product['slug'] ?? null) ?>" class="block">
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
                                <a href="<?= UiHelper::productUrl((int)$product['id'], $product['slug'] ?? null) ?>" class="hover:text-primary transition-colors">
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
                                <a href="<?= UiHelper::productUrl((int)$product['id'], $product['slug'] ?? null) ?>" class="w-8 h-8 bg-primary text-white rounded-lg flex items-center justify-center hover:bg-primary-container transition-all shadow-sm active:scale-95" title="Xem">
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

    <!-- Lucky Wheel Modal -->
    <div id="lucky-wheel-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl relative">
            <button onclick="document.getElementById('lucky-wheel-modal').classList.add('hidden'); document.getElementById('lucky-wheel-modal').classList.remove('flex');" class="absolute top-4 right-4 text-on-surface-variant hover:text-error bg-surface-container rounded-full w-8 h-8 flex items-center justify-center z-10 transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
            <div class="bg-gradient-to-br from-violet-500 to-fuchsia-500 p-6 text-center text-white pb-24 rounded-b-[40px]">
                <h2 class="text-2xl font-bold mb-1 drop-shadow-sm flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-yellow-300">stars</span> Vòng Quay May Mắn
                </h2>
                <p class="text-white/90 text-sm">Quay trúng Voucher - Săn deal cháy phố!</p>
            </div>
            <div class="px-6 pb-8 -mt-20">
                <div class="relative w-[280px] h-[280px] mx-auto bg-white rounded-full p-2 shadow-xl border-4 border-fuchsia-100 mb-6">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-0 h-0 border-l-[15px] border-l-transparent border-r-[15px] border-r-transparent border-t-[30px] border-t-fuchsia-600 z-20 drop-shadow-sm"></div>
                    <div id="wheel" class="w-full h-full rounded-full relative overflow-hidden transition-transform" style="transition: transform 4s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: conic-gradient(#ef4444 0deg 72deg, #f59e0b 72deg 144deg, #10b981 144deg 216deg, #3b82f6 216deg 288deg, #8b5cf6 288deg 360deg);">
                        <div style="position:absolute; top:0; left:0; right:0; bottom:0; border-radius:50%;">
                            <div style="position:absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(36deg) translateY(-85px); color:white; font-weight:800; font-size: 13px; text-shadow: 1px 1px 2px rgba(0,0,0,0.4);">5 Điểm</div>
                            <div style="position:absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(108deg) translateY(-85px); color:white; font-weight:800; font-size: 13px; text-shadow: 1px 1px 2px rgba(0,0,0,0.4);">May mắn</div>
                            <div style="position:absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(180deg) translateY(-85px); color:white; font-weight:800; font-size: 13px; text-shadow: 1px 1px 2px rgba(0,0,0,0.4);">Voucher 50K</div>
                            <div style="position:absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(252deg) translateY(-85px); color:white; font-weight:800; font-size: 13px; text-shadow: 1px 1px 2px rgba(0,0,0,0.4);">1 Điểm</div>
                            <div style="position:absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(324deg) translateY(-85px); color:white; font-weight:800; font-size: 13px; text-shadow: 1px 1px 2px rgba(0,0,0,0.4);">Voucher 20K</div>
                        </div>
                    </div>
                    <button id="spin-btn" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-16 h-16 bg-white border-4 border-fuchsia-500 rounded-full text-fuchsia-600 font-black text-sm shadow-md hover:scale-105 active:scale-95 transition-all z-10 flex items-center justify-center">
                        QUAY
                    </button>
                </div>
                <div id="lw-result-msg" class="text-center font-bold text-[15px] min-h-[32px] mt-6 mb-6 flex items-center justify-center px-4 rounded-lg"></div>
                <div class="text-center text-xs text-on-surface-variant bg-surface-container-low p-3 rounded-xl border border-border-subtle">
                    <p class="font-bold mb-1">Mỗi đơn hàng thành công = 1 lượt quay</p>
                    <p>Mua hàng thả ga, quay thưởng cực đã!</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
    <script>
        const lwBtn = document.getElementById('spin-btn');
        const lwWheel = document.getElementById('wheel');
        const lwResultMsg = document.getElementById('lw-result-msg');
        let lwIsSpinning = false;

        lwBtn.addEventListener('click', async () => {
            if (lwIsSpinning) return;
            lwIsSpinning = true;
            lwResultMsg.innerText = 'Đang quay...';
            lwResultMsg.className = "text-center font-bold text-[15px] min-h-[32px] mt-6 mb-6 flex items-center justify-center px-4 rounded-lg text-on-surface-variant animate-pulse";
            
            try {
                const response = await fetch('/api/lucky_wheel_spin.php', { method: 'POST' });
                const data = await response.json();
                
                if (!data.success) {
                    lwResultMsg.innerText = data.message;
                    lwResultMsg.className = "text-center font-bold text-[15px] min-h-[32px] mt-6 mb-6 flex items-center justify-center px-4 rounded-lg text-error bg-error/10";
                    lwIsSpinning = false;
                    return;
                }
                
                const targetDeg = 360 - (data.prize_index * 72 + 36);
                const extraSpins = 5 * 360; // Spin 5 full times
                const finalDeg = extraSpins + targetDeg;
                
                lwWheel.style.transform = `rotate(${finalDeg}deg)`;
                
                setTimeout(() => {
                    lwResultMsg.innerText = data.message;
                    lwResultMsg.className = "text-center font-bold text-[15px] min-h-[32px] mt-6 mb-6 flex items-center justify-center px-4 rounded-lg text-success bg-success/10 animate-bounce";
                    lwIsSpinning = false;
                    
                    if (data.prize_id !== 2) {
                        confetti({
                            particleCount: 100,
                            spread: 70,
                            origin: { y: 0.6 },
                            zIndex: 1000
                        });
                    }
                    
                    lwWheel.style.transition = 'none';
                    lwWheel.style.transform = `rotate(${targetDeg}deg)`;
                    setTimeout(() => { lwWheel.style.transition = 'transform 4s cubic-bezier(0.175, 0.885, 0.32, 1.275)'; }, 50);
                }, 4000);
                
            } catch (error) {
                console.error(error);
                lwResultMsg.innerText = "Có lỗi xảy ra, vui lòng thử lại!";
                lwResultMsg.className = "text-center font-bold text-[15px] min-h-[32px] mt-6 mb-6 flex items-center justify-center px-4 rounded-lg text-error bg-error/10";
                lwIsSpinning = false;
            }
        });
    </script>
</body>
</html>
