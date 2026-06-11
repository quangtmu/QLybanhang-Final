<?php
require_once __DIR__ . '/../config/config.php';

$db = getDB();

$queries = [
    "ALTER TABLE products ADD COLUMN is_recommended TINYINT(1) NOT NULL DEFAULT 0;",
    "ALTER TABLE products ADD COLUMN discount_price DECIMAL(15,2) NULL AFTER base_price;",
    "ALTER TABLE products ADD COLUMN weight_unit ENUM('g', 'kg') NOT NULL DEFAULT 'g' AFTER weight;",
    "ALTER TABLE products ADD COLUMN volume DECIMAL(8,2) NULL AFTER weight_unit;",
    "ALTER TABLE products ADD COLUMN volume_unit ENUM('ml', 'l', 'm3') NULL AFTER volume;",
    "ALTER TABLE products ADD COLUMN length DECIMAL(8,2) NULL AFTER volume_unit;",
    "ALTER TABLE products ADD COLUMN width DECIMAL(8,2) NULL AFTER length;",
    "ALTER TABLE products ADD COLUMN height DECIMAL(8,2) NULL AFTER width;",
    "ALTER TABLE product_variants ADD COLUMN restock_wait_days INT NOT NULL DEFAULT 0;",
    "ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL DEFAULT NULL;",
    "ALTER TABLE users ADD COLUMN reset_expires_at DATETIME NULL DEFAULT NULL;"
];

foreach ($queries as $query) {
    try {
        $db->exec($query);
        echo "Successfully executed: $query\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Skipped (Column already exists): $query\n";
        } else {
            echo "Error executing $query: " . $e->getMessage() . "\n";
        }
    }
}
