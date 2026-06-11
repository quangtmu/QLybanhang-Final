<?php

declare(strict_types=1);

if (!function_exists('buyerStars')) {
    function buyerStars(float $rating, int $count = 0): string
    {
        $rating = max(0, min(5, $rating));
        $full = (int) floor($rating);
        $stars = str_repeat('★', $full) . str_repeat('☆', 5 - $full);
        $label = $count > 0 ? number_format($rating, 1, ',', '.') . ' (' . $count . ')' : number_format($rating, 1, ',', '.');

        return '<span class="buyer-stars" aria-label="' . htmlspecialchars($label) . ' sao"><span>' . $stars . '</span><small>' . htmlspecialchars($label) . '</small></span>';
    }
}

if (!function_exists('buyerCategoryMeta')) {
    function buyerCategoryMeta(string $name, int $index = 0): array
    {
        $normalized = mb_strtolower($name);
        $map = [
            'đồ chơi' => ['bi-controller', 'sun'],
            'thời trang' => ['bi-bag-heart', 'rose'],
            'đồ điện tử' => ['bi-phone', 'blue'],
            'nhà cửa' => ['bi-house-heart', 'green'],
            'vật dụng nhà bếp' => ['bi-cup-hot', 'amber'],
            'gia vị' => ['bi-basket', 'orange'],
            'đồ ăn' => ['bi-egg-fried', 'lime'],
            'làm đẹp' => ['bi-stars', 'pink'],
            'sách' => ['bi-book', 'violet'],
            'thể thao' => ['bi-bicycle', 'cyan'],
        ];

        foreach ($map as $needle => $meta) {
            if (str_contains($normalized, $needle)) {
                return ['icon' => $meta[0], 'tone' => $meta[1]];
            }
        }

        $fallbacks = [
            ['bi-grid-1x2', 'blue'],
            ['bi-shop-window', 'green'],
            ['bi-gift', 'rose'],
            ['bi-lightning-charge', 'amber'],
            ['bi-stars', 'violet'],
        ];

        $fallback = $fallbacks[$index % count($fallbacks)];

        return ['icon' => $fallback[0], 'tone' => $fallback[1]];
    }
}

if (!function_exists('buyerCategoryLevelLabel')) {
    function buyerCategoryLevelLabel(string $level): string
    {
        return match ($level) {
            CATEGORY_LEVEL_LARGE => '',
            CATEGORY_LEVEL_MEDIUM => 'Nhóm sản phẩm',
            CATEGORY_LEVEL_SMALL => 'Nhánh sản phẩm',
            default => 'Danh mục',
        };
    }
}

if (!function_exists('buyerProductCard')) {
    function buyerProductCard(array $product, string $context = 'grid'): string
    {
        $id = (int) $product['id'];
        $name = htmlspecialchars((string) $product['name']);
        $image = htmlspecialchars((string) ($product['main_image_url'] ?? ''));
        $code = htmlspecialchars((string) ($product['product_code'] ?? ''));
        $category = htmlspecialchars((string) ($product['category_name'] ?? 'Chưa phân loại'));
        $storeName = htmlspecialchars((string) (($product['store_name'] ?? '') ?: 'Shop'));
        $storeSlug = trim((string) ($product['store_slug'] ?? ''));
        $price = UiHelper::money($product['base_price'] ?? 0);
        $sold = (int) ($product['sold_count'] ?? 0);
        $hasVariants = (int) ($product['has_variants'] ?? 0) === 1;
        $storeHtml = $storeSlug !== ''
            ? '<a class="product-store" href="/user/shop.php?slug=' . rawurlencode($storeSlug) . '"><i class="bi bi-shop-window"></i><span>' . $storeName . '</span></a>'
            : '<span class="product-store"><i class="bi bi-shop-window"></i><span>' . $storeName . '</span></span>';
        $imageHtml = $image !== ''
            ? '<img src="' . $image . '" alt="' . $name . '">'
            : '<span><i class="bi bi-image"></i>Ảnh</span>';
        $metaCode = $code !== '' ? '<span class="product-code">#' . $code . '</span>' : '';
        $actionHtml = $hasVariants
            ? '<a class="button-link product-buy-link" href="/user/product-detail.php?id=' . $id . '"><i class="bi bi-bag-check"></i>Mua</a>'
            : '<button type="button" class="js-add-cart product-cart-button" data-product-id="' . $id . '"><i class="bi bi-cart-plus"></i>Thêm</button>';

        return <<<HTML
        <article class="product-card market-product-card" data-card-context="{$context}">
            <a class="product-image" href="/user/product-detail.php?id={$id}">
                {$imageHtml}
                <span class="product-badge"><i class="bi bi-fire"></i>{$sold}</span>
            </a>
            <div class="product-card-body">
                {$storeHtml}
                <h2><a href="/user/product-detail.php?id={$id}">{$name}</a></h2>
                <p class="product-meta">
                    <span>{$category}</span>
                    {$metaCode}
                </p>
                <div class="product-card-bottom">
                    <strong class="price-cell">{$price}</strong>
                    <span><i class="bi bi-box-seam"></i>{$sold}</span>
                </div>
                <div class="product-card-actions">
                    <a class="button-link product-detail-link" href="/user/product-detail.php?id={$id}" aria-label="Xem chi tiết">
                        <i class="bi bi-eye"></i>
                    </a>
                    {$actionHtml}
                </div>
            </div>
        </article>
HTML;
    }
}

if (!function_exists('buyerStoreCard')) {
    function buyerStoreCard(array $store): string
    {
        $slug = rawurlencode((string) $store['store_slug']);
        $name = htmlspecialchars((string) $store['store_name']);
        $description = htmlspecialchars((string) (($store['description'] ?? '') ?: 'Shop đang hoạt động trên OmniSales.'));
        $logo = htmlspecialchars((string) ($store['logo_url'] ?? ''));
        $productCount = (int) ($store['product_count'] ?? 0);
        $soldCount = (int) ($store['sold_count'] ?? 0);
        $orderCount = (int) ($store['order_count'] ?? 0);
        $rating = (float) ($store['rating'] ?? 0);
        $ratingHtml = buyerStars($rating);
        $avatar = $logo !== ''
            ? '<img src="' . $logo . '" alt="' . $name . '">'
            : '<i class="bi bi-shop-window"></i>';

        return <<<HTML
        <a class="buyer-shop-card" href="/user/shop.php?slug={$slug}">
            <span class="buyer-shop-avatar">{$avatar}</span>
            <span class="buyer-shop-info">
                <strong>{$name}</strong>
                <small>{$description}</small>
                <span class="buyer-shop-stats">
                    <span><i class="bi bi-box-seam"></i>{$productCount}</span>
                    <span><i class="bi bi-fire"></i>{$soldCount}</span>
                    <span><i class="bi bi-receipt"></i>{$orderCount}</span>
                </span>
                {$ratingHtml}
            </span>
        </a>
HTML;
    }
}
