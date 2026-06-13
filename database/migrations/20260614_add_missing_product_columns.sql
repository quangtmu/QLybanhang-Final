ALTER TABLE `products`
ADD COLUMN `discount_price` DECIMAL(15,2) NULL AFTER `base_price`,
ADD COLUMN `stock_quantity` INT NOT NULL DEFAULT 0 AFTER `discount_price`,
ADD COLUMN `weight_unit` ENUM('g','kg') NOT NULL DEFAULT 'g' AFTER `weight`,
ADD COLUMN `volume` DECIMAL(8,2) NULL AFTER `weight_unit`,
ADD COLUMN `volume_unit` ENUM('ml','l','m3') NULL AFTER `volume`,
ADD COLUMN `length` DECIMAL(8,2) NULL AFTER `volume_unit`,
ADD COLUMN `width` DECIMAL(8,2) NULL AFTER `length`,
ADD COLUMN `height` DECIMAL(8,2) NULL AFTER `width`;
