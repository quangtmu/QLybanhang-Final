<?php
require_once __DIR__ . '/../config/config.php';

try {
    $db = getDB();
    // Thêm cột stock_quantity nếu chưa có
    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'stock_quantity'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE products ADD COLUMN stock_quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_price");
        echo "Cột stock_quantity đã được thêm thành công.\n";
    } else {
        echo "Cột stock_quantity đã tồn tại.\n";
    }

} catch (PDOException $e) {
    echo "Lỗi database: " . $e->getMessage() . "\n";
}
