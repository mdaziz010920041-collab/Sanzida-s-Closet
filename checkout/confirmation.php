<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/orders.php';

$order = null;
try {
    $connection = database_connection();
    $user = auth_current_user($connection);
    $order = order_find_for_view($connection, trim((string) ($_GET['order'] ?? '')), $user ? (int) $user['id'] : null);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}
if ($order === null) http_response_code(404);
$deliveryAddress = $order !== null ? json_decode((string) $order['shipping_address_snapshot'], true) : null;
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Order confirmation | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="checkout-page" data-payment-api="<?= escape_html(base_url('api/payment.php')) ?>" data-order-number="<?= escape_html((string) ($_GET['order'] ?? '')) ?>">
    <?php $page_title = 'Order confirmation'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="confirmation-main"><?php if ($order === null): ?><section class="empty-state"><p class="eyebrow">Order unavailable</p><h1>We could not find that order.</h1><p>It may have expired or you may need to sign in to view it.</p><a class="button" href="<?= escape_html(base_url('products/')) ?>">Return to the edit</a></section><?php else: ?><section class="confirmation-hero"><p class="eyebrow">Order received</p><h1>Thank you for<br><em>your order.</em></h1><p>Your order number is <strong><?= escape_html((string) $order['order_number']) ?></strong>. We have reserved your pieces and will update you as the order moves forward.</p></section><section class="order-detail-card"><div class="order-detail-card__header"><div><p class="label">Order status</p><span class="badge"><?= escape_html((string) $order['status']) ?></span></div><div><p class="label">Payment status</p><span class="badge"><?= escape_html((string) ($order['payment']['status'] ?? 'pending')) ?></span></div><div><p class="label">Shipping status</p><span class="badge"><?= escape_html((string) ($order['shipment']['status'] ?? 'pending')) ?></span></div></div><div class="order-detail-items"><?php foreach ($order['items'] as $item): ?><div class="order-detail-item"><div><strong><?= escape_html((string) $item['product_name']) ?></strong><span><?= escape_html((string) ($item['variant_description'] ?? '')) ?> &times; <?= escape_html((string) $item['quantity']) ?></span></div><strong><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $item['line_total'], 2)) ?></strong></div><?php endforeach; ?></div><div class="cart-summary__rows"><div><span>Subtotal</span><strong><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['subtotal'], 2)) ?></strong></div><div><span>Discount</span><strong>− <?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['discount_total'], 2)) ?></strong></div><div><span>Shipping</span><strong><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['shipping_total'], 2)) ?></strong></div><div class="cart-summary__total"><span>Total</span><strong><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['grand_total'], 2)) ?></strong></div></div><div class="confirmation-address"><p class="label">Delivery address</p><address><?= nl2br(escape_html((string) $order['shipping_address_snapshot'])) ?></address></div></section><div class="confirmation-actions"><a class="button" href="<?= escape_html(base_url('orders/view.php?order=' . rawurlencode((string) $order['order_number']))) ?>">View order details</a><a class="button button--outline" href="<?= escape_html(base_url('products/')) ?>">Continue browsing</a></div><?php endif; ?></main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
    <?php if ($order !== null && (($order['payment']['status'] ?? '') === 'paid' || ($order['payment']['provider'] ?? '') === 'cash_on_delivery')): ?><script>if (window.analytics_event && !sessionStorage.getItem('sc_purchase_<?= escape_html((string) $order['order_number']) ?>')) { window.analytics_event('purchase', {transaction_id: '<?= escape_html((string) $order['order_number']) ?>', currency: '<?= escape_html((string) $order['currency']) ?>', value: <?= json_encode((float) $order['grand_total']) ?>}); sessionStorage.setItem('sc_purchase_<?= escape_html((string) $order['order_number']) ?>', '1'); }</script><?php endif; ?>
</body>
<script src="<?= escape_html(asset_url('js/payment.js')) ?>" defer></script>
</html>