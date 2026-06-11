<?php

declare(strict_types=1);

function renderInvoiceTable(array $invoices): string
{
    if (!$invoices) {
        return '<p class="empty">Chưa có hóa đơn.</p>';
    }

    $html = '<div class="table-wrap"><table class="data-table"><thead><tr><th>Hóa đơn</th><th>Ngày xuất</th><th>Đơn hàng</th><th>Trạng thái đơn</th><th>Người mua</th><th>Email</th><th>Cửa hàng</th><th>Giá trị</th><th>Thuế</th><th>Tải file</th></tr></thead><tbody>';

    foreach ($invoices as $invoice) {
        $invoiceCode = htmlspecialchars((string) $invoice['invoice_code']);
        $createdAt = htmlspecialchars((string) $invoice['created_at']);
        $orderCode = htmlspecialchars((string) $invoice['order_code']);
        $orderStatus = htmlspecialchars(UiHelper::statusLabel((string) $invoice['order_status']));
        $buyer = htmlspecialchars((string) ($invoice['buyer_name'] ?? $invoice['buyer_email'] ?? 'Buyer'));
        $buyerEmail = htmlspecialchars((string) ($invoice['buyer_email'] ?? ''));
        $store = htmlspecialchars((string) (($invoice['store_name'] ?? '') ?: ($invoice['store_email'] ?? 'Shop')));
        $amount = number_format((float) $invoice['total_amount'], 0, ',', '.');
        $tax = number_format((float) $invoice['tax_amount'], 0, ',', '.');
        $downloadUrl = '/invoice-download.php?id=' . (int) $invoice['id'];

        $html .= <<<HTML
            <tr>
                <td><strong>{$invoiceCode}</strong></td>
                <td>{$createdAt}</td>
                <td><strong>{$orderCode}</strong></td>
                <td>{$orderStatus}</td>
                <td>{$buyer}</td>
                <td>{$buyerEmail}</td>
                <td>{$store}</td>
                <td><strong>{$amount}đ</strong></td>
                <td>{$tax}đ</td>
                <td><a class="button-link" href="{$downloadUrl}">Tải PDF</a></td>
            </tr>
HTML;
    }

    return $html . '</tbody></table></div>';
}
