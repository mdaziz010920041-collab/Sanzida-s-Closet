<?php

declare(strict_types=1);

function seo_slug(string $value): string
{
    $value = trim($value);
    if (function_exists('transliterator_transliterate')) $value = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'item';
}

function seo_absolute_url(string $path = ''): string
{
    $url = base_url($path);
    if (preg_match('~^https?://~i', $url)) return $url;
    if (APP_ENV === 'production' && APP_URL === '') throw new RuntimeException('APP_URL must be configured for production canonical URLs.');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($url, '/');
}

function seo_meta_tags(string $title, string $description, string $canonical, ?string $image = null, string $type = 'website'): string
{
    $tags = '<title>' . escape_html($title) . '</title><meta name="description" content="' . escape_html($description) . '"><link rel="canonical" href="' . escape_html($canonical) . '"><meta property="og:type" content="' . escape_html($type) . '"><meta property="og:site_name" content="' . escape_html(APP_NAME) . '"><meta property="og:title" content="' . escape_html($title) . '"><meta property="og:description" content="' . escape_html($description) . '"><meta property="og:url" content="' . escape_html($canonical) . '"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . escape_html($title) . '"><meta name="twitter:description" content="' . escape_html($description) . '">';
    $verification = trim((string) app_setting('seo.google_site_verification', ''));
    if ($verification !== '') $tags .= '<meta name="google-site-verification" content="' . escape_html($verification) . '">';
    if ($image !== null && $image !== '') $tags .= '<meta property="og:image" content="' . escape_html(seo_absolute_url($image)) . '"><meta name="twitter:image" content="' . escape_html(seo_absolute_url($image)) . '">';
    return $tags;
}

function seo_organization_schema(): array
{
    return ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => APP_NAME, 'url' => seo_absolute_url('/'), 'logo' => seo_absolute_url("Sanzida's Closet_logo.jpg")];
}

function seo_website_schema(): array
{
    return ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => APP_NAME, 'url' => seo_absolute_url('/')];
}

function seo_jsonld(array $schema): string
{
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . '</script>';
}
