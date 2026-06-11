<?php

declare(strict_types=1);

class ReviewModel
{
    public static function summaryForProduct(int $productId): array
    {
        if (!self::tableExists()) {
            return ['avg_rating' => 0.0, 'review_count' => 0];
        }

        $stmt = getDB()->prepare(
            'SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count
             FROM product_reviews
             WHERE product_id = :product_id'
        );
        $stmt->execute([':product_id' => $productId]);
        $row = $stmt->fetch() ?: [];

        return [
            'avg_rating' => (float) ($row['avg_rating'] ?? 0),
            'review_count' => (int) ($row['review_count'] ?? 0),
        ];
    }

    public static function listForProduct(int $productId, int $limit = 8): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = getDB()->prepare(
            'SELECT pr.rating, pr.comment, pr.created_at, u.full_name, u.username
             FROM product_reviews pr
             INNER JOIN users u ON u.id = pr.buyer_id
             WHERE pr.product_id = :product_id
             ORDER BY pr.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function reviewsForOrder(int $buyerId, int $orderId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = getDB()->prepare(
            'SELECT pr.*
             FROM product_reviews pr
             INNER JOIN orders o ON o.id = pr.order_id
             WHERE pr.buyer_id = :buyer_id
               AND pr.order_id = :order_id
               AND o.buyer_id = :buyer_id_check'
        );
        $stmt->execute([
            ':buyer_id' => $buyerId,
            ':buyer_id_check' => $buyerId,
            ':order_id' => $orderId,
        ]);

        $reviews = [];
        foreach ($stmt->fetchAll() as $review) {
            $reviews[(int) $review['order_item_id']] = $review;
        }

        return $reviews;
    }

    public static function save(int $buyerId, int $orderItemId, int $rating, string $comment): void
    {
        self::ensureTable();
        $rating = max(1, min(5, $rating));
        $comment = trim($comment);
        $item = self::reviewableOrderItem($buyerId, $orderItemId);

        $stmt = getDB()->prepare(
            'INSERT INTO product_reviews (order_id, order_item_id, product_id, buyer_id, rating, comment)
             VALUES (:order_id, :order_item_id, :product_id, :buyer_id, :rating, :comment)
             ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                comment = VALUES(comment),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':order_id' => (int) $item['order_id'],
            ':order_item_id' => $orderItemId,
            ':product_id' => (int) $item['product_id'],
            ':buyer_id' => $buyerId,
            ':rating' => $rating,
            ':comment' => $comment !== '' ? $comment : null,
        ]);

        self::refreshStoreRating((int) $item['store_id']);
    }

    private static function reviewableOrderItem(int $buyerId, int $orderItemId): array
    {
        $stmt = getDB()->prepare(
            'SELECT oi.id, oi.order_id, oi.product_id, o.store_id
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE oi.id = :order_item_id
               AND o.buyer_id = :buyer_id
               AND o.status = :delivered_status
             LIMIT 1'
        );
        $stmt->execute([
            ':order_item_id' => $orderItemId,
            ':buyer_id' => $buyerId,
            ':delivered_status' => ORDER_STATUS_DELIVERED,
        ]);
        $item = $stmt->fetch();

        if (!$item) {
            throw new RuntimeException('Chỉ có thể đánh giá sản phẩm trong đơn đã giao.');
        }

        return $item;
    }

    private static function refreshStoreRating(int $storeId): void
    {
        $stmt = getDB()->prepare(
            'SELECT COALESCE(AVG(pr.rating), 0) AS avg_rating
             FROM product_reviews pr
             INNER JOIN products p ON p.id = pr.product_id
             WHERE p.store_id = :store_id'
        );
        $stmt->execute([':store_id' => $storeId]);
        $avg = (float) $stmt->fetchColumn();

        $update = getDB()->prepare('UPDATE store_profiles SET rating = :rating WHERE user_id = :store_id');
        $update->execute([
            ':rating' => round($avg, 2),
            ':store_id' => $storeId,
        ]);
    }

    private static function tableExists(): bool
    {
        try {
            $stmt = getDB()->query("SHOW TABLES LIKE 'product_reviews'");
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private static function ensureTable(): void
    {
        getDB()->exec(
            'CREATE TABLE IF NOT EXISTS product_reviews (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                order_item_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                buyer_id BIGINT UNSIGNED NOT NULL,
                rating TINYINT UNSIGNED NOT NULL,
                comment TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_product_reviews_order_item (order_item_id),
                KEY idx_product_reviews_product_id (product_id),
                KEY idx_product_reviews_buyer_id (buyer_id),
                KEY idx_product_reviews_order_id (order_id),
                CONSTRAINT fk_product_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
                CONSTRAINT fk_product_reviews_order_item FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE CASCADE,
                CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
                CONSTRAINT fk_product_reviews_buyer FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
