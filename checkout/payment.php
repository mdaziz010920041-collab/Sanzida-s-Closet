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
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Retry payment | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="checkout-page" data-payment-api="<?= escape_html(base_url('api/payment.php')) ?>" data-order-number="<?= escape_html((string) ($_GET['order'] ?? '')) ?>" data-currency="<?= escape_html((string) ($order['currency'] ?? 'INR')) ?>">
<?php $page_title = 'Retry payment'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
<main class="confirmation-main"><?php if ($order === null): ?><section class="empty-state"><p class="eyebrow">Payment unavailable</p><h1>We could not find that order.</h1><a class="button" href="<?= escape_html(base_url('products/')) ?>">Return to the edit</a></section><?php else: ?><section class="confirmation-hero"><p class="eyebrow">Order <?= escape_html((string) $order['order_number']) ?></p><h1>Complete your<br><em>payment.</em></h1><p>Your order total is <?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['grand_total'], 2)) ?>. Payment will only be marked successful after server-side gateway verification.</p><?= csrf_field() ?><button class="button" type="button" data-payment-start>Start secure payment <span aria-hidden="true">&rarr;</span></button><p class="form-message" data-payment-message aria-live="polite"></p></section><?php endif; ?></main>
<?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?><script src="<?= escape_html(asset_url('js/payment.js')) ?>" defer></script>
</body>
</html>
