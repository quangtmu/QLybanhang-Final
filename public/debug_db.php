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
    echo "<p>Added successfully!</p>";

} catch (Exception $e) {
    echo "<h3>Exception:</h3><p>" . $e->getMessage() . "</p>";
}
