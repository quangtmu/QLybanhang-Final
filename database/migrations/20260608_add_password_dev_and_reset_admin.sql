USE `sales_system`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `password_dev` VARCHAR(255) NULL COMMENT 'Local dev only password hint. Do not use in production.' AFTER `password_hash`;

UPDATE `users`
SET `password_hash` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    `password_dev` = 'password',
    `is_first_login` = 1,
    `user_type` = 'admin',
    `deleted_at` = NULL
WHERE `email` = 'admin@example.com' OR `username` = 'admin';
