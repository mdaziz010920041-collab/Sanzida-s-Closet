<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

try {
    $connection = database_connection();
    $statement = $connection->prepare("UPDATE products SET discount_type = 'percentage', discount_value = :discount WHERE slug = :slug AND status = 'active'");
    foreach (['demo-structured-shoulder-bag' => 15, 'demo-linen-wrap-blouse' => 10] as $slug => $discount) $statement->execute(['slug' => $slug, 'discount' => $discount]);
    echo '2 demo sale products updated.';
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Sale seed failed.';
}