const crypto = require('node:crypto');
const bcrypt = require('bcryptjs');
const { verify: verifyArgon2 } = require('@node-rs/argon2');

function normalizeEmail(email) {
    return String(email || '').trim().toLowerCase();
}

function clientIp(request) {
    const value = String(request.headers['x-real-ip'] || request.socket?.remoteAddress || '');
    return value.length <= 45 ? value : '';
}

async function isRateLimited(pool, email, ip) {
    const [emailRows] = await pool.execute("SELECT COUNT(*) AS attempts FROM auth_login_attempts WHERE attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE AND succeeded = 0 AND email = ?", [email]);
    if (Number(emailRows[0]?.attempts) >= 5) return true;
    if (!ip) return false;
    const [ipRows] = await pool.execute("SELECT COUNT(*) AS attempts FROM auth_login_attempts WHERE attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE AND succeeded = 0 AND ip_address = ?", [ip]);
    return Number(ipRows[0]?.attempts) >= 15;
}

async function verifyPassword(password, passwordHash) {
    if (passwordHash.startsWith('$argon2')) return verifyArgon2(passwordHash, password);
    const compatibleHash = passwordHash.startsWith('$2y$') ? `$2b$${passwordHash.slice(4)}` : passwordHash;
    return bcrypt.compare(password, compatibleHash);
}

async function hashPassword(password) {
    return bcrypt.hash(password, 12);
}

async function createSession(pool, request, userId) {
    const token = crypto.randomBytes(32).toString('hex');
    const ip = clientIp(request);
    await pool.execute('INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL 30 DAY)', [userId, crypto.createHash('sha256').update(token).digest('hex'), ip || null, String(request.headers['user-agent'] || '').slice(0, 500)]);
    return token;
}

async function login(pool, request, emailInput, password) {
    const email = normalizeEmail(emailInput);
    const ip = clientIp(request);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || !password) return { success: false, message: 'Email or password is incorrect.' };
    if (await isRateLimited(pool, email, ip)) return { success: false, message: 'Too many attempts. Please try again in a few minutes.' };

    const [rows] = await pool.execute('SELECT id, email, password_hash, status FROM users WHERE email = ? LIMIT 1', [email]);
    const user = rows[0];
    let valid = false;
    if (user?.status === 'active') {
        try {
            valid = await verifyPassword(password, String(user.password_hash));
        } catch {
            valid = false;
        }
    }
    await pool.execute('INSERT INTO auth_login_attempts (email, ip_address, succeeded) VALUES (?, ?, ?)', [email, ip || null, valid ? 1 : 0]);
    if (!valid) return { success: false, message: 'Email or password is incorrect.' };

    const token = await createSession(pool, request, user.id);
    await pool.execute('UPDATE users SET last_login_at = CURRENT_TIMESTAMP(6) WHERE id = ?', [user.id]);
    return { success: true, user: { id: Number(user.id), email: user.email }, token };
}

async function currentUser(pool, token) {
    if (!token) return null;
    const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
    const [rows] = await pool.execute(`
        SELECT u.id, u.email, u.first_name, u.last_name, u.phone
        FROM users u JOIN user_sessions s ON s.user_id = u.id
        WHERE s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > UTC_TIMESTAMP() AND u.status = 'active'
        LIMIT 1`, [tokenHash]);
    if (!rows[0]) return null;
    await pool.execute('UPDATE user_sessions SET last_seen_at = CURRENT_TIMESTAMP(6) WHERE token_hash = ?', [tokenHash]);
    return rows[0];
}

async function register(pool, input) {
    const email = normalizeEmail(input.email);
    const firstName = String(input.first_name || '').trim();
    const lastName = String(input.last_name || '').trim();
    const phone = String(input.phone || '').trim();
    const password = String(input.password || '');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw new Error('Enter a valid email address.');
    if (!firstName || firstName.length > 80) throw new Error('Enter your first name.');
    if (lastName.length > 80 || phone.length > 30) throw new Error('Please check your profile details.');
    if (password.length < 8 || Buffer.byteLength(password, 'utf8') > 72) throw new Error('Password must be between 8 and 72 characters.');
    if (password !== String(input.password_confirmation || '')) throw new Error('Passwords do not match.');

    const passwordHash = await bcrypt.hash(password, 12);
    try {
        await pool.execute('INSERT INTO users (email, password_hash, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?)', [email, passwordHash, firstName, lastName || null, phone || null]);
    } catch (error) {
        if (error.code === 'ER_DUP_ENTRY') throw new Error('An account with this email already exists.');
        throw error;
    }
    return { email };
}

async function revokeSession(pool, token) {
    if (!token) return;
    const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
    await pool.execute('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE token_hash = ? AND revoked_at IS NULL', [tokenHash]);
}

module.exports = { clientIp, createSession, currentUser, hashPassword, isRateLimited, login, normalizeEmail, register, revokeSession, verifyPassword };