-- 1. Thêm cột store_reply và store_replied_at vào product_reviews
ALTER TABLE `product_reviews`
ADD COLUMN `store_reply` TEXT NULL AFTER `comment`,
ADD COLUMN `store_replied_at` DATETIME NULL AFTER `store_reply`;

-- 2. Thêm cột slug vào products và categories
ALTER TABLE `products`
ADD COLUMN `slug` VARCHAR(255) NULL AFTER `name`,
ADD UNIQUE INDEX `idx_products_slug` (`slug`);

ALTER TABLE `categories`
ADD COLUMN `slug` VARCHAR(255) NULL AFTER `name`,
ADD UNIQUE INDEX `idx_categories_slug` (`slug`);

-- (Tuỳ chọn: Khởi tạo slug ngẫu nhiên cho các bản ghi cũ để tránh lỗi UNIQUE nếu có bản ghi trùng tên. Do MySQL không hỗ trợ REGEXP_REPLACE đơn giản trên các bản cũ, tạm để NULL)

-- 3. Tạo bảng Vouchers
CREATE TABLE IF NOT EXISTS `vouchers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id` BIGINT UNSIGNED NULL, -- NULL nếu là Voucher của sàn (Admin)
    `buyer_id` BIGINT UNSIGNED NULL, -- NULL nếu áp dụng cho mọi khách
    `code` VARCHAR(50) NOT NULL,
    `discount_type` ENUM('percent', 'fixed') NOT NULL DEFAULT 'fixed',
    `discount_amount` DECIMAL(15,2) NOT NULL, -- Giá trị giảm (hoặc %)
    `min_order_amount` DECIMAL(15,2) NOT NULL DEFAULT 0, -- Giá trị đơn hàng tối thiểu
    `max_discount_amount` DECIMAL(15,2) NULL, -- Số tiền giảm tối đa (Dành cho loại percent)
    `usage_limit` INT UNSIGNED NOT NULL DEFAULT 0, -- Số lượt sử dụng tối đa (0 = không giới hạn)
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0, -- Số lượt đã sử dụng
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_vouchers_code` (`code`),
    KEY `idx_vouchers_store_id` (`store_id`),
    CONSTRAINT `fk_vouchers_store` FOREIGN KEY (`store_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng user_loyalty
CREATE TABLE IF NOT EXISTS `user_loyalty` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `total_spent` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `current_points` INT NOT NULL DEFAULT 0,
    `tier_level` ENUM('bronze', 'silver', 'gold', 'diamond') NOT NULL DEFAULT 'bronze',
    `spins_available` INT NOT NULL DEFAULT 0,
    `free_spin_used` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_user_loyalty_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng loyalty_points_history
CREATE TABLE IF NOT EXISTS `loyalty_points_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `points` INT NOT NULL, -- Dương là cộng, Âm là trừ
    `reason` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_loyalty_history_user_id` (`user_id`),
    CONSTRAINT `fk_loyalty_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bảng flash_sales và flash_sale_products
CREATE TABLE IF NOT EXISTS `flash_sales` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flash_sale_products` (
    `flash_sale_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `discount_price` DECIMAL(15,2) NOT NULL,
    `stock_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `sold_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`flash_sale_id`, `product_id`),
    CONSTRAINT `fk_flash_sale_products_sale` FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_flash_sale_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Thêm trạng thái lượt quay vào orders
ALTER TABLE `orders` ADD COLUMN `is_spin_used` TINYINT(1) DEFAULT 0 AFTER `final_amount`;
