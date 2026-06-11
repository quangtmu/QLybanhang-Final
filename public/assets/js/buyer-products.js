(function () {
    var form = document.getElementById('product-filter-form');
    var grid = document.getElementById('product-grid');
    var resultCount = document.getElementById('product-result-count');
    var detailForm = document.getElementById('detail-add-cart-form');
    var debounceTimer = null;

    function productCard(product) {
        var id = Number(product.id);
        var sold = Number(product.sold_count || 0);
        var storeSlug = product.store_slug ? String(product.store_slug) : '';
        var storeName = APP.escapeHtml(product.store_name || 'Shop');
        var image = product.main_image_url
            ? '<img src="' + APP.escapeHtml(product.main_image_url) + '" alt="' + APP.escapeHtml(product.name) + '">'
            : '<span>Chưa có ảnh</span>';
        var store = storeSlug
            ? '<a class="product-store" href="/user/shop.php?slug=' + encodeURIComponent(storeSlug) + '"><i class="bi bi-shop-window"></i>' + storeName + '</a>'
            : '<span class="product-store"><i class="bi bi-shop-window"></i>' + storeName + '</span>';

        var action = Number(product.has_variants) === 1
            ? '<a class="button-link product-buy-link" href="/user/product-detail.php?id=' + id + '">Chọn mua</a>'
            : '<button type="button" class="js-add-cart product-cart-button" data-product-id="' + id + '"><i class="bi bi-cart-plus"></i>Thêm</button>';

        return '' +
            '<article class="product-card market-product-card" data-card-context="search">' +
                '<a class="product-image" href="/user/product-detail.php?id=' + id + '">' +
                    image +
                    '<span class="product-badge">Đã bán ' + sold + '</span>' +
                '</a>' +
                '<div class="product-card-body">' +
                    store +
                    '<h2><a href="/user/product-detail.php?id=' + id + '">' + APP.escapeHtml(product.name) + '</a></h2>' +
                    '<p class="product-meta">' + APP.escapeHtml(product.category_name || 'Chưa phân loại') + ' · ' + APP.escapeHtml(product.product_code || '') + '</p>' +
                    '<div class="product-card-bottom">' +
                        '<strong class="price-cell">' + APP.money(product.base_price) + '</strong>' +
                        '<span>' + sold + ' đã bán</span>' +
                    '</div>' +
                    '<div class="product-card-actions">' +
                        '<a class="button-link product-detail-link" href="/user/product-detail.php?id=' + id + '">Chi tiết</a>' +
                        action +
                    '</div>' +
                '</div>' +
            '</article>';
    }

    async function loadProducts() {
        if (!form || !grid) {
            return;
        }

        var params = new URLSearchParams(new FormData(form));
        params.set('action', 'list');
        var response = await fetch('/api/user/products.php?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        });
        var json = await response.json();

        if (!json.success) {
            APP.toast(json.message || 'Không tải được sản phẩm.', 'error');
            return;
        }

        var items = json.data.items || [];
        grid.innerHTML = items.length
            ? items.map(productCard).join('')
            : '<section class="portal-panel empty-panel">Chưa có sản phẩm phù hợp.</section>';

        if (resultCount) {
            resultCount.textContent = Number(json.data.pagination.total || 0) + ' sản phẩm';
        }

        params.delete('action');
        window.history.replaceState(null, '', '/user/products.php?' + params.toString());
    }

    async function addCart(payload) {
        var response = await fetch('/api/user/cart.php', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        var json = await response.json();

        if (!json.success) {
            APP.toast(json.message || 'Không thêm được vào giỏ.', 'error');
            return false;
        }

        APP.toast('Đã thêm vào giỏ hàng.');
        return true;
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            loadProducts();
        });

        form.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadProducts, 300);
        });

        form.addEventListener('change', loadProducts);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-add-cart');

        if (!button) {
            return;
        }

        addCart({
            product_id: Number(button.dataset.productId),
            quantity: 1
        });
    });

    document.addEventListener('click', async function (event) {
        var buyNow = event.target.closest('.js-buy-now');

        if (!buyNow || !detailForm) {
            return;
        }

        var data = new FormData(detailForm);
        var params = new URLSearchParams();
        params.set('tab', 'checkout');
        params.set('buy_now_product_id', String(Number(data.get('product_id'))));
        params.set('quantity', String(Math.max(1, Number(data.get('quantity') || 1))));

        if (data.get('variant_id')) {
            params.set('variant_id', String(data.get('variant_id')));
        }

        window.location.href = '/user/orders.php?' + params.toString();
    });

    if (detailForm) {
        detailForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var data = new FormData(detailForm);
            addCart({
                product_id: Number(data.get('product_id')),
                variant_id: data.get('variant_id') || null,
                quantity: Number(data.get('quantity') || 1)
            });
        });
    }
})();
