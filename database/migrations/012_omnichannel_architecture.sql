-- Phase 19: marketplace integration boundaries. Secrets stay in server environment variables.
CREATE TABLE IF NOT EXISTS `marketplace_channels` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `status` ENUM('planned', 'enabled', 'paused') NOT NULL DEFAULT 'planned',
    `last_sync_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_marketplace_channels_code` (`code`)
) ENGINE=InnoDB;

INSERT IGNORE INTO `marketplace_channels` (`code`, `name`) VALUES
('website', 'Own website'), ('amazon', 'Amazon'), ('flipkart', 'Flipkart'), ('meesho', 'Meesho'), ('myntra', 'Myntra');

CREATE TABLE IF NOT EXISTS `marketplace_product_mappings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel_code` VARCHAR(40) NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `variant_id` BIGINT UNSIGNED NULL,
    `external_product_id` VARCHAR(190) NULL,
    `external_variant_id` VARCHAR(190) NULL,
    `status` ENUM('pending', 'active', 'error', 'disabled') NOT NULL DEFAULT 'pending',
    `last_synced_at` DATETIME(6) NULL,
    `last_error` VARCHAR(500) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_marketplace_product_variant` (`channel_code`, `product_id`, `variant_id`),
    KEY `idx_marketplace_product_external` (`channel_code`, `external_product_id`),
    CONSTRAINT `fk_marketplace_product_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_marketplace_product_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `marketplace_inventory` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel_code` VARCHAR(40) NOT NULL,
    `variant_id` BIGINT UNSIGNED NOT NULL,
    `available_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'synced', 'error', 'disabled') NOT NULL DEFAULT 'pending',
    `last_synced_at` DATETIME(6) NULL,
    `last_error` VARCHAR(500) NULL,
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_marketplace_inventory_variant` (`channel_code`, `variant_id`),
    KEY `idx_marketplace_inventory_status` (`channel_code`, `status`),
    CONSTRAINT `fk_marketplace_inventory_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `marketplace_order_mappings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel_code` VARCHAR(40) NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `external_order_id` VARCHAR(190) NOT NULL,
    `external_status` VARCHAR(80) NULL,
    `last_imported_at` DATETIME(6) NULL,
    `last_status_synced_at` DATETIME(6) NULL,
    `last_error` VARCHAR(500) NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_marketplace_external_order` (`channel_code`, `external_order_id`), UNIQUE KEY `uq_marketplace_order_channel` (`channel_code`, `order_id`),
    CONSTRAINT `fk_marketplace_order_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `marketplace_sync_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel_code` VARCHAR(40) NOT NULL,
    `sync_type` ENUM('product', 'inventory', 'price', 'order_import', 'order_status') NOT NULL,
    `entity_type` VARCHAR(60) NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('queued', 'running', 'completed', 'failed', 'blocked') NOT NULL DEFAULT 'queued',
    `payload` JSON NULL,
    `error_message` VARCHAR(500) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `started_at` DATETIME(6) NULL,
    `completed_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`), KEY `idx_marketplace_jobs_status` (`status`, `created_at`), KEY `idx_marketplace_jobs_channel_entity` (`channel_code`, `entity_type`, `entity_id`)
) ENGINE=InnoDB;
