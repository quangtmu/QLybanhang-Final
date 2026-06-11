<?php

declare(strict_types=1);

class CartModel
{
    public static function getForBuyer(int $buyerId): array
    {
        $cartId = self::ensureCart($buyerId);
        $items = self::items($buyerId);

        return [
            'id' => $cartId,
            'items' => $items,
            'summary' => self::summary($items),
        ];
    }

    public static function addItem(int $buyerId, array $data): void
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $variantId = self::nullableInt($data['variant_id'] ?? null);
        $quantity = (int) ($data['quantity'] ?? 1);

        if ($quantity <= 0) {
            throw new RuntimeException('So luong phai lon hon 0.');
        }

        $cartId = self::ensureCart($buyerId);
        $existing = self::findExistingItem($cartId, $productId, $variantId);
        $targetQuantity = $quantity + (int) ($existing['quantity'] ?? 0);
        self::validateProductSelection($productId, $variantId, $targetQuantity);

        if ($existing) {
            $stmt = getDB()->prepare(
                'UPDATE cart_items
                 SET quantity = quantity + :quantity
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => (int) $existing['id'],
                ':quantity' => $quantity,
            ]);
            return;
        }

        $stmt = getDB()->prepare(
            'INSERT INTO cart_items (cart_id, product_id, variant_id, quantity)
             VALUES (:cart_id, :product_id, :variant_id, :quantity)'
        );
        $stmt->execute([
            ':cart_id' => $cartId,
            ':product_id' => $productId,
            ':variant_id' => $variantId,
            ':quantity' => $quantity,
        ]);
    }

    public static function updateItem(int $buyerId, int $itemId, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new RuntimeException('So luong phai lon hon 0.');
        }

        $item = self::requireOwnedItem($buyerId, $itemId);
        self::validateProductSelection((int) $item['product_id'], self::nullableInt($item['variant_id']), $quantity);

        $stmt = getDB()->prepare(
            'UPDATE cart_items
             SET quantity = :quantity
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $itemId,
            ':quantity' => $quantity,
        ]);
    }

    public static function removeItem(int $buyerId, int $itemId): void
    {
        self::requireOwnedItem($buyerId, $itemId);
        $stmt = getDB()->prepare('DELETE FROM cart_items WHERE id = :id');
        $stmt->execute([':id' => $itemId]);
    }

    public static function clear(int $buyerId): void
    {
        $cartId = self::ensureCart($buyerId);
        $stmt = getDB()->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute([':cart_id' => $cartId]);
    }

    private static function ensureCart(int $buyerId): int
    {
        $stmt = getDB()->prepare('SELECT id FROM carts WHERE buyer_id = :buyer_id LIMIT 1');
        $stmt->execute([':buyer_id' => $buyerId]);
        $cart = $stmt->fetch();

        if ($cart) {
            return (int) $cart['id'];
        }

        $insert = getDB()->prepare('INSERT INTO carts (buyer_id) VALUES (:buyer_id)');
        $insert->execute([':buyer_id' => $buyerId]);

        return (int) getDB()->lastInsertId();
    }

    private static function items(int $buyerId): array
    {
        $stmt = getDB()->prepare(
            'SELECT ci.id, ci.product_id, ci.variant_id, ci.quantity,
                    p.product_code, p.name AS product_name, p.base_price, p.status, p.deleted_at,
                    p.main_image_url, p.store_id,
                    pv.type_label, pv.color, pv.size, pv.sku, pv.price AS variant_price,
                    pv.stock_quantity, pv.is_active AS variant_active,
                    sp.store_name
             FROM carts c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             INNER JOIN products p ON p.id = ci.product_id
             LEFT JOIN product_variants pv ON pv.id = ci.variant_id
             LEFT JOIN store_profiles sp ON sp.user_id = p.store_id
             WHERE c.buyer_id = :buyer_id
             ORDER BY ci.created_at DESC'
        );
        $stmt->execute([':buyer_id' => $buyerId]);
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['unit_price'] = $item['variant_id'] ? (float) $item['variant_price'] : (float) $item['base_price'];
            $item['subtotal'] = $item['unit_price'] * (int) $item['quantity'];
            $item['is_available'] = self::isItemAvailable($item);
        }

        return $items;
    }

    private static function summary(array $items): array
    {
        $quantity = 0;
        $total = 0.0;

        foreach ($items as $item) {
            if (!$item['is_available']) {
                continue;
            }
            $quantity += (int) $item['quantity'];
            $total += (float) $item['subtotal'];
        }

        return [
            'item_count' => count($items),
            'available_quantity' => $quantity,
            'total' => $total,
        ];
    }

    private static function validateProductSelection(int $productId, ?int $variantId, int $quantity = 1): void
    {
        if ($productId <= 0) {
            throw new RuntimeException('Thieu sản phẩm.');
        }

        $stmt = getDB()->prepare(
            'SELECT id, has_variants, stock_quantity
             FROM products
             WHERE id = :id
               AND status = :approved_status
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $productId,
            ':approved_status' => PRODUCT_STATUS_APPROVED,
        ]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new RuntimeException('Sản phẩm không khả dụng.');
        }

        if ((int) $product['has_variants'] === 1 && $variantId === null) {
            throw new RuntimeException('Vui lòng chọn biến thể sản phẩm.');
        }

        if ($variantId === null) {
            if ((int) $product['stock_quantity'] < $quantity) {
                throw new RuntimeException('Ton kho sản phẩm không du so luong.');
            }
            return;
        }

        $variantStmt = getDB()->prepare(
            'SELECT id, stock_quantity
             FROM product_variants
             WHERE id = :id
               AND product_id = :product_id
               AND is_active = 1
             LIMIT 1'
        );
        $variantStmt->execute([
            ':id' => $variantId,
            ':product_id' => $productId,
        ]);
        $variant = $variantStmt->fetch();

        if (!$variant) {
            throw new RuntimeException('Biến thể sản phẩm không hop le.');
        }

        if ((int) $variant['stock_quantity'] < $quantity) {
            throw new RuntimeException('Ton kho biến thể không du so luong.');
        }
    }

    private static function findExistingItem(int $cartId, int $productId, ?int $variantId): ?array
    {
        $variantSql = $variantId === null ? 'variant_id IS NULL' : 'variant_id = :variant_id';
        $params = [
            ':cart_id' => $cartId,
            ':product_id' => $productId,
        ];

        if ($variantId !== null) {
            $params[':variant_id'] = $variantId;
        }

        $stmt = getDB()->prepare(
            "SELECT id, quantity
             FROM cart_items
             WHERE cart_id = :cart_id
               AND product_id = :product_id
               AND {$variantSql}
             LIMIT 1"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private static function requireOwnedItem(int $buyerId, int $itemId): array
    {
        $stmt = getDB()->prepare(
            'SELECT ci.*
             FROM carts c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.buyer_id = :buyer_id AND ci.id = :item_id
             LIMIT 1'
        );
        $stmt->execute([
            ':buyer_id' => $buyerId,
            ':item_id' => $itemId,
        ]);
        $item = $stmt->fetch();

        if (!$item) {
            throw new RuntimeException('Không tìm thấy sản phẩm trong giỏ hàng.');
        }

        return $item;
    }

    private static function isItemAvailable(array $item): bool
    {
        if ($item['status'] !== PRODUCT_STATUS_APPROVED || $item['deleted_at'] !== null) {
            return false;
        }

        if ($item['variant_id'] !== null && (int) $item['variant_active'] !== 1) {
            return false;
        }

        return true;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
