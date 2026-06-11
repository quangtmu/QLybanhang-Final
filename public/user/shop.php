<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_USER);

$slug = trim((string) ($_GET['slug'] ?? ''));
$store = $slug !== '' ? BuyerProductModel::storeProfile($slug) : null;
$filters = [
    'store_id' => $store['user_id'] ?? 0,
    'search' => $_GET['search'] ?? '',
    'sort' => $_GET['sort'] ?? 'sold',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$products = $store ? BuyerProductModel::listProducts($filters) : ['items' => [], 'pagination' => ['total' => 0]];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $store ? htmlspecialchars((string) $store['store_name']) : 'Shop' ?> - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="flex-grow pt-[72px] pb-20 lg:pb-8">
        <div class="max-w-container-max mx-auto px-4 md:px-margin-desktop">

        <?php if (!$store): ?>
            <div class="text-center py-16 mt-4">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-3">store_mall_directory</span>
                <h1 class="text-xl font-bold mb-1">Không tìm thấy shop</h1>
                <p class="text-on-surface-variant text-sm">Shop không tồn tại hoặc chưa có sản phẩm.</p>
                <a class="inline-flex items-center gap-1 mt-3 text-primary text-sm font-semibold" href="/user/home.php">
                    <span class="material-symbols-outlined text-[16px]">home</span>Về trang chủ
                </a>
            </div>
        <?php else: ?>
            <!-- Shop Profile Banner -->
            <section class="relative rounded-2xl overflow-hidden mt-2 mb-4" style="<?= !empty($store['banner_url']) ? 'background-image: url(' . htmlspecialchars((string)$store['banner_url']) . '); background-size: cover; background-position: center;' : '' ?>">
                <div class="bg-gradient-to-r from-slate-900/90 via-blue-900/80 to-blue-700/70 p-5 md:p-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl overflow-hidden bg-white flex items-center justify-center flex-shrink-0 shadow-lg border-2 border-white/20">
                            <?php if (!empty($store['logo_url'])): ?>
                                <img src="<?= htmlspecialchars((string) $store['logo_url']) ?>" alt="<?= htmlspecialchars((string) $store['store_name']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-primary text-[28px]">store</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-white min-w-0">
                            <h1 class="text-xl md:text-2xl font-bold mb-1 truncate"><?= htmlspecialchars((string) $store['store_name']) ?></h1>
                            <p class="text-white/70 text-xs md:text-sm line-clamp-1"><?= htmlspecialchars((string) (($store['description'] ?? '') ?: 'Shop đang bán hàng trên OmniSales.')) ?></p>
                            <div class="flex items-center gap-3 mt-2 text-xs text-white/80">
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px] fill text-buyer-orange">star</span><?= number_format((float)($store['rating'] ?? 0), 1) ?></span>
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">inventory_2</span><?= (int) $store['product_count'] ?> SP</span>
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">local_fire_department</span><?= (int) $store['sold_count'] ?> đã bán</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Filter & Products -->
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs text-on-surface-variant"><?= (int) $products['pagination']['total'] ?> sản phẩm</p>
                <form method="get" class="flex items-center gap-2">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars((string) $store['store_slug']) ?>">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[16px] text-outline">search</span>
                        <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm trong shop..." class="border border-border-subtle rounded-lg py-1.5 pl-8 pr-3 text-xs w-[160px] focus:border-primary focus:ring-2 focus:ring-primary/10">
                    </div>
                    <select name="sort" class="border border-border-subtle rounded-lg px-2 py-1.5 text-xs bg-white focus:border-primary">
                        <option value="sold" <?= $filters['sort'] === 'sold' ? 'selected' : '' ?>>Bán chạy</option>
                        <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                        <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Giá ↑</option>
                        <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Giá ↓</option>
                    </select>
                    <button type="submit" class="bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-primary-container transition-all">
                        <span class="material-symbols-outlined text-[16px]">filter_list</span>
                    </button>
                </form>
            </div>

            <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <?php if (!$products['items']): ?>
                    <div class="col-span-full text-center py-12 bg-white rounded-xl border border-border-subtle">
                        <span class="material-symbols-outlined text-[40px] text-outline-variant mb-2">inventory_2</span>
                        <p class="text-on-surface-variant text-sm font-semibold">Chưa có sản phẩm</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products['items'] as $product): ?>
                        <?php
                        $imgUrl = !empty($product['main_image_url']) ? StorageService::publicUrl($product['main_image_url']) : 'https://placehold.co/400x400/e2e8f0/64748b?text=No+Image';
                        $sold = (int)($product['sold_count'] ?? 0);
                        ?>
                        <div class="group bg-white rounded-xl overflow-hidden border border-border-subtle hover:shadow-md transition-all flex flex-col h-full hover:-translate-y-0.5">
                            <a href="/user/product-detail.php?id=<?= (int)$product['id'] ?>" class="block">
                                <div class="aspect-square bg-surface-container-low overflow-hidden relative">
                                    <img alt="<?= htmlspecialchars((string)$product['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?= htmlspecialchars((string)$imgUrl) ?>"/>
                                    <?php if ($sold > 0): ?>
                                    <div class="absolute bottom-2 left-2 bg-black/50 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[12px] fill">local_fire_department</span><?= $sold ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="p-3 flex flex-col flex-grow">
                                <h3 class="text-xs font-semibold text-on-surface mb-1 line-clamp-2 min-h-[32px]">
                                    <a href="/user/product-detail.php?id=<?= (int)$product['id'] ?>" class="hover:text-primary transition-colors"><?= htmlspecialchars((string)$product['name']) ?></a>
                                </h3>
                                <div class="mt-auto flex items-end justify-between pt-1">
                                    <span class="text-sm font-bold text-primary"><?= number_format((float)$product['base_price']) ?>đ</span>
                                    <a href="/user/product-detail.php?id=<?= (int)$product['id'] ?>" class="w-8 h-8 bg-primary text-white rounded-lg flex items-center justify-center hover:bg-primary-container transition-all shadow-sm active:scale-95">
                                        <span class="material-symbols-outlined text-[16px]">shopping_bag</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
    <script src="/assets/js/buyer-products.js?v=20260609-6"></script>
</body>
</html>
