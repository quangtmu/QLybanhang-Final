<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';

$user = PermissionMiddleware::requireUserType(USER_TYPE_USER);
$errors = [];
$success = flash_success();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::checkCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
    } else {
        try {
            StoreRegistrationModel::submit((int) $user['id'], $_POST);
            $_SESSION['flash_success'] = 'Đã gửi đơn mở shop. Vui lòng chờ admin duyệt.';
            header('Location: /user/store-registration.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Lỗi hệ thống.';
        }
    }
}

$requests = StoreRegistrationModel::myRequests((int) $user['id']);
$csrfToken = AuthController::csrfToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mở shop - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="flex-grow pt-[72px] pb-20 lg:pb-8">
        <div class="max-w-3xl mx-auto px-4 md:px-6">

        <!-- Header -->
        <div class="mt-2 mb-4">
            <h1 class="text-lg font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">add_business</span>Đăng ký bán hàng
            </h1>
            <p class="text-xs text-on-surface-variant mt-0.5">Gửi thông tin để admin duyệt tài khoản shop.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-success/10 text-success p-3 rounded-lg font-medium text-sm mb-3 flex items-center gap-2 animate-fade-in">
                <span class="material-symbols-outlined text-[18px]">check_circle</span><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="bg-error/10 text-error p-3 rounded-lg text-sm mb-3">
                <?php foreach ($errors as $message): ?>
                    <p class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><?= htmlspecialchars((string) $message) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Registration States -->
        <?php
        $activeRequest = null;
        $rejectedRequests = [];
        foreach ($requests as $r) {
            if (in_array($r['status'], ['pending', 'approved'], true)) {
                $activeRequest = $r;
            } else {
                $rejectedRequests[] = $r;
            }
        }
        ?>

        <?php if ($activeRequest): ?>
            <?php if ($activeRequest['status'] === 'approved'): ?>
                <div class="bg-success/5 border border-success/20 rounded-2xl p-6 md:p-8 text-center shadow-sm relative overflow-hidden mb-6">
                    <div class="absolute top-0 right-0 p-4 opacity-10">
                        <span class="material-symbols-outlined text-[100px] text-success">store</span>
                    </div>
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="w-16 h-16 bg-success/20 text-success rounded-full flex items-center justify-center mb-4 ring-8 ring-success/5">
                            <span class="material-symbols-outlined text-[32px]">verified</span>
                        </div>
                        <h2 class="text-2xl font-bold text-on-surface mb-2">Shop của bạn đã được duyệt!</h2>
                        <p class="text-on-surface-variant max-w-md mx-auto mb-6">Chúc mừng bạn đã trở thành nhà bán hàng trên OmniSales. Bạn có thể truy cập Kênh Người Bán để đăng sản phẩm ngay bây giờ.</p>
                        
                        <div class="bg-white border border-border-subtle rounded-xl p-4 w-full max-w-md text-left mb-6 shadow-sm">
                            <h3 class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-3 pb-2 border-b border-border-subtle">Thông tin Kênh Người Bán</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-on-surface-variant">Tên Shop:</span>
                                    <a href="/user/shop.php?slug=<?= rawurlencode((string)$activeRequest['store_slug']) ?>" class="font-bold text-primary hover:underline flex items-center gap-1">
                                        <?= htmlspecialchars($activeRequest['store_name']) ?> <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                                    </a>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-on-surface-variant">Tên đăng nhập:</span>
                                    <span class="font-bold text-on-surface"><?= htmlspecialchars($activeRequest['store_username']) ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-on-surface-variant">Mật khẩu:</span>
                                    <span class="text-on-surface font-medium italic">Đã gửi qua thông báo hệ thống</span>
                                </div>
                            </div>
                        </div>

                        <a href="/login.php" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-primary text-white font-bold rounded-xl shadow-md hover:bg-primary-container transition-all hover:-translate-y-0.5">
                            <span class="material-symbols-outlined text-[20px]">storefront</span>Đăng nhập Kênh Người Bán
                        </a>
                    </div>
                </div>
            <?php else: // pending ?>
                <div class="bg-surface-container-low border border-border-subtle rounded-2xl p-6 md:p-8 text-center shadow-sm relative overflow-hidden mb-6">
                    <div class="absolute top-0 right-0 p-4 opacity-10">
                        <span class="material-symbols-outlined text-[100px] text-buyer-orange">hourglass_empty</span>
                    </div>
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="w-16 h-16 bg-buyer-orange/20 text-buyer-orange rounded-full flex items-center justify-center mb-4 ring-8 ring-buyer-orange/5">
                            <span class="material-symbols-outlined text-[32px]">pending_actions</span>
                        </div>
                        <h2 class="text-2xl font-bold text-on-surface mb-2">Đơn mở shop đang được duyệt</h2>
                        <p class="text-on-surface-variant max-w-md mx-auto mb-6">Hồ sơ đăng ký shop <strong class="text-on-surface"><?= htmlspecialchars($activeRequest['store_name']) ?></strong> của bạn đang được ban quản trị xét duyệt. Quá trình này thường mất từ 1-2 ngày làm việc. Vui lòng theo dõi thông báo.</p>
                        
                        <a href="/user/home.php" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white border border-border-subtle text-on-surface font-semibold rounded-xl hover:bg-surface-container transition-all">
                            Về trang chủ
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Registration Form -->
            <div class="bg-white rounded-2xl border border-border-subtle p-5 md:p-8 mb-6 shadow-sm">
                <div class="mb-6 pb-4 border-b border-border-subtle">
                    <h2 class="text-xl font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[24px] text-primary">edit_document</span>Thông tin đăng ký
                    </h2>
                    <p class="text-sm text-on-surface-variant mt-1">Vui lòng cung cấp thông tin chính xác để quá trình duyệt diễn ra nhanh chóng.</p>
                </div>
                
                <form method="post" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="bg-surface-container-low/30 rounded-xl p-4 border border-border-subtle space-y-4">
                        <h3 class="text-sm font-bold text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary">person</span>Thông tin chủ shop</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="text-xs font-bold text-on-surface">Họ tên <span class="text-error">*</span></span>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                            </label>
                            <label class="block">
                                <span class="text-xs font-bold text-on-surface">Điện thoại <span class="text-error">*</span></span>
                                <input type="tel" name="phone" value="<?= htmlspecialchars((string) ($user['phone'] ?? '')) ?>" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                            </label>
                            <label class="block">
                                <span class="text-xs font-bold text-on-surface">CCCD/CMND <span class="text-error">*</span></span>
                                <input type="text" name="cccd" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                            </label>
                            <label class="block">
                                <span class="text-xs font-bold text-on-surface-variant">URL ảnh CCCD</span>
                                <input type="text" name="cccd_image_url" placeholder="/uploads/documents/cccd.jpg" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 bg-white">
                            </label>
                        </div>
                    </div>

                    <div class="bg-surface-container-low/30 rounded-xl p-4 border border-border-subtle space-y-4">
                        <h3 class="text-sm font-bold text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary">store</span>Thông tin cửa hàng</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="text-xs font-bold text-on-surface">Tên shop <span class="text-error">*</span></span>
                                <input type="text" name="store_name" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Ví dụ: Omni Fashion">
                            </label>
                            <label class="block">
                                <span class="text-xs font-bold text-on-surface">Email liên hệ <span class="text-error">*</span></span>
                                <input type="email" name="gmail" value="<?= htmlspecialchars($user['email']) ?>" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                            </label>
                            <label class="block">
                                <span class="text-xs font-bold text-on-surface">Ngành hàng chính <span class="text-error">*</span></span>
                                <input type="text" name="product_category" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Ví dụ: Thời trang, Điện tử...">
                            </label>
                            <label class="block">
                                <span class="text-xs font-bold text-on-surface-variant">Giấy phép kinh doanh (nếu có)</span>
                                <input type="text" name="business_license_url" placeholder="URL ảnh giấy phép" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 bg-white">
                            </label>
                        </div>
                    </div>

                    <div class="bg-surface-container-low/30 rounded-xl p-4 border border-border-subtle space-y-4">
                        <h3 class="text-sm font-bold text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary">inventory_2</span>Sản phẩm dự kiến</h3>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface-variant">Danh sách sản phẩm mẫu</span>
                            <textarea name="sample_products" rows="2" placeholder="Áo thun nam, Quần jean nữ..." class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 bg-white"></textarea>
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-on-surface-variant">URL Ảnh sản phẩm mẫu</span>
                            <textarea name="sample_images" rows="2" placeholder="https://example.com/anh1.jpg" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 bg-white"></textarea>
                        </label>
                    </div>

                    <button type="submit" class="w-full sm:w-auto bg-primary text-white font-bold text-sm px-8 py-3.5 rounded-xl hover:bg-primary-container transition-all shadow-md flex items-center justify-center gap-2 active:scale-[0.98]">
                        <span class="material-symbols-outlined text-[20px]">send</span>Gửi Đơn Đăng Ký
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Rejected Requests History -->
        <?php if ($rejectedRequests): ?>
        <div class="bg-white rounded-xl border border-border-subtle overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-border-subtle bg-surface-container-low/30">
                <h2 class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">history</span>Lịch sử đăng ký bị từ chối
                </h2>
            </div>
            <div class="divide-y divide-border-subtle">
                <?php foreach ($rejectedRequests as $request): ?>
                <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-on-surface opacity-60 line-through"><?= htmlspecialchars($request['store_name']) ?></p>
                        <p class="text-xs text-on-surface-variant mt-0.5"><?= htmlspecialchars($request['product_category']) ?> · Gửi lúc: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($request['created_at']))) ?></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-error/10 text-error">
                            Bị từ chối
                        </span>
                        <?php if (!empty($request['admin_note'])): ?>
                            <span class="text-xs text-error bg-error/5 px-2 py-1 rounded-md border border-error/10">
                                Lý do: <?= htmlspecialchars((string)$request['admin_note']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
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
</body>
</html>
