<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/catalogue.php';
require_once dirname(__DIR__) . '/includes/seo.php';

$categories = [];
$database_error = false;
try {
    $categories = catalogue_list_categories(database_connection());
} catch (Throwable $exception) {
    $database_error = false;
    $categories = catalogue_demo_categories();
    error_log($exception->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= seo_meta_tags('Categories | ' . APP_NAME, 'Browse ' . APP_NAME . ' categories.', seo_absolute_url('categories/')) ?>
    <?= seo_jsonld(['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'Categories | ' . APP_NAME, 'url' => seo_absolute_url('categories/')]) ?>
    <link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>">
</head>
<body class="catalogue-page">
    <?php $page_title = 'Categories'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="catalogue-main">
        <section class="catalogue-heading" aria-labelledby="categories-title"><p class="eyebrow">Browse by feeling</p><h1 id="categories-title">The collections</h1><p>Explore categories populated from the active catalogue.</p></section>
        <?php if ($database_error): ?><div class="alert alert--soft catalogue-state"><strong>Categories unavailable.</strong><p>Category data will appear when the configured MySQL connection is available.</p></div><?php elseif ($categories === []): ?><div class="empty-state"><p class="eyebrow">Coming soon</p><h2>Our categories are taking shape.</h2><p>Active catalogue categories will appear here once they are added.</p></div><?php else: ?><div class="category-list-grid"><?php foreach ($categories as $category): ?><a class="category-list-card" href="<?= escape_html(base_url('products/?category=' . rawurlencode((string) $category['slug']))) ?>"><?php if (!empty($category['image_path'])): ?><img src="<?= escape_html(media_url($category['image_path'])) ?>" alt="<?= escape_html((string) $category['name']) ?>" loading="lazy" width="800" height="600"><?php else: ?><div class="catalogue-image-placeholder"><span>Category image coming soon</span></div><?php endif; ?><div><p class="label"><?= escape_html((string) $category['product_count']) ?> pieces</p><h2><?= escape_html((string) $category['name']) ?></h2><span class="text-action">Explore <span aria-hidden="true">&rarr;</span></span></div></a><?php endforeach; ?></div><?php endif; ?>
    </main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
</body>
</html>