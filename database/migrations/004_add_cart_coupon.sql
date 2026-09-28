-- Apply to databases created before Phase 9.
USE `sanzidas_closet`;

SET @cart_coupon_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'carts' AND COLUMN_NAME = 'coupon_id'
);
SET @cart_coupon_sql = IF(@cart_coupon_exists = 0, 'ALTER TABLE `carts` ADD COLUMN `coupon_id` BIGINT UNSIGNED NULL AFTER `user_id`, ADD INDEX `idx_carts_coupon` (`coupon_id`), ADD CONSTRAINT `fk_carts_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE cart_coupon_statement FROM @cart_coupon_sql;
EXECUTE cart_coupon_statement;
DEALLOCATE PREPARE cart_coupon_statement;