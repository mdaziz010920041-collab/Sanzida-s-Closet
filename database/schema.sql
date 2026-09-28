-- Sanzida's Closet | MySQL 8.x schema
-- Import into a dedicated database. Domain data is normalized; order records
-- retain snapshots so historical orders remain correct after catalogue edits.

CREATE DATABASE IF NOT EXISTS `sanzidas_closet` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sanzidas_closet`;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(80) NOT NULL, `slug` VARCHAR(80) NOT NULL, `description` VARCHAR(255) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_roles_name` (`name`), UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(120) NOT NULL, `description` VARCHAR(255) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_name` (`name`), UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB;

INSERT IGNORE INTO `permissions` (`name`, `slug`, `description`) VALUES
('Manage products', 'products.manage', 'Create and update catalogue products'),
('Manage categories', 'categories.manage', 'Create and update categories'),
('Manage orders', 'orders.manage', 'Review and update orders'),
('Manage customers', 'customers.manage', 'Review customer accounts'),
('Manage inventory', 'inventory.manage', 'Adjust stock and review history'),
('Manage coupons', 'coupons.manage', 'Create and update coupons'),
('Manage reviews', 'reviews.manage', 'Moderate customer reviews'),
('Manage content', 'content.manage', 'Manage pages and banners'),
('Manage SEO', 'seo.manage', 'Manage SEO metadata'),
('Manage settings', 'settings.manage', 'Manage store settings');
INSERT IGNORE INTO `permissions` (`name`, `slug`, `description`) VALUES
('View audit logs', 'audit.view', 'Review administrative audit events');
INSERT IGNORE INTO `permissions` (`name`, `slug`, `description`) VALUES
('View analytics reports', 'reports.view', 'Review commerce analytics reports');

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` BIGINT UNSIGNED NOT NULL, `permission_id` BIGINT UNSIGNED NOT NULL, PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `email` VARCHAR(190) NOT NULL, `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(80) NOT NULL, `last_name` VARCHAR(80) NULL, `phone` VARCHAR(30) NULL,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active', `email_verified_at` DATETIME(6) NULL, `last_login_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_users_email` (`email`), KEY `idx_users_status` (`status`), KEY `idx_users_phone` (`phone`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `admins` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `role_id` BIGINT UNSIGNED NOT NULL, `email` VARCHAR(190) NOT NULL, `password_hash` VARCHAR(255) NOT NULL,
    `display_name` VARCHAR(120) NOT NULL, `status` ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active', `last_login_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_admins_email` (`email`), KEY `idx_admins_role_status` (`role_id`, `status`),
    CONSTRAINT `fk_admins_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `admin_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `expires_at` DATETIME(6) NOT NULL,
    `revoked_at` DATETIME(6) NULL,
    `last_seen_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_admin_sessions_token` (`token_hash`), KEY `idx_admin_sessions_admin_active` (`admin_id`, `revoked_at`, `expires_at`),
    CONSTRAINT `fk_admin_sessions_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(80) NULL,
    `entity_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `metadata` JSON NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), KEY `idx_admin_audit_entity` (`entity_type`, `entity_id`, `created_at`), KEY `idx_admin_audit_admin_date` (`admin_id`, `created_at`),
    CONSTRAINT `fk_admin_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `brands` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(140) NOT NULL, `description` TEXT NULL, `logo_path` VARCHAR(500) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active', `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_brands_name` (`name`), UNIQUE KEY `uq_brands_slug` (`slug`), KEY `idx_brands_status` (`status`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `parent_id` BIGINT UNSIGNED NULL, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(140) NOT NULL,
    `description` TEXT NULL, `image_path` VARCHAR(500) NULL, `sort_order` INT UNSIGNED NOT NULL DEFAULT 0, `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_categories_slug` (`slug`), KEY `idx_categories_parent_status` (`parent_id`, `status`, `sort_order`),
    CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `sizes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(40) NOT NULL, `code` VARCHAR(20) NOT NULL, `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active', PRIMARY KEY (`id`), UNIQUE KEY `uq_sizes_code` (`code`), KEY `idx_sizes_status_order` (`status`, `sort_order`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `colors` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(60) NOT NULL, `slug` VARCHAR(80) NOT NULL, `hex_code` CHAR(7) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0, `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active', PRIMARY KEY (`id`),
    UNIQUE KEY `uq_colors_slug` (`slug`), KEY `idx_colors_status_order` (`status`, `sort_order`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `products` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `brand_id` BIGINT UNSIGNED NULL, `name` VARCHAR(180) NOT NULL, `slug` VARCHAR(200) NOT NULL,
    `short_description` VARCHAR(500) NULL, `description` LONGTEXT NULL, `base_price` DECIMAL(12,2) NOT NULL, `compare_at_price` DECIMAL(12,2) NULL,
    `discount_type` ENUM('none', 'percentage', 'fixed') NOT NULL DEFAULT 'none', `discount_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `currency` CHAR(3) NOT NULL DEFAULT 'BDT', `status` ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft', `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `published_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_products_slug` (`slug`), KEY `idx_products_catalogue` (`status`, `published_at`), KEY `idx_products_status_name` (`status`, `name`), KEY `idx_products_featured` (`is_featured`, `status`), KEY `idx_products_brand` (`brand_id`),
    CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_products_price` CHECK (`base_price` >= 0 AND (`compare_at_price` IS NULL OR `compare_at_price` >= `base_price`)), CONSTRAINT `chk_products_discount` CHECK (`discount_value` >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `product_categories` (
    `product_id` BIGINT UNSIGNED NOT NULL, `category_id` BIGINT UNSIGNED NOT NULL, PRIMARY KEY (`product_id`, `category_id`), KEY `idx_product_categories_category` (`category_id`, `product_id`),
    CONSTRAINT `fk_product_categories_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_product_categories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `product_id` BIGINT UNSIGNED NOT NULL, `size_id` BIGINT UNSIGNED NULL, `color_id` BIGINT UNSIGNED NULL,
    `sku` VARCHAR(100) NOT NULL, `barcode` VARCHAR(80) NULL, `price_override` DECIMAL(12,2) NULL, `compare_at_price_override` DECIMAL(12,2) NULL, `weight_grams` DECIMAL(10,3) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active', `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_product_variants_sku` (`sku`), UNIQUE KEY `uq_product_variants_barcode` (`barcode`), UNIQUE KEY `uq_product_variants_option_set` (`product_id`, `size_id`, `color_id`),
    KEY `idx_product_variants_product_status` (`product_id`, `status`), KEY `idx_product_variants_size_product` (`size_id`, `product_id`, `status`), KEY `idx_product_variants_color_product` (`color_id`, `product_id`, `status`), KEY `idx_product_variants_size_color` (`size_id`, `color_id`),
    CONSTRAINT `fk_product_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_product_variants_size` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`) ON DELETE RESTRICT, CONSTRAINT `fk_product_variants_color` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_product_variants_prices` CHECK (`price_override` IS NULL OR `price_override` >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `product_images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `product_id` BIGINT UNSIGNED NOT NULL, `variant_id` BIGINT UNSIGNED NULL, `file_path` VARCHAR(500) NOT NULL, `alt_text` VARCHAR(255) NOT NULL, `sort_order` INT UNSIGNED NOT NULL DEFAULT 0, `is_primary` TINYINT(1) NOT NULL DEFAULT 0, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`),
    KEY `idx_product_images_product_order` (`product_id`, `sort_order`), KEY `idx_product_images_variant` (`variant_id`), CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_product_images_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `product_videos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `product_id` BIGINT UNSIGNED NOT NULL, `variant_id` BIGINT UNSIGNED NULL, `video_url` VARCHAR(500) NOT NULL, `thumbnail_path` VARCHAR(500) NULL, `alt_text` VARCHAR(255) NULL, `sort_order` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`),
    KEY `idx_product_videos_product_order` (`product_id`, `sort_order`), CONSTRAINT `fk_product_videos_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_product_videos_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `inventory` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `variant_id` BIGINT UNSIGNED NOT NULL, `quantity_on_hand` INT NOT NULL DEFAULT 0, `quantity_reserved` INT UNSIGNED NOT NULL DEFAULT 0, `reorder_level` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_inventory_variant` (`variant_id`), KEY `idx_inventory_low_stock` (`quantity_on_hand`, `reorder_level`), CONSTRAINT `fk_inventory_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE, CONSTRAINT `chk_inventory_available` CHECK (`quantity_on_hand` >= 0 AND `quantity_reserved` <= `quantity_on_hand`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `variant_id` BIGINT UNSIGNED NOT NULL, `admin_id` BIGINT UNSIGNED NULL, `transaction_type` ENUM('receipt', 'sale', 'return', 'adjustment', 'reservation', 'release') NOT NULL, `quantity_change` INT NOT NULL, `quantity_after` INT UNSIGNED NOT NULL, `reference_type` VARCHAR(60) NULL, `reference_id` BIGINT UNSIGNED NULL, `note` VARCHAR(500) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), KEY `idx_inventory_transactions_variant_date` (`variant_id`, `created_at`), KEY `idx_inventory_transactions_reference` (`reference_type`, `reference_id`), CONSTRAINT `fk_inventory_transactions_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE RESTRICT, CONSTRAINT `fk_inventory_transactions_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `carts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NULL, `coupon_id` BIGINT UNSIGNED NULL, `session_token` CHAR(64) NOT NULL, `status` ENUM('active', 'converted', 'abandoned', 'expired') NOT NULL DEFAULT 'active', `currency` CHAR(3) NOT NULL DEFAULT 'BDT', `expires_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_carts_session_token` (`session_token`), KEY `idx_carts_user_status` (`user_id`, `status`), KEY `idx_carts_coupon` (`coupon_id`), KEY `idx_carts_expiry` (`status`, `expires_at`), CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cart_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `cart_id` BIGINT UNSIGNED NOT NULL, `variant_id` BIGINT UNSIGNED NOT NULL, `quantity` INT UNSIGNED NOT NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_cart_items_cart_variant` (`cart_id`, `variant_id`), CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_cart_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE RESTRICT, CONSTRAINT `chk_cart_items_quantity` CHECK (`quantity` > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `wishlists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL, `name` VARCHAR(80) NOT NULL DEFAULT 'My wishlist', `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_wishlists_user_name` (`user_id`, `name`), CONSTRAINT `fk_wishlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `wishlist_items` (
    `wishlist_id` BIGINT UNSIGNED NOT NULL, `variant_id` BIGINT UNSIGNED NOT NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`wishlist_id`, `variant_id`), CONSTRAINT `fk_wishlist_items_wishlist` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_wishlist_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `addresses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL, `label` VARCHAR(60) NOT NULL DEFAULT 'Address', `recipient_name` VARCHAR(160) NOT NULL, `phone` VARCHAR(30) NOT NULL, `line_1` VARCHAR(190) NOT NULL, `line_2` VARCHAR(190) NULL, `city` VARCHAR(100) NOT NULL, `state` VARCHAR(100) NULL, `postal_code` VARCHAR(20) NULL, `country_code` CHAR(2) NOT NULL DEFAULT 'BD', `is_default_shipping` TINYINT(1) NOT NULL DEFAULT 0, `is_default_billing` TINYINT(1) NOT NULL DEFAULT 0, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), KEY `idx_addresses_user` (`user_id`), CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `coupons` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(80) NOT NULL, `discount_type` ENUM('percentage', 'fixed') NOT NULL, `discount_value` DECIMAL(12,2) NOT NULL, `minimum_order_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00, `maximum_discount_amount` DECIMAL(12,2) NULL, `usage_limit` INT UNSIGNED NULL, `usage_limit_per_user` INT UNSIGNED NULL, `starts_at` DATETIME(6) NULL, `ends_at` DATETIME(6) NULL, `status` ENUM('active', 'inactive', 'expired') NOT NULL DEFAULT 'active', `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_coupons_code` (`code`), KEY `idx_coupons_validity` (`status`, `starts_at`, `ends_at`), CONSTRAINT `chk_coupons_value` CHECK (`discount_value` > 0 AND `minimum_order_amount` >= 0)
) ENGINE=InnoDB;

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
    PRIMARY KEY (`id`), UNIQUE KEY `uq_shipping_methods_code` (`code`), KEY `idx_shipping_methods_status_order` (`status`, `sort_order`),
    CONSTRAINT `chk_shipping_methods_amounts` CHECK (`charge` >= 0 AND (`free_shipping_threshold` IS NULL OR `free_shipping_threshold` >= 0))
) ENGINE=InnoDB;

INSERT IGNORE INTO `shipping_methods` (`code`, `name`, `description`, `charge`, `free_shipping_threshold`, `estimated_days_min`, `estimated_days_max`, `provider`, `sort_order`)
VALUES ('standard', 'Standard delivery', 'Reliable standard delivery', 120.00, 5000.00, 3, 7, 'manual', 1);

CREATE TABLE IF NOT EXISTS `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NULL, `coupon_id` BIGINT UNSIGNED NULL, `order_number` VARCHAR(40) NOT NULL, `email` VARCHAR(190) NOT NULL, `status` ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'completed', 'returned') NOT NULL DEFAULT 'pending', `currency` CHAR(3) NOT NULL DEFAULT 'BDT', `subtotal` DECIMAL(12,2) NOT NULL, `discount_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00, `shipping_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00, `tax_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00, `grand_total` DECIMAL(12,2) NOT NULL, `shipping_address_id` BIGINT UNSIGNED NULL, `billing_address_id` BIGINT UNSIGNED NULL, `shipping_address_snapshot` JSON NOT NULL, `billing_address_snapshot` JSON NULL, `notes` TEXT NULL, `placed_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_orders_number` (`order_number`), KEY `idx_orders_user_date` (`user_id`, `created_at`), KEY `idx_orders_status_date` (`status`, `created_at`), KEY `idx_orders_email` (`email`), CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL, CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL, CONSTRAINT `fk_orders_shipping_address` FOREIGN KEY (`shipping_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL, CONSTRAINT `fk_orders_billing_address` FOREIGN KEY (`billing_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL, CONSTRAINT `chk_orders_amounts` CHECK (`subtotal` >= 0 AND `discount_total` >= 0 AND `shipping_total` >= 0 AND `tax_total` >= 0 AND `grand_total` >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `order_id` BIGINT UNSIGNED NOT NULL, `product_id` BIGINT UNSIGNED NULL, `variant_id` BIGINT UNSIGNED NULL, `sku` VARCHAR(100) NOT NULL, `product_name` VARCHAR(180) NOT NULL, `variant_description` VARCHAR(255) NULL, `unit_price` DECIMAL(12,2) NOT NULL, `quantity` INT UNSIGNED NOT NULL, `line_total` DECIMAL(12,2) NOT NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), KEY `idx_order_items_order` (`order_id`), KEY `idx_order_items_product` (`product_id`), CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL, CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL, CONSTRAINT `chk_order_items_amounts` CHECK (`unit_price` >= 0 AND `quantity` > 0 AND `line_total` >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `coupon_usage` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `coupon_id` BIGINT UNSIGNED NOT NULL, `order_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NULL, `discount_amount` DECIMAL(12,2) NOT NULL, `used_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_coupon_usage_order` (`order_id`), KEY `idx_coupon_usage_coupon_user` (`coupon_id`, `user_id`), CONSTRAINT `fk_coupon_usage_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE RESTRICT, CONSTRAINT `fk_coupon_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_coupon_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `order_id` BIGINT UNSIGNED NOT NULL, `provider` VARCHAR(60) NOT NULL, `provider_order_id` VARCHAR(190) NULL, `provider_payment_id` VARCHAR(190) NULL, `provider_signature` CHAR(64) NULL, `amount` DECIMAL(12,2) NOT NULL, `currency` CHAR(3) NOT NULL DEFAULT 'BDT', `status` ENUM('pending', 'authorized', 'paid', 'failed', 'refunded', 'partially_refunded') NOT NULL DEFAULT 'pending', `failure_reason` VARCHAR(255) NULL, `metadata` JSON NULL, `paid_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_payments_provider_order` (`provider`, `provider_order_id`), UNIQUE KEY `uq_payments_provider_reference` (`provider`, `provider_payment_id`), KEY `idx_payments_order_status` (`order_id`, `status`), CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE, CONSTRAINT `chk_payments_amount` CHECK (`amount` >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `payment_webhook_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider` VARCHAR(60) NOT NULL,
    `event_id` VARCHAR(190) NOT NULL,
    `event_type` VARCHAR(120) NOT NULL,
    `payload` JSON NOT NULL,
    `status` ENUM('received', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'received',
    `processed_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_webhook_event` (`provider`, `event_id`),
    KEY `idx_payment_webhook_status` (`provider`, `status`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `shipments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `order_id` BIGINT UNSIGNED NOT NULL, `carrier` VARCHAR(80) NULL, `tracking_number` VARCHAR(120) NULL, `status` ENUM('pending', 'packed', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled') NOT NULL DEFAULT 'pending', `shipped_at` DATETIME(6) NULL, `delivered_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_shipments_tracking` (`carrier`, `tracking_number`), KEY `idx_shipments_order_status` (`order_id`, `status`), CONSTRAINT `fk_shipments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `product_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL, `order_item_id` BIGINT UNSIGNED NULL, `rating` TINYINT UNSIGNED NOT NULL, `title` VARCHAR(160) NULL, `body` TEXT NULL, `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending', `verified_purchase` TINYINT(1) NOT NULL DEFAULT 0, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_reviews_user_product` (`user_id`, `product_id`), KEY `idx_reviews_product_status` (`product_id`, `status`, `created_at`), CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_reviews_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL, CONSTRAINT `chk_reviews_rating` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `returns` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `order_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NULL, `return_number` VARCHAR(40) NOT NULL, `status` ENUM('requested', 'approved', 'received', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'requested', `reason` VARCHAR(255) NOT NULL, `customer_note` TEXT NULL, `admin_note` TEXT NULL, `requested_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `resolved_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_returns_number` (`return_number`), KEY `idx_returns_order_status` (`order_id`, `status`), CONSTRAINT `fk_returns_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT, CONSTRAINT `fk_returns_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `return_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `return_id` BIGINT UNSIGNED NOT NULL, `order_item_id` BIGINT UNSIGNED NOT NULL, `quantity` INT UNSIGNED NOT NULL, `reason` VARCHAR(255) NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_return_items_line` (`return_id`, `order_item_id`), CONSTRAINT `fk_return_items_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_return_items_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE RESTRICT, CONSTRAINT `chk_return_items_quantity` CHECK (`quantity` > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `refunds` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `order_id` BIGINT UNSIGNED NOT NULL, `payment_id` BIGINT UNSIGNED NULL, `return_id` BIGINT UNSIGNED NULL, `provider_refund_id` VARCHAR(190) NULL, `amount` DECIMAL(12,2) NOT NULL, `currency` CHAR(3) NOT NULL DEFAULT 'BDT', `status` ENUM('pending', 'processed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending', `reason` VARCHAR(255) NULL, `processed_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_refunds_provider_reference` (`provider_refund_id`), KEY `idx_refunds_order_status` (`order_id`, `status`), CONSTRAINT `fk_refunds_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT, CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL, CONSTRAINT `fk_refunds_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE SET NULL, CONSTRAINT `chk_refunds_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NULL, `admin_id` BIGINT UNSIGNED NULL, `type` VARCHAR(80) NOT NULL, `title` VARCHAR(180) NOT NULL, `message` TEXT NOT NULL, `read_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), KEY `idx_notifications_user_read` (`user_id`, `read_at`, `created_at`), KEY `idx_notifications_admin_read` (`admin_id`, `read_at`, `created_at`), CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_notifications_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE, CONSTRAINT `chk_notifications_recipient` CHECK ((`user_id` IS NOT NULL) OR (`admin_id` IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `pages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(180) NOT NULL, `slug` VARCHAR(200) NOT NULL, `content` LONGTEXT NOT NULL, `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft', `published_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_pages_slug` (`slug`), KEY `idx_pages_status_published` (`status`, `published_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `banners` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(180) NOT NULL, `subtitle` VARCHAR(255) NULL, `image_path` VARCHAR(500) NOT NULL, `mobile_image_path` VARCHAR(500) NULL, `link_url` VARCHAR(500) NULL, `placement` VARCHAR(80) NOT NULL, `sort_order` INT UNSIGNED NOT NULL DEFAULT 0, `status` ENUM('draft', 'active', 'inactive') NOT NULL DEFAULT 'draft', `starts_at` DATETIME(6) NULL, `ends_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), KEY `idx_banners_placement_status` (`placement`, `status`, `sort_order`), KEY `idx_banners_schedule` (`starts_at`, `ends_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `setting_key` VARCHAR(120) NOT NULL, `setting_value` JSON NOT NULL, `is_public` TINYINT(1) NOT NULL DEFAULT 0, `updated_by_admin_id` BIGINT UNSIGNED NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_settings_key` (`setting_key`), CONSTRAINT `fk_settings_admin` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `seo_metadata` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `entity_type` ENUM('page', 'product', 'category', 'brand') NOT NULL, `entity_id` BIGINT UNSIGNED NOT NULL, `meta_title` VARCHAR(180) NULL, `meta_description` VARCHAR(320) NULL, `meta_keywords` TEXT NULL, `canonical_url` VARCHAR(500) NULL, `og_image_path` VARCHAR(500) NULL, `structured_data` JSON NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_seo_metadata_entity` (`entity_type`, `entity_id`), KEY `idx_seo_metadata_type` (`entity_type`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `email` VARCHAR(190) NOT NULL, `status` ENUM('pending', 'subscribed', 'unsubscribed') NOT NULL DEFAULT 'pending', `confirmation_token_hash` CHAR(64) NULL, `subscribed_at` DATETIME(6) NULL, `unsubscribed_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_newsletter_email` (`email`), UNIQUE KEY `uq_newsletter_confirmation_token` (`confirmation_token_hash`), KEY `idx_newsletter_status` (`status`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `return_policies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `days_from_delivery` INT UNSIGNED NOT NULL DEFAULT 7, `eligible_statuses` JSON NOT NULL, `allowed_resolutions` JSON NOT NULL, `restocking_fee_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00, `customer_pays_return_shipping` TINYINT(1) NOT NULL DEFAULT 0, `allow_damaged_item` TINYINT(1) NOT NULL DEFAULT 1, `allow_wrong_item` TINYINT(1) NOT NULL DEFAULT 1, `allow_missing_item` TINYINT(1) NOT NULL DEFAULT 1, `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active', `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_return_policies_name` (`name`), KEY `idx_return_policies_status` (`status`)
) ENGINE=InnoDB;

INSERT IGNORE INTO `return_policies` (`name`, `days_from_delivery`, `eligible_statuses`, `allowed_resolutions`)
VALUES ('Default policy', 7, '["delivered", "completed"]', '["refund", "exchange", "store_credit"]');

SET @orders_shipping_columns = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'shipping_method_id');
SET @orders_shipping_sql = IF(@orders_shipping_columns = 0, 'ALTER TABLE `orders` ADD COLUMN `shipping_method_id` BIGINT UNSIGNED NULL AFTER `coupon_id`, ADD COLUMN `shipping_method_code` VARCHAR(60) NULL AFTER `shipping_method_id`, ADD COLUMN `shipping_method_name` VARCHAR(120) NULL AFTER `shipping_method_code`, ADD INDEX `idx_orders_shipping_method` (`shipping_method_id`), ADD CONSTRAINT `fk_orders_shipping_method` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE orders_shipping_statement FROM @orders_shipping_sql; EXECUTE orders_shipping_statement; DEALLOCATE PREPARE orders_shipping_statement;

SET @shipments_provider_columns = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shipments' AND COLUMN_NAME = 'provider_shipment_id');
SET @shipments_provider_sql = IF(@shipments_provider_columns = 0, 'ALTER TABLE `shipments` ADD COLUMN `shipping_method_id` BIGINT UNSIGNED NULL AFTER `order_id`, ADD COLUMN `provider` VARCHAR(60) NULL AFTER `shipping_method_id`, ADD COLUMN `provider_shipment_id` VARCHAR(190) NULL AFTER `provider`, ADD COLUMN `estimated_delivery_at` DATETIME(6) NULL AFTER `status`, ADD UNIQUE KEY `uq_shipments_provider_reference` (`provider`, `provider_shipment_id`), ADD CONSTRAINT `fk_shipments_method` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE shipments_provider_statement FROM @shipments_provider_sql; EXECUTE shipments_provider_statement; DEALLOCATE PREPARE shipments_provider_statement;

SET @returns_workflow_columns = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'returns' AND COLUMN_NAME = 'return_type');
SET @returns_workflow_sql = IF(@returns_workflow_columns = 0, 'ALTER TABLE `returns` ADD COLUMN `return_type` ENUM(\'return\', \'exchange\') NOT NULL DEFAULT \'return\' AFTER `return_number`, MODIFY COLUMN `status` ENUM(\'requested\', \'approved\', \'rejected\', \'pickup_scheduled\', \'received\', \'inspecting\', \'refunded\', \'exchanged\', \'completed\', \'cancelled\') NOT NULL DEFAULT \'requested\', ADD COLUMN `resolution` ENUM(\'refund\', \'exchange\', \'store_credit\') NULL AFTER `reason`, ADD COLUMN `inspection_status` ENUM(\'pending\', \'passed\', \'failed\') NOT NULL DEFAULT \'pending\' AFTER `resolution`, ADD COLUMN `exchange_variant_id` BIGINT UNSIGNED NULL AFTER `inspection_status`, ADD COLUMN `approved_at` DATETIME(6) NULL AFTER `requested_at`, ADD COLUMN `received_at` DATETIME(6) NULL AFTER `approved_at`, ADD CONSTRAINT `fk_returns_exchange_variant` FOREIGN KEY (`exchange_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE returns_workflow_statement FROM @returns_workflow_sql; EXECUTE returns_workflow_statement; DEALLOCATE PREPARE returns_workflow_statement;

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `last_seen_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `expires_at` DATETIME(6) NOT NULL,
    `revoked_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_sessions_token` (`token_hash`),
    KEY `idx_user_sessions_user_active` (`user_id`, `revoked_at`, `expires_at`),
    CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME(6) NOT NULL,
    `used_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_reset_token` (`token_hash`),
    KEY `idx_password_reset_user_expiry` (`user_id`, `expires_at`, `used_at`),
    CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `auth_login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `succeeded` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_auth_attempts_email_time` (`email`, `attempted_at`, `succeeded`),
    KEY `idx_auth_attempts_ip_time` (`ip_address`, `attempted_at`, `succeeded`)
) ENGINE=InnoDB;

SET @cart_coupon_constraint_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'carts' AND CONSTRAINT_NAME = 'fk_carts_coupon'
);
SET @cart_coupon_constraint_sql = IF(@cart_coupon_constraint_exists = 0, 'ALTER TABLE `carts` ADD CONSTRAINT `fk_carts_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE cart_coupon_constraint_statement FROM @cart_coupon_constraint_sql;
EXECUTE cart_coupon_constraint_statement;
DEALLOCATE PREPARE cart_coupon_constraint_statement;