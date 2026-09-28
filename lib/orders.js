const crypto = require('node:crypto');
const { validCoupon } = require('./cart');

function orderNumber() {
    const date = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    return `SC-${date}-${crypto.randomBytes(4).toString('hex').toUpperCase()}`;
}

function checkoutAddress(input) {
    const address = {
        recipient_name: String(input.recipient_name || '').trim(),
        phone: String(input.phone || '').trim(),
        line_1: String(input.line_1 || '').trim(),
        line_2: String(input.line_2 || '').trim() || null,
        city: String(input.city || '').trim(),
        state: String(input.state || '').trim() || null,
        postal_code: String(input.postal_code || '').trim() || null,
        country_code: String(input.country_code || '').trim().toUpperCase(),
    };
    if (!address.recipient_name || !address.phone || !address.line_1 || !address.city) throw new Error('Complete the required delivery address fields.');
    if (!/^[A-Z]{2}$/.test(address.country_code)) throw new Error('Enter a valid country code.');
    return address;
}

async function checkoutCreateOrder(pool, { input, cartId, userId, sessionToken }) {
    const email = String(input.email || '').trim().toLowerCase();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw new Error('Enter a valid email address.');
    const address = checkoutAddress(input);
    const paymentMethod = String(input.payment_method || '');
    if (!['online_pending', 'cash_on_delivery'].includes(paymentMethod)) throw new Error('Choose an available payment method.');
    if (paymentMethod === 'online_pending' && (!process.env.RAZORPAY_KEY_ID || !process.env.RAZORPAY_SECRET)) {
        throw new Error('Online payment is temporarily unavailable. Choose cash on delivery or try again later.');
    }

    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        const [cartRows] = await connection.execute("SELECT id, user_id, session_token, coupon_id FROM carts WHERE id = ? AND status = 'active' FOR UPDATE", [cartId]);
        const cart = cartRows[0];
        if (!cart || (userId ? Number(cart.user_id) !== Number(userId) : cart.user_id !== null || cart.session_token !== sessionToken)) {
            throw new Error('Your cart is no longer active.');
        }

        let savedAddressId = null;
        const requestedAddressId = Number(input.address_id || 0);
        if (userId && requestedAddressId > 0) {
            const [addresses] = await connection.execute('SELECT id, recipient_name, phone, line_1, line_2, city, state, postal_code, country_code FROM addresses WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE', [requestedAddressId, userId]);
            if (addresses[0]) {
                savedAddressId = Number(addresses[0].id);
                Object.assign(address, addresses[0]);
            }
        }

        const [methods] = await connection.execute("SELECT id, code, name, provider, charge, free_shipping_threshold FROM shipping_methods WHERE code = ? AND status = 'active' LIMIT 1", [String(input.shipping_method || '')]);
        const shippingMethod = methods[0];
        if (!shippingMethod) throw new Error('Choose an available shipping method.');

        const [items] = await connection.execute(`
            SELECT item.variant_id, item.quantity, v.product_id, v.sku, v.price_override,
                   p.name, p.currency, p.base_price, p.discount_type, p.discount_value,
                   s.name AS size_name, c.name AS color_name
            FROM cart_items item JOIN product_variants v ON v.id = item.variant_id AND v.status = 'active'
            JOIN products p ON p.id = v.product_id AND p.status = 'active'
            LEFT JOIN sizes s ON s.id = v.size_id LEFT JOIN colors c ON c.id = v.color_id
            WHERE item.cart_id = ? ORDER BY item.id FOR UPDATE`, [cartId]);
        if (!items.length) throw new Error('Your cart is empty.');

        let subtotal = 0;
        const lines = [];
        for (const item of items) {
            const [stocks] = await connection.execute('SELECT id, quantity_on_hand, quantity_reserved FROM inventory WHERE variant_id = ? FOR UPDATE', [item.variant_id]);
            const stock = stocks[0];
            const available = stock ? Number(stock.quantity_on_hand) - Number(stock.quantity_reserved) : 0;
            if (!stock || available < Number(item.quantity)) throw new Error('One or more pieces no longer have enough stock. Please review your bag.');
            const basePrice = Number(item.base_price);
            const productPrice = item.discount_type === 'percentage' ? Math.max(0, basePrice - basePrice * Number(item.discount_value) / 100)
                : item.discount_type === 'fixed' ? Math.max(0, basePrice - Number(item.discount_value)) : basePrice;
            const unitPrice = Number(item.price_override ?? productPrice);
            const lineTotal = unitPrice * Number(item.quantity);
            subtotal += lineTotal;
            lines.push({ item, stock, unitPrice, lineTotal });
        }

        const threshold = shippingMethod.free_shipping_threshold === null ? null : Number(shippingMethod.free_shipping_threshold);
        const shipping = threshold !== null && subtotal >= threshold ? 0 : Number(shippingMethod.charge);
        const couponRows = cart.coupon_id ? await connection.execute('SELECT code FROM coupons WHERE id = ? LIMIT 1', [cart.coupon_id]) : [[]];
        const couponCode = couponRows[0][0]?.code;
        const coupon = couponCode ? await validCoupon(connection, couponCode, subtotal, userId) : null;
        const discount = Number(coupon?.discount || 0);
        const total = Math.max(0, subtotal - discount + shipping);
        const currency = String(lines[0].item.currency || 'INR');
        const number = orderNumber();
        const shippingSnapshot = JSON.stringify(address);
        const [orderResult] = await connection.execute(`
            INSERT INTO orders (user_id, coupon_id, shipping_method_id, shipping_method_code, shipping_method_name,
                order_number, email, status, currency, subtotal, discount_total, shipping_total, tax_total,
                grand_total, shipping_address_id, billing_address_id, shipping_address_snapshot,
                billing_address_snapshot, notes, placed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP(6))`, [
            userId || null, coupon?.id || null, shippingMethod.id, shippingMethod.code, shippingMethod.name,
            number, email, currency, subtotal, discount, shipping, total, savedAddressId, savedAddressId,
            shippingSnapshot, shippingSnapshot, `Shipping: ${shippingMethod.name}; Payment: ${paymentMethod}`,
        ]);
        const newOrderId = Number(orderResult.insertId);

        for (const { item, stock, unitPrice, lineTotal } of lines) {
            const description = [item.size_name, item.color_name].filter(Boolean).join(' / ') || null;
            await connection.execute(`INSERT INTO order_items (order_id, product_id, variant_id, sku, product_name, variant_description, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`, [newOrderId, item.product_id, item.variant_id, item.sku, item.name, description, unitPrice, item.quantity, lineTotal]);
            const reserved = Number(stock.quantity_reserved) + Number(item.quantity);
            const [reservation] = await connection.execute('UPDATE inventory SET quantity_reserved = quantity_reserved + ? WHERE id = ? AND quantity_on_hand - quantity_reserved >= ?', [item.quantity, stock.id, item.quantity]);
            if (reservation.affectedRows !== 1) throw new Error('Stock changed while placing your order. Please try again.');
            await connection.execute(`INSERT INTO inventory_transactions (variant_id, transaction_type, quantity_change, quantity_after, reference_type, reference_id, note) VALUES (?, 'reservation', 0, ?, 'order', ?, 'Stock reserved at order creation')`, [item.variant_id, Number(stock.quantity_on_hand) - reserved, newOrderId]);
        }

        const provider = paymentMethod === 'cash_on_delivery' ? 'cash_on_delivery' : 'online_pending';
        await connection.execute("INSERT INTO payments (order_id, provider, amount, currency, status) VALUES (?, ?, ?, ?, 'pending')", [newOrderId, provider, total, currency]);
        await connection.execute("INSERT INTO shipments (order_id, shipping_method_id, provider, status) VALUES (?, ?, ?, 'pending')", [newOrderId, shippingMethod.id, shippingMethod.provider]);
        if (coupon) await connection.execute('INSERT INTO coupon_usage (coupon_id, order_id, user_id, discount_amount) VALUES (?, ?, ?, ?)', [coupon.id, newOrderId, userId || null, discount]);
        if (!userId) {
            const tokenHash = crypto.createHash('sha256').update(sessionToken).digest('hex');
            await connection.execute('INSERT INTO guest_order_access (order_id, token_hash, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 90 DAY)', [newOrderId, tokenHash]);
        }
        await connection.execute("UPDATE carts SET status = 'converted' WHERE id = ?", [cartId]);
        await connection.execute('DELETE FROM cart_items WHERE cart_id = ?', [cartId]);
        await connection.commit();
        return { id: newOrderId, order_number: number, total, currency, payment_method: paymentMethod };
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
}

async function findOrder(pool, orderNumber, userId, sessionToken) {
    const tokenHash = sessionToken ? crypto.createHash('sha256').update(sessionToken).digest('hex') : '';
    const [rows] = await pool.execute(`
        SELECT o.* FROM orders o
        LEFT JOIN guest_order_access guest ON guest.order_id = o.id
        WHERE o.order_number = ? AND ((? IS NOT NULL AND o.user_id = ?) OR (o.user_id IS NULL AND guest.token_hash = ? AND guest.expires_at > UTC_TIMESTAMP()))
        LIMIT 1`, [orderNumber, userId || null, userId || null, tokenHash]);
    const order = rows[0];
    if (!order) return null;
    const [items] = await pool.execute('SELECT id, product_id, variant_id, product_name, sku, variant_description, unit_price, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id', [order.id]);
    const [payments] = await pool.execute('SELECT provider, amount, status, paid_at FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [order.id]);
    const [shipments] = await pool.execute('SELECT carrier, tracking_number, status, shipped_at, delivered_at FROM shipments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [order.id]);
    return { ...order, items, payment: payments[0] || null, shipment: shipments[0] || null };
}

module.exports = { checkoutAddress, checkoutCreateOrder, findOrder };