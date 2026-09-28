-- Apply to databases created before Phase 11.
USE `sanzidas_closet`;

SET @payment_order_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'provider_order_id');
SET @payment_order_sql = IF(@payment_order_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `provider_order_id` VARCHAR(190) NULL AFTER `provider`, ADD UNIQUE KEY `uq_payments_provider_order` (`provider`, `provider_order_id`)', 'SELECT 1');
PREPARE payment_order_statement FROM @payment_order_sql;
EXECUTE payment_order_statement;
DEALLOCATE PREPARE payment_order_statement;

SET @payment_signature_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'provider_signature');
SET @payment_signature_sql = IF(@payment_signature_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `provider_signature` CHAR(64) NULL AFTER `provider_payment_id`', 'SELECT 1');
PREPARE payment_signature_statement FROM @payment_signature_sql;
EXECUTE payment_signature_statement;
DEALLOCATE PREPARE payment_signature_statement;

SET @payment_metadata_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'metadata');
SET @payment_metadata_sql = IF(@payment_metadata_exists = 0, 'ALTER TABLE `payments` ADD COLUMN `metadata` JSON NULL AFTER `failure_reason`', 'SELECT 1');
PREPARE payment_metadata_statement FROM @payment_metadata_sql;
EXECUTE payment_metadata_statement;
DEALLOCATE PREPARE payment_metadata_statement;

CREATE TABLE IF NOT EXISTS `payment_webhook_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider` VARCHAR(60) NOT NULL,
    `event_id` VARCHAR(190) NOT NULL,
    `event_type` VARCHAR(120) NOT NULL,
    `payload` JSON NOT NULL,
    `status` ENUM('received', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'received',
    `processed_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`), UNIQUE KEY `uq_payment_webhook_event` (`provider`, `event_id`), KEY `idx_payment_webhook_status` (`provider`, `status`, `created_at`)
) ENGINE=InnoDB;