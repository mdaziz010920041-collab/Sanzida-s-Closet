const crypto = require('node:crypto');

function decodeJson(value, fallback) {
    if (Array.isArray(value)) return value;
    if (value && typeof value === 'object') return value;
    try {
        return JSON.parse(String(value));
    } catch {
        return fallback;
    }
}

async function returnPolicy(pool) {
    const [rows] = await pool.query("SELECT * FROM return_policies WHERE status = 'active' ORDER BY id LIMIT 1");
    const policy = rows[0];
    if (!policy) return null;
    return { ...policy, eligible_statuses: decodeJson(policy.eligible_statuses, ['delivered', 'completed']), allowed_resolutions: decodeJson(policy.allowed_resolutions, ['refund']) };
}

function makeReturnNumber() {
    return `RET-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${crypto.randomBytes(4).toString('hex').toUpperCase()}`;
}

async function createReturnRequest(pool, userId, input) {
    const policy = await returnPolicy(pool);
    if (!policy) throw new Error('Return policy is not configured.');
    const orderItemId = Number(input.order_item_id);
    const quantity = Number(input.quantity);
    const reason = String(input.reason || '');
    const returnType = String(input.return_type || 'return');
    const resolution = String(input.resolution || 'refund');
    const note = String(input.note || '').trim().slice(0, 4000);
    const allowedReasons = ['changed_mind', 'damaged_item', 'wrong_item', 'missing_item', 'defective', 'other'];
    if (!allowedReasons.includes(reason) || !['return', 'exchange'].includes(returnType) || !['refund', 'exchange', 'store_credit'].includes(resolution)) throw new Error('Choose a valid return option.');
    if (!policy.allowed_resolutions.includes(resolution)) throw new Error('That resolution is not available under the current return policy.');
    const reasonFlags = { damaged_item: 'allow_damaged_item', wrong_item: 'allow_wrong_item', missing_item: 'allow_missing_item' };
    if (reasonFlags[reason] && !policy[reasonFlags[reason]]) throw new Error('That return reason is not covered by the current policy.');

    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        const [itemRows] = await connection.execute(`
            SELECT item.id, item.order_id, item.quantity, o.order_number, o.status, shipment.delivered_at
            FROM order_items item JOIN orders o ON o.id = item.order_id
            LEFT JOIN shipments shipment ON shipment.order_id = o.id
            WHERE item.id = ? AND o.user_id = ?
            ORDER BY shipment.id DESC LIMIT 1 FOR UPDATE`, [orderItemId, userId]);
        const item = itemRows[0];
        if (!item || !policy.eligible_statuses.includes(item.status) || !Number.isInteger(quantity) || quantity < 1 || quantity > Number(item.quantity)) {
            throw new Error('This order item is not eligible for that return request.');
        }
        if (Number(policy.days_from_delivery) > 0) {
            const deliveredAt = item.delivered_at ? new Date(item.delivered_at).getTime() : 0;
            if (!deliveredAt || deliveredAt < Date.now() - Number(policy.days_from_delivery) * 86400000) throw new Error('This order is outside the return window.');
        }
        const [usedRows] = await connection.execute(`
            SELECT COALESCE(SUM(ri.quantity), 0) AS quantity
            FROM return_items ri JOIN returns r ON r.id = ri.return_id
            WHERE ri.order_item_id = ? AND r.status NOT IN ('rejected', 'cancelled')`, [orderItemId]);
        if (quantity > Number(item.quantity) - Number(usedRows[0]?.quantity || 0)) throw new Error('That quantity has already been included in a return request.');

        const [result] = await connection.execute(`
            INSERT INTO returns (order_id, user_id, return_number, return_type, status, reason, resolution, customer_note)
            VALUES (?, ?, ?, ?, 'requested', ?, ?, ?)`, [item.order_id, userId, makeReturnNumber(), returnType, reason, resolution, note || null]);
        await connection.execute('INSERT INTO return_items (return_id, order_item_id, quantity, reason) VALUES (?, ?, ?, ?)', [result.insertId, orderItemId, quantity, reason]);
        await connection.commit();
        return { id: Number(result.insertId), order_number: item.order_number };
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
}

module.exports = { createReturnRequest, returnPolicy };