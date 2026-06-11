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
    <title>Điều khoản Dịch vụ - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background pt-[64px] pb-20 lg:pb-8">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="max-w-4xl mx-auto w-full px-4 mt-8 flex-grow">
        <div class="bg-white rounded-2xl border border-border-subtle p-6 md:p-10 shadow-sm mb-8">
            <h1 class="text-2xl font-bold text-on-surface mb-6 flex items-center gap-2 border-b border-border-subtle pb-4">
                <span class="material-symbols-outlined text-[28px] text-primary">gavel</span> Điều khoản Dịch vụ
            </h1>
            
            <div class="space-y-6 text-on-surface-variant leading-relaxed text-sm md:text-base">
                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">1. Chấp nhận điều khoản</h2>
                    <p>Bằng việc truy cập và sử dụng website OmniSales, bạn đồng ý tuân thủ các Điều khoản Dịch vụ này. Nếu bạn không đồng ý với bất kỳ phần nào của điều khoản, vui lòng không sử dụng dịch vụ của chúng tôi.</p>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">2. Tài khoản người dùng</h2>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li>Bạn chịu trách nhiệm bảo mật thông tin tài khoản và mật khẩu của mình.</li>
                        <li>Bạn đồng ý cung cấp thông tin chính xác, đầy đủ khi đăng ký tài khoản.</li>
                        <li>OmniSales có quyền tạm ngưng hoặc khóa tài khoản nếu phát hiện hành vi gian lận, vi phạm pháp luật hoặc vi phạm quy định của hệ thống.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-bold text-on-surface mb-2">3. Trách nhiệm của các bên</h2>
                    <ul class="list-disc pl-5 space-y-1 mt-2">
                        <li><strong>Đối với người bán:</strong> Cam kết cung cấp sản phẩm chất lượng, mô tả trung thực và tuân thủ các chính sách bảo hành, đổi trả.</li>
                        <li><strong>Đối với người mua:</strong> Đọc kỹ thông tin sản phẩm trước khi mua, thanh toán đầy đủ và đánh giá sản phẩm một cách công tâm.</li>
                        <li><strong>Đối với OmniSales:</strong> Đóng vai trò là nền tảng kết nối. Chúng tôi không trực tiếp sản xuất hay sở hữu sản phẩm, nhưng cam kết hỗ trợ giải quyết tranh chấp một cách công bằng.</li>
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
