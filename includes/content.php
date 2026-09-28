<?php

declare(strict_types=1);

function content_setting(PDO $connection, string $key, array $default = []): array
{
    $statement = $connection->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key AND is_public = 1 LIMIT 1');
    $statement->execute(['setting_key' => $key]);
    $value = $statement->fetchColumn();
    if ($value === false || $value === null) return $default;
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? array_merge($default, $decoded) : $default;
}

function content_page(PDO $connection, string $slug): array
{
    $statement = $connection->prepare("SELECT id, title, slug, content, status FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1");
    $statement->execute(['slug' => $slug]);
    return $statement->fetch() ?: [];
}

function content_banners(PDO $connection, string $placement): array
{
    $statement = $connection->prepare("SELECT id, title, subtitle, image_path, mobile_image_path, link_url, alt_text, sort_order FROM banners WHERE placement = :placement AND status = 'active' AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP()) ORDER BY sort_order, id");
    $statement->execute(['placement' => $placement]);
    return $statement->fetchAll();
}
