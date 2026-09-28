<?php

declare(strict_types=1);

function escape_html(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_setting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        if (!function_exists('database_connection')) return $cache[$key] = $default;
        $statement = database_connection()->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();
        if ($value === false) return $cache[$key] = $default;
        $decoded = json_decode((string) $value, true);
        if ($decoded === '') return $cache[$key] = $default;
        return $cache[$key] = $decoded === null && $value !== 'null' ? $value : $decoded;
    } catch (Throwable) {
        return $cache[$key] = $default;
    }
}

function base_url(string $path = ''): string
{
    $base = APP_URL !== '' ? APP_URL : '';

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/';
        $scriptDir = trim(dirname(str_replace('\\', '/', rawurldecode((string) $scriptPath))), '/');

        if ($scriptDir !== '' && $scriptDir !== '.' && $scriptDir !== '/') {
            $base = $scheme . '://' . $host . '/' . implode('/', array_map('rawurlencode', explode('/', $scriptDir)));
        } else {
            $base = $scheme . '://' . $host;
        }
    }

    $base = rtrim($base, '/');
    $relativePath = ltrim($path, '/');
    $suffixPosition = strcspn($relativePath, '?#');
    $pathPart = substr($relativePath, 0, $suffixPosition);
    $suffix = substr($relativePath, $suffixPosition);

    if ($pathPart === '') {
        return $base === '' ? '/' : $base . '/';
    }

    $encodedSegments = array_map(
        static fn (string $segment): string => rawurlencode(str_replace('%2F', '/', $segment)),
        explode('/', $pathPart)
    );

    return $base . '/' . implode('/', $encodedSegments) . $suffix;
}

function asset_url(string $path): string
{
    $asset = base_url('assets/' . ltrim($path, '/'));
    return (str_starts_with($path, 'css/') || str_starts_with($path, 'js/')) ? $asset . '?v=20260925' : $asset;
}

function media_url(?string $path): string
{
    if ($path === null || trim($path) === '') {
        return '';
    }

    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }

    return base_url(rawurldecode(ltrim($path, '/')));
}

function query_string(array $values): string
{
    return http_build_query(array_filter($values, static fn (mixed $value): bool => $value !== null && $value !== ''));
}

function request_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function normalize_currency_code(?string $currency): string
{
    $code = strtoupper(trim((string) ($currency ?? 'INR')));
    if ($code === '' || $code === 'BDT') {
        return 'INR';
    }

    return $code;
}

function currency_symbol(?string $currency): string
{
    return match (normalize_currency_code($currency)) {
        'INR', 'BDT' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        default => normalize_currency_code($currency),
    };
}

function format_money_value(?string $currency, float $amount): string
{
    return currency_symbol($currency) . ' ' . number_format($amount, 2);
}
