-- Apply to databases created before Phase 13.
USE `sanzidas_closet`;

CREATE TABLE IF NOT EXISTS `admin_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `admin_id` BIGINT UNSIGNED NOT NULL, `token_hash` CHAR(64) NOT NULL, `ip_address` VARCHAR(45) NULL, `user_agent` VARCHAR(500) NULL, `expires_at` DATETIME(6) NOT NULL, `revoked_at` DATETIME(6) NULL, `last_seen_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), UNIQUE KEY `uq_admin_sessions_token` (`token_hash`), KEY `idx_admin_sessions_admin_active` (`admin_id`, `revoked_at`, `expires_at`), CONSTRAINT `fk_admin_sessions_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `admin_id` BIGINT UNSIGNED NULL, `action` VARCHAR(100) NOT NULL, `entity_type` VARCHAR(80) NULL, `entity_id` BIGINT UNSIGNED NULL, `ip_address` VARCHAR(45) NULL, `metadata` JSON NULL, `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY (`id`), KEY `idx_admin_audit_entity` (`entity_type`, `entity_id`, `created_at`), KEY `idx_admin_audit_admin_date` (`admin_id`, `created_at`), CONSTRAINT `fk_admin_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;