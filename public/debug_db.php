<?php
require_once __DIR__ . '/../config/config.php';
try {
    $db = getDB();
    
    echo "<h3>Products columns:</h3><ul>";
    $stmt = $db->query("SHOW COLUMNS FROM `products`");
    foreach($stmt->fetchAll() as $row) echo "<li>{$row['Field']}</li>";
    echo "</ul>";

    echo "<h3>Product Variants columns:</h3><ul>";
    $stmt = $db->query("SHOW COLUMNS FROM `product_variants`");
    foreach($stmt->fetchAll() as $row) echo "<li>{$row['Field']}</li>";
    echo "</ul>";

    echo "<h3>Adding restock_wait_days:</h3>";
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("ALTER TABLE `product_variants` ADD COLUMN `restock_wait_days` INT NOT NULL DEFAULT 0");
    echo "<p>Added restock_wait_days successfully!</p>";
} catch (Exception $e) {
    echo "<h3>Exception (restock_wait_days):</h3><p>" . $e->getMessage() . "</p>";
}

try {
    echo "<h3>Adding product_reviews table:</h3>";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `product_reviews` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` BIGINT UNSIGNED NOT NULL,
            `order_item_id` BIGINT UNSIGNED NOT NULL,
            `product_id` BIGINT UNSIGNED NOT NULL,
            `buyer_id` BIGINT UNSIGNED NOT NULL,
            `rating` TINYINT UNSIGNED NOT NULL,
            `comment` TEXT NULL,
            `store_reply` TEXT NULL,
            `store_replied_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_product_reviews_order_item` (`order_item_id`),
            KEY `idx_product_reviews_product_id` (`product_id`),
            KEY `idx_product_reviews_buyer_id` (`buyer_id`),
            KEY `idx_product_reviews_order_id` (`order_id`),
            CONSTRAINT `fk_product_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_product_reviews_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_product_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_product_reviews_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `chk_product_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "<p>Added product_reviews successfully!</p>";
} catch (Exception $e) {
    echo "<h3>Exception (product_reviews):</h3><p>" . $e->getMessage() . "</p>";
}

try {
    echo "<h3>Adding buyer_id to vouchers:</h3>";
    $db->exec("ALTER TABLE `vouchers` ADD COLUMN `buyer_id` BIGINT UNSIGNED NULL AFTER `store_id`");
    echo "<p>Added buyer_id successfully!</p>";
} catch (Exception $e) {
    echo "<h3>Exception (buyer_id):</h3><p>" . $e->getMessage() . "</p>";
}
