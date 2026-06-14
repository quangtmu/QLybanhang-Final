(function () {
    var checkoutForm = document.getElementById('cart-checkout-form');
    var selectAll = document.getElementById('cart-select-all');
    var selectedTotal = document.getElementById('cart-selected-total');
    var selectedCount = document.getElementById('cart-selected-count');
    var checkoutButton = document.getElementById('cart-checkout-button');

    function money(value) {
        return new Intl.NumberFormat('vi-VN').format(Math.max(0, Number(value || 0))) + 'đ';
    }

    function selectedCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll('.cart-item-checkbox:not(:disabled):checked'));
    }

    function updateCartSelectionSummary() {
        var enabled = Array.prototype.slice.call(document.querySelectorAll('.cart-item-checkbox:not(:disabled)'));
        var selected = selectedCheckboxes();
        var total = 0;
        var count = 0;

        selected.forEach(function (checkbox) {
            var row = checkbox.closest('.cart-item-row');
            total += Number(row ? row.dataset.subtotal : 0);
            count += Number(row ? row.dataset.quantity : 0);
        });

        if (selectedTotal) {
            selectedTotal.textContent = money(total);
        }

        var finalPrice = document.getElementById('cart-final-price');
        if (finalPrice) {
            finalPrice.textContent = money(total);
        }

        if (selectedCount) {
            selectedCount.textContent = String(count);
        }

        if (checkoutButton) {
            checkoutButton.disabled = selected.length === 0;
            checkoutButton.innerHTML = '<i class="bi bi-bag-check"></i>Mua hàng (' + count + ')';
        }

        if (selectAll) {
            selectAll.checked = enabled.length > 0 && selected.length === enabled.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < enabled.length;
        }

        document.querySelectorAll('.cart-store-card').forEach(function (card) {
            var storeToggle = card.querySelector('.cart-store-select');
            var storeItems = Array.prototype.slice.call(card.querySelectorAll('.cart-item-checkbox:not(:disabled)'));
            var storeSelected = storeItems.filter(function (checkbox) { return checkbox.checked; });

            if (storeToggle) {
                storeToggle.checked = storeItems.length > 0 && storeSelected.length === storeItems.length;
                storeToggle.indeterminate = storeSelected.length > 0 && storeSelected.length < storeItems.length;
            }
        });
    }

    async function requestCart(url, options) {
        var response = await fetch(url, Object.assign({
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }, options || {}));
        var json = await response.json();

        if (!json.success) {
            APP.toast(json.message || 'Không cập nhật được giỏ hàng.', 'error');
            return;
        }

        APP.toast('Đã cập nhật giỏ hàng.');
        window.setTimeout(function () {
            window.location.reload();
        }, 350);
    }

    document.addEventListener('change', function (event) {
        var input = event.target.closest('.cart-quantity');

        if (!input) {
            return;
        }

        requestCart('/api/user/cart.php?item_id=' + encodeURIComponent(input.dataset.itemId), {
            method: 'PUT',
            body: JSON.stringify({ quantity: Number(input.value || 0) })
        });
    });

    document.addEventListener('change', function (event) {
        var itemCheckbox = event.target.closest('.cart-item-checkbox');
        var storeCheckbox = event.target.closest('.cart-store-select');

        if (itemCheckbox) {
            updateCartSelectionSummary();
            return;
        }

        if (storeCheckbox) {
            var card = storeCheckbox.closest('.cart-store-card');
            if (card) {
                card.querySelectorAll('.cart-item-checkbox:not(:disabled)').forEach(function (checkbox) {
                    checkbox.checked = storeCheckbox.checked;
                });
            }
            updateCartSelectionSummary();
            return;
        }

        if (event.target === selectAll) {
            document.querySelectorAll('.cart-item-checkbox:not(:disabled)').forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
            updateCartSelectionSummary();
        }
    });

    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (event) {
            if (selectedCheckboxes().length === 0) {
                event.preventDefault();
                APP.toast('Vui lòng chọn ít nhất một sản phẩm để mua.', 'error');
            }
        });
    }

    document.addEventListener('click', function (event) {
        var removeButton = event.target.closest('.js-remove-cart');

        if (removeButton) {
            requestCart('/api/user/cart.php?item_id=' + encodeURIComponent(removeButton.dataset.itemId), {
                method: 'DELETE'
            });
            return;
        }

        if (event.target.closest('#clear-cart-button')) {
            requestCart('/api/user/cart.php?action=clear', {
                method: 'DELETE'
            });
        }
    });

    updateCartSelectionSummary();
})();
