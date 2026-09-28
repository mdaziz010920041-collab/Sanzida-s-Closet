<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/seo.php';
header('Content-Type: application/xml; charset=utf-8');
$urls = [seo_absolute_url('/'), seo_absolute_url('products/'), seo_absolute_url('categories/')];
try {
    $connection = database_connection();
    foreach ($connection->query("SELECT slug FROM products WHERE status = 'active'")->fetchAll() as $row) $urls[] = seo_absolute_url('products/' . rawurlencode((string) $row['slug']) . '/');
    foreach ($connection->query("SELECT slug FROM categories WHERE status = 'active'")->fetchAll() as $row) $urls[] = seo_absolute_url('products/?category=' . rawurlencode((string) $row['slug']));
} catch (Throwable $exception) { error_log($exception->getMessage()); }
?><<?= '?xml version="1.0" encoding="UTF-8"?' ?>><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><?php foreach (array_unique($urls) as $url): ?><url><loc><?= escape_html($url) ?></loc></url><?php endforeach; ?></urlset>
