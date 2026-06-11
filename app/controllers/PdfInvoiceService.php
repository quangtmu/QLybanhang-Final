<?php

declare(strict_types=1);

class PdfInvoiceService
{
    public static function writeInvoice(array $invoice, string $path): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Không thể tạo thu muc xuất hóa đơn.');
        }

        $stream = '';
        
        // --- HEADER ---
        // Blue Header Bar
        $stream .= self::rect(0, 730, 595, 112, true, "0.09 0.41 0.88");
        
        // Title
        $stream .= self::text('HOA DON BAN HANG', 40, 785, 24, true, "1 1 1");
        
        // Invoice Details (Right Aligned roughly)
        $stream .= self::text('Ma HD: ' . ($invoice['invoice_code'] ?? ''), 360, 800, 11, true, "1 1 1");
        $stream .= self::text('Ngay xuat: ' . ($invoice['created_at'] ?? date('Y-m-d H:i:s')), 360, 782, 10, false, "0.9 0.9 0.95");
        $stream .= self::text('Ma don hang: ' . ($invoice['order_code'] ?? ''), 360, 764, 10, false, "0.9 0.9 0.95");

        // --- INFO SECTION ---
        // Buyer Info
        $stream .= self::text('KHACH HANG', 40, 680, 11, true, "0.5 0.5 0.5");
        $stream .= self::text(($invoice['buyer_name'] ?? ''), 40, 660, 12, true, "0.1 0.1 0.1");
        $stream .= self::text(($invoice['buyer_email'] ?? ''), 40, 644, 10, false, "0.4 0.4 0.4");
        
        // Shop Info
        $stream .= self::text('DON VI BAN HANG', 320, 680, 11, true, "0.5 0.5 0.5");
        $stream .= self::text((($invoice['store_name'] ?? '') ?: ($invoice['store_email'] ?? '')), 320, 660, 12, true, "0.1 0.1 0.1");
        $stream .= self::text('Nguoi xuat: ' . (($invoice['issued_by_name'] ?? '') ?: ('User #' . ($invoice['issued_by'] ?? ''))), 320, 644, 10, false, "0.4 0.4 0.4");

        // --- TABLE HEADER ---
        $y = 580;
        // Light grey background for table header
        $stream .= self::rect(40, $y - 8, 515, 28, true, "0.95 0.96 0.98");
        $stream .= self::rect(40, $y - 8, 515, 28, false, "", "0.85 0.88 0.92"); // border
        $stream .= self::text('SAN PHAM', 52, $y, 10, true, "0.3 0.3 0.35");
        $stream .= self::text('SL', 340, $y, 10, true, "0.3 0.3 0.35");
        $stream .= self::text('DON GIA', 400, $y, 10, true, "0.3 0.3 0.35");
        $stream .= self::text('THANH TIEN', 475, $y, 10, true, "0.3 0.3 0.35");

        // --- TABLE ROWS ---
        $y -= 28;
        foreach (array_slice($invoice['items'] ?? [], 0, 15) as $item) {
            $stream .= self::text((string) ($item['product_name'] ?? ''), 52, $y, 10, false, "0.1 0.1 0.1", 45);
            $stream .= self::text((string) (int) ($item['quantity'] ?? 0), 340, $y, 10, false, "0.2 0.2 0.2");
            $stream .= self::text(self::money((float) ($item['unit_price'] ?? 0)), 400, $y, 10, false, "0.2 0.2 0.2");
            $stream .= self::text(self::money((float) ($item['subtotal'] ?? 0)), 475, $y, 10, true, "0.1 0.1 0.1");
            
            $y -= 12;
            $stream .= self::line(40, $y, 555, $y, "0.92 0.94 0.96");
            $y -= 16;
        }

        if (count($invoice['items'] ?? []) > 15) {
            $stream .= self::text('... va ' . (count($invoice['items']) - 15) . ' san pham khac', 52, $y, 10, false, "0.5 0.5 0.5");
            $y -= 24;
        }

        // --- TOTALS ---
        $totalBoxY = max(100, $y - 120);
        
        // Subtle background for totals
        $stream .= self::rect(320, $totalBoxY, 235, 115, true, "0.98 0.98 0.99");
        $stream .= self::rect(320, $totalBoxY, 235, 115, false, "", "0.85 0.88 0.92"); // border

        $rows = [
            ['Tong tien hang', self::money((float) ($invoice['order_total_amount'] ?? $invoice['total_amount'] ?? 0))],
            ['Phi van chuyen', self::money((float) ($invoice['shipping_fee'] ?? 0))],
            ['Giam gia', '-' . self::money((float) ($invoice['discount_amount'] ?? 0))],
            ['Thue (VAT 8%)', self::money((float) ($invoice['tax_amount'] ?? 0))],
            ['THANH TOAN', self::money((float) ($invoice['total_amount'] ?? 0))],
        ];
        
        $rowY = $totalBoxY + 88;
        foreach ($rows as $index => [$label, $value]) {
            $isTotal = $index === 4;
            $color = $isTotal ? "0.09 0.41 0.88" : "0.4 0.4 0.4";
            $valColor = $isTotal ? "0.09 0.41 0.88" : "0.1 0.1 0.1";
            $size = $isTotal ? 12 : 10;
            
            if ($isTotal) {
                $stream .= self::line(335, $rowY + 12, 540, $rowY + 12, "0.85 0.88 0.92");
                $rowY -= 6;
            }

            $stream .= self::text($label, 335, $rowY, $size, $isTotal, $color);
            $stream .= self::text($value, 460, $rowY, $size, $isTotal, $valColor);
            
            $rowY -= 20;
        }

        // --- FOOTER ---
        $stream .= self::line(40, 80, 555, 80, "0.85 0.88 0.92");
        $stream .= self::text('Cam on quy khach da mua hang!', 40, 55, 12, true, "0.09 0.41 0.88");
        $stream .= self::text('Neu ban co bat ky cau hoi nao ve hoa don nay, vui long lien he voi chung toi.', 40, 40, 10, false, "0.5 0.5 0.5");
        $stream .= self::text('Tai lieu duoc xuat tu he thong QLyBanHang.', 40, 25, 9, false, "0.6 0.6 0.6");

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>\nendobj\n",
            "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

        if (file_put_contents($path, $pdf) === false) {
            throw new RuntimeException('Không thể ghi file hóa đơn PDF.');
        }
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . ' VND';
    }

    private static function text(string $text, int $x, int $y, int $size = 10, bool $bold = false, string $color = "0 0 0", int $maxChars = 80): string
    {
        $text = strlen($text) > $maxChars ? substr($text, 0, $maxChars - 3) . '...' : $text;
        $font = $bold ? '/F2' : '/F1';
        return "BT\n{$color} rg\n{$font} {$size} Tf\n1 0 0 1 {$x} {$y} Tm (" . self::escape($text) . ") Tj\n0 0 0 rg\nET\n";
    }

    private static function rect(int $x, int $y, int $w, int $h, bool $fill = false, string $color = "0.94 0.97 1", string $strokeColor = "0.82 0.86 0.91"): string
    {
        if ($fill) {
            return "{$color} rg\n{$x} {$y} {$w} {$h} re f\n0 0 0 rg\n";
        }
        return "{$strokeColor} RG\n{$x} {$y} {$w} {$h} re S\n0 0 0 RG\n";
    }

    private static function line(int $x1, int $y1, int $x2, int $y2, string $color = "0.88 0.91 0.95"): string
    {
        return "{$color} RG\n{$x1} {$y1} m {$x2} {$y2} l S\n0 0 0 RG\n";
    }

    private static function escape(string $text): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if ($ascii === false) {
            $ascii = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
