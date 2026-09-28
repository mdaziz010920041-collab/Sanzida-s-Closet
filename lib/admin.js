const crypto = require('node:crypto');
const { clientIp, isRateLimited, normalizeEmail, verifyPassword } = require('./auth');

async function adminLogin(pool, request, emailInput, password) {
    const email = normalizeEmail(emailInput);
    const ip = clientIp(request);
    if (await isRateLimited(pool, email, ip)) return { success: false, message: 'Too many attempts. Please try again in a few minutes.' };
    const [rows] = await pool.execute(`SELECT a.id, a.email, a.password_hash, a.display_name, a.status, r.id AS role_id, r.name AS role_name, r.slug AS role_slug
        FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.email = ? LIMIT 1`, [email]);
    const admin = rows[0];
    let valid = false;
    if (admin?.status === 'active') {
        try {
            valid = await verifyPassword(password, String(admin.password_hash));
        } catch {
            valid = false;
        }
    }
    await pool.execute('INSERT INTO auth_login_attempts (email, ip_address, succeeded) VALUES (?, ?, ?)', [email, ip || null, valid ? 1 : 0]);
    if (!valid) return { success: false, message: 'Email or password is incorrect.' };

    const token = crypto.randomBytes(32).toString('hex');
    const [permissions] = await pool.execute('SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [admin.role_id]);
    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        await connection.execute('INSERT INTO admin_sessions (admin_id, token_hash, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL 8 HOUR)', [admin.id, crypto.createHash('sha256').update(token).digest('hex'), ip || null, String(request.headers['user-agent'] || '').slice(0, 500)]);
        await connection.execute('UPDATE admins SET last_login_at = CURRENT_TIMESTAMP(6) WHERE id = ?', [admin.id]);
        await connection.execute('INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)', [admin.id, 'admin.login', 'admin', admin.id, ip || null]);
        await connection.commit();
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
    return { success: true, token, admin: { id: Number(admin.id), email: admin.email, display_name: admin.display_name, role_id: Number(admin.role_id), role_name: admin.role_name, role_slug: admin.role_slug, permissions: permissions.map((item) => item.slug) } };
}

async function currentAdmin(pool, token) {
    if (!token) return null;
    const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
    const [rows] = await pool.execute(`SELECT a.id, a.email, a.display_name, r.id AS role_id, r.name AS role_name, r.slug AS role_slug, s.id AS session_id
        FROM admins a JOIN roles r ON r.id = a.role_id JOIN admin_sessions s ON s.admin_id = a.id
        WHERE s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > UTC_TIMESTAMP() AND a.status = 'active' LIMIT 1`, [tokenHash]);
    const admin = rows[0];
    if (!admin) return null;
    const [permissions] = await pool.execute('SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [admin.role_id]);
    admin.permissions = permissions.map((item) => item.slug);
    await pool.execute('UPDATE admin_sessions SET last_seen_at = CURRENT_TIMESTAMP(6) WHERE id = ?', [admin.session_id]);
    return admin;
}

function adminHasPermission(admin, permission) {
    return ['owner', 'super-admin'].includes(admin.role_slug) || admin.permissions.includes(permission);
}

async function updateAdminRecord(pool, admin, moduleName, input) {
    const id = Math.max(0, Number.parseInt(input.id, 10) || 0);
    const action = String(input.action || '');
    const status = String(input.status || '');
    if (!id && !(moduleName === 'inventory' && action === 'adjust')) throw new Error('A record is required.');
    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        if (moduleName === 'orders' && action === 'status') {
            const allowed = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'completed', 'returned'];
            if (!allowed.includes(status)) throw new Error('Invalid order status.');
            const [rows] = await connection.execute('SELECT status FROM orders WHERE id = ? FOR UPDATE', [id]);
            if (!rows[0]) throw new Error('Order not found.');
            await connection.execute('UPDATE orders SET status = ? WHERE id = ?', [status, id]);
            if (status === 'cancelled' && rows[0].status !== 'cancelled') {
                const [items] = await connection.execute('SELECT variant_id, quantity FROM order_items WHERE order_id = ? AND variant_id IS NOT NULL', [id]);
                for (const item of items) {
                    const [stocks] = await connection.execute('SELECT id, quantity_reserved, quantity_on_hand FROM inventory WHERE variant_id = ? FOR UPDATE', [item.variant_id]);
                    const stock = stocks[0];
                    if (!stock) continue;
                    const released = Math.min(Number(item.quantity), Number(stock.quantity_reserved));
                    if (released <= 0) continue;
                    const reserved = Number(stock.quantity_reserved) - released;
                    await connection.execute('UPDATE inventory SET quantity_reserved = ? WHERE id = ?', [reserved, stock.id]);
                    await connection.execute("INSERT INTO inventory_transactions (variant_id, admin_id, transaction_type, quantity_change, quantity_after, reference_type, reference_id, note) VALUES (?, ?, 'release', 0, ?, 'order', ?, 'Reservation released after order cancellation')", [item.variant_id, admin.id, Number(stock.quantity_on_hand) - reserved, id]);
                }
            }
        } else if (moduleName === 'orders' && action === 'payment_status') {
            if (!['pending', 'authorized', 'paid', 'failed', 'refunded', 'partially_refunded'].includes(status)) throw new Error('Invalid payment status.');
            await connection.execute('UPDATE payments SET status = ? WHERE order_id = ?', [status, id]);
        } else if (moduleName === 'orders' && action === 'shipping_status') {
            if (!['pending', 'packed', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled'].includes(status)) throw new Error('Invalid shipping status.');
            const [rows] = await connection.execute('SELECT id FROM shipments WHERE order_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE', [id]);
            if (rows[0]) await connection.execute('UPDATE shipments SET status = ? WHERE id = ?', [status, rows[0].id]);
            else await connection.execute('INSERT INTO shipments (order_id, status) VALUES (?, ?)', [id, status]);
        } else if (moduleName === 'customers' && action === 'status') {
            if (!['active', 'inactive', 'suspended'].includes(status)) throw new Error('Invalid customer status.');
            await connection.execute('UPDATE users SET status = ? WHERE id = ?', [status, id]);
        } else if (moduleName === 'inventory' && action === 'adjust') {
            const variantId = Math.max(0, Number.parseInt(input.variant_id, 10) || 0);
            const change = Number.parseInt(input.quantity_change, 10) || 0;
            if (!variantId || !change) throw new Error('Enter a non-zero inventory adjustment.');
            const [rows] = await connection.execute('SELECT id, quantity_on_hand, quantity_reserved FROM inventory WHERE variant_id = ? FOR UPDATE', [variantId]);
            const item = rows[0];
            if (!item || Number(item.quantity_on_hand) + change < Number(item.quantity_reserved)) throw new Error('Adjustment would make available stock negative.');
            const quantity = Number(item.quantity_on_hand) + change;
            await connection.execute('UPDATE inventory SET quantity_on_hand = ? WHERE id = ?', [quantity, item.id]);
            await connection.execute("INSERT INTO inventory_transactions (variant_id, admin_id, transaction_type, quantity_change, quantity_after, note) VALUES (?, ?, 'adjustment', ?, ?, ?)", [variantId, admin.id, change, quantity - Number(item.quantity_reserved), String(input.note || 'Admin adjustment').slice(0, 500)]);
        } else if (moduleName === 'reviews' && action === 'moderate') {
            if (!['approved', 'rejected', 'pending'].includes(status)) throw new Error('Invalid review status.');
            await connection.execute('UPDATE reviews SET status = ? WHERE id = ?', [status, id]);
        } else if (moduleName === 'reviews' && action === 'delete') {
            await connection.execute('DELETE FROM reviews WHERE id = ?', [id]);
        } else if (moduleName === 'returns' && action === 'status') {
            if (!['requested', 'approved', 'rejected', 'received', 'inspecting', 'refunded', 'exchanged', 'completed', 'cancelled'].includes(status)) throw new Error('Invalid return status.');
            await connection.execute('UPDATE returns SET status = ? WHERE id = ?', [status, id]);
        } else if (moduleName === 'refunds' && action === 'status') {
            if (!['pending', 'processed', 'failed', 'cancelled'].includes(status)) throw new Error('Invalid refund status.');
            await connection.execute('UPDATE refunds SET status = ? WHERE id = ?', [status, id]);
        } else if (moduleName === 'products' && action === 'archive') {
            await connection.execute("UPDATE products SET status = 'archived' WHERE id = ?", [id]);
        } else {
            throw new Error('This admin action is not available.');
        }
        await connection.execute('INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id, metadata) VALUES (?, ?, ?, ?, ?)', [admin.id, `${moduleName}.${action}`, moduleName === 'customers' ? 'user' : moduleName.replace(/s$/, ''), id, JSON.stringify({ status: status || null, variant_id: input.variant_id || null, quantity_change: input.quantity_change || null })]);
        await connection.commit();
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
}

async function adminLogout(pool, token, admin) {
    if (!token) return;
    const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
    const connection = await pool.getConnection();
    try {
        await connection.beginTransaction();
        await connection.execute('UPDATE admin_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE token_hash = ? AND revoked_at IS NULL', [tokenHash]);
        if (admin) await connection.execute('INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)', [admin.id, 'admin.logout', 'admin', admin.id]);
        await connection.commit();
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
}

module.exports = { adminHasPermission, adminLogin, adminLogout, currentAdmin, updateAdminRecord };