<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/flash.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = AuthMiddleware::user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hỗ trợ khách hàng - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background pt-[64px] pb-20 lg:pb-8">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="max-w-4xl mx-auto w-full px-4 mt-8 flex-grow">
        <div class="bg-white rounded-2xl border border-border-subtle p-6 md:p-10 shadow-sm mb-8">
            <h1 class="text-2xl font-bold text-on-surface mb-6 flex items-center gap-2 border-b border-border-subtle pb-4">
                <span class="material-symbols-outlined text-[28px] text-primary">support_agent</span> Trung tâm hỗ trợ
            </h1>
            
            <div class="space-y-6 text-on-surface-variant">
                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">1. Liên hệ với chúng tôi</h2>
                    <p class="mb-2">Nếu bạn cần hỗ trợ về đơn hàng, sản phẩm hoặc các vấn đề kỹ thuật, vui lòng liên hệ với OmniSales qua các kênh sau:</p>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li><strong>Hotline:</strong> 1900 xxxx (8:00 - 22:00 hàng ngày)</li>
                        <li><strong>Email:</strong> support@omnisales.vn</li>
                        <li><strong>Trò chuyện trực tuyến:</strong> Sử dụng tính năng <a href="/chat.php" class="text-primary hover:underline">Liên hệ shop</a> hoặc chat với Admin.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">2. Câu hỏi thường gặp (FAQ)</h2>
                    <div class="space-y-4 mt-3">
                        <div class="bg-surface-container-low p-4 rounded-xl border border-border-subtle">
                            <h3 class="font-bold text-on-surface mb-1">Làm sao để theo dõi đơn hàng?</h3>
                            <p class="text-sm">Bạn có thể theo dõi tiến trình đơn hàng trong phần <a href="/user/orders.php?tab=manage" class="text-primary hover:underline">Quản lý Đơn hàng</a>. Trạng thái sẽ được cập nhật theo thời gian thực.</p>
                        </div>
                        <div class="bg-surface-container-low p-4 rounded-xl border border-border-subtle">
                            <h3 class="font-bold text-on-surface mb-1">Tôi có thể thay đổi địa chỉ nhận hàng sau khi đặt không?</h3>
                            <p class="text-sm">Địa chỉ không thể thay đổi sau khi đơn hàng chuyển sang trạng thái Đang giao. Vui lòng liên hệ shop để được hỗ trợ kịp thời nếu đơn hàng đang ở trạng thái Chờ xác nhận.</p>
                        </div>
                    </div>
                </section>
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
