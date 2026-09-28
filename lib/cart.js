const crypto = require('node:crypto');

async function activeCartId(pool, userId, sessionToken) {
    const [rows] = userId
        ? await pool.execute("SELECT id FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1", [userId])
        : await pool.execute("SELECT id FROM carts WHERE user_id IS NULL AND session_token = ? AND status = 'active' ORDER BY id DESC LIMIT 1", [sessionToken]);
    if (rows[0]) return Number(rows[0].id);
    const storedToken = userId ? crypto.randomBytes(32).toString('hex') : sessionToken;
    const [result] = await pool.execute("INSERT INTO carts (user_id, session_token, status, expires_at) VALUES (?, ?, 'active', UTC_TIMESTAMP() + INTERVAL 90 DAY)", [userId || null, storedToken]);
    return Number(result.insertId);
}

async function itemSnapshot(pool, variantId) {
    const [rows] = await pool.execute(`
        SELECT v.id AS variant_id, v.product_id, v.sku, v.price_override, p.name, p.slug, p.currency,
               (SELECT image.file_path FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order, image.id LIMIT 1) AS image_path,
               (SELECT image.alt_text FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order, image.id LIMIT 1) AS image_alt,
               s.name AS size_name, c.name AS color_name,
               COALESCE(GREATEST(i.quantity_on_hand - i.quantity_reserved, 0), 0) AS available_stock,
               CASE WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0)
                    WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0) ELSE p.base_price END AS product_price
        FROM product_variants v JOIN products p ON p.id = v.product_id AND p.status = 'active'
        LEFT JOIN inventory i ON i.variant_id = v.id LEFT JOIN sizes s ON s.id = v.size_id
        LEFT JOIN colors c ON c.id = v.color_id
        WHERE v.id = ? AND v.status = 'active' LIMIT 1`, [variantId]);
    const item = rows[0];
    if (!item) return null;
    item.unit_price = Number(item.price_override ?? item.product_price);
    item.available_stock = Number(item.available_stock);
    return item;
}

async function addItem(pool, cartId, variantId, quantity) {
    const item = await itemSnapshot(pool, variantId);
    if (!item) throw new Error('This product variant is unavailable.');
    const [existingRows] = await pool.execute('SELECT quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?', [cartId, variantId]);
    const newQuantity = Number(existingRows[0]?.quantity || 0) + Math.max(1, Math.min(99, quantity));
    if (newQuantity > item.available_stock) throw new Error(`Only ${item.available_stock} available.`);
    await pool.execute(`INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP(6)`, [cartId, variantId, newQuantity]);
    return { item, quantity: newQuantity };
}

async function updateItem(pool, cartId, variantId, quantity) {
    if (quantity <= 0) return removeItem(pool, cartId, variantId);
    const item = await itemSnapshot(pool, variantId);
    const safeQuantity = Math.min(99, quantity);
    if (!item || safeQuantity > item.available_stock) throw new Error('The requested quantity is not available.');
    await pool.execute('UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP(6) WHERE cart_id = ? AND variant_id = ?', [safeQuantity, cartId, variantId]);
}

async function removeItem(pool, cartId, variantId) {
    await pool.execute('DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?', [cartId, variantId]);
}

async function validCoupon(pool, code, subtotal, userId) {
    const [rows] = await pool.execute(`SELECT * FROM coupons WHERE code = ? AND status = 'active'
        AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP()) LIMIT 1`, [String(code || '').trim().toUpperCase()]);
    const coupon = rows[0];
    if (!coupon || subtotal < Number(coupon.minimum_order_amount)) return null;
    const [usageRows] = await pool.execute('SELECT COUNT(*) AS uses FROM coupon_usage WHERE coupon_id = ?', [coupon.id]);
    if (coupon.usage_limit !== null && Number(usageRows[0].uses) >= Number(coupon.usage_limit)) return null;
    if (userId && coupon.usage_limit_per_user !== null) {
        const [userRows] = await pool.execute('SELECT COUNT(*) AS uses FROM coupon_usage WHERE coupon_id = ? AND user_id = ?', [coupon.id, userId]);
        if (Number(userRows[0].uses) >= Number(coupon.usage_limit_per_user)) return null;
    }
    let discount = coupon.discount_type === 'percentage' ? subtotal * Number(coupon.discount_value) / 100 : Number(coupon.discount_value);
    if (coupon.maximum_discount_amount !== null) discount = Math.min(discount, Number(coupon.maximum_discount_amount));
    return { id: Number(coupon.id), code: coupon.code, discount: Math.min(subtotal, Math.max(0, discount)) };
}

async function cartSummary(pool, userId, sessionToken) {
    const cartId = await activeCartId(pool, userId, sessionToken);
    const [items] = await pool.execute(`
        SELECT item.variant_id, item.quantity, p.name, p.slug, p.currency,
               (SELECT image.file_path FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order, image.id LIMIT 1) AS image_path,
               (SELECT image.alt_text FROM product_images image WHERE image.product_id = p.id ORDER BY image.is_primary DESC, image.sort_order, image.id LIMIT 1) AS image_alt,
               s.name AS size_name, c.name AS color_name, v.price_override,
               CASE WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0)
                    WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0) ELSE p.base_price END AS product_price,
               COALESCE(GREATEST(i.quantity_on_hand - i.quantity_reserved, 0), 0) AS available_stock
        FROM cart_items item JOIN product_variants v ON v.id = item.variant_id
        JOIN products p ON p.id = v.product_id AND p.status = 'active'
        LEFT JOIN inventory i ON i.variant_id = v.id LEFT JOIN sizes s ON s.id = v.size_id
        LEFT JOIN colors c ON c.id = v.color_id
        WHERE item.cart_id = ? ORDER BY item.created_at DESC`, [cartId]);
    let subtotal = 0;
    const normalizedItems = items.map((item) => {
        item.quantity = Number(item.quantity);
        item.available_stock = Number(item.available_stock);
        item.unit_price = Number(item.price_override ?? item.product_price);
        item.line_total = item.unit_price * item.quantity;
        subtotal += item.line_total;
        return item;
    });
    const [[cart]] = await pool.execute('SELECT coupon_id FROM carts WHERE id = ?', [cartId]);
    let coupon = null;
    if (cart?.coupon_id) {
        const [[couponRow]] = await pool.execute('SELECT code FROM coupons WHERE id = ?', [cart.coupon_id]);
        if (couponRow) coupon = await validCoupon(pool, couponRow.code, subtotal, userId);
    }
    const [shippingRows] = await pool.query("SELECT charge, free_shipping_threshold FROM shipping_methods WHERE status = 'active' ORDER BY sort_order, name LIMIT 1");
    const shippingMethod = shippingRows[0];
    const shipping = shippingMethod
        ? shippingMethod.free_shipping_threshold !== null && subtotal >= Number(shippingMethod.free_shipping_threshold) ? 0 : Number(shippingMethod.charge)
        : 0;
    const discount = Number(coupon?.discount || 0);
    return { cart_id: cartId, items: normalizedItems, item_count: normalizedItems.reduce((sum, item) => sum + item.quantity, 0), subtotal, discount, shipping, total: Math.max(0, subtotal - discount + shipping), coupon };
}

async function applyCoupon(pool, cartId, userId, code) {
    const [[row]] = await pool.execute(`
        SELECT COALESCE(SUM(item.quantity * COALESCE(v.price_override,
            CASE WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0)
                 WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0) ELSE p.base_price END)), 0) AS subtotal
        FROM cart_items item JOIN product_variants v ON v.id = item.variant_id
        JOIN products p ON p.id = v.product_id AND p.status = 'active' WHERE item.cart_id = ?`, [cartId]);
    const coupon = await validCoupon(pool, code, Number(row.subtotal), userId);
    if (!coupon) throw new Error('This coupon is invalid or does not apply to this cart.');
    await pool.execute('UPDATE carts SET coupon_id = ? WHERE id = ?', [coupon.id, cartId]);
    return coupon;
}

async function clearCoupon(pool, cartId) {
    await pool.execute('UPDATE carts SET coupon_id = NULL WHERE id = ?', [cartId]);
}

async function mergeGuestCart(pool, guestToken, userId) {
    if (!guestToken) return;
    const [guestRows] = await pool.execute("SELECT id FROM carts WHERE user_id IS NULL AND session_token = ? AND status = 'active' LIMIT 1", [guestToken]);
    if (!guestRows[0]) return;
    const guestCartId = Number(guestRows[0].id);
    const userCartId = await activeCartId(pool, userId, guestToken);
    const [items] = await pool.execute('SELECT variant_id, quantity FROM cart_items WHERE cart_id = ?', [guestCartId]);
    for (const item of items) {
        try {
            await addItem(pool, userCartId, Number(item.variant_id), Number(item.quantity));
        } catch (error) {
            console.error('Skipping unavailable guest cart item during merge:', error.message);
        }
    }
    await pool.execute("UPDATE carts SET status = 'converted' WHERE id = ?", [guestCartId]);
}

module.exports = { activeCartId, addItem, applyCoupon, cartSummary, clearCoupon, itemSnapshot, mergeGuestCart, removeItem, updateItem, validCoupon };