-- Phase 17: indexes for catalogue, review, media, and CMS read paths.
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_images' AND INDEX_NAME = 'idx_product_images_primary_order');
SET @index_sql = IF(@index_exists = 0, 'ALTER TABLE `product_images` ADD INDEX `idx_product_images_primary_order` (`product_id`, `is_primary`, `sort_order`, `id`)', 'SELECT 1');
PREPARE index_statement FROM @index_sql;
EXECUTE index_statement;
DEALLOCATE PREPARE index_statement;

SET @review_index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND INDEX_NAME = 'idx_reviews_product_approved');
SET @review_index_sql = IF(@review_index_exists = 0, 'ALTER TABLE `reviews` ADD INDEX `idx_reviews_product_approved` (`product_id`, `status`, `rating`)', 'SELECT 1');
PREPARE review_index_statement FROM @review_index_sql;
EXECUTE review_index_statement;
DEALLOCATE PREPARE review_index_statement;
