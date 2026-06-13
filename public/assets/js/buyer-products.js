(function () {
    var form = document.getElementById('product-filter-form');
    var grid = document.getElementById('product-grid');
    var resultCount = document.getElementById('product-result-count');
    var detailForm = document.getElementById('detail-add-cart-form');
    var debounceTimer = null;

    function productCard(product) {
        var id = Number(product.id);
        var sold = Number(product.sold_count || 0);
        var storeName = APP.escapeHtml(product.store_name || 'Shop');
        var name = APP.escapeHtml(product.name);
        
        var imgUrl = product.main_image_url || '';
        if (imgUrl && imgUrl.startsWith('http')) {
            // keep as is
        } else if (imgUrl) {
            imgUrl = 'https://pub-309aa43ab7414948a1e66726694eda95.r2.dev/' + imgUrl;
        } else {
            imgUrl = 'https://placehold.co/400x400/e2e8f0/64748b?text=No+Image';
        }
        
        var productUrl = '/user/product-detail.php?id=' + id + (product.slug ? '&slug=' + APP.escapeHtml(product.slug) : '');
        var hasVariants = Number(product.has_variants) === 1;

        var soldBadge = sold > 0 
            ? '<div class="absolute bottom-2 left-2 bg-black/50 backdrop-blur-sm text-white text-[10px] font-semibold px-2 py-0.5 rounded-full flex items-center gap-0.5">' +
              '<span class="material-symbols-outlined text-[12px] fill">local_fire_department</span>' + sold +
              '</div>' 
            : '';

        var actionBtn = hasVariants
            ? '<a href="' + productUrl + '" class="w-8 h-8 bg-primary text-white rounded-lg flex items-center justify-center hover:bg-primary-container transition-all shadow-sm active:scale-95" title="Chọn mua"><span class="material-symbols-outlined text-[16px]">shopping_bag</span></a>'
            : '<button type="button" class="js-add-cart w-8 h-8 bg-primary text-white rounded-lg flex items-center justify-center hover:bg-primary-container transition-all shadow-sm active:scale-95" data-product-id="' + id + '" title="Thêm giỏ"><span class="material-symbols-outlined text-[16px]">add_shopping_cart</span></button>';

        return '' +
            '<div class="group bg-white rounded-xl overflow-hidden border border-border-subtle hover:shadow-md transition-all duration-300 flex flex-col h-full hover:-translate-y-0.5">' +
                '<a href="' + productUrl + '" class="block">' +
                    '<div class="aspect-square bg-surface-container-low overflow-hidden relative">' +
                        '<img alt="' + name + '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="' + imgUrl + '"/>' +
                        soldBadge +
                    '</div>' +
                '</a>' +
                '<div class="p-3 flex flex-col flex-grow">' +
                    '<p class="text-[11px] text-on-surface-variant mb-0.5 flex items-center gap-0.5 truncate">' +
                        '<span class="material-symbols-outlined text-[12px]">store</span>' + storeName +
                    '</p>' +
                    '<h3 class="text-xs font-semibold text-on-surface mb-1 line-clamp-2 leading-relaxed min-h-[32px]">' +
                        '<a href="' + productUrl + '" class="hover:text-primary transition-colors">' + name + '</a>' +
                    '</h3>' +
                    '<div class="mt-auto flex items-end justify-between pt-1">' +
                        '<span class="text-sm font-bold text-primary">' + APP.money(product.base_price) + '</span>' +
                        actionBtn +
                    '</div>' +
                '</div>' +
            '</div>';
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
