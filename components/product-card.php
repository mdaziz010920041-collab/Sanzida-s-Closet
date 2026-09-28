<?php

declare(strict_types=1);

function render_product_card(array $product): void
{
    $imagePath = media_url($product['image_path'] ?? null);
    $imageAlt = (string) ($product['image_alt'] ?? $product['name'] ?? 'Product image');
    $sellingPrice = number_format((float) ($product['selling_price'] ?? $product['base_price'] ?? 0), 2);
    $compareAtPrice = (float) ($product['compare_at_price'] ?? 0);
    $hasDiscount = $compareAtPrice > (float) ($product['selling_price'] ?? $product['base_price'] ?? 0);
    $productUrl = base_url('products/' . rawurlencode((string) $product['slug']) . '/');
    $stock = (int) ($product['available_stock'] ?? 0);
    ?>
    <article class="product-card" data-product-slug="<?= escape_html((string) $product['slug']) ?>">
        <a class="product-card__link" href="<?= escape_html($productUrl) ?>" data-recent-product="<?= escape_html((string) $product['slug']) ?>">
            <div class="product-card__media">
                <?php if ($imagePath !== ''): ?>
                    <img src="<?= escape_html($imagePath) ?>" alt="<?= escape_html($imageAlt) ?>" loading="lazy" decoding="async" width="640" height="800" sizes="(max-width: 767px) 92vw, (max-width: 1024px) 30vw, 23vw">
                <?php else: ?>
                    <div class="catalogue-image-placeholder" aria-label="Product image pending"><span>Image coming soon</span></div>
                <?php endif; ?>
                <?php if (!empty($product['is_featured'])): ?><span class="badge product-card__badge">Featured</span><?php endif; ?>
            </div>
            <div class="product-card__body">
                <?php if (!empty($product['brand_name'])): ?><p class="label"><?= escape_html((string) $product['brand_name']) ?></p><?php endif; ?>
                <h2 class="product-card__title"><?= escape_html((string) $product['name']) ?></h2>
                <div class="product-card__price-row"><span class="product-card__price"><?= escape_html(currency_symbol((string) ($product['currency'] ?? 'INR'))) ?> <?= escape_html($sellingPrice) ?></span><?php if ($hasDiscount): ?><del class="product-card__compare-price"><?= escape_html(currency_symbol((string) ($product['currency'] ?? 'INR'))) ?> <?= escape_html(number_format($compareAtPrice, 2)) ?></del><?php endif; ?></div>
                    <p class="product-card__availability <?= $stock > 0 ? 'is-available' : 'is-unavailable' ?>"><?= $stock > 0 ? 'Available' : 'Currently unavailable' ?></p>
            </div>
        </a>
                <?php if (!empty($product['wishlist_variant_id'])): ?><div class="product-card__action"><button class="link-button" type="button" data-wishlist-action="move_to_cart" data-variant-id="<?= escape_html((string) $product['wishlist_variant_id']) ?>">Move to bag</button></div><?php endif; ?>
    </article>
    <?php
}