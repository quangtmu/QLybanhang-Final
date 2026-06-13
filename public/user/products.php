<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_USER);
$categorySlug = (string) ($_GET['category_slug'] ?? '');
$categoryId = $_GET['category_id'] ?? '';
if ($categorySlug !== '') {
    $stmt = getDB()->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $categorySlug]);
    $foundId = $stmt->fetchColumn();
    if ($foundId) {
        $categoryId = (int) $foundId;
    }
}

$filters = [
    'search' => $_GET['search'] ?? ($_GET['q'] ?? ''),
    'category_id' => $categoryId,
    'tag_id' => $_GET['tag_id'] ?? '',
    'min_price' => $_GET['min_price'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
    'sort' => $_GET['sort'] ?? 'newest',
    'page' => $_GET['page'] ?? 1,
];
$products = BuyerProductModel::listProducts($filters);
$categories = BuyerProductModel::activeCategories();
$tags = BuyerProductModel::activeTags();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản phẩm - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="flex-grow pt-[72px] pb-20 lg:pb-8">
        <div class="max-w-container-max mx-auto px-4 md:px-margin-desktop">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4 mt-2">
            <div>
                <h1 class="text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">grid_view</span>Sản phẩm
                </h1>
                <p class="text-xs text-on-surface-variant mt-0.5" id="product-result-count"><?= (int) $products['pagination']['total'] ?> sản phẩm</p>
            </div>
            <a class="text-xs text-primary font-semibold flex items-center gap-1" href="/user/home.php">
                <span class="material-symbols-outlined text-[16px]">home</span>Trang chủ
            </a>
        </div>

        <!-- Filter -->
        <form class="bg-white rounded-xl border border-border-subtle p-3 mb-4" id="product-filter-form">
            <div class="flex flex-wrap gap-2">
                <div class="flex-1 min-w-[200px] relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] text-outline">search</span>
                    <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm sản phẩm..." class="w-full border border-border-subtle rounded-lg py-2 pl-9 pr-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all">
                </div>
                <select name="category_id" class="border border-border-subtle rounded-lg px-3 py-2 text-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 min-w-[140px]">
                    <option value="">Danh mục</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (string) $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="tag_id" class="border border-border-subtle rounded-lg px-3 py-2 text-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 min-w-[120px] hidden sm:block">
                    <option value="">Tag</option>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?= (int) $tag['id'] ?>" <?= (string) $filters['tag_id'] === (string) $tag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tag['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="sort" class="border border-border-subtle rounded-lg px-3 py-2 text-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 min-w-[120px]">
                    <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                    <option value="sold" <?= $filters['sort'] === 'sold' ? 'selected' : '' ?>>Bán chạy</option>
                    <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Giá ↑</option>
                    <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Giá ↓</option>
                </select>
                <button type="submit" class="bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-primary-container transition-all shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">filter_list</span>Lọc
                </button>
            </div>
        </form>

        <!-- Product Grid -->
        <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3" id="product-grid">
            <?php if (!$products['items']): ?>
                <div class="col-span-full text-center py-12 bg-white rounded-xl border border-border-subtle">
                    <span class="material-symbols-outlined text-[40px] text-outline-variant mb-2">search_off</span>
                    <p class="text-on-surface-variant text-sm font-semibold">Chưa có sản phẩm phù hợp</p>
                </div>
            <?php else: ?>
                <?php foreach ($products['items'] as $product): ?>
                    <?php
                    $imgUrl = !empty($product['main_image_url']) ? StorageService::publicUrl($product['main_image_url']) : 'https://placehold.co/400x400/e2e8f0/64748b?text=No+Image';
                    $sold = (int)($product['sold_count'] ?? 0);
                    $hasVariants = (int)($product['has_variants'] ?? 0) === 1;
                    ?>
                    <div class="group bg-white rounded-xl overflow-hidden border border-border-subtle hover:shadow-md transition-all duration-300 flex flex-col h-full hover:-translate-y-0.5">
                        <a href="<?= UiHelper::productUrl((int)$product['id'], $product['slug'] ?? null) ?>" class="block">
                            <div class="aspect-square bg-surface-container-low overflow-hidden relative">
                                <img alt="<?= htmlspecialchars((string)$product['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?= htmlspecialchars((string)$imgUrl) ?>"/>
                                <?php if ($sold > 0): ?>
                                <div class="absolute bottom-2 left-2 bg-black/50 backdrop-blur-sm text-white text-[10px] font-semibold px-2 py-0.5 rounded-full flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[12px] fill">local_fire_department</span><?= $sold ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="p-3 flex flex-col flex-grow">
                            <p class="text-[11px] text-on-surface-variant mb-0.5 flex items-center gap-0.5 truncate">
                                <span class="material-symbols-outlined text-[12px]">store</span>
                                <?= htmlspecialchars((string)($product['store_name'] ?? 'Shop')) ?>
                            </p>
                            <h3 class="text-xs font-semibold text-on-surface mb-1 line-clamp-2 leading-relaxed min-h-[32px]">
                                <a href="<?= UiHelper::productUrl((int)$product['id'], $product['slug'] ?? null) ?>" class="hover:text-primary transition-colors">
                                    <?= htmlspecialchars((string)$product['name']) ?>
                                </a>
                            </h3>
                            <div class="mt-auto flex items-end justify-between pt-1">
                                <span class="text-sm font-bold text-primary"><?= number_format((float)$product['base_price']) ?>đ</span>
                                <?php if ($hasVariants): ?>
                                    <a href="<?= UiHelper::productUrl((int)$product['id'], $product['slug'] ?? null) ?>" class="w-8 h-8 bg-primary text-white rounded-lg flex items-center justify-center hover:bg-primary-container transition-all shadow-sm active:scale-95" title="Chọn mua">
                                        <span class="material-symbols-outlined text-[16px]">shopping_bag</span>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="js-add-cart w-8 h-8 bg-primary text-white rounded-lg flex items-center justify-center hover:bg-primary-container transition-all shadow-sm active:scale-95" data-product-id="<?= (int)$product['id'] ?>" title="Thêm giỏ">
                                        <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

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
    <script src="/assets/js/buyer-products.js?v=20260613-1"></script>
</body>
</html>
