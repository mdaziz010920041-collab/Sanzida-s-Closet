const PRICE_SQL = `CASE
    WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0)
    WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0)
    ELSE p.base_price
END`;

function catalogueFilters(searchParams, routeMode) {
    const value = (key) => String(searchParams.get(key) || '').trim();
    return {
        search: value('q'),
        category: value('category'),
        brand: value('brand'),
        size: value('size'),
        color: value('color'),
        minPrice: value('min_price'),
        maxPrice: value('max_price'),
        availability: value('availability'),
        discount: routeMode === 'sale' ? 'on-sale' : value('discount'),
        sort: routeMode === 'new-arrivals' ? 'newest' : value('sort') || 'newest',
        page: Math.max(1, Math.min(1000, Number.parseInt(value('page') || '1', 10) || 1)),
        perPage: 20,
    };
}

async function listProducts(pool, filters) {
    const where = ["p.status = 'active'"];
    const parameters = [];
    const add = (condition, value) => {
        where.push(condition);
        parameters.push(value);
    };

    if (filters.category) add("EXISTS (SELECT 1 FROM product_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = p.id AND c.slug = ? AND c.status = 'active')", filters.category);
    if (filters.brand) add('b.slug = ?', filters.brand);
    if (/^\d+$/.test(filters.size)) add("EXISTS (SELECT 1 FROM product_variants sv WHERE sv.product_id = p.id AND sv.size_id = ? AND sv.status = 'active')", Number(filters.size));
    if (/^\d+$/.test(filters.color)) add("EXISTS (SELECT 1 FROM product_variants cv WHERE cv.product_id = p.id AND cv.color_id = ? AND cv.status = 'active')", Number(filters.color));
    if (filters.minPrice !== '' && Number.isFinite(Number(filters.minPrice))) add(`${PRICE_SQL} >= ?`, Math.max(0, Number(filters.minPrice)));
    if (filters.maxPrice !== '' && Number.isFinite(Number(filters.maxPrice))) add(`${PRICE_SQL} <= ?`, Math.max(0, Number(filters.maxPrice)));
    if (filters.availability === 'in-stock') where.push("EXISTS (SELECT 1 FROM product_variants iv JOIN inventory i ON i.variant_id = iv.id WHERE iv.product_id = p.id AND iv.status = 'active' AND i.quantity_on_hand > i.quantity_reserved)");
    if (filters.discount === 'on-sale') where.push("p.discount_type <> 'none' AND p.discount_value > 0");
    if (filters.search) {
        where.push('(p.name LIKE ? OR p.short_description LIKE ? OR EXISTS (SELECT 1 FROM product_variants qv WHERE qv.product_id = p.id AND qv.sku LIKE ?))');
        const term = `%${filters.search}%`;
        parameters.push(term, term, term);
    }

    const sortOptions = {
        newest: 'p.published_at DESC, p.id DESC',
        featured: 'p.is_featured DESC, p.published_at DESC, p.id DESC',
        popular: 'popularity DESC, p.published_at DESC, p.id DESC',
        'price-low': 'selling_price ASC, p.id DESC',
        'price-high': 'selling_price DESC, p.id DESC',
    };
    const sort = sortOptions[filters.sort] || sortOptions.newest;
    const whereSql = where.join(' AND ');
    const [countRows] = await pool.execute(`SELECT COUNT(DISTINCT p.id) AS total FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE ${whereSql}`, parameters);
    const total = Number(countRows[0]?.total || 0);
    const offset = (filters.page - 1) * filters.perPage;
    const [items] = await pool.execute(`
        SELECT p.id, p.name, p.slug, p.short_description, p.base_price, p.compare_at_price,
               p.discount_type, p.discount_value, p.currency, p.is_featured, b.name AS brand_name,
               (SELECT image.file_path FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order ASC, image.id ASC LIMIT 1) AS image_path,
               (SELECT image.alt_text FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order ASC, image.id ASC LIMIT 1) AS image_alt,
               ${PRICE_SQL} AS selling_price,
               (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status IN ('confirmed', 'processing', 'shipped', 'delivered', 'completed')) AS popularity,
               COALESCE(SUM(GREATEST(i.quantity_on_hand - i.quantity_reserved, 0)), 0) AS available_stock
        FROM products p LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_variants v ON v.product_id = p.id AND v.status = 'active'
        LEFT JOIN inventory i ON i.variant_id = v.id
        WHERE ${whereSql}
        GROUP BY p.id, p.name, p.slug, p.short_description, p.base_price, p.compare_at_price,
                 p.discount_type, p.discount_value, p.currency, p.is_featured, b.name
        ORDER BY ${sort} LIMIT ? OFFSET ?`, [...parameters, filters.perPage, offset]);

    return { items, total, page: filters.page, perPage: filters.perPage, pages: Math.max(1, Math.ceil(total / filters.perPage)) };
}

async function listCategories(pool) {
    const [categories] = await pool.query(`
        SELECT c.id, c.name, c.slug, c.description, c.image_path,
               COUNT(DISTINCT p.id) AS product_count
        FROM categories c LEFT JOIN product_categories pc ON pc.category_id = c.id
        LEFT JOIN products p ON p.id = pc.product_id AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id, c.name, c.slug, c.description, c.image_path
        ORDER BY c.sort_order, c.name`);
    return categories;
}

async function findProduct(pool, slug) {
    const [rows] = await pool.execute(`
        SELECT p.id, p.name, p.slug, p.short_description, p.description, p.base_price,
               p.compare_at_price, p.discount_type, p.discount_value, p.currency,
               b.name AS brand_name
        FROM products p LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.slug = ? AND p.status = 'active' LIMIT 1`, [slug]);
    const product = rows[0];
    if (!product) return null;

    const [images] = await pool.execute('SELECT id, file_path, alt_text, sort_order, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id', [product.id]);
    const [variants] = await pool.execute(`
        SELECT v.id, v.sku, v.size_id, v.color_id, v.price_override, v.compare_at_price_override,
               s.name AS size_name, s.code AS size_code, c.name AS color_name, c.hex_code,
               COALESCE(GREATEST(i.quantity_on_hand - i.quantity_reserved, 0), 0) AS available_stock
        FROM product_variants v LEFT JOIN sizes s ON s.id = v.size_id
        LEFT JOIN colors c ON c.id = v.color_id LEFT JOIN inventory i ON i.variant_id = v.id
        WHERE v.product_id = ? AND v.status = 'active'
        ORDER BY s.sort_order, c.sort_order, v.id`, [product.id]);
    const [categories] = await pool.execute(`
        SELECT c.name, c.slug FROM product_categories pc
        JOIN categories c ON c.id = pc.category_id
        WHERE pc.product_id = ? AND c.status = 'active' ORDER BY c.sort_order, c.name`, [product.id]);

    return { ...product, images, variants, categories };
}

module.exports = { catalogueFilters, findProduct, listCategories, listProducts };