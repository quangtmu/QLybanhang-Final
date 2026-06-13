<?php

declare(strict_types=1);

class FlashSaleModel
{
    public static function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Tên Flash Sale không được để trống.');
        }

        $stmt = getDB()->prepare(
            'INSERT INTO flash_sales (name, start_date, end_date, is_active)
             VALUES (:name, :start_date, :end_date, :is_active)'
        );
        $stmt->execute([
            ':name' => $name,
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
            ':is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        ]);

        return (int) getDB()->lastInsertId();
    }

    public static function getAll(): array
    {
        $stmt = getDB()->prepare('SELECT * FROM flash_sales ORDER BY start_date DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = getDB()->prepare('SELECT * FROM flash_sales WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Tên Flash Sale không được để trống.');
        }

        $stmt = getDB()->prepare(
            'UPDATE flash_sales
             SET name = :name, start_date = :start_date, end_date = :end_date, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
            ':is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        ]);
    }

    public static function getActiveFlashSale(): ?array
    {
        $stmt = getDB()->prepare(
            'SELECT * FROM flash_sales
             WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()
             ORDER BY start_date ASC LIMIT 1'
        );
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function addProduct(int $flashSaleId, int $productId, float $discountPrice, int $stockQuantity): void
    {
        $stmt = getDB()->prepare(
            'INSERT INTO flash_sale_products (flash_sale_id, product_id, discount_price, stock_quantity)
             VALUES (:fs_id, :p_id, :price, :stock)
             ON DUPLICATE KEY UPDATE discount_price = :price2, stock_quantity = :stock2'
        );
        $stmt->execute([
            ':fs_id' => $flashSaleId,
            ':p_id' => $productId,
            ':price' => $discountPrice,
            ':stock' => $stockQuantity,
            ':price2' => $discountPrice,
            ':stock2' => $stockQuantity,
        ]);
    }

    public static function getProducts(int $flashSaleId): array
    {
        $stmt = getDB()->prepare(
            'SELECT fsp.*, p.name AS product_name, p.main_image_url, p.base_price, sp.store_name
             FROM flash_sale_products fsp
             JOIN products p ON p.id = fsp.product_id
             JOIN store_profiles sp ON sp.user_id = p.store_id
             WHERE fsp.flash_sale_id = :fs_id'
        );
        $stmt->execute([':fs_id' => $flashSaleId]);
        return $stmt->fetchAll();
    }

    public static function addBulkProducts(int $flashSaleId, string $type, int $targetId, float $discountPercent, int $stockQuantity): int
    {
        $discountFactor = 1.0 - ($discountPercent / 100.0);
        $where = '';
        $params = [':target' => $targetId, ':status' => PRODUCT_STATUS_APPROVED];
        
        if ($type === 'product') {
            $where = 'id = :target';
        } elseif ($type === 'shop') {
            $where = 'store_id = :target';
        } elseif ($type === 'category') {
            $where = 'category_id = :target';
        } else {
            throw new RuntimeException('Loại áp dụng không hợp lệ.');
        }

        $stmt = getDB()->prepare("SELECT id, base_price FROM products WHERE $where AND status = :status AND deleted_at IS NULL");
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        if (!$products) return 0;

        $count = 0;
        foreach ($products as $p) {
            $discountPrice = floor($p['base_price'] * $discountFactor);
            self::addProduct($flashSaleId, (int) $p['id'], $discountPrice, $stockQuantity);
            $count++;
        }
        
        return $count;
    }
}
