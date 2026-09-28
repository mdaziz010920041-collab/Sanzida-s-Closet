-- Apply to databases created before Phase 6.
USE `sanzidas_closet`;

SET @seo_keywords_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'seo_metadata'
      AND COLUMN_NAME = 'meta_keywords'
);

SET @seo_keywords_sql = IF(
    @seo_keywords_exists = 0,
    'ALTER TABLE `seo_metadata` ADD COLUMN `meta_keywords` TEXT NULL AFTER `meta_description`',
    'SELECT 1'
);

PREPARE seo_keywords_statement FROM @seo_keywords_sql;
EXECUTE seo_keywords_statement;
DEALLOCATE PREPARE seo_keywords_statement;