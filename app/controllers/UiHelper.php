<?php

declare(strict_types=1);

class UiHelper
{
    public static function money(float|int|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 0, ',', '.') . ' đ';
    }

    public static function slugify(string $string): string
    {
        $string = mb_strtolower($string, 'UTF-8');
        $string = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/', 'a', $string);
        $string = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/', 'e', $string);
        $string = preg_replace('/(ì|í|ị|ỉ|ĩ)/', 'i', $string);
        $string = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/', 'o', $string);
        $string = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/', 'u', $string);
        $string = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/', 'y', $string);
        $string = preg_replace('/(đ)/', 'd', $string);
        $string = preg_replace('/[^a-z0-9\-]+/', '-', $string);
        return trim((string) preg_replace('/-+/', '-', $string), '-');
    }

    public static function productUrl(int $id, string $slug = null): string
    {
        if ($slug) {
            return '/san-pham/' . $slug;
        }
        return '/user/product-detail.php?id=' . $id;
    }

    public static function categoryUrl(int $id, string $slug = null): string
    {
        if ($slug) {
            return '/danh-muc/' . $slug;
        }
        return '/user/products.php?category=' . $id;
    }

    public static function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            PRODUCT_STATUS_DRAFT => 'Bản nháp',
            PRODUCT_STATUS_PENDING_REVIEW => 'Chờ duyệt',
            PRODUCT_STATUS_APPROVED => 'Đã duyệt',
            PRODUCT_STATUS_REJECTED => 'Bị từ chối',
            PRODUCT_STATUS_ARCHIVED => 'Đã lưu trữ',
            ORDER_STATUS_PENDING => 'Chờ xác nhận',
            ORDER_STATUS_CONFIRMED => 'Đã xác nhận',
            ORDER_STATUS_PROCESSING => 'Đang chuẩn bị',
            ORDER_STATUS_SHIPPED => 'Đã gửi hàng',
            ORDER_STATUS_DELIVERING => 'Đang giao',
            ORDER_STATUS_DELIVERED => 'Đã giao',
            ORDER_STATUS_CANCELLED => 'Đã hủy',
            ORDER_STATUS_REFUNDING => 'Đang hoàn tiền',
            ORDER_STATUS_REFUNDED => 'Đã hoàn tiền',
            SHIPMENT_STATUS_WAITING_PICKUP => 'Chờ lấy hàng',
            SHIPMENT_STATUS_PICKED_UP => 'Đã lấy hàng',
            SHIPMENT_STATUS_IN_TRANSIT => 'Đang vận chuyển',
            SHIPMENT_STATUS_OUT_FOR_DELIVERY => 'Đang giao cho khách',
            SHIPMENT_STATUS_DELIVERED => 'Giao thành công',
            SHIPMENT_STATUS_CANCELLED => 'Đã hủy',
            USER_TYPE_ADMIN => 'Quản trị viên',
            USER_TYPE_SUB_ADMIN_ACTIVE => 'Sub-admin đang hoạt động',
            USER_TYPE_SUB_ADMIN_INACTIVE => 'Sub-admin tạm khóa',
            USER_TYPE_STORE_PENDING => 'Shop chờ duyệt',
            USER_TYPE_STORE_APPROVED => 'Shop đã duyệt',
            USER_TYPE_STORE_REJECTED => 'Shop bị từ chối',
            USER_TYPE_STORE_SUSPENDED => 'Shop tạm khóa',
            USER_TYPE_STORE_EMPLOYEE => 'Nhân viên shop',
            USER_TYPE_USER => 'Khách hàng',
            USER_TYPE_USER_BANNED => 'Khách hàng bị khóa',
            default => $status ? str_replace('_', ' ', (string) $status) : 'Chưa cập nhật',
        };
    }

    public static function statusClass(?string $status): string
    {
        return match ((string) $status) {
            PRODUCT_STATUS_APPROVED,
            ORDER_STATUS_DELIVERED,
            SHIPMENT_STATUS_DELIVERED => 'badge-success',
            PRODUCT_STATUS_PENDING_REVIEW,
            ORDER_STATUS_PENDING,
            ORDER_STATUS_PROCESSING,
            SHIPMENT_STATUS_WAITING_PICKUP,
            SHIPMENT_STATUS_IN_TRANSIT,
            SHIPMENT_STATUS_OUT_FOR_DELIVERY => 'badge-warning',
            PRODUCT_STATUS_REJECTED,
            ORDER_STATUS_CANCELLED,
            ORDER_STATUS_REFUNDED,
            SHIPMENT_STATUS_CANCELLED => 'badge-error',
            PRODUCT_STATUS_ARCHIVED => 'badge-muted',
            default => '',
        };
    }

    public static function requiredMark(): string
    {
        return '&nbsp;<span class="required-mark" aria-hidden="true">*</span>';
    }

    public static function sortLink(string $column, string $label, string $currentSortBy, string $currentSortDir, array $queryParams): string
    {
        $dir = 'DESC';
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.3; margin-left:2px"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></svg>';
        
        if ($currentSortBy === $column) {
            $dir = $currentSortDir === 'ASC' ? 'DESC' : 'ASC';
            $icon = $currentSortDir === 'ASC' 
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:2px"><path d="m18 15-6-6-6 6"/></svg>' 
                : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:2px"><path d="m6 9 6 6 6-6"/></svg>';
        }
        
        $query = $queryParams;
        $query['sort_by'] = $column;
        $query['sort_dir'] = $dir;
        
        return '<a href="?' . htmlspecialchars(http_build_query($query)) . '" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; white-space: nowrap;">' . htmlspecialchars($label) . $icon . '</a>';
    }

    public static function richTextHtml(?string $html): string
    {
        $value = trim((string) $html);
        if ($value === '') {
            return '';
        }

        if ($value === strip_tags($value)) {
            return nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        return self::sanitizeRichText($value);
    }

    public static function sanitizeRichText(?string $html): string
    {
        $value = trim((string) $html);
        if ($value === '') {
            return '';
        }

        $value = str_replace("\0", '', $value);
        $value = preg_replace('/<(script|style|iframe|object|embed|meta|link|form|input|button|svg|math)\b[^>]*>.*?<\/\1>/is', '', $value) ?? '';
        $value = preg_replace('/<\/?(script|style|iframe|object|embed|meta|link|form|input|button|svg|math)\b[^>]*>/is', '', $value) ?? '';

        $allowedTags = '<p><br><div><span><strong><b><em><i><u><s><ul><ol><li><a><h2><h3><h4><blockquote>';
        $value = strip_tags($value, $allowedTags);

        $value = preg_replace_callback('/<([a-z][a-z0-9]*)(\s[^>]*)?>/i', static function (array $match): string {
            $tag = strtolower($match[1]);
            $attrs = $match[2] ?? '';

            if ($tag === 'br') {
                return '<br>';
            }

            $safeAttrs = [];
            if ($attrs !== '') {
                preg_match_all('/([a-zA-Z0-9:-]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+)/', $attrs, $attrMatches, PREG_SET_ORDER);

                foreach ($attrMatches as $attrMatch) {
                    $name = strtolower($attrMatch[1]);
                    $raw = trim($attrMatch[2], "\"'");
                    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if ($name === 'href' && $tag === 'a') {
                        $href = trim($raw);
                        if (preg_match('/^(https?:\/\/|mailto:|tel:|\/(?!\/))/i', $href)) {
                            $safeAttrs['href'] = $href;
                            $safeAttrs['target'] = '_blank';
                            $safeAttrs['rel'] = 'noopener noreferrer';
                        }
                        continue;
                    }

                    if ($name === 'style' && in_array($tag, ['p', 'div', 'span', 'h2', 'h3', 'h4', 'blockquote'], true)) {
                        $style = self::sanitizeRichTextStyle($raw);
                        if ($style !== '') {
                            $safeAttrs['style'] = $style;
                        }
                    }
                }
            }

            $attrHtml = '';
            foreach ($safeAttrs as $name => $rawValue) {
                $attrHtml .= ' ' . $name . '="' . htmlspecialchars($rawValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }

            return '<' . $tag . $attrHtml . '>';
        }, $value) ?? '';

        $value = preg_replace('/(<br>\s*){3,}/i', '<br><br>', $value) ?? '';

        return trim($value);
    }

    private static function sanitizeRichTextStyle(string $style): string
    {
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }

            [$property, $rawValue] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $rawValue = strtolower($rawValue);

            if ($property === 'text-align' && in_array($rawValue, ['left', 'center', 'right', 'justify'], true)) {
                $safe[] = 'text-align: ' . $rawValue;
                continue;
            }

            if ($property === 'font-size' && preg_match('/^([0-9]+(?:\.[0-9]+)?)(px|rem|em)$/', $rawValue, $matches)) {
                $size = (float) $matches[1];
                $unit = $matches[2];
                if (($unit === 'px' && $size >= 12 && $size <= 32) || ($unit !== 'px' && $size >= 0.75 && $size <= 2)) {
                    $sizeValue = floor($size) === $size
                        ? (string) (int) $size
                        : rtrim(rtrim(number_format($size, 2, '.', ''), '0'), '.');
                    $safe[] = 'font-size: ' . $sizeValue . $unit;
                }
            }
        }

        return implode('; ', $safe);
    }
}
