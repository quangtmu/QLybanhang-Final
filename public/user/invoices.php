<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once BASE_PATH . '/includes/invoice_table.php';
require_once __DIR__ . '/_buyer_ui.php';

$user = PermissionMiddleware::requireModule(MODULE_INVOICES);
$filters = [
    'search' => $_GET['search'] ?? '',
    'order_status' => $_GET['order_status'] ?? '',
    'page' => $_GET['page'] ?? 1,
    'limit' => 20,
];
$result = InvoiceModel::paginateForActor($user, $filters);
$invoices = $result['items'];
$pagination = $result['pagination'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn - OmniSales</title>
    <?php include __DIR__ . '/_tailwind_head.php'; ?>
</head>
<body class="font-body-md text-body-md antialiased min-h-screen flex flex-col bg-background text-on-background">
    <?php include __DIR__ . '/_tailwind_header.php'; ?>

    <main class="flex-grow pt-[72px] pb-20 lg:pb-8">
        <div class="max-w-5xl mx-auto px-4 md:px-6">

        <!-- Header -->
        <div class="flex items-center justify-between mt-2 mb-4">
            <div>
                <h1 class="text-lg font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">description</span>Hóa đơn
                </h1>
                <p class="text-xs text-on-surface-variant mt-0.5"><?= (int) $pagination['total'] ?> hóa đơn</p>
            </div>
        </div>

        <!-- Filter -->
        <form method="get" class="bg-white rounded-xl border border-border-subtle p-3 mb-4">
            <div class="flex flex-wrap gap-2">
                <div class="flex-1 min-w-[200px] relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] text-outline">search</span>
                    <input type="search" name="search" value="<?= htmlspecialchars((string) $filters['search']) ?>" placeholder="Tìm hóa đơn, đơn hàng, shop..." class="w-full border border-border-subtle rounded-lg py-2 pl-9 pr-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all">
                </div>
                <select name="order_status" class="border border-border-subtle rounded-lg px-3 py-2 text-sm bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 min-w-[140px]">
                    <option value="">Trạng thái đơn</option>
                    <?php foreach (OrderModel::orderStatuses() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['order_status'] === $status ? 'selected' : '' ?>>
                            <?= htmlspecialchars(UiHelper::statusLabel($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-primary-container transition-all shadow-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">filter_list</span>Lọc
                </button>
            </div>
        </form>

        <!-- Invoice Table -->
        <div class="bg-white rounded-xl border border-border-subtle overflow-hidden">
            <?php if (!$invoices): ?>
                <div class="text-center py-12">
                    <span class="material-symbols-outlined text-[40px] text-outline-variant mb-2">description</span>
                    <p class="text-on-surface-variant text-sm font-semibold">Chưa có hóa đơn nào</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-surface-container-low/50 text-on-surface-variant text-xs uppercase font-bold border-b border-border-subtle">
                            <tr>
                                <th class="px-4 py-3">Hóa đơn</th>
                                <th class="px-4 py-3">Ngày xuất</th>
                                <th class="px-4 py-3">Đơn hàng</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3">Shop</th>
                                <th class="px-4 py-3 text-right">Giá trị</th>
                                <th class="px-4 py-3 text-right">Thuế</th>
                                <th class="px-4 py-3 text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            <?php foreach ($invoices as $invoice): ?>
                                <tr class="hover:bg-surface-container-low/30 transition-colors">
                                    <td class="px-4 py-3 font-semibold text-primary">#<?= htmlspecialchars((string) $invoice['invoice_code']) ?></td>
                                    <td class="px-4 py-3 text-on-surface-variant"><?= htmlspecialchars((string) $invoice['created_at']) ?></td>
                                    <td class="px-4 py-3 font-semibold text-on-surface">#<?= htmlspecialchars((string) $invoice['order_code']) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-surface-container-high text-on-surface-variant">
                                            <?= htmlspecialchars(UiHelper::statusLabel((string) $invoice['order_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-on-surface flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[14px] text-on-surface-variant">store</span>
                                        <?= htmlspecialchars((string) (($invoice['store_name'] ?? '') ?: ($invoice['store_email'] ?? 'Shop'))) ?>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-on-surface"><?= number_format((float) $invoice['total_amount'], 0, ',', '.') ?>đ</td>
                                    <td class="px-4 py-3 text-right text-on-surface-variant"><?= number_format((float) $invoice['tax_amount'], 0, ',', '.') ?>đ</td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="/invoice-download.php?id=<?= (int) $invoice['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary/10 text-primary hover:bg-primary/20 transition-colors rounded-lg font-semibold text-xs" title="Tải PDF">
                                            <span class="material-symbols-outlined text-[16px]">download</span>Tải PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
