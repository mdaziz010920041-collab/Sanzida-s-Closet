<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($term) < 2) {
    echo json_encode(['items' => []], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $statement = database_connection()->prepare("SELECT DISTINCT p.name, p.slug, variant.sku FROM products p LEFT JOIN product_variants variant ON variant.product_id = p.id AND variant.status = 'active' WHERE p.status = 'active' AND (p.name LIKE :name_term OR variant.sku LIKE :sku_term) ORDER BY p.is_featured DESC, p.published_at DESC, p.name ASC LIMIT 8");
    $searchTerm = '%' . mb_substr($term, 0, 80) . '%';
    $statement->execute(['name_term' => $searchTerm, 'sku_term' => $searchTerm]);
    $items = array_map(static fn (array $item): array => [
        'name' => $item['name'],
        'slug' => $item['slug'],
        'sku' => $item['sku'],
        'url' => base_url('products/' . rawurlencode((string) $item['slug']) . '/'),
    ], $statement->fetchAll());
    echo json_encode(['items' => $items], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    echo json_encode(['items' => [], 'error' => 'Search is temporarily unavailable.'], JSON_THROW_ON_ERROR);
}