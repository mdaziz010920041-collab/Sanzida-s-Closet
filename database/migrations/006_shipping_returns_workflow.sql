-- Apply to databases created before Phase 12.
USE `sanzidas_closet`;

CREATE TABLE IF NOT EXISTS `shipping_methods` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(60) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NULL,
    `carrier` VARCHAR(80) NULL,
    `charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `free_shipping_threshold` DECIMAL(12,2) NULL,
    `estimated_days_min` TINYINT UNSIGNED NULL,
    `estimated_days_max` TINYINT UNSIGNED NULL,
    `provider` VARCHAR(60) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_shipping_methods_code` (`code`), KEY `idx_shipping_methods_status_order` (`status`, `sort_order`)
) ENGINE=InnoDB;
INSERT IGNORE INTO `shipping_methods` (`code`, `name`, `description`, `charge`, `free_shipping_threshold`, `estimated_days_min`, `estimated_days_max`, `provider`, `sort_order`) VALUES ('standard', 'Standard delivery', 'Reliable standard delivery', 120.00, 5000.00, 3, 7, 'manual', 1);

SET @orders_shipping_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_method_id') = 0, 'ALTER TABLE `orders` ADD COLUMN `shipping_method_id` BIGINT UNSIGNED NULL AFTER `coupon_id`, ADD COLUMN `shipping_method_code` VARCHAR(60) NULL AFTER `shipping_method_id`, ADD COLUMN `shipping_method_name` VARCHAR(120) NULL AFTER `shipping_method_code`, ADD INDEX `idx_orders_shipping_method` (`shipping_method_id`), ADD CONSTRAINT `fk_orders_shipping_method` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @orders_shipping_sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @shipments_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'provider_shipment_id') = 0, 'ALTER TABLE `shipments` ADD COLUMN `shipping_method_id` BIGINT UNSIGNED NULL AFTER `order_id`, ADD COLUMN `provider` VARCHAR(60) NULL AFTER `shipping_method_id`, ADD COLUMN `provider_shipment_id` VARCHAR(190) NULL AFTER `provider`, ADD COLUMN `estimated_delivery_at` DATETIME(6) NULL AFTER `status`, ADD UNIQUE KEY `uq_shipments_provider_reference` (`provider`, `provider_shipment_id`), ADD CONSTRAINT `fk_shipments_method` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @shipments_sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS `return_policies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `days_from_delivery` INT UNSIGNED NOT NULL DEFAULT 7, `eligible_statuses` JSON NOT NULL, `allowed_resolutions` JSON NOT NULL, `restocking_fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00, `customer_pays_return_shipping` TINYINT(1) NOT NULL DEFAULT 0, `allow_damaged_item` TINYINT(1) NOT NULL DEFAULT 1, `allow_wrong_item` TINYINT(1) NOT NULL DEFAULT 1, `allow_missing_item` TINYINT(1) NOT NULL DEFAULT 1, `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active', `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_return_policies_name` (`name`)
) ENGINE=InnoDB;
INSERT IGNORE INTO `return_policies` (`name`, `days_from_delivery`, `eligible_statuses`, `allowed_resolutions`) VALUES ('Default policy', 7, '["delivered", "completed"]', '["refund", "exchange", "store_credit"]');

SET @returns_sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'returns' AND COLUMN_NAME = 'return_type') = 0, 'ALTER TABLE `returns` ADD COLUMN `return_type` ENUM(\'return\', \'exchange\') NOT NULL DEFAULT \'return\' AFTER `return_number`, MODIFY COLUMN `status` ENUM(\'requested\', \'approved\', \'rejected\', \'pickup_scheduled\', \'received\', \'inspecting\', \'refunded\', \'exchanged\', \'completed\', \'cancelled\') NOT NULL DEFAULT \'requested\', ADD COLUMN `resolution` ENUM(\'refund\', \'exchange\', \'store_credit\') NULL AFTER `reason`, ADD COLUMN `inspection_status` ENUM(\'pending\', \'passed\', \'failed\') NOT NULL DEFAULT \'pending\' AFTER `resolution`, ADD COLUMN `exchange_variant_id` BIGINT UNSIGNED NULL AFTER `inspection_status`, ADD COLUMN `approved_at` DATETIME(6) NULL AFTER `requested_at`, ADD COLUMN `received_at` DATETIME(6) NULL AFTER `approved_at`, ADD CONSTRAINT `fk_returns_exchange_variant` FOREIGN KEY (`exchange_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @returns_sql; EXECUTE s; DEALLOCATE PREPARE s;
