USE `sales_system`;

ALTER TABLE `store_registration_requests`
    ADD COLUMN `store_user_id` BIGINT UNSIGNED NULL AFTER `admin_note`,
    ADD KEY `idx_store_requests_store_user_id` (`store_user_id`),
    ADD CONSTRAINT `fk_store_requests_store_user`
        FOREIGN KEY (`store_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
