<?php

declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_lifetime', '0');
    session_name('sanzidas_closet_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function security_safe_url(string $value, string $default = '#'): string
{
    $value = trim($value);
    if ($value === '' || str_starts_with($value, '//') || str_contains($value, '\\') || str_contains($value, "\0") || preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) return $default;
    return $value[0] === '/' || $value[0] === '#' ? $value : (filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : $default);
}

function security_safe_next(string $value, string $default = '/account/'): string
{
    if ($value === '' || $value[0] !== '/' || str_starts_with($value, '//') || str_contains($value, '\\') || str_contains($value, "\0")) return $default;
    return security_safe_url($value, $default);
}

function csrf_token(): string
{
    start_secure_session();
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function verify_csrf_token(?string $token): bool
{
    start_secure_session();
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escape_html(csrf_token()) . '">';
}
