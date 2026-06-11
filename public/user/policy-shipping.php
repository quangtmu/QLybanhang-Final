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
    <title>Chính sách Giao hàng - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background pt-[64px] pb-20 lg:pb-8">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="max-w-4xl mx-auto w-full px-4 mt-8 flex-grow">
        <div class="bg-white rounded-2xl border border-border-subtle p-6 md:p-10 shadow-sm mb-8">
            <h1 class="text-2xl font-bold text-on-surface mb-6 flex items-center gap-2 border-b border-border-subtle pb-4">
                <span class="material-symbols-outlined text-[28px] text-primary">local_shipping</span> Chính sách Giao hàng
            </h1>
            
            <div class="space-y-6 text-on-surface-variant leading-relaxed text-sm md:text-base">
                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">1. Thời gian vận chuyển</h2>
                    <p>Thời gian giao hàng dự kiến phụ thuộc vào địa chỉ nhận hàng của bạn và đơn vị vận chuyển:</p>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li><strong>Nội thành:</strong> 1 - 2 ngày làm việc.</li>
                        <li><strong>Ngoại thành / Tỉnh lẻ:</strong> 3 - 5 ngày làm việc.</li>
                    </ul>
                    <p class="mt-2 italic text-sm text-on-surface-variant">Lưu ý: Thời gian này không bao gồm các ngày Lễ, Tết hoặc trường hợp bất khả kháng do thời tiết, thiên tai.</p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">2. Phí vận chuyển</h2>
                    <p>Phí giao hàng được tính toán tự động dựa trên trọng lượng, kích thước kiện hàng và khoảng cách từ cửa hàng đến địa chỉ nhận hàng của bạn. Bạn sẽ thấy rõ phí ship ngay tại màn hình thanh toán trước khi xác nhận đặt hàng.</p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">3. Kiểm tra hàng</h2>
                    <p>Khách hàng vui lòng kiểm tra kỹ số lượng, ngoại quan kiện hàng (có bị rách, móp méo hay không) trước khi ký nhận với bưu tá. Nếu có vấn đề bất thường, vui lòng từ chối nhận hàng và liên hệ ngay với <a href="/user/support.php" class="text-primary hover:underline">Hỗ trợ</a>.</p>
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
