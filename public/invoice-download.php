<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$user = PermissionMiddleware::requireModule(MODULE_INVOICES);
$invoiceId = (int) ($_GET['id'] ?? 0);

if ($invoiceId <= 0) {
    http_response_code(422);
    echo 'Thieu invoice id.';
    exit;
}

$invoice = InvoiceModel::detailForActor($invoiceId, $user);

if (!$invoice) {
    http_response_code(404);
    echo 'Không tìm thấy hóa đơn.';
    exit;
}

$path = InvoiceModel::ensurePdfFile($invoice);
$fileName = $invoice['invoice_code'] . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
