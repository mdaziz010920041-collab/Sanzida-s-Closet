<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/analytics.php';

$order = null;
register_shutdown_function(static function () use (&$order): void {
    if (!empty($order['refund'])) {
        echo '<script>if (window.analytics_event && !sessionStorage.getItem("sc_refund_' . escape_html((string) $order['refund']['id']) . '")) { window.analytics_event("refund", {transaction_id: ' . json_encode((string) $order['order_number']) . ', currency: ' . json_encode((string) $order['refund']['currency']) . ', value: ' . json_encode((float) $order['refund']['amount']) . '}); sessionStorage.setItem("sc_refund_' . escape_html((string) $order['refund']['id']) . '", "1"); }</script>';
    }
});
try {
    $connection = database_connection();
    $user = auth_current_user($connection);
    $order = order_find_for_view($connection, trim((string) ($_GET['order'] ?? '')), $user ? (int) $user['id'] : null);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}
if ($order === null) http_response_code(404);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Order details | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head><body class="checkout-page">
<?php $page_title = 'Order details'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?><main class="confirmation-main"><?php if ($order === null): ?><section class="empty-state"><p class="eyebrow">404 / not found</p><h1>Order unavailable.</h1><a class="button" href="<?= escape_html(base_url('account/')) ?>">Return to account</a></section><?php else: ?><section class="order-detail-card"><div class="section__header"><div><p class="eyebrow">Order <?= escape_html((string) $order['order_number']) ?></p><h1>Order details.</h1></div><span class="badge"><?= escape_html((string) $order['status']) ?></span></div><div class="order-detail-items"><?php foreach ($order['items'] as $item): ?><div class="order-detail-item"><div><strong><?= escape_html((string) $item['product_name']) ?></strong><span><?= escape_html((string) ($item['variant_description'] ?? '')) ?> &times; <?= escape_html((string) $item['quantity']) ?></span></div><strong><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $item['line_total'], 2)) ?></strong></div><?php endforeach; ?></div><div class="cart-summary__rows"><div><span>Subtotal</span><strong><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['subtotal'], 2)) ?></strong></div><div><span>Discount</span><strong><?= escape_html(number_format((float) $order['discount_total'], 2)) ?></strong></div><div><span>Shipping</span><strong><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['shipping_total'], 2)) ?></strong></div><div class="cart-summary__total"><span>Total</span><strong><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['grand_total'], 2)) ?></strong></div></div><p class="muted">Payment: <?= escape_html((string) ($order['payment']['status'] ?? 'pending')) ?>. Shipping: <?= escape_html((string) ($order['shipment']['status'] ?? 'pending')) ?>.</p></section><?php endif; ?></main><?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?></body></html>