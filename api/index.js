const fs = require('node:fs');
const path = require('node:path');
const { databasePool } = require('../lib/database');
const { catalogueFilters, findProduct, listCategories, listProducts } = require('../lib/catalogue');
const { renderCatalogue, renderCategories, renderErrorPage, renderProduct } = require('../lib/catalogue-pages');
const { activeCartId, addItem, applyCoupon, cartSummary, clearCoupon, mergeGuestCart, removeItem, updateItem } = require('../lib/cart');
const { createSession, currentUser, hashPassword, login, normalizeEmail, register, revokeSession, verifyPassword } = require('../lib/auth');
const { clearSessionCookies, ensureCsrfToken, ensureSessionToken, readSignedToken, verifyCsrfToken, writeSessionToken } = require('../lib/session');
const { jsonResponse, requestBody } = require('../lib/request');
const { renderCartPage } = require('../lib/cart-pages');
const { renderAuthPage } = require('../lib/auth-pages');
const { renderAccountPage } = require('../lib/account-pages');
const { updateWishlist } = require('../lib/wishlist');
const { renderHome } = require('../lib/home-page');
const { checkoutCreateOrder, findOrder } = require('../lib/orders');
const { renderCheckout, renderOrder, renderPaymentRetry } = require('../lib/checkout-pages');
const { paymentInitiate, paymentVerify, processPaymentWebhook } = require('../lib/payments');
const { rawRequestBody } = require('../lib/request');
const { createReturnRequest, returnPolicy } = require('../lib/returns');
const { renderReturnForm, renderReturnPolicy } = require('../lib/return-pages');
const { adminHasPermission, adminLogin, adminLogout, currentAdmin, updateAdminRecord } = require('../lib/admin');
const { renderAdminDashboard, renderAdminLogin, renderAdminModule } = require('../lib/admin-pages');

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
}[character]));

const staticContentTypes = {
    '.css': 'text/css; charset=utf-8',
    '.gif': 'image/gif',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.js': 'text/javascript; charset=utf-8',
    '.png': 'image/png',
    '.svg': 'image/svg+xml',
    '.webp': 'image/webp',
};

async function serveStaticAsset(request, response) {
    if (!['GET', 'HEAD'].includes(request.method)) return false;

    let pathname;
    try {
        pathname = decodeURIComponent(new URL(request.url || '/', 'http://localhost').pathname);
    } catch {
        response.writeHead(400);
        response.end();
        return true;
    }

    const prefix = pathname.startsWith('/assets/') ? '/assets/' : pathname.startsWith('/uploads/') ? '/uploads/' : '';
    if (!prefix) return false;

    const root = path.resolve(__dirname, '..', prefix.slice(1, -1));
    const filePath = path.resolve(root, pathname.slice(prefix.length));
    if (!filePath.startsWith(`${root}${path.sep}`)) {
        response.writeHead(403);
        response.end();
        return true;
    }

    try {
        const stat = await fs.promises.stat(filePath);
        if (!stat.isFile()) throw new Error('Not a file');
        response.writeHead(200, {
            'cache-control': 'public, max-age=31536000, immutable',
            'content-type': staticContentTypes[path.extname(filePath).toLowerCase()] || 'application/octet-stream',
            'content-length': stat.size,
            'x-content-type-options': 'nosniff',
        });
        if (request.method === 'HEAD') response.end();
        else response.end(await fs.promises.readFile(filePath));
    } catch {
        response.writeHead(404);
        response.end();
    }
    return true;
}

function imageUrl(imagePath) {
    const value = String(imagePath || '').trim();
    if (/^https:\/\//i.test(value)) return value;
    const localPath = value.replace(/^\/+/, '');
    if (/^uploads\/[A-Za-z0-9_./-]+$/.test(localPath) && !localPath.split('/').some((segment) => segment === '.' || segment === '..')) return `/${localPath}`;
    return '';
}

function money(currency, amount) {
    const symbol = ['INR', 'BDT'].includes(String(currency || 'INR').toUpperCase()) ? '₹' : `${currency || 'INR'} `;
    return `${symbol} ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function requestOrigin(request) {
    if (process.env.APP_URL) return String(process.env.APP_URL).replace(/\/$/, '');
    const host = String(request.headers.host || 'localhost');
    const scheme = request.headers['x-forwarded-proto'] === 'https' ? 'https' : 'http';
    return `${scheme}://${host}`;
}

function productCard(product) {
    const price = Number(product.selling_price ?? product.base_price ?? 0);
    const image = imageUrl(product.image_path);
    return `<article class="product-card"><a href="/products/view.php?slug=${encodeURIComponent(product.slug)}"><div class="product-card__media">${image ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(product.image_alt || product.name)}" loading="lazy" width="640" height="800">` : '<div class="catalogue-image-placeholder"><span>Image coming soon</span></div>'}</div><div class="product-card__body"><p class="label">${escapeHtml(product.brand_name || "Sanzida's Closet")}</p><h2 class="product-card__title">${escapeHtml(product.name)}</h2><p class="product-card__price">${escapeHtml(money(product.currency, price))}</p></div></a></article>`;
}

async function homePage(request) {
    try {
        const pool = databasePool();
        const [products] = await pool.query(`
            SELECT p.name, p.slug, p.base_price, p.compare_at_price, p.discount_type,
                   p.discount_value, p.currency, b.name AS brand_name,
                   p.is_featured,
                   (SELECT pi.file_path FROM product_images pi
                    WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image_path,
                   (SELECT pi.alt_text FROM product_images pi
                    WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image_alt
            FROM products p
            LEFT JOIN brands b ON b.id = p.brand_id
            WHERE p.status = 'active'
            ORDER BY p.is_featured DESC, p.published_at DESC, p.id DESC
            LIMIT 4
        `);
        const [bestSellers] = await pool.query(`
            SELECT p.name, p.slug, p.base_price, p.compare_at_price, p.discount_type,
                   p.discount_value, p.currency, p.is_featured, b.name AS brand_name,
                   (SELECT pi.file_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image_path,
                   (SELECT pi.alt_text FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image_alt
            FROM products p LEFT JOIN brands b ON b.id = p.brand_id
            WHERE p.status = 'active' AND p.is_featured = 1
            ORDER BY p.published_at DESC, p.id DESC LIMIT 3
        `);
        const categories = await listCategories(pool);
        for (const product of [...products, ...bestSellers]) {
            const basePrice = Number(product.base_price || 0);
            const discount = Number(product.discount_value || 0);
            product.selling_price = product.discount_type === 'percentage'
                ? Math.max(0, basePrice - basePrice * discount / 100)
                : product.discount_type === 'fixed' ? Math.max(0, basePrice - discount) : basePrice;
        }
        return renderHome(products, bestSellers, categories, requestOrigin(request));
    } catch (error) {
        console.error('Homepage catalogue unavailable:', error.message);
        return renderHome([], [], [], requestOrigin(request));
    }
}

module.exports = async function handler(request, response) {
    if (await serveStaticAsset(request, response)) return;

    const requestUrl = new URL(request.url || '/', `http://${request.headers.host || 'localhost'}`);
    const pathname = requestUrl.pathname.replace(/\/+$/, '') || '/';
    const requestedNext = requestUrl.searchParams.get('next') || '/account/';
    const nextPath = requestedNext.startsWith('/') && !requestedNext.startsWith('//') && !/[\\\0]/.test(requestedNext) && !/^[a-z][a-z0-9+.-]*:/i.test(requestedNext) ? requestedNext : '/account/';

    if (request.method === 'GET' && ['/', '/index.php'].includes(pathname)) {
        response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
        response.end(await homePage(request));
        return;
    }

    if (['/auth/login.php', '/auth/login', '/auth/register.php', '/auth/register'].includes(pathname) && ['GET', 'POST'].includes(request.method)) {
        const registering = pathname.includes('register');
        let values = { first_name: '', last_name: '', email: '', phone: '' };
        let errorMessage = '';
        try {
            const token = ensureSessionToken(request, response);
            const csrfToken = ensureCsrfToken(request, response, token);
            const pool = databasePool();
            if (request.method === 'POST') {
                const body = await requestBody(request);
                values = { first_name: body.first_name || '', last_name: body.last_name || '', email: body.email || '', phone: body.phone || '' };
                if (!verifyCsrfToken(request, body.csrf_token, token)) {
                    errorMessage = 'Your session expired. Please try again.';
                } else if (registering) {
                    try {
                        await register(pool, body);
                    } catch (error) {
                        if (error.code) throw error;
                        errorMessage = error.message;
                    }
                    if (!errorMessage) {
                        const result = await login(pool, request, body.email, body.password);
                        if (!result.success) errorMessage = 'Your account was created. Please sign in.';
                        else {
                            await mergeGuestCart(pool, token, result.user.id);
                            writeSessionToken(request, response, result.token);
                            ensureCsrfToken(request, response, result.token);
                            response.writeHead(303, { location: '/account/', 'cache-control': 'no-store' });
                            response.end();
                            return;
                        }
                    }
                } else {
                    const result = await login(pool, request, body.email, body.password);
                    if (!result.success) errorMessage = result.message;
                    else {
                        await mergeGuestCart(pool, token, result.user.id);
                        writeSessionToken(request, response, result.token);
                        ensureCsrfToken(request, response, result.token);
                        response.writeHead(303, { location: nextPath, 'cache-control': 'no-store' });
                        response.end();
                        return;
                    }
                }
            }
            response.writeHead(errorMessage ? 422 : 200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderAuthPage(registering ? 'register' : 'login', csrfToken, nextPath, errorMessage, values));
        } catch (error) {
            console.error('Authentication request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Sign-in unavailable', 'Please try again when the account service is available.'));
        }
        return;
    }

    if (['/auth/logout.php', '/auth/logout'].includes(pathname) && request.method === 'POST') {
        try {
            const token = readSignedToken(request);
            const body = await requestBody(request);
            if (!token || !verifyCsrfToken(request, body.csrf_token, token)) {
                response.writeHead(303, { location: '/auth/login.php', 'cache-control': 'no-store' });
                response.end();
                return;
            }
            await revokeSession(databasePool(), token);
            clearSessionCookies(request, response);
            response.writeHead(303, { location: '/auth/login.php', 'cache-control': 'no-store' });
            response.end();
        } catch (error) {
            console.error('Logout request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/plain; charset=utf-8', 'cache-control': 'no-store' });
            response.end('Sign-out is temporarily unavailable.');
        }
        return;
    }

    if (['/admin/login.php', '/admin/login'].includes(pathname) && ['GET', 'POST'].includes(request.method)) {
        try {
            const token = ensureSessionToken(request, response);
            const csrfToken = ensureCsrfToken(request, response, token);
            const pool = databasePool();
            if (await currentAdmin(pool, readSignedToken(request))) {
                response.writeHead(303, { location: '/admin/', 'cache-control': 'no-store' });
                response.end();
                return;
            }
            let errorMessage = '';
            if (request.method === 'POST') {
                const body = await requestBody(request);
                if (!verifyCsrfToken(request, body.csrf_token, token)) errorMessage = 'Your session expired. Please try again.';
                else {
                    const result = await adminLogin(pool, request, body.email, body.password);
                    if (!result.success) errorMessage = result.message;
                    else {
                        writeSessionToken(request, response, result.token);
                        ensureCsrfToken(request, response, result.token);
                        response.writeHead(303, { location: '/admin/', 'cache-control': 'no-store' });
                        response.end();
                        return;
                    }
                }
            }
            response.writeHead(errorMessage ? 422 : 200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderAdminLogin(csrfToken, errorMessage));
        } catch (error) {
            console.error('Admin sign-in failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Admin sign-in unavailable', 'Admin authentication is temporarily unavailable.'));
        }
        return;
    }

    if (['/admin/logout.php', '/admin/logout'].includes(pathname) && request.method === 'POST') {
        try {
            const token = readSignedToken(request);
            const body = await requestBody(request);
            if (!token || !verifyCsrfToken(request, body.csrf_token, token)) {
                response.writeHead(303, { location: '/admin/login.php', 'cache-control': 'no-store' });
                response.end();
                return;
            }
            const pool = databasePool();
            const admin = await currentAdmin(pool, token);
            await adminLogout(pool, token, admin);
            clearSessionCookies(request, response);
            response.writeHead(303, { location: '/admin/login.php', 'cache-control': 'no-store' });
            response.end();
        } catch (error) {
            console.error('Admin sign-out failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/plain; charset=utf-8', 'cache-control': 'no-store' });
            response.end('Admin sign-out is temporarily unavailable.');
        }
        return;
    }

    const adminModuleAliases = {
        '/admin/products.php': 'products', '/admin/categories.php': 'categories', '/admin/orders.php': 'orders',
        '/admin/customers.php': 'customers', '/admin/inventory.php': 'inventory', '/admin/coupons.php': 'coupons',
        '/admin/reviews.php': 'reviews', '/admin/banners.php': 'content', '/admin/content.php': 'content',
        '/admin/seo.php': 'seo', '/admin/settings.php': 'settings', '/admin/reports.php': 'reports',
    };
    const moduleName = pathname === '/admin/module.php' ? String(requestUrl.searchParams.get('name') || 'products') : adminModuleAliases[pathname];
    if (['GET', 'POST'].includes(request.method) && (['/admin', '/admin/index.php'].includes(pathname) || moduleName)) {
        try {
            const token = readSignedToken(request);
            const pool = databasePool();
            const admin = await currentAdmin(pool, token);
            if (!admin) {
                response.writeHead(303, { location: '/admin/login.php', 'cache-control': 'no-store' });
                response.end();
                return;
            }
            if (!moduleName) {
                const queries = [
                    "SELECT COALESCE(SUM(grand_total), 0) AS value FROM orders WHERE status IN ('confirmed', 'processing', 'shipped', 'delivered', 'completed')",
                    'SELECT COUNT(*) AS value FROM orders', 'SELECT COUNT(*) AS value FROM users',
                    "SELECT COUNT(*) AS value FROM products WHERE status <> 'archived'",
                    "SELECT COUNT(*) AS value FROM orders WHERE status IN ('pending', 'confirmed', 'processing')",
                    "SELECT COUNT(*) AS value FROM returns WHERE status IN ('requested', 'approved', 'received', 'inspecting')",
                    "SELECT COUNT(*) AS value FROM refunds WHERE status = 'pending'",
                    'SELECT COUNT(*) AS value FROM inventory WHERE quantity_on_hand - quantity_reserved <= reorder_level',
                ];
                const results = await Promise.all(queries.map((query) => pool.query(query)));
                const metrics = { revenue: results[0][0][0].value, orders: results[1][0][0].value, customers: results[2][0][0].value, products: results[3][0][0].value, pending_orders: results[4][0][0].value, returns: results[5][0][0].value, refunds: results[6][0][0].value, low_stock: results[7][0][0].value };
                const csrfToken = ensureCsrfToken(request, response, token);
                response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
                response.end(renderAdminDashboard(admin, metrics, csrfToken));
                return;
            }

            const permissionMap = { products: 'products.manage', categories: 'categories.manage', orders: 'orders.manage', customers: 'customers.manage', inventory: 'inventory.manage', coupons: 'coupons.manage', reviews: 'reviews.manage', returns: 'orders.manage', refunds: 'orders.manage', content: 'content.manage', seo: 'seo.manage', settings: 'settings.manage', audit: 'audit.view', reports: 'reports.view' };
            const allowedModules = Object.keys(permissionMap);
            const selectedModule = allowedModules.includes(moduleName) ? moduleName : 'products';
            let rows = [];
            let errorMessage = '';
            let successMessage = '';
            if (!adminHasPermission(admin, permissionMap[selectedModule])) {
                response.writeHead(403, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
                response.end(renderAdminModule(admin, selectedModule, [], 'You do not have permission to manage this module.'));
                return;
            }
            if (request.method === 'POST') {
                const body = await requestBody(request);
                if (!verifyCsrfToken(request, body.csrf_token, token)) errorMessage = 'Your session expired. Please try again.';
                else {
                    try {
                        await updateAdminRecord(pool, admin, selectedModule, body);
                        successMessage = 'Changes saved.';
                    } catch (error) {
                        if (error.code) throw error;
                        errorMessage = error.message;
                    }
                }
            }
            if (!errorMessage) {
                const queries = {
                    products: 'SELECT id, name, slug, base_price, status, updated_at FROM products ORDER BY updated_at DESC LIMIT 100',
                    categories: 'SELECT id, name, slug, status, parent_id, image_path, updated_at FROM categories ORDER BY sort_order, name LIMIT 100',
                    orders: 'SELECT id, order_number, email, status, grand_total, currency, created_at FROM orders ORDER BY created_at DESC LIMIT 100',
                    customers: 'SELECT id, email, first_name, last_name, status, created_at FROM users WHERE email LIKE ? OR first_name LIKE ? OR last_name LIKE ? ORDER BY created_at DESC LIMIT 100',
                    inventory: 'SELECT i.variant_id, p.name, v.sku, i.quantity_on_hand, i.quantity_reserved, i.reorder_level FROM inventory i JOIN product_variants v ON v.id = i.variant_id JOIN products p ON p.id = v.product_id ORDER BY (i.quantity_on_hand - i.quantity_reserved <= i.reorder_level) DESC, p.name LIMIT 100',
                    coupons: 'SELECT id, code, discount_type, discount_value, status, starts_at, ends_at FROM coupons ORDER BY created_at DESC LIMIT 100',
                    reviews: 'SELECT r.id, p.name AS product_name, r.rating, r.status, r.created_at FROM reviews r JOIN products p ON p.id = r.product_id ORDER BY r.created_at DESC LIMIT 100',
                    returns: 'SELECT id, return_number, order_id, return_type, status, reason, requested_at FROM returns ORDER BY requested_at DESC LIMIT 100',
                    refunds: 'SELECT id, order_id, amount, currency, status, reason, created_at FROM refunds ORDER BY created_at DESC LIMIT 100',
                    content: 'SELECT id, title, slug, status, updated_at FROM pages ORDER BY updated_at DESC LIMIT 100',
                    seo: 'SELECT id, entity_type, entity_id, meta_title, canonical_url, updated_at FROM seo_metadata ORDER BY updated_at DESC LIMIT 100',
                    settings: 'SELECT id, setting_key, is_public, updated_at FROM settings ORDER BY setting_key LIMIT 100',
                    audit: 'SELECT id, admin_id, action, entity_type, entity_id, ip_address, created_at FROM admin_audit_logs ORDER BY created_at DESC LIMIT 150',
                    reports: "SELECT DATE(created_at) AS order_date, COUNT(*) AS orders, SUM(grand_total) AS revenue FROM orders WHERE status NOT IN ('cancelled', 'returned') GROUP BY DATE(created_at) ORDER BY order_date DESC LIMIT 30",
                };
                const query = queries[selectedModule];
                const result = selectedModule === 'customers'
                    ? await pool.execute(query, Array(3).fill(`%${String(requestUrl.searchParams.get('q') || '').trim().slice(0, 100)}%`))
                    : await pool.query(query);
                rows = result[0];
            }
            const csrfToken = ensureCsrfToken(request, response, token);
            response.writeHead(errorMessage ? 422 : 200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderAdminModule(admin, selectedModule, rows, errorMessage, csrfToken, successMessage));
        } catch (error) {
            console.error('Admin request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Admin unavailable', 'Admin data is temporarily unavailable.'));
        }
        return;
    }

    if (['/account', '/account/index.php'].includes(pathname) && ['GET', 'POST'].includes(request.method)) {
        try {
            let token = readSignedToken(request);
            const pool = databasePool();
            let user = await currentUser(pool, token);
            if (!user) {
                response.writeHead(303, { location: `/auth/login.php?next=${encodeURIComponent('/account/')}`, 'cache-control': 'no-store' });
                response.end();
                return;
            }
            let csrfToken = ensureCsrfToken(request, response, token);
            let message = '';
            let isError = false;
            if (request.method === 'POST') {
                const body = await requestBody(request);
                if (!verifyCsrfToken(request, body.csrf_token, token)) {
                    message = 'Your session expired. Please try again.';
                    isError = true;
                } else if (body.action === 'profile') {
                    const firstName = String(body.first_name || '').trim();
                    const lastName = String(body.last_name || '').trim();
                    const email = normalizeEmail(body.email);
                    const phone = String(body.phone || '').trim();
                    if (!firstName || firstName.length > 80 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        message = 'Enter a valid name and email address.';
                        isError = true;
                    } else if (lastName.length > 80 || phone.length > 30) {
                        message = 'Please shorten your profile details.';
                        isError = true;
                    } else {
                        try {
                            await pool.execute('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, email_verified_at = IF(email = ?, email_verified_at, NULL) WHERE id = ?', [firstName, lastName || null, email, phone || null, user.email, user.id]);
                            message = 'Your profile has been updated.';
                        } catch (error) {
                            if (error.code !== 'ER_DUP_ENTRY') throw error;
                            message = 'That email address is already in use.';
                            isError = true;
                        }
                    }
                } else if (body.action === 'password') {
                    const newPassword = String(body.new_password || '');
                    if (Buffer.byteLength(newPassword, 'utf8') < 8 || Buffer.byteLength(newPassword, 'utf8') > 72 || newPassword !== String(body.new_password_confirmation || '')) {
                        message = 'Your new passwords must match and be between 8 and 72 characters.';
                        isError = true;
                    } else {
                        const [passwordRows] = await pool.execute('SELECT password_hash FROM users WHERE id = ? LIMIT 1', [user.id]);
                        if (!passwordRows[0] || !(await verifyPassword(String(body.current_password || ''), passwordRows[0].password_hash))) {
                            message = 'Your current password is incorrect.';
                            isError = true;
                        } else {
                            const connection = await pool.getConnection();
                            try {
                                await connection.beginTransaction();
                                await connection.execute('UPDATE users SET password_hash = ? WHERE id = ?', [await hashPassword(newPassword), user.id]);
                                await connection.execute('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE user_id = ? AND revoked_at IS NULL', [user.id]);
                                token = await createSession(connection, request, user.id);
                                await connection.commit();
                            } catch (error) {
                                await connection.rollback();
                                throw error;
                            } finally {
                                connection.release();
                            }
                            writeSessionToken(request, response, token);
                            csrfToken = ensureCsrfToken(request, response, token);
                            message = 'Your password has been updated and other sessions were signed out.';
                        }
                    }
                } else if (body.action === 'address') {
                    const recipient = String(body.recipient_name || '').trim();
                    const phone = String(body.phone || '').trim();
                    const line1 = String(body.line_1 || '').trim();
                    const city = String(body.city || '').trim();
                    const country = String(body.country_code || 'BD').trim().toUpperCase();
                    if (!recipient || !phone || !line1 || !city || !/^[A-Z]{2}$/.test(country)) {
                        message = 'Complete the required address fields.';
                        isError = true;
                    } else {
                        await pool.execute('INSERT INTO addresses (user_id, label, recipient_name, phone, line_1, line_2, city, state, postal_code, country_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [user.id, String(body.label || 'Address').trim().slice(0, 60) || 'Address', recipient, phone, line1, String(body.line_2 || '').trim() || null, city, String(body.state || '').trim() || null, String(body.postal_code || '').trim() || null, country]);
                        message = 'Your address has been saved.';
                    }
                }
                user = await currentUser(pool, token) || user;
            }
            const [addresses] = await pool.execute('SELECT id, label, recipient_name, phone, line_1, line_2, city, state, postal_code, country_code FROM addresses WHERE user_id = ? ORDER BY created_at DESC', [user.id]);
            const [orders] = await pool.execute('SELECT order_number, status, grand_total, currency, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10', [user.id]);
            const [wishlist] = await pool.execute(`SELECT p.id, p.name, p.slug, p.base_price, p.discount_type, p.discount_value, p.currency, v.id AS variant_id, image.file_path AS image_path, image.alt_text AS image_alt, CASE WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0) WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0) ELSE p.base_price END AS selling_price FROM wishlists w JOIN wishlist_items wi ON wi.wishlist_id = w.id JOIN product_variants v ON v.id = wi.variant_id JOIN products p ON p.id = v.product_id AND p.status = 'active' LEFT JOIN product_images image ON image.product_id = p.id AND image.is_primary = 1 WHERE w.user_id = ? ORDER BY wi.created_at DESC LIMIT 8`, [user.id]);
            response.writeHead(isError ? 422 : 200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderAccountPage(user, orders, csrfToken, addresses, wishlist, message, isError));
        } catch (error) {
            console.error('Account request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Account unavailable', 'Please try again when the account service is available.'));
        }
        return;
    }

    if (request.method === 'GET' && ['/returns-exchanges', '/returns-exchanges.php'].includes(pathname)) {
        try {
            const policy = await returnPolicy(databasePool());
            response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderReturnPolicy(policy));
        } catch (error) {
            console.error('Return policy request failed:', error.message);
            response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderReturnPolicy(null));
        }
        return;
    }

    if (['/orders/return.php', '/orders/return'].includes(pathname) && ['GET', 'POST'].includes(request.method)) {
        try {
            const token = readSignedToken(request);
            const pool = databasePool();
            const user = await currentUser(pool, token);
            if (!user) {
                const returnUrl = `/orders/return.php?order=${encodeURIComponent(requestUrl.searchParams.get('order') || '')}`;
                response.writeHead(303, { location: `/auth/login.php?next=${encodeURIComponent(returnUrl)}`, 'cache-control': 'no-store' });
                response.end();
                return;
            }
            const csrfToken = ensureCsrfToken(request, response, token);
            let orderNumber = String(requestUrl.searchParams.get('order') || '').trim();
            let order = await findOrder(pool, orderNumber, Number(user.id), token);
            const policy = await returnPolicy(pool);
            let errorMessage = '';
            let successMessage = '';
            if (request.method === 'POST') {
                const body = await requestBody(request);
                orderNumber = String(body.order || orderNumber).trim();
                order = await findOrder(pool, orderNumber, Number(user.id), token);
                if (!verifyCsrfToken(request, body.csrf_token, token)) errorMessage = 'Your session expired. Please try again.';
                else if (!order) errorMessage = 'Order not found.';
                else {
                    const result = await createReturnRequest(pool, Number(user.id), body);
                    successMessage = `Return request submitted for order ${result.order_number}.`;
                }
            }
            response.writeHead(errorMessage ? 422 : order || successMessage ? 200 : 404, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderReturnForm(order, policy, csrfToken, errorMessage, successMessage));
        } catch (error) {
            console.error('Return request failed:', error.message);
            response.writeHead(error.code ? 503 : 422, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Return request unavailable', error.code ? 'Please try again when returns are available.' : error.message));
        }
        return;
    }

    if (['/checkout', '/checkout/index.php'].includes(pathname) && ['GET', 'POST'].includes(request.method)) {
        try {
            const token = ensureSessionToken(request, response);
            const csrfToken = ensureCsrfToken(request, response, token);
            const pool = databasePool();
            const user = await currentUser(pool, token);
            const cart = await cartSummary(pool, user?.id || null, token);
            const [shippingMethods] = await pool.query("SELECT id, code, name, description, carrier, charge, free_shipping_threshold FROM shipping_methods WHERE status = 'active' ORDER BY sort_order, name");
            const [addresses] = user ? await pool.execute('SELECT id, label, recipient_name, phone, line_1, line_2, city, state, postal_code, country_code FROM addresses WHERE user_id = ? ORDER BY is_default_shipping DESC, created_at DESC', [user.id]) : [[]];
            let errorMessage = '';
            if (request.method === 'POST') {
                const body = await requestBody(request);
                if (!verifyCsrfToken(request, body.csrf_token, token)) errorMessage = 'Your session expired. Please try again.';
                else {
                    const order = await checkoutCreateOrder(pool, { input: body, cartId: cart.cart_id, userId: user?.id || null, sessionToken: token });
                    const destination = order.payment_method === 'online_pending' ? '/checkout/payment.php' : '/checkout/confirmation.php';
                    response.writeHead(303, { location: `${destination}?order=${encodeURIComponent(order.order_number)}`, 'cache-control': 'no-store' });
                    response.end();
                    return;
                }
            }
            response.writeHead(errorMessage ? 422 : 200, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderCheckout(cart, csrfToken, user, addresses, shippingMethods, errorMessage));
        } catch (error) {
            console.error('Checkout request failed:', error.message);
            response.writeHead(error.code ? 503 : 422, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Checkout unavailable', error.code ? 'Please try again when checkout is available.' : error.message));
        }
        return;
    }

    if (request.method === 'GET' && ['/checkout/confirmation.php', '/checkout/confirmation', '/orders/view.php', '/orders/view'].includes(pathname)) {
        try {
            const token = readSignedToken(request);
            const user = await currentUser(databasePool(), token);
            const order = await findOrder(databasePool(), String(requestUrl.searchParams.get('order') || '').trim(), user?.id || null, token);
            response.writeHead(order ? 200 : 404, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderOrder(order, pathname.includes('view') ? 'Order details' : 'Order confirmation'));
        } catch (error) {
            console.error('Order view request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Order unavailable', 'Please try again when order history is available.'));
        }
        return;
    }

    if (request.method === 'GET' && ['/checkout/payment.php', '/checkout/payment'].includes(pathname)) {
        try {
            const token = readSignedToken(request);
            const user = await currentUser(databasePool(), token);
            const order = await findOrder(databasePool(), String(requestUrl.searchParams.get('order') || '').trim(), user?.id || null, token);
            if (order) ensureCsrfToken(request, response, token);
            const csrfToken = token ? ensureCsrfToken(request, response, token) : '';
            response.writeHead(order ? 200 : 404, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderPaymentRetry(order, csrfToken));
        } catch (error) {
            console.error('Payment page request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Payment unavailable', 'Please try again when payment is available.'));
        }
        return;
    }

    if (request.method === 'GET' && ['/products', '/products/index.php', '/new-arrivals', '/new-arrivals/index.php', '/sale', '/sale/index.php'].includes(pathname)) {
        const routeMode = pathname.startsWith('/new-arrivals') ? 'new-arrivals' : pathname.startsWith('/sale') ? 'sale' : 'products';
        const filters = catalogueFilters(requestUrl.searchParams, routeMode);
        try {
            const data = await listProducts(databasePool(), filters);
            const title = routeMode === 'new-arrivals' ? 'New arrivals' : routeMode === 'sale' ? 'Sale' : filters.category ? filters.category.replace(/-/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'Shop';
            response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderCatalogue(data, filters, title));
        } catch (error) {
            console.error('Catalogue request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Catalogue unavailable', 'Product data will appear when the configured MySQL connection is available.'));
        }
        return;
    }

    if (request.method === 'GET' && ['/cart', '/cart/index.php'].includes(pathname)) {
        try {
            const token = ensureSessionToken(request, response);
            const csrfToken = ensureCsrfToken(request, response, token);
            const pool = databasePool();
            const user = await currentUser(pool, readSignedToken(request));
            const cart = await cartSummary(pool, user?.id || null, token);
            response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderCartPage(cart, csrfToken));
        } catch (error) {
            console.error('Cart page request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Bag unavailable', 'Please try again when the cart service is available.'));
        }
        return;
    }

    if (pathname === '/api/cart.php' || pathname === '/api/cart') {
        try {
            const token = ensureSessionToken(request, response);
            const csrfToken = ensureCsrfToken(request, response, token);
            const pool = databasePool();
            const user = await currentUser(pool, readSignedToken(request));
            const body = request.method === 'POST' ? await requestBody(request) : {};
            if (request.method === 'POST' && !verifyCsrfToken(request, body.csrf_token, token)) {
                jsonResponse(response, 422, { success: false, message: 'Your session expired. Refresh and try again.' });
                return;
            }
            const cartId = await activeCartId(pool, user?.id || null, token);
            const action = String(body.action || requestUrl.searchParams.get('action') || 'summary');
            let added;
            if (action === 'add') added = await addItem(pool, cartId, Number(body.variant_id), Number(body.quantity || 1));
            else if (action === 'update') await updateItem(pool, cartId, Number(body.variant_id), Number(body.quantity || 0));
            else if (action === 'remove') await removeItem(pool, cartId, Number(body.variant_id));
            else if (action === 'apply_coupon') await applyCoupon(pool, cartId, user?.id || null, body.code);
            else if (action === 'clear_coupon') await clearCoupon(pool, cartId);
            else if (action !== 'summary') {
                jsonResponse(response, 422, { success: false, message: 'Unsupported cart action.' });
                return;
            }
            const cart = await cartSummary(pool, user?.id || null, token);
            const messages = { add: 'Added to your bag.', update: 'Bag updated.', remove: 'Removed from your bag.', apply_coupon: 'Coupon applied.', clear_coupon: 'Coupon removed.' };
            jsonResponse(response, 200, { success: true, message: messages[action] || '', ...(added ? { added } : {}), cart, csrfToken });
        } catch (error) {
            console.error('Cart API request failed:', error.message);
            const validationError = !error.code && !['Request body is too large.', 'Unsupported request content type.'].includes(error.message);
            jsonResponse(response, validationError ? 422 : 503, { success: false, message: validationError ? error.message : 'Cart service is temporarily unavailable.' });
        }
        return;
    }

    if (pathname === '/api/wishlist.php' || pathname === '/api/wishlist') {
        if (request.method !== 'POST') {
            jsonResponse(response, 405, { success: false, message: 'Method not allowed.' });
            return;
        }
        try {
            const token = readSignedToken(request);
            const pool = databasePool();
            const user = await currentUser(pool, token);
            if (!user) {
                const scheme = request.headers['x-forwarded-proto'] === 'https' ? 'https' : 'http';
                const base = `${scheme}://${request.headers.host || 'localhost'}`;
                let next = '/products/';
                try {
                    const referer = new URL(request.headers.referer || '/products/', base);
                    if (referer.origin === base) next = `${referer.pathname}${referer.search}`;
                } catch {}
                jsonResponse(response, 401, { success: false, message: 'Sign in to use your wishlist.', login_url: `/auth/login.php?next=${encodeURIComponent(next)}` });
                return;
            }
            const body = await requestBody(request);
            if (!verifyCsrfToken(request, body.csrf_token, token)) {
                jsonResponse(response, 422, { success: false, message: 'Your session expired. Refresh and try again.' });
                return;
            }
            const result = await updateWishlist(pool, Number(user.id), token, String(body.action || 'add'), Number(body.variant_id));
            jsonResponse(response, 200, { success: true, ...result });
        } catch (error) {
            console.error('Wishlist API request failed:', error.message);
            const validationError = !error.code && !['Request body is too large.', 'Unsupported request content type.'].includes(error.message);
            jsonResponse(response, validationError ? 422 : 503, { success: false, message: validationError ? error.message : 'Wishlist service is temporarily unavailable.' });
        }
        return;
    }

    const productPath = pathname.match(/^\/products\/([^/]+)$/);
    if (request.method === 'GET' && (pathname === '/products/view.php' || productPath)) {
        const rawSlug = pathname === '/products/view.php' ? requestUrl.searchParams.get('slug') || '' : productPath[1];
        let slug = rawSlug;
        try {
            slug = decodeURIComponent(rawSlug);
        } catch {}
        try {
            const token = ensureSessionToken(request, response);
            const csrfToken = ensureCsrfToken(request, response, token);
            const product = await findProduct(databasePool(), slug);
            if (!product) {
                response.writeHead(404, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
                response.end(renderErrorPage('Piece not found', 'The requested product is not currently available.'));
                return;
            }
            response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderProduct(product, csrfToken));
        } catch (error) {
            console.error('Product request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Product unavailable', 'Product details will appear when MySQL is available.'));
        }
        return;
    }

    if (request.method === 'GET' && ['/categories', '/categories/index.php'].includes(pathname)) {
        try {
            const categories = await listCategories(databasePool());
            response.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderCategories(categories));
        } catch (error) {
            console.error('Category request failed:', error.message);
            response.writeHead(503, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff' });
            response.end(renderErrorPage('Categories unavailable', 'Category data will appear when the configured MySQL connection is available.'));
        }
        return;
    }

    if (pathname === '/api/payment-webhook.php' || pathname === '/api/payment-webhook') {
        if (request.method !== 'POST') {
            jsonResponse(response, 405, { success: false, message: 'Method not allowed.' });
            return;
        }
        try {
            const rawBody = await rawRequestBody(request, 1024 * 1024);
            await processPaymentWebhook(databasePool(), rawBody.toString('utf8'), String(request.headers['x-razorpay-signature'] || ''), String(request.headers['x-razorpay-event-id'] || ''));
            jsonResponse(response, 200, { success: true, received: true });
        } catch (error) {
            console.error('Payment webhook failed:', error.message);
            jsonResponse(response, error.message.includes('signature') ? 400 : 503, { success: false, message: error.message.includes('signature') ? 'Webhook signature could not be verified.' : 'Webhook processing is temporarily unavailable.' });
        }
        return;
    }

    if (pathname === '/api/payment.php' || pathname === '/api/payment') {
        if (request.method !== 'POST') {
            jsonResponse(response, 405, { success: false, message: 'Method not allowed.' });
            return;
        }
        try {
            const token = readSignedToken(request);
            const body = await requestBody(request);
            if (!token || !verifyCsrfToken(request, body.csrf_token, token)) {
                jsonResponse(response, 422, { success: false, message: 'Your session expired. Refresh and try again.' });
                return;
            }
            const pool = databasePool();
            const user = await currentUser(pool, token);
            const orderNumber = String(body.order_number || '').trim();
            const result = body.action === 'verify'
                ? await paymentVerify(pool, orderNumber, user?.id || null, token, String(body.razorpay_order_id || ''), String(body.razorpay_payment_id || ''), String(body.razorpay_signature || ''))
                : await paymentInitiate(pool, orderNumber, user?.id || null, token);
            jsonResponse(response, 200, { success: true, ...result });
        } catch (error) {
            console.error('Payment request failed:', error.message);
            const serverError = /gateway|temporarily|not configured|missing for this order/i.test(error.message);
            jsonResponse(response, serverError ? 503 : 422, { success: false, message: serverError ? 'Payment service is temporarily unavailable.' : error.message });
        }
        return;
    }

    if (request.method === 'GET' && ['/sitemap.xml', '/sitemap.php'].includes(pathname)) {
        const origin = requestOrigin(request);
        const urls = new Set([`${origin}/`, `${origin}/products/`, `${origin}/categories/`]);
        try {
            const pool = databasePool();
            const [products] = await pool.query("SELECT slug FROM products WHERE status = 'active'");
            const [categories] = await pool.query("SELECT slug FROM categories WHERE status = 'active'");
            for (const product of products) urls.add(`${origin}/products/${encodeURIComponent(product.slug)}/`);
            for (const category of categories) urls.add(`${origin}/products/?category=${encodeURIComponent(category.slug)}`);
        } catch (error) {
            console.error('Sitemap catalogue query failed:', error.message);
        }
        const body = `<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${[...urls].map((url) => `<url><loc>${escapeHtml(url)}</loc></url>`).join('')}</urlset>`;
        response.writeHead(200, { 'content-type': 'application/xml; charset=utf-8', 'cache-control': 'public, max-age=300', 'x-content-type-options': 'nosniff' });
        response.end(body);
        return;
    }

    if (request.method === 'GET' && pathname === '/robots.txt') {
        const origin = requestOrigin(request);
        response.writeHead(200, { 'content-type': 'text/plain; charset=utf-8', 'cache-control': 'public, max-age=300', 'x-content-type-options': 'nosniff' });
        response.end(`User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /account/\nDisallow: /checkout/\nDisallow: /api/\nSitemap: ${origin}/sitemap.xml\n`);
        return;
    }

    if (request.method === 'GET' && ['/api/health', '/api/health.php'].includes(pathname)) {
        let database = 'unavailable';
        let status = 503;
        try {
            await databasePool().query('SELECT 1');
            database = 'ok';
            status = 200;
        } catch (error) {
            console.error('Health check database error:', error.message);
        }
        response.writeHead(status, { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' });
        response.end(JSON.stringify({ status: database === 'ok' ? 'ok' : 'degraded', application: process.env.APP_NAME || "Sanzida's Closet", database }));
        return;
    }

    if (request.method === 'GET' && ['/api/search-suggestions', '/api/search-suggestions.php'].includes(pathname)) {
        const term = String(requestUrl.searchParams.get('q') || '').trim().slice(0, 80);
        if (term.length < 2) {
            jsonResponse(response, 200, { items: [] });
            return;
        }
        try {
            const searchTerm = `%${term}%`;
            const [items] = await databasePool().execute(`
                SELECT DISTINCT p.name, p.slug, v.sku FROM products p
                LEFT JOIN product_variants v ON v.product_id = p.id AND v.status = 'active'
                WHERE p.status = 'active' AND (p.name LIKE ? OR v.sku LIKE ?)
                ORDER BY p.is_featured DESC, p.published_at DESC, p.name LIMIT 8`, [searchTerm, searchTerm]);
            jsonResponse(response, 200, { items: items.map((item) => ({ ...item, url: `/products/${encodeURIComponent(item.slug)}/` })) });
        } catch (error) {
            console.error('Search suggestion request failed:', error.message);
            jsonResponse(response, 503, { items: [], error: 'Search is temporarily unavailable.' });
        }
        return;
    }

    response.writeHead(404, { 'content-type': 'text/plain; charset=utf-8', 'x-content-type-options': 'nosniff' });
    response.end('Not found');
};