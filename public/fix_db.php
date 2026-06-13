<?php
require_once __DIR__ . '/../config/config.php';
try {
    getDB()->exec("ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(255) NULL AFTER `password_dev`, ADD COLUMN `reset_token_expires_at` DATETIME NULL AFTER `reset_token`");
    echo "Thêm cột reset_token thành công!";
} catch (Exception $e) {
    echo "Lỗi (hoặc đã thêm từ trước): " . $e->getMessage();
}
