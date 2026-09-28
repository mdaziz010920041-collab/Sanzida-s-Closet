-- Phase 14: editable CMS metadata for banners.
SET @banner_alt_columns = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'banners' AND COLUMN_NAME = 'alt_text');
SET @banner_alt_sql = IF(@banner_alt_columns = 0, 'ALTER TABLE `banners` ADD COLUMN `alt_text` VARCHAR(255) NULL AFTER `image_path`', 'SELECT 1');
PREPARE banner_alt_statement FROM @banner_alt_sql;
EXECUTE banner_alt_statement;
DEALLOCATE PREPARE banner_alt_statement;
