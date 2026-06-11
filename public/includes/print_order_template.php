<?php
// Shared template for printing order
// Expected variables: $order, $items, $printTitle

// Fetch store info if available (or use defaults)
$storeName = $order['store_name'] ?? 'Shop';
$buyerName = $order['buyer_name'] ?? '';
$buyerEmail = $order['buyer_email'] ?? '';
$shippingData = $order['shipping_address_data'] ?? json_decode((string)($order['shipping_address'] ?? ''), true) ?: [];
if (!empty($shippingData)) {
    $buyerName = $shippingData['receiver_name'] ?? $buyerName;
    $shippingPhone = $shippingData['receiver_phone'] ?? ($order['shipping_phone'] ?? 'Không có SĐT');
    $addressParts = array_filter([
        $shippingData['address_line'] ?? '',
        $shippingData['ward'] ?? '',
        $shippingData['district'] ?? '',
        $shippingData['province'] ?? ''
    ]);
    $shippingAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Khách nhận tại quầy hoặc không có địa chỉ.';
} else {
    $shippingAddress = $order['shipping_address'] ?? 'Khách nhận tại quầy hoặc không có địa chỉ.';
    $shippingPhone = $order['shipping_phone'] ?? 'Không có SĐT';
}

$orderCode = $order['order_code'] ?? '';
$totalAmount = number_format((float) ($order['total_amount'] ?? 0)) . 'đ';
$finalAmount = number_format((float) ($order['final_amount'] ?? 0)) . 'đ';
$createdAt = $order['created_at'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($printTitle) ?></title>
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            color: #000;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
        }
        .print-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border: 2px dashed #000;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            text-transform: uppercase;
        }
        .barcode {
            margin-top: 10px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
            padding: 5px;
            border: 1px solid #000;
            display: inline-block;
        }
        .section {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .section-half {
            width: 48%;
        }
        .section-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            color: #555;
            margin-bottom: 5px;
        }
        .info-text {
            font-size: 16px;
            line-height: 1.5;
            margin: 0;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th, .table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background: #f1f5f9;
            font-weight: bold;
        }
        .footer {
            text-align: right;
            font-size: 18px;
        }
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #000;
        }
        .print-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 220px;
            margin: 30px auto;
            padding: 14px;
            background: linear-gradient(180deg, #0f766e, #115e59);
            color: #fff;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            border: 1px solid #115e59;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.2);
            transition: all 0.2s;
        }
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(15, 118, 110, 0.3);
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .print-container {
                box-shadow: none;
                border: 2px dashed #000;
                margin: 0;
                width: 100%;
                max-width: none;
            }
            .print-btn {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="print-btn">🖨️ Bấm để in đơn</button>

    <div class="print-container">
        <div class="header">
            <h1>Phiếu Giao Hàng</h1>
            <div class="barcode">*<?= htmlspecialchars($orderCode) ?>*</div>
            <p style="margin: 5px 0 0; font-size: 12px;">Ngày tạo: <?= htmlspecialchars($createdAt) ?></p>
        </div>

        <div class="section">
            <div class="section-half">
                <div class="section-title">Người gửi (Store)</div>
                <p class="info-text"><strong><?= htmlspecialchars($storeName) ?></strong></p>
            </div>
            <div class="section-half">
                <div class="section-title">Người nhận (Khách hàng)</div>
                <p class="info-text"><strong><?= htmlspecialchars($buyerName) ?></strong></p>
                <p class="info-text">SĐT: <strong><?= htmlspecialchars($shippingPhone) ?></strong></p>
                <p class="info-text">Địa chỉ: <?= nl2br(htmlspecialchars($shippingAddress)) ?></p>
            </div>
        </div>

        <div class="section-title">Chi tiết sản phẩm</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 60%">Sản phẩm</th>
                    <th style="width: 20%">SL</th>
                    <th style="width: 20%">Giá</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                <?php
                                $varDetails = [];
                                if (!empty($item['type_label'])) $varDetails[] = $item['type_label'];
                                if (!empty($item['color'])) $varDetails[] = $item['color'];
                                if (!empty($item['size'])) $varDetails[] = $item['size'];
                                if (!empty($varDetails)) {
                                    echo "<br><span style='font-size:12px;'>" . htmlspecialchars(implode(' - ', $varDetails)) . "</span>";
                                }
                                ?>
                            </td>
                            <td style="text-align: center; font-weight: bold;"><?= (int)$item['quantity'] ?></td>
                            <td><?= number_format((float)($item['unit_price'] ?? $item['price'] ?? 0)) . 'đ' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Không có thông tin sản phẩm.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <div style="margin-bottom: 5px;">Tạm tính: <strong><?= $totalAmount ?></strong></div>
            <div>Tổng thu (COD): <span class="total-amount"><?= $finalAmount ?></span></div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #555;">
            Cảm ơn quý khách đã mua hàng!
        </div>
    </div>

    <script>
        // Tự động bật cửa sổ in khi mở trang (tuỳ chọn)
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
