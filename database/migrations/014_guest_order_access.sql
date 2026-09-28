CREATE TABLE IF NOT EXISTS `guest_order_access` (
    `order_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME(6) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`order_id`),
    KEY `idx_guest_order_access_token_expiry` (`token_hash`, `expires_at`),
    CONSTRAINT `fk_guest_order_access_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;