<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/cart.php';

$headerCart = ['item_count' => 0, 'items' => [], 'subtotal' => 0, 'discount' => 0, 'shipping' => 0, 'total' => 0, 'coupon' => null];
try {
    $headerConnection = database_connection();
    $headerUser = auth_current_user($headerConnection);
    $headerCart = cart_summary($headerConnection, $headerUser ? (int) $headerUser['id'] : null);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}

/** @var string $page_title */
?>
<header class="catalogue-header">
    <a class="brand" href="<?= escape_html(base_url('/')) ?>" aria-label="Sanzida's Closet home">
        <img class="brand-logo" src="<?= escape_html(base_url("Sanzida's Closet_logo.jpg")) ?>" alt="" width="52" height="52">
        <span class="brand-name">Sanzida's Closet</span>
    </a>
    <nav aria-label="Catalogue navigation" class="catalogue-header__nav">
        <a href="<?= escape_html(base_url('/')) ?>">Home</a>
        <a href="<?= escape_html(base_url('products/')) ?>">Shop</a>
        <a href="<?= escape_html(base_url('new-arrivals/')) ?>">New Arrivals</a>
        <a href="<?= escape_html(base_url('categories/')) ?>">Collections</a>
        <a href="<?= escape_html(base_url('products/?category=dresses')) ?>">Dresses</a>
        <a href="<?= escape_html(base_url('products/?category=tops')) ?>">Tops</a>
        <a href="<?= escape_html(base_url('products/?category=accessories')) ?>">Accessories</a>
        <a class="nav-link--sale" href="<?= escape_html(base_url('sale/')) ?>">Sale</a>
    </nav>
    <div class="catalogue-header__actions">
        <a class="text-action" href="<?= escape_html(base_url('products/')) ?>">Browse <span aria-hidden="true">&rarr;</span></a>
        <a class="header-cart-link" href="<?= escape_html(base_url('cart/')) ?>" aria-label="Shopping bag, <?= escape_html((string) $headerCart['item_count']) ?> items">Bag <span class="count-badge"><?= escape_html((string) $headerCart['item_count']) ?></span></a>
    </div>
</header>
<aside class="cart-drawer" data-cart-drawer aria-hidden="true" aria-label="Shopping bag">
    <div class="cart-drawer__header"><p class="label">Your bag</p><button class="icon-button" type="button" data-cart-close aria-label="Close shopping bag"><span class="ui-icon ui-icon--close" aria-hidden="true"></span></button></div>
    <div class="cart-drawer__items" data-cart-items>
        <?php if ($headerCart['items'] === []): ?><p class="muted cart-empty">Your bag is waiting for its first piece.</p><?php else: ?><?php foreach ($headerCart['items'] as $item): ?><div class="cart-line"><div class="cart-line__image"><?php if (!empty($item['image_path'])): ?><img src="<?= escape_html(media_url($item['image_path'])) ?>" alt="<?= escape_html((string) $item['image_alt']) ?>" width="80" height="100"><?php else: ?><span>SC</span><?php endif; ?></div><div class="cart-line__content"><strong><?= escape_html((string) $item['name']) ?></strong><span><?= escape_html((string) ($item['size_name'] ?: $item['color_name'] ?: '')) ?></span><span><?= escape_html(currency_symbol((string) ($item['currency'] ?? 'INR'))) ?> <?= escape_html(number_format((float) $item['unit_price'], 2)) ?> &times; <?= escape_html((string) $item['quantity']) ?></span></div></div><?php endforeach; ?><?php endif; ?>
    </div>
    <div class="cart-drawer__footer"><div class="cart-total-row"><span>Subtotal</span><strong data-cart-subtotal><?= escape_html(currency_symbol((string) ($headerCart['currency'] ?? 'INR'))) ?> <?= escape_html(number_format((float) $headerCart['subtotal'], 2)) ?></strong></div><a class="button" href="<?= escape_html(base_url('cart/')) ?>">View bag <span aria-hidden="true">&rarr;</span></a></div>
</aside>
<div class="cart-backdrop" data-cart-close hidden></div>