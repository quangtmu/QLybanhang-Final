-- Automatically use the currently connected database

INSERT INTO `users` (
    `uuid`,
    `username`,
    `email`,
    `password_hash`,
    `password_dev`,
    `full_name`,
    `user_type`,
    `is_first_login`,
    `email_verified_at`
) VALUES (
    UUID(),
    'admin',
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'password',
    'System Administrator',
    'admin',
    1,
    NOW()
) ON DUPLICATE KEY UPDATE
    `password_hash` = VALUES(`password_hash`),
    `password_dev` = VALUES(`password_dev`),
    `full_name` = VALUES(`full_name`),
    `user_type` = VALUES(`user_type`),
    `is_first_login` = VALUES(`is_first_login`),
    `email_verified_at` = VALUES(`email_verified_at`),
    `deleted_at` = NULL;

-- Mat khau mac dinh cua file SQL nay la: password
-- Cot password_dev chi de xem nhanh tren local/dev. Khong dung cho production.
