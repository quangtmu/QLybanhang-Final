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
    <title>Chính sách Đổi trả - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background pt-[64px] pb-20 lg:pb-8">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="max-w-4xl mx-auto w-full px-4 mt-8 flex-grow">
        <div class="bg-white rounded-2xl border border-border-subtle p-6 md:p-10 shadow-sm mb-8">
            <h1 class="text-2xl font-bold text-on-surface mb-6 flex items-center gap-2 border-b border-border-subtle pb-4">
                <span class="material-symbols-outlined text-[28px] text-primary">assignment_return</span> Chính sách Đổi trả
            </h1>
            
            <div class="space-y-6 text-on-surface-variant leading-relaxed text-sm md:text-base">
                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">1. Điều kiện đổi trả</h2>
                    <p>Khách hàng có thể yêu cầu đổi trả sản phẩm trong vòng <strong>7 ngày</strong> kể từ ngày nhận hàng thành công nếu đáp ứng các điều kiện sau:</p>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li>Sản phẩm bị lỗi kỹ thuật do nhà sản xuất.</li>
                        <li>Sản phẩm giao không đúng màu sắc, kích thước, hoặc mẫu mã như đã đặt.</li>
                        <li>Sản phẩm còn nguyên vẹn, đầy đủ tem mác, hộp đựng và chưa qua sử dụng.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">2. Quy trình đổi trả</h2>
                    <ol class="list-decimal pl-5 space-y-1 mt-2">
                        <li>Liên hệ trực tiếp với người bán qua phần <a href="/chat.php" class="text-primary hover:underline">Liên hệ shop</a> hoặc chat hỗ trợ.</li>
                        <li>Cung cấp video/hình ảnh chứng minh lỗi sản phẩm hoặc sự nhầm lẫn.</li>
                        <li>Đóng gói sản phẩm cẩn thận và gửi về địa chỉ shop được hướng dẫn.</li>
                        <li>Shop kiểm tra và tiến hành hoàn tiền hoặc gửi sản phẩm thay thế trong vòng 3-5 ngày làm việc.</li>
                    </ol>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">3. Các trường hợp từ chối đổi trả</h2>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li>Quá thời hạn 7 ngày kể từ ngày nhận hàng.</li>
                        <li>Sản phẩm bị hư hỏng do lỗi của người sử dụng (rơi vỡ, trầy xước, vào nước...).</li>
                        <li>Sản phẩm khuyến mãi, giảm giá sốc không áp dụng chính sách đổi trả (trừ khi có quy định riêng).</li>
                    </ul>
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
