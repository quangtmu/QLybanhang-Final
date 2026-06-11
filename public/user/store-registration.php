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

        <!-- Requests Table -->
        <?php if ($requests): ?>
        <div class="bg-white rounded-xl border border-border-subtle overflow-hidden mb-4">
            <div class="px-4 py-3 border-b border-border-subtle bg-surface-container-low/30">
                <h2 class="text-sm font-bold text-on-surface flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">list_alt</span>Đơn đã gửi
                </h2>
            </div>
            <div class="divide-y divide-border-subtle">
                <?php foreach ($requests as $request): ?>
                <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <?php if (!empty($request['store_slug'])): ?>
                            <a href="/user/shop.php?slug=<?= rawurlencode((string)$request['store_slug']) ?>" class="text-sm font-bold text-primary hover:underline">
                                <?= htmlspecialchars($request['store_name']) ?>
                            </a>
                        <?php else: ?>
                            <p class="text-sm font-bold text-on-surface"><?= htmlspecialchars($request['store_name']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-on-surface-variant mt-0.5"><?= htmlspecialchars($request['product_category']) ?> · <?= htmlspecialchars($request['gmail']) ?></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold <?php
                            if ($request['status'] === 'approved') echo 'bg-success/10 text-success';
                            elseif ($request['status'] === 'rejected') echo 'bg-error/10 text-error';
                            else echo 'bg-buyer-orange/10 text-buyer-orange';
                        ?>"><?= htmlspecialchars($request['status']) ?></span>
                        <?php if (!empty($request['admin_note'])): ?>
                            <span class="text-xs text-on-surface-variant" title="<?= htmlspecialchars((string)$request['admin_note']) ?>">
                                <span class="material-symbols-outlined text-[14px]">info</span>
                            </span>
                        <?php endif; ?>
                        <?php if ($request['store_username']): ?>
                            <span class="text-xs text-success font-semibold flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                <?= htmlspecialchars($request['store_username']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <div class="bg-white rounded-xl border border-border-subtle p-5">
            <h2 class="text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">edit_square</span>Đơn mở shop
            </h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                        <input type="text" name="cccd_image_url" placeholder="/uploads/documents/cccd.jpg" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-on-surface">Tên shop <span class="text-error">*</span></span>
                        <input type="text" name="store_name" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-on-surface">Gmail <span class="text-error">*</span></span>
                        <input type="email" name="gmail" value="<?= htmlspecialchars($user['email']) ?>" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-on-surface-variant">Giấy phép kinh doanh</span>
                        <input type="text" name="business_license_url" placeholder="/uploads/documents/license.pdf" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-on-surface">Loại sản phẩm <span class="text-error">*</span></span>
                        <input type="text" name="product_category" required class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10">
                    </label>
                </div>
                <label class="block">
                    <span class="text-xs font-bold text-on-surface-variant">Sản phẩm mẫu</span>
                    <textarea name="sample_products" rows="3" placeholder="Mỗi dòng một sản phẩm mẫu" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10"></textarea>
                </label>
                <label class="block">
                    <span class="text-xs font-bold text-on-surface-variant">Ảnh sản phẩm mẫu</span>
                    <textarea name="sample_images" rows="3" placeholder="Mỗi dòng một URL ảnh" class="mt-1 w-full border border-border-subtle rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10"></textarea>
                </label>
                <button type="submit" class="bg-primary text-white font-semibold text-sm px-6 py-2.5 rounded-xl hover:bg-primary-container transition-all shadow-sm flex items-center gap-2 active:scale-[0.98]">
                    <span class="material-symbols-outlined text-[18px]">send</span>Gửi đơn
                </button>
            </form>
        </div>

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
