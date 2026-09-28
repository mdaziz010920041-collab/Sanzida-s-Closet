<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/catalogue.php';
require_once dirname(__DIR__) . '/components/product-card.php';
require_once dirname(__DIR__) . '/includes/seo.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$product = null;
$related = [];
$database_error = false;

try {
    if ($slug !== '') {
        $connection = database_connection();
        $product = catalogue_find_product($connection, $slug);
        if ($product !== null) {
            $related = catalogue_related_products($connection, (int) $product['id']);
        }
    }
} catch (Throwable $exception) {
    $database_error = false;
    $product = catalogue_demo_product_by_slug($slug);
    if ($product !== null) {
        $related = array_values(array_filter(catalogue_seed_demo_products(4), static fn (array $item): bool => ($item['slug'] ?? '') !== $slug));
    }
    error_log($exception->getMessage());
}

if ($product === null) {
    http_response_code($database_error ? 503 : 404);
}

$page_title = $product !== null ? (string) ($product['meta_title'] ?: $product['name'] . ' | ' . APP_NAME) : 'Product unavailable | ' . APP_NAME;
$page_description = $product !== null ? (string) ($product['meta_description'] ?: $product['short_description'] ?: 'Discover this piece from ' . APP_NAME . '.') : 'The requested product is unavailable.';
$canonical = $product !== null && !empty($product['canonical_url']) ? (string) $product['canonical_url'] : seo_absolute_url('products/' . rawurlencode($slug) . '/');
$images = $product['images'] ?? [];
$firstImage = $images[0] ?? null;
$variants = $product['variants'] ?? [];
$sizes = [];
$colors = [];
$totalStock = 0;
foreach ($variants as $variant) {
    $totalStock += (int) ($variant['available_stock'] ?? 0);
    if (!empty($variant['size_id'])) {
        $sizes[(string) $variant['size_id']] = $variant;
    }
    if (!empty($variant['color_id'])) {
        $colors[(string) $variant['color_id']] = $variant;
    }
}
$basePrice = (float) ($product['base_price'] ?? 0);
$sellingPrice = $basePrice;
if (($product['discount_type'] ?? 'none') === 'percentage') {
    $sellingPrice = max(0, $basePrice - ($basePrice * (float) $product['discount_value'] / 100));
} elseif (($product['discount_type'] ?? 'none') === 'fixed') {
    $sellingPrice = max(0, $basePrice - (float) $product['discount_value']);
}
$structuredData = null;
if (!empty($product['structured_data'])) {
    $structuredData = is_string($product['structured_data']) ? json_decode($product['structured_data'], true) : $product['structured_data'];
}
if (!is_array($structuredData)) {
    $structuredData = [
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product['name'] ?? '',
        'description' => $page_description, 'sku' => $variants[0]['sku'] ?? null,
        'brand' => ['@type' => 'Brand', 'name' => $product['brand_name'] ?? APP_NAME],
        'image' => array_values(array_filter(array_map(static fn (array $image): string => seo_absolute_url((string) $image['file_path']), $images))),
        'offers' => ['@type' => 'Offer', 'priceCurrency' => $product['currency'] ?? 'INR', 'price' => number_format($sellingPrice, 2, '.', ''), 'availability' => $totalStock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => $canonical],
    ];
    if ((int) ($product['review_count'] ?? 0) > 0 && $product['rating_value'] !== null) $structuredData['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => number_format((float) $product['rating_value'], 1, '.', ''), 'reviewCount' => (int) $product['review_count']];
}
if ((int) ($product['review_count'] ?? 0) === 0 && is_array($structuredData)) unset($structuredData['aggregateRating']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= seo_meta_tags($page_title, $page_description, $canonical, $firstImage ? media_url($firstImage['file_path']) : null, 'product') ?>
    <?php if (!empty($product['meta_keywords'])): ?><meta name="keywords" content="<?= escape_html((string) $product['meta_keywords']) ?>"><?php endif; ?>
    <link rel="canonical" href="<?= escape_html($canonical) ?>">
    <link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>">
    <?php if ($product !== null): ?><?= seo_jsonld($structuredData) ?><?= seo_jsonld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => seo_absolute_url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Shop', 'item' => seo_absolute_url('products/')], ['@type' => 'ListItem', 'position' => 3, 'name' => $product['name'], 'item' => $canonical]]]) ?><?php endif; ?>
</head>
    <body class="catalogue-page product-detail-page" data-cart-api="<?= escape_html(base_url('api/cart.php')) ?>" data-wishlist-api="<?= escape_html(base_url('api/wishlist.php')) ?>" data-product-view='<?= escape_html(json_encode(['slug' => $product['slug'] ?? '', 'name' => $product['name'] ?? '', 'price' => $sellingPrice, 'currency' => $product['currency'] ?? 'INR', 'url' => seo_absolute_url('products/' . rawurlencode((string) ($product['slug'] ?? '')) . '/'), 'image' => $firstImage ? media_url($firstImage['file_path']) : ''], JSON_THROW_ON_ERROR)) ?>'>
    <?php $page_title = 'Product'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="product-detail-main">
        <?php if ($database_error): ?>
            <div class="empty-state"><p class="eyebrow">Catalogue unavailable</p><h1>We could not load this piece.</h1><p>Product data will appear when the configured MySQL connection is available.</p><a class="button" href="<?= escape_html(base_url('products/')) ?>">Return to catalogue</a></div>
        <?php elseif ($product === null): ?>
            <div class="empty-state"><p class="eyebrow">404 / not found</p><h1>This piece has moved on.</h1><p>The requested product does not exist or is not currently published.</p><a class="button" href="<?= escape_html(base_url('products/')) ?>">Browse the catalogue</a></div>
        <?php else: ?>
            <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= escape_html(base_url('/')) ?>">Home</a><span aria-hidden="true">/</span><a href="<?= escape_html(base_url('products/')) ?>">Shop</a><span aria-hidden="true">/</span><span aria-current="page"><?= escape_html((string) $product['name']) ?></span></nav>
            <section class="product-detail" aria-labelledby="product-title">
                <div class="product-gallery">
                    <div class="product-gallery__main">
                        <?php if ($firstImage): ?><button class="product-gallery__zoom" type="button" data-gallery-zoom aria-label="Zoom product image"><img id="product-main-image" src="<?= escape_html(media_url($firstImage['file_path'])) ?>" alt="<?= escape_html((string) $firstImage['alt_text']) ?>" width="1000" height="1250" fetchpriority="high" decoding="async"></button><?php else: ?><div class="catalogue-image-placeholder catalogue-image-placeholder--large"><span>Product imagery coming soon</span></div><?php endif; ?>
                    </div>
                    <?php if (count($images) > 1): ?><div class="product-gallery__thumbs" aria-label="Product images"><?php foreach ($images as $index => $image): ?><button class="gallery-thumb <?= $index === 0 ? 'is-active' : '' ?>" type="button" data-gallery-thumb data-gallery-src="<?= escape_html(media_url($image['file_path'])) ?>" data-gallery-alt="<?= escape_html((string) $image['alt_text']) ?>"><img src="<?= escape_html(media_url($image['file_path'])) ?>" alt="" loading="lazy" decoding="async" width="120" height="150"></button><?php endforeach; ?></div><?php endif; ?>
                    <?php if (!empty($product['videos'])): ?><div class="product-video-list"><p class="label">Product video</p><?php foreach ($product['videos'] as $video): ?><a class="link" href="<?= escape_html((string) $video['video_url']) ?>" target="_blank" rel="noopener noreferrer"><?= escape_html((string) ($video['alt_text'] ?: 'Watch product video')) ?> &nearr;</a><?php endforeach; ?></div><?php endif; ?>
                </div>
                <div class="product-detail__info">
                    <?php if (!empty($product['brand_name'])): ?><p class="eyebrow"><?= escape_html((string) $product['brand_name']) ?></p><?php endif; ?>
                    <h1 id="product-title"><?= escape_html((string) $product['name']) ?></h1>
                    <p class="product-detail__short"><?= escape_html((string) ($product['short_description'] ?? '')) ?></p>
                    <div class="product-detail__price"><strong data-product-price><?= escape_html(currency_symbol((string) ($product['currency'] ?? 'INR'))) ?> <?= escape_html(number_format($sellingPrice, 2)) ?></strong><?php if ((float) ($product['compare_at_price'] ?? 0) > $sellingPrice): ?><del><?= escape_html(currency_symbol((string) ($product['currency'] ?? 'INR'))) ?> <?= escape_html(number_format((float) $product['compare_at_price'], 2)) ?></del><?php endif; ?></div>
                    <p class="product-stock <?= $totalStock > 0 ? 'is-available' : 'is-unavailable' ?>" data-stock-message><?= $totalStock > 0 ? 'In stock' : 'Currently unavailable' ?></p>
                    <div class="product-description"><?= nl2br(escape_html((string) ($product['description'] ?? ''))) ?></div>
                    <?php if ($variants !== []): ?><form class="variant-form" data-variant-form data-cart-form data-default-price="<?= escape_html(number_format($sellingPrice, 2, '.', '')) ?>" data-currency="<?= escape_html((string) ($product['currency'] ?? 'INR')) ?>" data-variants='<?= escape_html(json_encode($variants, JSON_THROW_ON_ERROR)) ?>' action="<?= escape_html(base_url('api/cart.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="variant_id" data-selected-variant><div class="variant-group" data-variant-group="size"><div class="variant-group__header"><span class="label">Size</span><span class="muted" data-selected-size>Select a size</span></div><div class="variant-options"><?php foreach ($sizes as $size): ?><button class="size-option" type="button" data-size-id="<?= escape_html((string) $size['size_id']) ?>"><?= escape_html((string) ($size['size_name'] ?: $size['size_code'])) ?></button><?php endforeach; ?></div></div><div class="variant-group" data-variant-group="color"><div class="variant-group__header"><span class="label">Colour</span><span class="muted" data-selected-color>Select a colour</span></div><div class="variant-options color-options"><?php foreach ($colors as $color): ?><button class="color-option" type="button" data-color-id="<?= escape_html((string) $color['color_id']) ?>" style="--swatch: <?= escape_html((string) ($color['hex_code'] ?: '#D79E75')) ?>"><span class="sr-only"><?= escape_html((string) $color['color_name']) ?></span></button><?php endforeach; ?></div></div><div class="quantity-control quantity-control--detail"><label class="field__label" for="product-quantity">Quantity</label><button type="button" data-detail-quantity="decrease" aria-label="Decrease quantity">&minus;</button><input id="product-quantity" name="quantity" type="number" min="1" value="1" inputmode="numeric"><button type="button" data-detail-quantity="increase" aria-label="Increase quantity">+</button></div><p class="variant-sku muted" data-variant-sku>Choose available options to see the SKU.</p><p class="form-message" data-cart-message aria-live="polite"></p><div class="product-actions"><button class="button" type="submit" data-add-to-cart disabled>Add to bag <span aria-hidden="true">&rarr;</span></button><button class="button button--outline is-disabled" type="button" data-wishlist-action data-variant-id="" aria-label="Save to wishlist" disabled>Save to wishlist</button></div></form><?php endif; ?>
                    <?php if (!empty($product['categories'])): ?><div class="product-tags"><?php foreach ($product['categories'] as $category): ?><a class="badge" href="<?= escape_html(base_url('products/?category=' . rawurlencode((string) $category['slug']))) ?>"><?= escape_html((string) $category['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
                </div>
            </section>
            <?php if ($related !== []): ?><section class="related-products" aria-labelledby="related-title"><div class="section__header"><div><p class="eyebrow">You may also like</p><h2 id="related-title">Related pieces</h2></div></div><div class="product-grid product-grid--catalogue"><?php foreach ($related as $relatedProduct) { render_product_card($relatedProduct); } ?></div></section><?php endif; ?>
            <section class="recent-products" data-recent-container hidden aria-labelledby="recent-title"><div class="section__header"><div><p class="eyebrow">From your visit</p><h2 id="recent-title">Recently viewed</h2></div></div><div class="product-grid product-grid--catalogue" data-recent-grid></div></section>
        <?php endif; ?>
    </main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
    <dialog class="image-zoom-dialog" data-image-dialog><button class="icon-button" type="button" data-image-dialog-close aria-label="Close enlarged image"><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button><button class="image-zoom-dialog__arrow image-zoom-dialog__arrow--prev" type="button" data-image-dialog-prev aria-label="Previous product image">&larr;</button><img data-image-dialog-image alt=""><button class="image-zoom-dialog__arrow image-zoom-dialog__arrow--next" type="button" data-image-dialog-next aria-label="Next product image">&rarr;</button></dialog>
    <script src="<?= escape_html(asset_url('js/catalogue.js')) ?>" defer></script><script src="<?= escape_html(asset_url('js/cart.js')) ?>" defer></script>
</body>
</html>