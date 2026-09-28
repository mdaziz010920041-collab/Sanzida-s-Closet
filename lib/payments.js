const crypto = require('node:crypto');
const Razorpay = require('razorpay');
const { findOrder } = require('./orders');

function paymentConfig() {
    return {
        keyId: String(process.env.RAZORPAY_KEY_ID || '').trim(),
        secret: String(process.env.RAZORPAY_SECRET || '').trim(),
        webhookSecret: String(process.env.RAZORPAY_WEBHOOK_SECRET || '').trim(),
        currency: String(process.env.PAYMENT_CURRENCY || 'INR').trim().toUpperCase(),
    };
}

function gateway() {
    const config = paymentConfig();
    if (!config.keyId || !config.secret) throw new Error('Payment gateway credentials are not configured.');
    return new Razorpay({ key_id: config.keyId, key_secret: config.secret });
}

async function paymentInitiate(pool, orderNumber, userId, sessionToken) {
    const order = await findOrder(pool, orderNumber, userId, sessionToken);
    if (!order) throw new Error('Order not found.');
    if (order.payment?.status === 'paid') return { status: 'paid', order_number: orderNumber };
    if (!['online_pending', 'razorpay'].includes(String(order.payment?.provider || ''))) throw new Error('Online payment is not enabled for this order.');
    const config = paymentConfig();
    if (order.currency !== config.currency) throw new Error('Payment currency is not configured for this order.');

    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        const [rows] = await connection.execute('SELECT id, provider_order_id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE', [order.id]);
        const payment = rows[0];
        if (!payment) throw new Error('Payment record is missing for this order.');
        if (payment.provider_order_id) {
            await connection.commit();
            return { status: 'pending', key_id: config.keyId, provider_order_id: payment.provider_order_id, amount: Math.round(Number(order.grand_total) * 100), currency: order.currency, order_number: orderNumber };
        }
        const gatewayOrder = await gateway().orders.create({
            amount: Math.round(Number(order.grand_total) * 100),
            currency: order.currency,
            receipt: order.order_number,
            notes: { order_number: order.order_number },
        });
        if (!gatewayOrder?.id) throw new Error('Payment gateway did not return an order ID.');
        await connection.execute("UPDATE payments SET provider = 'razorpay', provider_order_id = ?, status = 'pending', metadata = ? WHERE id = ?", [gatewayOrder.id, JSON.stringify({ mode: process.env.PAYMENT_MODE || 'test' }), payment.id]);
        await connection.commit();
        return { status: 'pending', key_id: config.keyId, provider_order_id: gatewayOrder.id, amount: Math.round(Number(order.grand_total) * 100), currency: order.currency, order_number: orderNumber };
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
}

function signaturesMatch(expectedHex, actualHex) {
    if (!/^[a-f0-9]{64}$/i.test(actualHex)) return false;
    const expected = Buffer.from(expectedHex, 'hex');
    const actual = Buffer.from(actualHex, 'hex');
    return expected.length === actual.length && crypto.timingSafeEqual(expected, actual);
}

async function paymentVerify(pool, orderNumber, userId, sessionToken, providerOrderId, providerPaymentId, signature) {
    const order = await findOrder(pool, orderNumber, userId, sessionToken);
    if (!order || !providerOrderId || !providerPaymentId || !signature) throw new Error('Payment verification data is incomplete.');
    const [rows] = await pool.execute('SELECT provider_order_id, status FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [order.id]);
    const localPayment = rows[0];
    if (!localPayment || localPayment.provider_order_id !== providerOrderId || localPayment.status === 'paid') throw new Error('Payment does not match the order.');
    const config = paymentConfig();
    const expected = crypto.createHmac('sha256', config.secret).update(`${providerOrderId}|${providerPaymentId}`).digest('hex');
    if (!config.secret || !signaturesMatch(expected, signature)) throw new Error('Payment signature could not be verified.');
    const providerPayment = await gateway().payments.fetch(providerPaymentId);
    if (providerPayment.order_id !== providerOrderId || providerPayment.status !== 'captured' || Number(providerPayment.amount) !== Math.round(Number(order.grand_total) * 100) || providerPayment.currency !== order.currency) {
        throw new Error('Payment gateway confirmation did not match the order.');
    }

    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        const [result] = await connection.execute(`UPDATE payments SET provider = 'razorpay', provider_order_id = ?, provider_payment_id = ?, provider_signature = ?, status = 'paid', paid_at = CURRENT_TIMESTAMP(6), metadata = ? WHERE order_id = ? AND provider_order_id = ? AND status <> 'paid'`, [providerOrderId, providerPaymentId, signature, JSON.stringify(providerPayment), order.id, providerOrderId]);
        if (result.affectedRows !== 1) throw new Error('Payment was already processed or no longer matches this order.');
        await connection.execute("UPDATE orders SET status = CASE WHEN status IN ('pending', 'confirmed') THEN 'confirmed' ELSE status END WHERE id = ?", [order.id]);
        await connection.commit();
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
    return { status: 'paid', order_number: orderNumber };
}

async function processPaymentWebhook(pool, rawBody, signature, eventId) {
    const config = paymentConfig();
    if (!config.webhookSecret) throw new Error('Payment webhook is not configured.');
    const expected = crypto.createHmac('sha256', config.webhookSecret).update(rawBody).digest('hex');
    if (!signaturesMatch(expected, signature)) throw new Error('Webhook signature could not be verified.');
    const payload = JSON.parse(rawBody);
    const providerEventId = eventId || crypto.createHash('sha256').update(rawBody).digest('hex');
    const eventType = String(payload.event || 'unknown');
    const entity = payload.payload?.payment?.entity || {};
    const providerOrderId = String(entity.order_id || '');
    const status = eventType === 'payment.captured' ? 'paid' : eventType === 'payment.failed' ? 'failed' : null;

    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        await connection.execute('INSERT INTO payment_webhook_events (provider, event_id, event_type, payload) VALUES (?, ?, ?, ?)', ['razorpay', providerEventId, eventType, rawBody]);
        if (providerOrderId && status) {
            const [payments] = await connection.execute('SELECT id, order_id, amount, currency FROM payments WHERE provider_order_id = ? LIMIT 1 FOR UPDATE', [providerOrderId]);
            const payment = payments[0];
            if (payment && status === 'paid' && (Number(entity.amount) !== Math.round(Number(payment.amount) * 100) || entity.currency !== payment.currency)) {
                throw new Error('Webhook payment amount or currency did not match the order.');
            }
            if (payment) {
                await connection.execute(`UPDATE payments SET provider = 'razorpay', provider_payment_id = ?, status = ?, failure_reason = ?, paid_at = CASE WHEN ? = 'paid' THEN CURRENT_TIMESTAMP(6) ELSE paid_at END, metadata = ? WHERE id = ?`, [entity.id || null, status, status === 'failed' ? String(entity.error_description || 'Payment failed').slice(0, 255) : null, status, rawBody, payment.id]);
                if (status === 'paid') await connection.execute("UPDATE orders SET status = CASE WHEN status IN ('pending', 'confirmed') THEN 'confirmed' ELSE status END WHERE id = ?", [payment.order_id]);
                await connection.execute("UPDATE payment_webhook_events SET status = 'processed', processed_at = CURRENT_TIMESTAMP(6) WHERE provider = 'razorpay' AND event_id = ?", [providerEventId]);
            } else {
                await connection.execute("UPDATE payment_webhook_events SET status = 'ignored', processed_at = CURRENT_TIMESTAMP(6) WHERE provider = 'razorpay' AND event_id = ?", [providerEventId]);
            }
        } else {
            await connection.execute("UPDATE payment_webhook_events SET status = 'ignored', processed_at = CURRENT_TIMESTAMP(6) WHERE provider = 'razorpay' AND event_id = ?", [providerEventId]);
        }
        await connection.commit();
    } catch (error) {
        await connection.rollback();
        if (error.code === 'ER_DUP_ENTRY') return;
        throw error;
    } finally {
        connection.release();
    }
}

module.exports = { paymentInitiate, paymentVerify, processPaymentWebhook };