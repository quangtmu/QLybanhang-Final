USE `sales_system`;

UPDATE `users`
SET `password_hash` = '$2y$10$ELwG46AXsM0LjgZYRrLhK.W5okjJzsO8H1e2aSsp6KVHapiqEW/mm',
    `password_dev` = 'Store@123456',
    `is_first_login` = 1,
    `deleted_at` = NULL
WHERE `user_type` = 'store_approved';

-- Local/dev only. Sau khi chay file nay, tat ca shop da duyet login bang password: Store@123456
