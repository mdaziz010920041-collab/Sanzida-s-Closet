-- Apply to databases created before Phase 7.
USE `sanzidas_closet`;

SET @products_name_index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_status_name'
);
SET @products_name_sql = IF(@products_name_index_exists = 0, 'ALTER TABLE `products` ADD INDEX `idx_products_status_name` (`status`, `name`)', 'SELECT 1');
PREPARE products_name_statement FROM @products_name_sql;
EXECUTE products_name_statement;
DEALLOCATE PREPARE products_name_statement;

SET @variant_size_index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants' AND INDEX_NAME = 'idx_product_variants_size_product'
);
SET @variant_size_sql = IF(@variant_size_index_exists = 0, 'ALTER TABLE `product_variants` ADD INDEX `idx_product_variants_size_product` (`size_id`, `product_id`, `status`)', 'SELECT 1');
PREPARE variant_size_statement FROM @variant_size_sql;
EXECUTE variant_size_statement;
DEALLOCATE PREPARE variant_size_statement;

SET @variant_color_index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants' AND INDEX_NAME = 'idx_product_variants_color_product'
);
SET @variant_color_sql = IF(@variant_color_index_exists = 0, 'ALTER TABLE `product_variants` ADD INDEX `idx_product_variants_color_product` (`color_id`, `product_id`, `status`)', 'SELECT 1');
PREPARE variant_color_statement FROM @variant_color_sql;
EXECUTE variant_color_statement;
DEALLOCATE PREPARE variant_color_statement;