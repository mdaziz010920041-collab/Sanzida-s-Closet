<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/catalogue.php';
require_once dirname(__DIR__) . '/components/product-card.php';
require_once dirname(__DIR__) . '/includes/seo.php';

$search = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$brand = trim((string) ($_GET['brand'] ?? ''));
$size = trim((string) ($_GET['size'] ?? ''));
$color = trim((string) ($_GET['color'] ?? ''));
$minPrice = trim((string) ($_GET['min_price'] ?? ''));
$maxPrice = trim((string) ($_GET['max_price'] ?? ''));
$availability = trim((string) ($_GET['availability'] ?? ''));
$discount = trim((string) ($_GET['discount'] ?? ''));
$isNewArrivals = !empty($_GET['new_arrivals']);
$isSale = !empty($_GET['sale']);
$sort = trim((string) ($_GET['sort'] ?? 'newest'));
$sort = $isNewArrivals ? 'newest' : $sort;
$discount = $isSale ? 'on-sale' : $discount;
$page = max(1, (int) ($_GET['page'] ?? 1));
$catalogue = ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => 20, 'pages' => 1];
$categories = [];
$filterOptions = ['sizes' => [], 'colors' => [], 'brands' => []];
$database_error = false;

try {
    $connection = database_connection();
    $catalogue = catalogue_list_products($connection, ['search' => $search, 'category' => $category, 'brand' => $brand, 'size' => $size, 'color' => $color, 'min_price' => $minPrice, 'max_price' => $maxPrice, 'availability' => $availability, 'discount' => $discount, 'sort' => $sort, 'page' => $page]);
    $categories = catalogue_list_categories($connection);
    $filterOptions = catalogue_filter_options($connection);
} catch (Throwable $exception) {
    $database_error = false;
    $catalogue = catalogue_demo_catalogue_data(['sort' => $sort, 'page' => $page, 'per_page' => 20]);
    $categories = catalogue_demo_categories();
    $filterOptions = catalogue_demo_filter_options();
    error_log($exception->getMessage());
}

$page_title = $isNewArrivals ? 'New Arrivals | ' . APP_NAME : ($isSale ? 'Sale | ' . APP_NAME : ($category !== '' ? ucfirst(str_replace('-', ' ', $category)) . ' | ' . APP_NAME : 'Shop | ' . APP_NAME));
$page_description = $isNewArrivals ? 'Discover the newest women\'s fashion pieces from ' . APP_NAME . '.' : ($isSale ? 'Shop special offers and considered women\'s fashion pieces on sale at ' . APP_NAME . '.' : ($category !== '' ? 'Explore ' . ucfirst(str_replace('-', ' ', $category)) . ' from ' . APP_NAME . '.' : 'Shop the curated ' . APP_NAME . ' catalogue.'));
$catalogue_canonical = $isNewArrivals ? seo_absolute_url('new-arrivals/') : ($isSale ? seo_absolute_url('sale/') : ($category !== '' ? seo_absolute_url('products/?category=' . rawurlencode($category)) : seo_absolute_url('products/')));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= seo_meta_tags($page_title, $page_description, $catalogue_canonical) ?>
    <?= seo_jsonld(['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $page_title, 'url' => $catalogue_canonical]) ?>
    <link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>">
</head>
<body class="catalogue-page">
    <?php require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="catalogue-main">
        <section class="catalogue-heading" aria-labelledby="catalogue-title">
            <p class="eyebrow"><?= $isNewArrivals ? 'Just in' : ($isSale ? 'Special offers' : 'The catalogue') ?></p>
            <h1 id="catalogue-title"><?= $isNewArrivals ? 'New arrivals' : ($isSale ? 'Sale' : ($category !== '' ? escape_html(ucfirst(str_replace('-', ' ', $category))) : 'Shop')) ?></h1>
            <p><?= $isNewArrivals ? 'The latest pieces, newly added to the Sanzida\'s Closet edit.' : ($isSale ? 'Thoughtful pieces, now at a little less.' : 'Thoughtful pieces, presented as they arrive from the catalogue.') ?></p>
        </section>
        <section class="catalogue-toolbar" aria-label="Catalogue controls">
            <form class="catalogue-search" method="get" action="<?= escape_html(base_url('products/')) ?>">
                <label class="sr-only" for="catalogue-query">Search products by name or SKU</label>
                <input class="input" id="catalogue-query" type="search" name="q" value="<?= escape_html($search) ?>" placeholder="Search name or SKU" autocomplete="off" data-search-input data-suggestions-url="<?= escape_html(base_url('api/search-suggestions.php')) ?>">
                <button class="button" type="submit">Search</button>
                <div class="search-suggestions" data-search-suggestions hidden></div>
            </form>
            <div class="catalogue-toolbar__controls"><button class="button button--outline filter-toggle" type="button" data-filter-open aria-controls="catalogue-filters" aria-expanded="false">Filters <span aria-hidden="true">+</span></button><label class="sr-only" for="catalogue-sort">Sort products</label><select class="select" id="catalogue-sort" name="sort" form="catalogue-filter-form" onchange="this.form.submit()"><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option><option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option><option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popularity</option><option value="price-low" <?= $sort === 'price-low' ? 'selected' : '' ?>>Price: low to high</option><option value="price-high" <?= $sort === 'price-high' ? 'selected' : '' ?>>Price: high to low</option></select></div>
        </section>
        <div class="filter-backdrop" data-filter-close hidden></div>
        <aside class="filter-drawer" id="catalogue-filters" aria-label="Filter products" aria-hidden="true">
            <div class="filter-drawer__header"><p class="label">Refine the edit</p><button class="icon-button" type="button" data-filter-close aria-label="Close filters"><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button></div>
            <form id="catalogue-filter-form" class="filter-form" method="get" action="<?= escape_html(base_url('products/')) ?>">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= escape_html($search) ?>"><?php endif; ?>
                <div class="filter-group"><label class="field__label" for="filter-category">Category</label><select class="select" id="filter-category" name="category"><option value="">All categories</option><?php foreach ($categories as $item): ?><option value="<?= escape_html((string) $item['slug']) ?>" <?= $category === $item['slug'] ? 'selected' : '' ?>><?= escape_html((string) $item['name']) ?></option><?php endforeach; ?></select></div>
                <div class="filter-group"><label class="field__label" for="filter-brand">Brand</label><select class="select" id="filter-brand" name="brand"><option value="">All brands</option><?php foreach ($filterOptions['brands'] as $item): ?><option value="<?= escape_html((string) $item['slug']) ?>" <?= $brand === $item['slug'] ? 'selected' : '' ?>><?= escape_html((string) $item['name']) ?></option><?php endforeach; ?></select></div>
                <div class="filter-group"><span class="field__label">Size</span><div class="filter-options"><?php foreach ($filterOptions['sizes'] as $item): ?><label><input type="radio" name="size" value="<?= escape_html((string) $item['id']) ?>" <?= $size === (string) $item['id'] ? 'checked' : '' ?>><span><?= escape_html((string) ($item['name'] ?: $item['code'])) ?></span></label><?php endforeach; ?></div></div>
                <div class="filter-group"><span class="field__label">Color</span><div class="filter-options filter-options--colors"><?php foreach ($filterOptions['colors'] as $item): ?><label title="<?= escape_html((string) $item['name']) ?>"><input type="radio" name="color" value="<?= escape_html((string) $item['id']) ?>" <?= $color === (string) $item['id'] ? 'checked' : '' ?>><span style="--swatch: <?= escape_html((string) ($item['hex_code'] ?: '#D79E75')) ?>"><span class="sr-only"><?= escape_html((string) $item['name']) ?></span></span></label><?php endforeach; ?></div></div>
                <div class="filter-group"><span class="field__label">Price range</span><div class="price-fields"><label><span class="sr-only">Minimum price</span><input class="input" type="number" min="0" step="0.01" name="min_price" value="<?= escape_html($minPrice) ?>" placeholder="Min"></label><label><span class="sr-only">Maximum price</span><input class="input" type="number" min="0" step="0.01" name="max_price" value="<?= escape_html($maxPrice) ?>" placeholder="Max"></label></div></div>
                <div class="filter-group"><span class="field__label">Availability</span><label class="check-option"><input type="checkbox" name="availability" value="in-stock" <?= $availability === 'in-stock' ? 'checked' : '' ?>> In stock only</label></div>
                <div class="filter-group"><span class="field__label">Offers</span><label class="check-option"><input type="checkbox" name="discount" value="on-sale" <?= $discount === 'on-sale' ? 'checked' : '' ?>> On sale</label></div>
                <input type="hidden" name="sort" value="<?= escape_html($sort) ?>"><div class="filter-actions"><button class="button" type="submit">Apply filters</button><a class="button button--outline" href="<?= escape_html(base_url('products/')) ?>">Clear all</a></div>
            </form>
        </aside>
        <?php if ($categories !== []): ?><nav class="category-filter" aria-label="Filter by category"><a class="<?= $category === '' ? 'is-active' : '' ?>" href="<?= escape_html(base_url('products/')) ?>">All pieces</a><?php foreach ($categories as $item): ?><a class="<?= $category === $item['slug'] ? 'is-active' : '' ?>" href="<?= escape_html(base_url('products/?category=' . rawurlencode((string) $item['slug']))) ?>"><?= escape_html((string) $item['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
        <?php if ($database_error): ?>
            <div class="alert alert--soft catalogue-state"><strong>Catalogue unavailable.</strong><p>Product data will appear when the configured MySQL connection is available.</p></div>
        <?php elseif ($catalogue['items'] === []): ?>
            <div class="empty-state"><p class="eyebrow">No pieces found</p><h2>The edit is still taking shape.</h2><p>Try another search or return to the full catalogue.</p><a class="button" href="<?= escape_html(base_url('products/')) ?>">View all pieces</a></div>
        <?php else: ?>
            <div class="catalogue-results"><p class="muted" aria-live="polite"><?= escape_html((string) $catalogue['total']) ?> <?= $catalogue['total'] === 1 ? 'piece' : 'pieces' ?></p><div class="product-grid product-grid--catalogue"><?php foreach ($catalogue['items'] as $product) { render_product_card($product); } ?></div></div>
            <?php if ($catalogue['pages'] > 1): ?><nav class="pagination" aria-label="Catalogue pages"><?php for ($index = 1; $index <= $catalogue['pages']; $index++): ?><a class="<?= $index === $catalogue['page'] ? 'is-active' : '' ?>" href="<?= escape_html(base_url('products/?' . query_string(['q' => $search, 'category' => $category, 'brand' => $brand, 'sort' => $sort, 'page' => $index]))) ?>" aria-label="Page <?= $index ?>"><?= $index ?></a><?php endfor; ?></nav><?php endif; ?>
        <?php endif; ?>
    </main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
    <script src="<?= escape_html(asset_url('js/catalogue.js')) ?>" defer></script>
</body>
</html>