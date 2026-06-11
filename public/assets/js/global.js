(function () {
    window.APP = window.APP || {};

    window.APP.escapeHtml = function (value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    window.APP.money = function (value) {
        return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' đ';
    };

    window.APP.statusLabel = function (status) {
        var labels = {
            draft: 'Bản nháp',
            pending_review: 'Chờ duyệt',
            approved: 'Đã duyệt',
            rejected: 'Bị từ chối',
            archived: 'Đã lưu trữ',
            pending: 'Chờ xác nhận',
            confirmed: 'Đã xác nhận',
            processing: 'Đang chuẩn bị',
            shipped: 'Đã gửi hàng',
            delivering: 'Đang giao',
            delivered: 'Đã giao',
            cancelled: 'Đã hủy',
            refunding: 'Đang hoàn tiền',
            refunded: 'Đã hoàn tiền',
            waiting_pickup: 'Chờ lấy hàng',
            picked_up: 'Đã lấy hàng',
            in_transit: 'Đang vận chuyển',
            out_for_delivery: 'Đang giao cho khách'
        };
        return labels[status] || String(status || 'Chưa cập nhật').replaceAll('_', ' ');
    };

    window.APP.toast = function (message, type) {
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'success');
        toast.textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(function () {
            toast.classList.add('is-hiding');
            window.setTimeout(function () {
                toast.remove();
            }, 220);
        }, 2200);
    };

    function setFieldError(field, message) {
        var wrap = field.closest('label') || field.parentElement;
        field.classList.add('is-invalid');
        if (!wrap) return;
        var old = wrap.querySelector('.field-error');
        if (old) old.remove();
        var error = document.createElement('span');
        error.className = 'field-error';
        error.textContent = message;
        wrap.appendChild(error);
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        var wrap = field.closest('label') || field.parentElement;
        var old = wrap ? wrap.querySelector('.field-error') : null;
        if (old) old.remove();
    }

    document.addEventListener('input', function (event) {
        if (event.target.matches('input, textarea, select')) {
            clearFieldError(event.target);
        }
    });

    document.querySelectorAll('.js-validate').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var firstInvalid = null;
            form.querySelectorAll('[required]').forEach(function (field) {
                clearFieldError(field);
                if (!field.value) {
                    setFieldError(field, 'Vui lòng nhập thông tin bắt buộc.');
                    firstInvalid = firstInvalid || field;
                }
            });
            form.querySelectorAll('input[type="number"][min]').forEach(function (field) {
                if (field.value !== '' && Number(field.value) < Number(field.min)) {
                    setFieldError(field, 'Giá trị phải từ ' + field.min + ' trở lên.');
                    firstInvalid = firstInvalid || field;
                }
            });
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
            }
        });
    });

    document.querySelectorAll('input[type="file"][data-preview-target]').forEach(function (input) {
        input.addEventListener('change', function () {
            var target = document.getElementById(input.dataset.previewTarget);
            if (!target) return;
            target.innerHTML = '';
            Array.from(input.files || []).forEach(function (file) {
                if (!file.type.startsWith('image/')) return;
                var img = document.createElement('img');
                img.alt = file.name;
                img.src = URL.createObjectURL(file);
                target.appendChild(img);
            });
            if (!target.children.length) {
                target.innerHTML = '<span>Chưa chọn ảnh</span>';
            }
        });
    });

    function buildPortalChrome() {
        if (!document.body.classList.contains('portal-page')) return;
        var shell = document.querySelector('.portal-shell');
        var topbar = document.querySelector('.topbar');
        if (!shell || !topbar || document.querySelector('.portal-commandbar')) return;

        if (!topbar.dataset.staticNav) {
            normalizePortalMenu(topbar);
        }

        topbar.querySelectorAll('a').forEach(function (link) {
            var linkUrl = new URL(link.href, window.location.origin);
            var linkTab = linkUrl.searchParams.get('tab');
            var currentTab = new URLSearchParams(window.location.search).get('tab');
            if (window.location.pathname === '/admin/users.php' && !currentTab) {
                currentTab = 'buyer';
            }
            var isCurrent = linkUrl.pathname === window.location.pathname && (!linkTab || linkTab === currentTab);
            if (isCurrent) {
                link.classList.add('is-active');
                link.setAttribute('aria-current', 'page');
            }
        });

        if (topbar.dataset.staticNav === 'buyer') {
            document.body.classList.add('portal-ready');
            return;
        }

        var commandbar = document.createElement('div');
        commandbar.className = 'portal-commandbar';
        commandbar.innerHTML = [
            '<label class="portal-search">',
            '<input type="search" placeholder="Tìm kiếm nhanh..." aria-label="Tìm kiếm nhanh">',
            '</label>',
            '<span></span>',
            '<div class="portal-actions-mini">',
            '<a class="portal-icon-link portal-notification-link" href="/notifications.php" aria-label="Thông báo"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg><span class="nav-badge" data-notification-badge>0</span></a>',
            '<a class="portal-icon-link" href="/profile.php" aria-label="Tài khoản"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></a>',
            '</div>'
        ].join('');
        shell.insertBefore(commandbar, topbar.nextSibling);

        fetch('/api/notifications.php?action=unread-count', { credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (payload) {
                var count = Number(payload && payload.unread_count ? payload.unread_count : 0);
                var badge = document.querySelector('[data-notification-badge]');
                if (!badge || count <= 0) return;
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.add('is-visible');
            })
            .catch(function () {});

        document.body.classList.add('portal-ready');
    }

    buildPortalChrome();

    function normalizePortalMenu(topbar) {
        if (document.body.classList.contains('storefront-page')) return;

        var links = Array.from(topbar.querySelectorAll('a')).map(function (link) {
            return link.getAttribute('href') || '';
        });
        var type = links.some(function (href) { return href.startsWith('/admin/'); })
            ? 'admin'
            : (links.some(function (href) { return href.startsWith('/store/'); }) ? 'store' : 'user');

        var menus = {
            admin: [
                { href: '/admin/dashboard.php', label: 'Dashboard', icon: 'dashboard' },
                {
                    label: 'Quản lý người dùng',
                    icon: 'users',
                    children: [
                        ['/admin/users.php?tab=buyer', 'Quản lý người mua', ['/admin/users.php']],
                        ['/admin/users.php?tab=store', 'Quản lý store', ['/admin/users.php']],
                        ['/admin/users.php?tab=admin', 'Quản lý Sub-admin', ['/admin/users.php']]
                    ]
                },
                {
                    label: 'Quản lý đơn hàng',
                    icon: 'orders',
                    children: [
                        ['/admin/orders.php', 'Danh sách đơn hàng'],
                        ['/admin/shipments.php', 'Vận đơn'],
                        ['/admin/invoices.php', 'Xử lý hóa đơn']
                    ]
                },
                {
                    label: 'Quản lý sản phẩm',
                    icon: 'products',
                    children: [
                        ['/admin/products.php', 'Yêu cầu duyệt sản phẩm'],
                        ['/admin/categories.php', 'Quản lý danh mục'],
                        ['/admin/tags.php', 'Quản lý tags']
                    ]
                },
                {
                    label: 'Quản lý quảng cáo',
                    icon: 'ads',
                    children: [
                        ['/admin/banners.php', 'Quản lý Banner']
                    ]
                },
                { href: '/chat.php', label: 'Tin nhắn', icon: 'messages' },
                { href: '/profile.php', label: 'Hồ sơ', icon: 'profile' },
                { href: '/logout.php', label: 'Đăng xuất', icon: 'logout' }
            ],
            store: [
                ['/store/dashboard.php', 'Bảng điều khiển'],
                ['/store/products.php', 'Sản phẩm'],
                ['/store/orders.php', 'Đơn hàng'],
                ['/store/shipments.php', 'Vận đơn'],
                ['/store/invoices.php', 'Hóa đơn'],
                ['/store/employees.php', 'Nhân viên'],
                ['/chat.php', 'Chat'],
                ['/notifications.php', 'Thông báo'],
                ['/profile.php', 'Hồ sơ'],
                ['/logout.php', 'Đăng xuất']
            ],
            user: [
                ['/user/home.php', 'Trang chủ'],
                ['/user/products.php', 'Sản phẩm'],
                ['/user/cart.php', 'Giỏ hàng'],
                ['/user/orders.php', 'Đơn hàng'],
                ['/user/invoices.php', 'Hóa đơn'],
                ['/chat.php', 'Chat'],
                ['/notifications.php', 'Thông báo'],
                ['/user/store-registration.php', 'Mở shop'],
                ['/profile.php', 'Hồ sơ'],
                ['/logout.php', 'Đăng xuất']
            ]
        };

        var label = type === 'admin' ? 'Quản trị hệ thống' : (type === 'store' ? 'Kênh bán hàng' : 'OmniSales');
        var strong = topbar.querySelector('strong');
        if (strong) {
            var iconPath = type === 'admin' ? '/assets/images/admin_icon.png' : (type === 'store' ? '/assets/images/store_icon.png' : '/assets/images/buyer_icon.png');
            strong.innerHTML = '<img src="' + iconPath + '" alt="" class="portal-logo" style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover;"> ' + label;
        }

        var wrap = topbar.querySelector('span');
        if (!wrap) return;
        wrap.innerHTML = menus[type].map(function (item) {
            if (Array.isArray(item)) {
                return '<a href="' + item[0] + '">' + item[1] + '</a>';
            }

            if (item.children) {
                var childLinks = item.children.map(function (child) {
                    return '<a class="portal-subnav-link" href="' + child[0] + '">' + child[1] + '</a>';
                }).join('');
                return '<details class="portal-nav-group"><summary data-icon="' + item.icon + '">' + item.label + '</summary><div>' + childLinks + '</div></details>';
            }

            return '<a href="' + item.href + '" data-icon="' + item.icon + '">' + item.label + '</a>';
        }).join('');

        wrap.querySelectorAll('.portal-nav-group').forEach(function (group) {
            var isCurrentGroup = Array.from(group.querySelectorAll('a')).some(function (link) {
                var linkUrl = new URL(link.href, window.location.origin);
                var currentTab = new URLSearchParams(window.location.search).get('tab');
                if (window.location.pathname === '/admin/users.php' && !currentTab) {
                    currentTab = 'buyer';
                }
                if (linkUrl.pathname !== window.location.pathname) return false;
                if (linkUrl.searchParams.has('tab')) {
                    return linkUrl.searchParams.get('tab') === currentTab;
                }
                return true;
            });
            if (isCurrentGroup) {
                group.open = true;
            }
        });
    }
})();
