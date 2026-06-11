ALTER TABLE `users` 
ADD COLUMN `reset_token` VARCHAR(255) NULL DEFAULT NULL AFTER `remember_token`,
ADD COLUMN `reset_expires_at` DATETIME NULL DEFAULT NULL AFTER `reset_token`;
