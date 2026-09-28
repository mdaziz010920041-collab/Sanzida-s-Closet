-- Sanzida's Closet | Demo catalogue seed
-- Safe to run more than once: product slugs, category slugs, and SKUs are unique.

INSERT INTO brands (name, slug, description, status)
VALUES ('Sanzida''s Closet', 'sanzidas-closet', 'Thoughtful fashion for every version of you.', 'active')
ON DUPLICATE KEY UPDATE description = VALUES(description), status = VALUES(status);

INSERT INTO categories (name, slug, description, sort_order, status)
VALUES
    ('Dresses', 'dresses', 'Easy silhouettes for everyday plans and special moments.', 1, 'active'),
    ('Tops', 'tops', 'Polished separates with a soft point of view.', 2, 'active'),
    ('Accessories', 'accessories', 'Finishing touches for the considered wardrobe.', 3, 'active'),
    ('Collections', 'collections', 'Curated edits from the Sanzida''s Closet collection.', 4, 'active')
ON DUPLICATE KEY UPDATE description = VALUES(description), sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO sizes (name, code, sort_order, status)
VALUES
    ('Small', 'S', 1, 'active'),
    ('Medium', 'M', 2, 'active'),
    ('Large', 'L', 3, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO colors (name, slug, hex_code, sort_order, status)
VALUES
    ('Ivory', 'ivory', '#F4EEE7', 1, 'active'),
    ('Rose', 'rose', '#C98F91', 2, 'active'),
    ('Black', 'black', '#1D1B1B', 3, 'active'),
    ('Sage', 'sage', '#A7B5A0', 4, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), hex_code = VALUES(hex_code), sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO products (brand_id, name, slug, short_description, description, base_price, compare_at_price, discount_type, discount_value, currency, status, is_featured, published_at)
SELECT brand.id, seed.name, seed.slug, seed.short_description, seed.description, seed.base_price, seed.compare_at_price, seed.discount_type, seed.discount_value, 'INR', 'active', seed.is_featured, UTC_TIMESTAMP()
FROM brands brand
CROSS JOIN (
    SELECT 'The Sanzida Satin Dress' AS name, 'sanzida-satin-dress' AS slug, 'A fluid midi dress with a flattering drape and a softly sculpted waist.' AS short_description, 'Made for elevated everyday dressing, this satin slip dress feels polished for dinners, celebrations, and slow evenings out.' AS description, 4200.00 AS base_price, 5200.00 AS compare_at_price, 'percentage' AS discount_type, 15.00 AS discount_value, 1 AS is_featured
    UNION ALL SELECT 'Mira Pleated Blouse', 'mira-pleated-blouse', 'An elegant blouse with delicate pleats and a soft tailored finish.', 'This refined blouse brings structure and movement together for office days, gallery visits, and dinners after dark.', 2600.00, 3200.00, 'fixed', 400.00, 1
    UNION ALL SELECT 'Laila Everyday Tote', 'laila-everyday-tote', 'A roomy structure in a polished neutral with comfort-first hardware.', 'The Laila Tote is the everyday companion for travel, errands, and polished afternoons, with generous capacity and a refined silhouette.', 3100.00, 3900.00, 'percentage', 20.00, 1
) seed
WHERE brand.slug = 'sanzidas-closet'
ON DUPLICATE KEY UPDATE
    name = VALUES(name), short_description = VALUES(short_description), description = VALUES(description), base_price = VALUES(base_price), compare_at_price = VALUES(compare_at_price), discount_type = VALUES(discount_type), discount_value = VALUES(discount_value), status = 'active', is_featured = VALUES(is_featured), published_at = COALESCE(products.published_at, UTC_TIMESTAMP());

INSERT IGNORE INTO product_categories (product_id, category_id)
SELECT product.id, category.id
FROM products product
INNER JOIN categories category ON category.slug = CASE
    WHEN product.slug = 'sanzida-satin-dress' THEN 'dresses'
    WHEN product.slug = 'mira-pleated-blouse' THEN 'tops'
    ELSE 'accessories'
END
WHERE product.slug IN ('sanzida-satin-dress', 'mira-pleated-blouse', 'laila-everyday-tote');

INSERT IGNORE INTO product_variants (product_id, size_id, color_id, sku, status)
SELECT product.id, size.id, color.id, CONCAT('DEMO-', UPPER(REPLACE(product.slug, '-', '')), '-', size.code, '-', UPPER(color.slug)), 'active'
FROM products product
CROSS JOIN sizes size
CROSS JOIN colors color
WHERE product.slug IN ('sanzida-satin-dress', 'mira-pleated-blouse', 'laila-everyday-tote')
  AND size.code IN ('S', 'M', 'L')
  AND color.slug IN ('ivory', 'black', 'rose', 'sage');

INSERT IGNORE INTO inventory (variant_id, quantity_on_hand, quantity_reserved, reorder_level)
SELECT variant.id, 12, 0, 3
FROM product_variants variant
INNER JOIN products product ON product.id = variant.product_id
WHERE product.slug IN ('sanzida-satin-dress', 'mira-pleated-blouse', 'laila-everyday-tote');

INSERT INTO product_images (product_id, file_path, alt_text, sort_order, is_primary)
SELECT product.id, CASE product.slug
        WHEN 'sanzida-satin-dress' THEN 'uploads/products/demo-satin-slip-dress-front.svg'
        WHEN 'mira-pleated-blouse' THEN 'uploads/products/demo-linen-wrap-blouse-front.svg'
        WHEN 'laila-everyday-tote' THEN 'uploads/products/demo-structured-shoulder-bag-front.svg'
END, CONCAT(product.name, ' product image'), 0, 1
FROM products product
WHERE product.slug IN ('sanzida-satin-dress', 'mira-pleated-blouse', 'laila-everyday-tote')
    AND NOT EXISTS (SELECT 1 FROM product_images existing WHERE existing.product_id = product.id);
