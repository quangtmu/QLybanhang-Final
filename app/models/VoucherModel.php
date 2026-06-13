<?php

declare(strict_types=1);

class VoucherModel
{
    public static function create(array $data, ?int $storeId = null): int
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            throw new RuntimeException('Mã voucher không được để trống.');
        }

        $stmt = getDB()->prepare('SELECT id FROM vouchers WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Mã voucher đã tồn tại.');
        }

        $stmt = getDB()->prepare(
            'INSERT INTO vouchers (store_id, code, discount_type, discount_amount, min_order_amount, max_discount_amount, usage_limit, start_date, end_date)
             VALUES (:store_id, :code, :discount_type, :discount_amount, :min_order_amount, :max_discount_amount, :usage_limit, :start_date, :end_date)'
        );
        $stmt->execute([
            ':store_id' => $storeId,
            ':code' => $code,
            ':discount_type' => $data['discount_type'] === 'percent' ? 'percent' : 'fixed',
            ':discount_amount' => max(0, (float) ($data['discount_amount'] ?? 0)),
            ':min_order_amount' => max(0, (float) ($data['min_order_amount'] ?? 0)),
            ':max_discount_amount' => isset($data['max_discount_amount']) && $data['max_discount_amount'] !== '' ? max(0, (float) $data['max_discount_amount']) : null,
            ':usage_limit' => max(0, (int) ($data['usage_limit'] ?? 0)),
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
        ]);

        return (int) getDB()->lastInsertId();
    }

    public static function getStoreVouchers(int $storeId): array
    {
        $stmt = getDB()->prepare('SELECT * FROM vouchers WHERE store_id = :store_id ORDER BY created_at DESC');
        $stmt->execute([':store_id' => $storeId]);
        return $stmt->fetchAll();
    }

    public static function deleteStoreVoucher(int $id, int $storeId): void
    {
        $stmt = getDB()->prepare('DELETE FROM vouchers WHERE id = :id AND store_id = :store_id');
        $stmt->execute([':id' => $id, ':store_id' => $storeId]);
    }

    public static function findByCode(string $code): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM vouchers WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function incrementUsage(int $id): void
    {
        $stmt = getDB()->prepare('UPDATE vouchers SET used_count = used_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function validateAndCalculateDiscount(array $voucher, float $orderTotal, ?int $storeId = null, ?int $buyerId = null): float
    {
        $now = date('Y-m-d H:i:s');
        if ($now < $voucher['start_date']) {
            throw new RuntimeException('Voucher chưa đến thời gian sử dụng.');
        }
        if ($now > $voucher['end_date']) {
            throw new RuntimeException('Voucher đã hết hạn.');
        }
        if ($voucher['usage_limit'] > 0 && $voucher['used_count'] >= $voucher['usage_limit']) {
            throw new RuntimeException('Voucher đã hết lượt sử dụng.');
        }
        if ($orderTotal < $voucher['min_order_amount']) {
            throw new RuntimeException('Đơn hàng chưa đạt giá trị tối thiểu ' . number_format((float) $voucher['min_order_amount']) . 'đ để sử dụng voucher này.');
        }
        if ($voucher['store_id'] !== null && $voucher['store_id'] !== $storeId) {
            throw new RuntimeException('Voucher này không áp dụng cho shop này.');
        }
        if ($voucher['buyer_id'] !== null && $voucher['buyer_id'] !== $buyerId) {
            throw new RuntimeException('Voucher này chỉ dành riêng cho tài khoản khác.');
        }

        if ($voucher['discount_type'] === 'percent') {
            $discount = (float)$orderTotal * ((float)$voucher['discount_amount'] / 100);
            $maxDiscount = (float)($voucher['max_discount_amount'] ?? 0);
            if ($maxDiscount > 0 && $discount > $maxDiscount) {
                $discount = $maxDiscount;
            }
        } else {
            $discount = (float) $voucher['discount_amount'];
        }

        return (float) min($discount, (float)$orderTotal);
    }

    public static function getActiveVouchersForStores(array $storeIds, int $buyerId = 0): array
    {
        if (empty($storeIds)) {
            $stmt = getDB()->prepare('SELECT * FROM vouchers WHERE store_id IS NULL AND (buyer_id IS NULL OR buyer_id = :buyer_id) AND start_date <= NOW() AND end_date >= NOW() AND (usage_limit = 0 OR used_count < usage_limit)');
            $stmt->execute([':buyer_id' => $buyerId]);
            return $stmt->fetchAll();
        }
        $inStr = str_repeat('?,', count($storeIds) - 1) . '?';
        $params = $storeIds;
        $params[] = $buyerId;
        $stmt = getDB()->prepare("SELECT * FROM vouchers WHERE (store_id IS NULL OR store_id IN ($inStr)) AND (buyer_id IS NULL OR buyer_id = ?) AND start_date <= NOW() AND end_date >= NOW() AND (usage_limit = 0 OR used_count < usage_limit)");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
