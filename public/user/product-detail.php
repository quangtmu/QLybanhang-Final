<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_USER);

$id = (int) ($_GET['id'] ?? 0);
$slug = (string) ($_GET['slug'] ?? '');
$product = null;

if ($slug !== '') {
    $product = BuyerProductModel::detailBySlug($slug);
    if ($product) {
        $id = (int) $product['id'];
    }
} elseif ($id > 0) {
    $product = BuyerProductModel::detail($id);
}

if (!$product) {
    header('Location: /user/products.php');
    exit;
}

$relatedProducts = $product ? BuyerProductModel::relatedProducts($product, 8) : [];
$reviewSummary = $product ? ReviewModel::summaryForProduct((int) $product['id']) : ['avg_rating' => 0.0, 'review_count' => 0];
$reviews = $product ? ReviewModel::listForProduct((int) $product['id'], 8) : [];
$galleryImages = [];
if ($product) {
    $galleryImages = array_filter(array_merge(
        [(string) ($product['main_image_url'] ?? '')],
        json_decode((string) ($product['images'] ?? '[]'), true) ?: []
    ));
    $galleryImages = array_values(array_unique($galleryImages));
}

$activeFlashSale = class_exists('FlashSaleModel') ? FlashSaleModel::getActiveFlashSale() : null;
$fsProduct = null;
if ($activeFlashSale && $product) {
    $fsProducts = FlashSaleModel::getProducts((int) $activeFlashSale['id']);
    foreach ($fsProducts as $fsp) {
        if ((int)$fsp['product_id'] === (int)$product['id']) {
            $fsProduct = $fsp;
            break;
        }
    }
}
$displayPrice = $fsProduct ? (float) $fsProduct['discount_price'] : (float) $product['base_price'];
$hasFlashSale = $fsProduct !== null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= $product ? htmlspecialchars((string) $product['name']) : 'Sản phẩm' ?> - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="text-on-background font-body-md pb-20 lg:pb-0 pt-[64px]">

    <?php include __DIR__ . '/_tailwind_header.php'; ?>

<main class="max-w-[1280px] mx-auto px-4 md:px-6 mt-4">
    <?php if (!$product): ?>
        <div class="py-16 text-center">
            <span class="material-symbols-outlined text-[48px] text-outline-variant mb-3">search_off</span>
            <h1 class="text-xl font-bold">Không tìm thấy sản phẩm</h1>
            <p class="text-on-surface-variant text-sm mt-1">Sản phẩm không tồn tại hoặc chưa được duyệt.</p>
            <a href="/user/products.php" class="inline-flex items-center gap-1 mt-4 text-primary text-sm font-semibold">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>Về danh sách
            </a>
        </div>
    <?php else: ?>

    <!-- Breadcrumb -->
    <div class="hidden md:flex items-center gap-1.5 text-xs text-on-surface-variant mb-4">
        <a class="hover:text-primary transition-colors" href="/user/home.php">Trang chủ</a>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <a class="hover:text-primary transition-colors" href="/user/products.php">Sản phẩm</a>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <span class="text-on-surface font-medium"><?= htmlspecialchars((string) $product['category_name'] ?? 'Danh mục') ?></span>
    </div>

    <div class="flex flex-col md:flex-row gap-6 bg-white md:p-5 rounded-none md:rounded-xl md:border md:border-border-subtle md:shadow-sm">
        
        <!-- Left: Product Image Gallery -->
        <div class="w-full md:w-1/2 flex flex-col gap-2">
            <div class="w-full aspect-square bg-surface-container-low md:rounded-xl overflow-hidden relative group" id="main-image-container">
                <?php $mainImg = !empty($product['main_image_url']) ? $product['main_image_url'] : 'https://placehold.co/800x800/e2e8f0/64748b?text=No+Image'; ?>
                <img id="main-product-image" src="<?= htmlspecialchars((string)$mainImg) ?>" alt="<?= htmlspecialchars((string) $product['name']) ?>" class="w-full h-full object-cover object-center"/>
            </div>
            
            <?php if (count($galleryImages) > 1): ?>
            <div class="hidden md:flex gap-2 overflow-x-auto no-scrollbar py-1" id="thumbnail-container">
                <?php foreach ($galleryImages as $idx => $img): ?>
                <div class="w-16 h-16 flex-shrink-0 rounded-lg border-2 <?= $idx === 0 ? 'border-primary' : 'border-border-subtle hover:border-outline-variant' ?> overflow-hidden cursor-pointer transition-colors thumb-item" onclick="changeMainImage('<?= htmlspecialchars((string)$img) ?>', this)">
                    <img src="<?= htmlspecialchars((string)$img) ?>" class="w-full h-full object-cover"/>
                </div>
                <?php endforeach; ?>
            </div>
            <script>
                function changeMainImage(url, element) {
                    document.getElementById('main-product-image').src = url;
                    document.querySelectorAll('#thumbnail-container .thumb-item').forEach(el => {
                        el.classList.remove('border-primary');
                        el.classList.add('border-border-subtle');
                    });
                    element.classList.remove('border-border-subtle');
                    element.classList.add('border-primary');
                }
            </script>
            <?php endif; ?>
        </div>

        <!-- Right: Product Info -->
        <div class="w-full md:w-1/2 flex flex-col px-4 md:px-0 pb-4 md:pb-0">
            <form id="detail-add-cart-form" class="flex flex-col h-full">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                <!-- Title & Info -->
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider"><?= htmlspecialchars((string) $product['category_name'] ?? 'Sản phẩm') ?></span>
                        <div class="flex items-center gap-1 text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined fill text-buyer-orange text-[14px]">star</span>
                            <span class="font-bold"><?= number_format((float) $reviewSummary['avg_rating'], 1) ?></span>
                            <span>(<?= (int) $reviewSummary['review_count'] ?>)</span>
                            <span class="mx-1">·</span>
                            <span><?= (int) $product['sold_count'] ?> đã bán</span>
                        </div>
                    </div>
                    <h1 class="text-xl md:text-2xl font-bold text-on-surface leading-tight mb-3">
                        <?= htmlspecialchars((string) $product['name']) ?>
                    </h1>
                    
                    <?php if ($hasFlashSale): ?>
                        <div class="bg-gradient-to-r from-error to-orange-500 p-3 rounded-t-xl text-white flex justify-between items-center relative overflow-hidden">
                            <div class="absolute -right-4 -top-4 opacity-20">
                                <span class="material-symbols-outlined text-[80px] fill">bolt</span>
                            </div>
                            <div class="flex items-center gap-2 relative z-10">
                                <span class="material-symbols-outlined fill text-yellow-300">flash_on</span>
                                <span class="font-bold uppercase tracking-wider">Đang Flash Sale</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 bg-error/5 p-3 rounded-b-xl border border-error/20 mb-2">
                            <span class="text-2xl md:text-3xl text-error font-bold"><?= number_format($displayPrice, 0, ',', '.') ?> ₫</span>
                            <span class="text-on-surface-variant line-through text-sm"><?= number_format((float) $product['base_price'], 0, ',', '.') ?> ₫</span>
                            <span class="bg-error text-white text-[10px] font-bold px-1.5 py-0.5 rounded uppercase">-<?= round(((float)$product['base_price'] - $displayPrice) / (float)$product['base_price'] * 100) ?>%</span>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center bg-primary/5 p-3 rounded-xl border border-primary/10">
                            <span class="text-2xl md:text-3xl text-primary font-bold"><?= number_format((float) $product['base_price'], 0, ',', '.') ?> ₫</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="w-full h-px bg-border-subtle mb-4"></div>

                <!-- Variations -->
                <?php if (!empty($product['variants'])): ?>
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-on-surface mb-2">Phân loại</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($product['variants'] as $idx => $variant): ?>
                            <?php 
                            $vLabel = array_filter([$variant['type_label'] ?? '', $variant['color'] ?? '', $variant['size'] ?? '']);
                            $vLabelStr = $vLabel ? implode(' - ', $vLabel) : 'Mặc định';
                            $vPrice = number_format((float) $variant['price'], 0, ',', '.');
                            ?>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="variant_id" value="<?= (int) $variant['id'] ?>" class="peer sr-only" <?= $idx === 0 ? 'checked' : '' ?>>
                                <div class="px-3 py-1.5 rounded-lg border border-border-subtle text-on-surface-variant text-sm peer-checked:border-primary peer-checked:text-primary peer-checked:bg-primary/5 transition-all hover:bg-surface-container-low">
                                    <?= htmlspecialchars($vLabelStr) ?> <span class="text-xs opacity-75">(<?= $vPrice ?>₫)</span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <script>
                    const productVariants = <?= json_encode(array_map(function($v) {
                        return [
                            'id' => (int) $v['id'],
                            'stock_quantity' => (int) $v['stock_quantity']
                        ];
                    }, $product['variants'] ?? [])) ?>;
                    const baseStock = <?= (int) ($product['stock_quantity'] ?? 0) ?>;
                    
                    function updateBuyButtonsState() {
                        const variantRadios = document.querySelectorAll('input[name="variant_id"]');
                        let hasStock = false;
                        
                        if (variantRadios.length > 0) {
                            const checkedRadio = document.querySelector('input[name="variant_id"]:checked');
                            if (checkedRadio) {
                                const variantId = parseInt(checkedRadio.value, 10);
                                const variant = productVariants.find(v => v.id === variantId);
                                hasStock = variant && variant.stock_quantity > 0;
                            }
                        } else {
                            hasStock = baseStock > 0;
                        }
                        
                        // Action buttons
                        const buttons = [
                            ...document.querySelectorAll('.buy-now-button'),
                            document.querySelector('#detail-add-cart-form button[type="submit"]'),
                            document.querySelector('.lg\\:hidden button[onclick*="detail-add-cart-form"]')
                        ].filter(Boolean);
                        
                        buttons.forEach(btn => {
                            if (!hasStock) {
                                btn.disabled = true;
                                btn.classList.add('opacity-50', 'cursor-not-allowed');
                                btn.classList.remove('hover:bg-primary-container', 'hover:bg-primary/20', 'active:scale-[0.98]', 'active:bg-primary/20', 'active:bg-primary-container');
                            } else {
                                btn.disabled = false;
                                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                                if (btn.classList.contains('bg-primary/10')) {
                                    btn.classList.add('hover:bg-primary/20', 'active:scale-[0.98]');
                                } else {
                                    btn.classList.add('hover:bg-primary-container', 'active:scale-[0.98]');
                                }
                            }
                        });
                        
                        const qtyInput = document.getElementById('qty');
                        if (qtyInput) {
                            qtyInput.disabled = !hasStock;
                            if (hasStock) {
                                const maxStock = variantRadios.length > 0 
                                    ? productVariants.find(v => v.id === parseInt(document.querySelector('input[name="variant_id"]:checked')?.value, 10))?.stock_quantity 
                                    : baseStock;
                                qtyInput.max = maxStock;
                            }
                        }
                    }
                    
                    document.querySelectorAll('input[name="variant_id"]').forEach(radio => {
                        radio.addEventListener('change', updateBuyButtonsState);
                    });
                    
                    document.addEventListener('DOMContentLoaded', updateBuyButtonsState);
                </script>
                <?php else: ?>
                <script>
                    const baseStock = <?= (int) ($product['stock_quantity'] ?? 0) ?>;
                    function updateBuyButtonsState() {
                        const hasStock = baseStock > 0;
                        const buttons = [
                            ...document.querySelectorAll('.buy-now-button'),
                            document.querySelector('#detail-add-cart-form button[type="submit"]'),
                            document.querySelector('.lg\\:hidden button[onclick*="detail-add-cart-form"]')
                        ].filter(Boolean);
                        
                        buttons.forEach(btn => {
                            if (!hasStock) {
                                btn.disabled = true;
                                btn.classList.add('opacity-50', 'cursor-not-allowed');
                            }
                        });
                        
                        const qtyInput = document.getElementById('qty');
                        if (qtyInput) {
                            qtyInput.disabled = !hasStock;
                            if (hasStock) qtyInput.max = baseStock;
                        }
                    }
                    document.addEventListener('DOMContentLoaded', updateBuyButtonsState);
                </script>
                <?php endif; ?>

                <!-- Quantity -->
                <div class="mb-5">
                    <h3 class="text-sm font-bold text-on-surface mb-2">Số lượng</h3>
                    <div class="flex items-center border border-border-subtle rounded-lg h-9 w-[110px] overflow-hidden">
                        <button type="button" onclick="document.getElementById('qty').stepDown()" class="w-9 h-full flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low transition-colors border-r border-border-subtle">
                            <span class="material-symbols-outlined text-[18px]">remove</span>
                        </button>
                        <input id="qty" name="quantity" class="w-8 h-full text-center border-none focus:ring-0 text-sm text-on-surface p-0 bg-transparent" type="number" min="1" value="1"/>
                        <button type="button" onclick="document.getElementById('qty').stepUp()" class="w-9 h-full flex items-center justify-center text-on-surface-variant hover:bg-surface-container-low transition-colors border-l border-border-subtle">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                        </button>
                    </div>
                </div>

                <!-- Actions (Desktop) -->
                <div class="hidden md:flex gap-3 mt-auto">
                    <button type="submit" class="flex-1 bg-primary/10 text-primary border border-primary/30 font-bold h-11 rounded-xl flex items-center justify-center gap-2 hover:bg-primary/20 transition-all active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">add_shopping_cart</span>
                        Thêm giỏ hàng
                    </button>
                    <button type="button" class="js-buy-now buy-now-button flex-1 bg-primary text-white font-bold h-11 rounded-xl flex items-center justify-center gap-2 hover:bg-primary-container transition-all shadow-sm active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
                        Mua ngay
                    </button>
                </div>
            </form>

            <!-- Store Info - SEPARATED from form, with proper spacing -->
            <?php if (!empty($product['store_slug'])): ?>
            <a href="/user/shop.php?slug=<?= rawurlencode((string) $product['store_slug']) ?>" class="mt-4 bg-surface-container-low rounded-xl p-3 border border-border-subtle flex items-center gap-3 hover:border-primary/30 transition-all group block">
                <div class="w-10 h-10 rounded-full overflow-hidden border border-border-subtle bg-white flex-shrink-0 flex items-center justify-center text-primary">
                    <?php if (!empty($product['logo_url'])): ?>
                        <img src="<?= htmlspecialchars((string) $product['logo_url']) ?>" alt="Logo" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="material-symbols-outlined text-[20px]">store</span>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-bold text-on-surface group-hover:text-primary transition-colors truncate"><?= htmlspecialchars((string) ($product['store_name'] ?? 'Shop')) ?></h4>
                    <p class="text-xs text-on-surface-variant flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px] text-buyer-orange fill">star</span>
                        <?= number_format((float) ($product['rating'] ?? 0), 1) ?> · Xem shop
                    </p>
                </div>
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant group-hover:text-primary transition-colors">chevron_right</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mt-4 bg-white md:rounded-xl md:border md:border-border-subtle md:shadow-sm mb-6 overflow-hidden">
        <div class="flex border-b border-border-subtle">
            <button type="button" class="tab-btn px-5 py-3 text-sm font-bold text-primary border-b-2 border-primary transition-colors focus:outline-none" onclick="switchTab('description', this)">
                Mô tả
            </button>
            <button type="button" class="tab-btn px-5 py-3 text-sm font-bold text-on-surface-variant hover:text-primary border-b-2 border-transparent transition-colors focus:outline-none" onclick="switchTab('reviews', this)">
                Đánh giá (<?= (int)$reviewSummary['review_count'] ?>)
            </button>
        </div>

        <div class="p-4 md:p-6">
            <!-- Description Tab -->
            <div id="tab-description" class="tab-content block">
                <div class="rich-content max-w-none" style="overflow-wrap: break-word;">
                    <style>
                        .rich-content img { max-width: 100%; height: auto; object-fit: contain; }
                    </style>
                    <?php if (!empty($product['description'])): ?>
                        <?= UiHelper::richTextHtml((string) $product['description']) ?>
                    <?php else: ?>
                        <p class="italic text-on-surface-variant text-sm">Shop chưa cập nhật mô tả chi tiết.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews Tab -->
            <div id="tab-reviews" class="tab-content hidden">
                <h3 class="text-base font-bold mb-3 border-b border-border-subtle pb-2">Đánh giá từ người mua</h3>
                <?php if (!$reviews): ?>
                    <p class="text-on-surface-variant italic text-sm">Chưa có đánh giá nào.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($reviews as $review): ?>
                            <div class="border-b border-border-subtle pb-3 last:border-0 last:pb-0">
                                <div class="flex items-center justify-between mb-1">
                                    <strong class="text-sm text-on-surface"><?= htmlspecialchars((string) (($review['full_name'] ?? '') ?: ($review['username'] ?? 'Người mua'))) ?></strong>
                                    <div class="flex text-buyer-orange">
                                        <?php
                                        $rStars = (int) $review['rating'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo '<span class="material-symbols-outlined text-[14px] ' . ($i <= $rStars ? 'fill' : '') . '">star</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php if (!empty($review['comment'])): ?>
                                    <p class="text-on-surface-variant text-sm"><?= nl2br(htmlspecialchars((string) $review['comment'])) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($review['store_reply'])): ?>
                                    <div class="mt-2 bg-surface-container-lowest border border-border-subtle rounded-lg p-3 text-sm">
                                        <p class="font-semibold text-on-surface mb-1 text-xs">Phản hồi từ Cửa hàng:</p>
                                        <p class="text-on-surface-variant"><?= nl2br(htmlspecialchars((string) $review['store_reply'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                <div class="text-[11px] text-outline mt-1"><?= htmlspecialchars((string) $review['created_at']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
                el.classList.remove('block');
            });
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('text-primary', 'border-primary');
                el.classList.add('text-on-surface-variant', 'border-transparent');
            });
            
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            document.getElementById('tab-' + tabId).classList.add('block');
            
            btn.classList.remove('text-on-surface-variant', 'border-transparent');
            btn.classList.add('text-primary', 'border-primary');
        }
    </script>
    
    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
    <div class="mb-6">
        <h3 class="text-lg font-bold mb-3 text-on-surface">Sản phẩm liên quan</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
            <?php foreach ($relatedProducts as $rp): ?>
                <?php 
                $rpUrl = "/user/product-detail.php?id=" . (int) $rp['id'];
                $rpImg = !empty($rp['main_image_url']) ? StorageService::publicUrl($rp['main_image_url']) : 'https://placehold.co/400x400/e2e8f0/64748b?text=No+Image';
                $rpPrice = number_format((float) $rp['base_price'], 0, ',', '.');
                ?>
                <a href="<?= $rpUrl ?>" class="group bg-white border border-border-subtle rounded-xl overflow-hidden hover:shadow-md transition-all flex flex-col h-full">
                    <div class="aspect-square bg-surface-container-low overflow-hidden">
                        <img src="<?= htmlspecialchars($rpImg) ?>" alt="<?= htmlspecialchars($rp['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    </div>
                    <div class="p-2.5 flex flex-col flex-1">
                        <h4 class="text-xs text-on-surface line-clamp-2 mb-1 group-hover:text-primary transition-colors font-medium"><?= htmlspecialchars($rp['name']) ?></h4>
                        <div class="mt-auto">
                            <span class="text-sm font-bold text-primary"><?= $rpPrice ?>₫</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</main>

<!-- Mobile Bottom Actions -->
<?php if ($product): ?>
<div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-border-subtle px-4 py-2.5 z-40 flex items-center gap-2 shadow-[0_-2px_10px_rgba(0,0,0,0.05)]">
    <a href="/user/shop.php?slug=<?= rawurlencode((string) $product['store_slug']) ?>" class="w-10 h-10 flex-shrink-0 flex items-center justify-center border border-border-subtle rounded-lg text-on-surface-variant active:bg-surface-variant transition-colors">
        <span class="material-symbols-outlined text-[20px]">store</span>
    </a>
    <button type="button" onclick="document.querySelector('#detail-add-cart-form').dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))" class="flex-1 h-10 bg-primary/10 text-primary font-semibold text-sm rounded-lg flex items-center justify-center gap-1 active:bg-primary/20 transition-colors">
        <span class="material-symbols-outlined text-[16px]">add_shopping_cart</span>Thêm giỏ
    </button>
    <button type="button" class="js-buy-now buy-now-button flex-1 h-10 bg-primary text-white font-semibold text-sm rounded-lg flex items-center justify-center gap-1 active:bg-primary-container transition-colors shadow-sm">
        <span class="material-symbols-outlined text-[16px]">shopping_bag</span>Mua ngay
    </button>
</div>
<?php endif; ?>

<script src="/assets/js/global.js?v=20260609-3"></script>
<script src="/assets/js/buyer-products.js?v=20260609-7"></script>

</body>
</html>
