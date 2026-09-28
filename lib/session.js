const crypto = require('node:crypto');

const SESSION_COOKIE = 'sc_session';
const CSRF_COOKIE = 'sc_csrf';

function secret() {
    const value = process.env.SESSION_SECRET || '';
    if (value.length < 32) throw new Error('SESSION_SECRET must contain at least 32 characters.');
    return value;
}

function signature(value) {
    return crypto.createHmac('sha256', secret()).update(value).digest('base64url');
}

function cookies(request) {
    const result = {};
    for (const part of String(request.headers.cookie || '').split(';')) {
        const separator = part.indexOf('=');
        if (separator < 0) continue;
        const key = part.slice(0, separator).trim();
        try {
            result[key] = decodeURIComponent(part.slice(separator + 1).trim());
        } catch {}
    }
    return result;
}

function appendCookie(response, value) {
    const current = response.getHeader('Set-Cookie');
    response.setHeader('Set-Cookie', current ? [...[].concat(current), value] : value);
}

function cookieOptions(request, maxAge, httpOnly) {
    const secure = process.env.NODE_ENV === 'production' || request.headers['x-forwarded-proto'] === 'https';
    return `Path=/; SameSite=Lax; Max-Age=${maxAge}${httpOnly ? '; HttpOnly' : ''}${secure ? '; Secure' : ''}`;
}

function readSignedToken(request) {
    const raw = cookies(request)[SESSION_COOKIE] || '';
    const separator = raw.lastIndexOf('.');
    if (separator < 0) return null;
    const token = raw.slice(0, separator);
    const provided = raw.slice(separator + 1);
    if (!/^[a-f0-9]{64}$/.test(token)) return null;
    const expected = signature(token);
    const left = Buffer.from(provided);
    const right = Buffer.from(expected);
    return left.length === right.length && crypto.timingSafeEqual(left, right) ? token : null;
}

function writeSessionToken(request, response, token) {
    appendCookie(response, `${SESSION_COOKIE}=${encodeURIComponent(`${token}.${signature(token)}`)}; ${cookieOptions(request, 60 * 60 * 24 * 30, true)}`);
}

function clearSessionCookies(request, response) {
    appendCookie(response, `${SESSION_COOKIE}=; ${cookieOptions(request, 0, true)}`);
    appendCookie(response, `${CSRF_COOKIE}=; ${cookieOptions(request, 0, false)}`);
}

function ensureSessionToken(request, response) {
    const existing = readSignedToken(request);
    if (existing) return existing;
    const token = crypto.randomBytes(32).toString('hex');
    writeSessionToken(request, response, token);
    return token;
}

function ensureCsrfToken(request, response, sessionToken) {
    const existing = cookies(request)[CSRF_COOKIE] || '';
    const separator = existing.lastIndexOf('.');
    if (separator > 0) {
        const token = existing.slice(0, separator);
        const provided = Buffer.from(existing.slice(separator + 1));
        const expected = Buffer.from(signature(`${sessionToken}:${token}`));
        if (/^[a-f0-9]{64}$/.test(token) && provided.length === expected.length && crypto.timingSafeEqual(provided, expected)) return token;
    }

    const token = crypto.randomBytes(32).toString('hex');
    appendCookie(response, `${CSRF_COOKIE}=${encodeURIComponent(`${token}.${signature(`${sessionToken}:${token}`)}`)}; ${cookieOptions(request, 60 * 60 * 24 * 30, false)}`);
    return token;
}

function verifyCsrfToken(request, submittedToken, sessionToken) {
    const cookie = cookies(request)[CSRF_COOKIE] || '';
    const separator = cookie.lastIndexOf('.');
    if (separator < 0 || typeof submittedToken !== 'string') return false;
    const token = cookie.slice(0, separator);
    const provided = Buffer.from(cookie.slice(separator + 1));
    const expected = Buffer.from(signature(`${sessionToken}:${token}`));
    const submitted = Buffer.from(submittedToken);
    const expectedToken = Buffer.from(token);
    return /^[a-f0-9]{64}$/.test(token)
        && provided.length === expected.length
        && submitted.length === expectedToken.length
        && crypto.timingSafeEqual(provided, expected)
        && crypto.timingSafeEqual(submitted, expectedToken);
}

module.exports = { clearSessionCookies, ensureCsrfToken, ensureSessionToken, readSignedToken, verifyCsrfToken, writeSessionToken };