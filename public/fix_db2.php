<?php
require_once __DIR__ . '/../config/config.php';
try {
    $db = getDB();
    $columns = [
        "ADD COLUMN `discount_price` DECIMAL(15,2) NULL AFTER `base_price`",
        "ADD COLUMN `stock_quantity` INT NOT NULL DEFAULT 0 AFTER `discount_price`",
        "ADD COLUMN `weight_unit` ENUM('g','kg') NOT NULL DEFAULT 'g' AFTER `weight`",
        "ADD COLUMN `volume` DECIMAL(8,2) NULL AFTER `weight_unit`",
        "ADD COLUMN `volume_unit` ENUM('ml','l','m3') NULL AFTER `volume`",
        "ADD COLUMN `length` DECIMAL(8,2) NULL AFTER `volume_unit`",
        "ADD COLUMN `width` DECIMAL(8,2) NULL AFTER `length`",
        "ADD COLUMN `height` DECIMAL(8,2) NULL AFTER `width`",
        "ADD COLUMN `is_recommended` TINYINT(1) NOT NULL DEFAULT 0 AFTER `height`"
    ];
    foreach ($columns as $colSql) {
        try {
            $db->exec("ALTER TABLE `products` " . $colSql);
        } catch (Exception $e) {
            // Ignore if column exists
        }
    }
    
    // Also add restock_wait_days to product_variants
    try {
        $db->exec("ALTER TABLE `product_variants` ADD COLUMN `restock_wait_days` INT NOT NULL DEFAULT 0");
    } catch (Exception $e) {
        // Ignore if column exists
    }

    echo "<h3>Thêm các cột thành công!</h3>";
    echo "<p>Vui lòng xóa file này đi để bảo mật.</p>";
} catch (Exception $e) {
    echo "<h3>Lỗi (hoặc đã thêm từ trước):</h3><p>" . $e->getMessage() . "</p>";
}
