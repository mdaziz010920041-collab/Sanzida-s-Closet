<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/cart.php';

$cart = ['items' => [], 'item_count' => 0, 'subtotal' => 0, 'discount' => 0, 'shipping' => 0, 'total' => 0, 'coupon' => null];
$cartError = false;
try {
    $connection = database_connection();
    $user = auth_current_user($connection);
    $cart = cart_summary($connection, $user ? (int) $user['id'] : null);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $cartError = true;
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Your bag | <?= escape_html(APP_NAME) ?></title><meta name="description" content="Review your Sanzida's Closet shopping bag."><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="cart-page" data-cart-page data-cart-server-error="<?= $cartError ? '1' : '0' ?>" data-cart-api="<?= escape_html(base_url('api/cart.php')) ?>" data-wishlist-api="<?= escape_html(base_url('api/wishlist.php')) ?>">
    <?php $page_title = 'Your bag'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="cart-main"><?= csrf_field() ?><section class="cart-heading"><p class="eyebrow">Your edit</p><h1>Your shopping<br><em>bag.</em></h1><p class="muted" data-cart-count><?= escape_html((string) $cart['item_count']) ?> items</p></section>
        <?php if ($cartError): ?><div class="alert alert--soft"><strong>Bag unavailable.</strong><p>Please try again when the cart service is available.</p></div><?php elseif ($cart['items'] === []): ?><section class="empty-state"><p class="eyebrow">Nothing here yet</p><h2>Your bag is waiting.</h2><p>Explore the catalogue and save a piece for later.</p><a class="button" href="<?= escape_html(base_url('products/')) ?>">Browse the edit <span aria-hidden="true">&rarr;</span></a></section><?php else: ?><div class="cart-layout"><section class="cart-items" aria-labelledby="cart-items-title"><h2 class="sr-only" id="cart-items-title">Bag items</h2><div data-cart-page-items><?php foreach ($cart['items'] as $item): ?><article class="cart-page-line" data-cart-line data-variant-id="<?= escape_html((string) $item['variant_id']) ?>"><div class="cart-page-line__image"><?php if (!empty($item['image_path'])): ?><img src="<?= escape_html(media_url($item['image_path'])) ?>" alt="<?= escape_html((string) $item['image_alt']) ?>" width="160" height="200"><?php else: ?><div class="catalogue-image-placeholder"><span>Image coming soon</span></div><?php endif; ?></div><div class="cart-page-line__content"><p class="label"><?= escape_html((string) ($item['size_name'] ?: $item['color_name'] ?: 'Selected piece')) ?></p><h2><?= escape_html((string) $item['name']) ?></h2><p class="muted">SKU: <?= escape_html((string) $item['variant_id']) ?></p><div class="cart-page-line__bottom"><strong><?= escape_html((string) $item['currency']) ?> <?= escape_html(number_format((float) $item['unit_price'], 2)) ?></strong><div class="quantity-control" aria-label="Quantity"><button type="button" data-cart-quantity="decrease" aria-label="Decrease quantity">&minus;</button><span data-cart-quantity-value><?= escape_html((string) $item['quantity']) ?></span><button type="button" data-cart-quantity="increase" aria-label="Increase quantity">+</button></div><button class="link-button" type="button" data-cart-remove>Remove</button></div><p class="cart-line__stock <?= (int) $item['available_stock'] > 0 ? 'is-available' : 'is-unavailable' ?>"><?= (int) $item['available_stock'] > 0 ? escape_html((string) $item['available_stock']) . ' available' : 'Currently unavailable' ?></p></div></article><?php endforeach; ?></div></section><aside class="cart-summary" aria-labelledby="summary-title"><p class="eyebrow">Summary</p><h2 id="summary-title">A considered total.</h2><div class="cart-summary__rows"><div><span>Subtotal</span><strong data-cart-subtotal>₹ <?= escape_html(number_format((float) $cart['subtotal'], 2)) ?></strong></div><div><span>Discount</span><strong data-cart-discount>− ₹ <?= escape_html(number_format((float) $cart['discount'], 2)) ?></strong></div><div><span>Estimated shipping</span><strong data-cart-shipping>₹ <?= escape_html(number_format((float) $cart['shipping'], 2)) ?></strong></div><div class="cart-summary__total"><span>Total</span><strong data-cart-total>₹ <?= escape_html(number_format((float) $cart['total'], 2)) ?></strong></div></div><form class="coupon-form" data-coupon-form><label class="field__label" for="coupon-code">Coupon code</label><div><input class="input" id="coupon-code" name="code" placeholder="Enter code"><button class="button button--outline" type="submit">Apply</button></div><p class="form-message" data-coupon-message aria-live="polite"></p></form><a class="button button--full" href="<?= escape_html(base_url('checkout/')) ?>">Proceed to secure checkout <span aria-hidden="true">&rarr;</span></a><p class="muted cart-summary__note">Shipping is estimated at ₹ 120 below ₹ 5,000 and complimentary above that threshold.</p></aside></div><?php endif; ?>
    </main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
    <script src="<?= escape_html(asset_url('js/cart.js')) ?>" defer></script>
</body>
</html>