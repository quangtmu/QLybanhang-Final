SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'shipments'
      AND COLUMN_NAME = 'proof_image_url'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `shipments` ADD COLUMN `proof_image_url` VARCHAR(512) NULL AFTER `shipper_phone`',
    'SELECT ''proof_image_url already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
