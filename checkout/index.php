<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/cart.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/shipping.php';
require_once dirname(__DIR__) . '/includes/payments.php';

$errors = [];
$connection = null;
$user = null;
$cart = ['items' => [], 'item_count' => 0, 'subtotal' => 0, 'discount' => 0, 'shipping' => 0, 'total' => 0, 'coupon' => null];
$addresses = [];
$shippingMethods = [];
$values = ['email' => '', 'recipient_name' => '', 'phone' => '', 'line_1' => '', 'line_2' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'country_code' => 'BD', 'shipping_method' => 'standard', 'payment_method' => 'online_pending'];

try {
    $connection = database_connection();
    $user = auth_current_user($connection);
    $shippingMethods = shipping_methods($connection);
    if ($shippingMethods !== [] && !isset($_POST['shipping_method'])) $values['shipping_method'] = (string) $shippingMethods[0]['code'];
    if ($user) {
        $values['email'] = (string) $user['email'];
        $addressStatement = $connection->prepare('SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default_shipping DESC, created_at DESC');
        $addressStatement->execute(['user_id' => $user['id']]);
        $addresses = $addressStatement->fetchAll();
    }
    $cart = cart_summary($connection, $user ? (int) $user['id'] : null);
    if (request_is_post()) {
        foreach ($values as $key => $value) $values[$key] = trim((string) ($_POST[$key] ?? $value));
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Your session expired. Please try again.';
        } else {
            $addressId = (int) ($_POST['address_id'] ?? 0);
            if ($addressId > 0 && $user) {
                $savedAddress = $connection->prepare('SELECT recipient_name, phone, line_1, line_2, city, state, postal_code, country_code FROM addresses WHERE id = :id AND user_id = :user_id LIMIT 1');
                $savedAddress->execute(['id' => $addressId, 'user_id' => $user['id']]);
                $savedAddress = $savedAddress->fetch();
                if ($savedAddress) $values = array_merge($values, $savedAddress);
            }
            try {
                if ($values['payment_method'] === 'online_pending' && !payment_is_configured()) {
                    throw new InvalidArgumentException('Online payment is temporarily unavailable. Please choose cash on delivery or try again later.');
                }
                $result = checkout_create_order($connection, (int) $cart['cart_id'], $user ? (int) $user['id'] : null, $values['email'], $values);
                $destination = $values['payment_method'] === 'online_pending' ? 'checkout/payment.php' : 'checkout/confirmation.php';
                header('Location: ' . base_url($destination . '?order=' . rawurlencode($result['order_number'])));
                exit;
            } catch (InvalidArgumentException $exception) {
                $errors['form'] = $exception->getMessage();
            }
        }
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $errors['form'] = 'Checkout is temporarily unavailable. Please try again later.';
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Checkout | <?= escape_html(APP_NAME) ?></title><meta name="description" content="Complete your Sanzida's Closet order."><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="checkout-page" data-checkout-server-error="<?= $connection === null ? '1' : '0' ?>">
    <?php $page_title = 'Checkout'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="checkout-main">
        <div class="checkout-steps" aria-label="Checkout progress"><span class="is-current">1 Cart</span><span class="is-current">2 Details</span><span>3 Confirmation</span></div>
        <?php if (isset($errors['form'])): ?><div class="alert alert--soft" role="alert"><?= escape_html($errors['form']) ?></div><?php endif; ?>
        <?php if ($cart['items'] === []): ?><section class="empty-state"><p class="eyebrow">Your bag is empty</p><h1>Nothing to check out yet.</h1><a class="button" href="<?= escape_html(base_url('products/')) ?>">Browse the edit</a></section><?php else: ?><form class="checkout-layout" method="post" novalidate><?= csrf_field() ?><section class="checkout-form"><div class="checkout-panel"><p class="eyebrow">1 / Contact</p><h1>Delivery details.</h1><?php if ($user): ?><p class="checkout-signed-in">Signed in as <?= escape_html((string) $user['email']) ?>. <a class="link" href="<?= escape_html(base_url('account/')) ?>">Manage account</a></p><?php else: ?><p class="checkout-signed-in">Checking out as a guest. <a class="link" href="<?= escape_html(base_url('auth/login.php?next=/checkout/')) ?>">Sign in</a> to keep your order in your account.</p><?php endif; ?><div class="field"><label class="field__label" for="checkout-email">Email address</label><input class="input" id="checkout-email" name="email" type="email" value="<?= escape_html($values['email']) ?>" autocomplete="email" required></div><?php if ($addresses !== []): ?><div class="field"><label class="field__label" for="saved-address">Saved address</label><select class="select" id="saved-address" name="address_id"><option value="0">Enter a new address</option><?php foreach ($addresses as $address): ?><option value="<?= escape_html((string) $address['id']) ?>"><?= escape_html((string) $address['label']) ?> &middot; <?= escape_html((string) $address['line_1']) ?></option><?php endforeach; ?></select></div><?php endif; ?><div class="form-grid"><div class="field"><label class="field__label" for="checkout-recipient">Recipient name</label><input class="input" id="checkout-recipient" name="recipient_name" value="<?= escape_html($values['recipient_name']) ?>" required></div><div class="field"><label class="field__label" for="checkout-phone">Phone</label><input class="input" id="checkout-phone" name="phone" value="<?= escape_html($values['phone']) ?>" autocomplete="tel" required></div><div class="field"><label class="field__label" for="checkout-line-1">Address line</label><input class="input" id="checkout-line-1" name="line_1" value="<?= escape_html($values['line_1']) ?>" required></div><div class="field"><label class="field__label" for="checkout-line-2">Apartment / suite</label><input class="input" id="checkout-line-2" name="line_2" value="<?= escape_html($values['line_2']) ?>"></div><div class="field"><label class="field__label" for="checkout-city">City</label><input class="input" id="checkout-city" name="city" value="<?= escape_html($values['city']) ?>" required></div><div class="field"><label class="field__label" for="checkout-state">State / region</label><input class="input" id="checkout-state" name="state" value="<?= escape_html($values['state']) ?>"></div><div class="field"><label class="field__label" for="checkout-postal">Postal code</label><input class="input" id="checkout-postal" name="postal_code" value="<?= escape_html($values['postal_code']) ?>"></div><div class="field"><label class="field__label" for="checkout-country">Country code</label><input class="input" id="checkout-country" name="country_code" value="<?= escape_html($values['country_code']) ?>" maxlength="2" required></div></div></div><div class="checkout-panel"><p class="eyebrow">2 / Delivery</p><h2>Shipping method.</h2><label class="choice-card"><input type="radio" name="shipping_method" value="standard" checked><span><strong>Standard delivery</strong><small>₹ 120 under ₹ 5,000, complimentary above.</small></span></label></div><div class="checkout-panel"><p class="eyebrow">3 / Payment</p><h2>Payment method.</h2><label class="choice-card"><input type="radio" name="payment_method" value="cash_on_delivery" checked><span><strong>Cash on delivery</strong><small>Payment status will remain pending until fulfilment workflow confirms it.</small></span></label><label class="choice-card"><input type="radio" name="payment_method" value="online_pending"><span><strong>Online payment</strong><small>Gateway configuration is required before this can be completed.</small></span></label></div></section><aside class="checkout-summary"><p class="eyebrow">Order summary</p><h2>Your selection.</h2><div class="checkout-items"><?php foreach ($cart['items'] as $item): ?><div class="checkout-item"><span><?= escape_html((string) $item['name']) ?> &times; <?= escape_html((string) $item['quantity']) ?></span><strong><?= escape_html((string) $item['currency']) ?> <?= escape_html(number_format((float) $item['line_total'], 2)) ?></strong></div><?php endforeach; ?></div><div class="cart-summary__rows"><div><span>Subtotal</span><strong>₹ <?= escape_html(number_format((float) $cart['subtotal'], 2)) ?></strong></div><div><span>Discount</span><strong>− ₹ <?= escape_html(number_format((float) $cart['discount'], 2)) ?></strong></div><div><span>Estimated shipping</span><strong>₹ <?= escape_html(number_format((float) $cart['shipping'], 2)) ?></strong></div><div class="cart-summary__total"><span>Total</span><strong>₹ <?= escape_html(number_format((float) $cart['total'], 2)) ?></strong></div></div><button class="button button--full" type="submit">Place order <span aria-hidden="true">&rarr;</span></button><p class="muted checkout-trust">Totals, stock, prices, coupon, and address are validated again on the server before creation.</p></aside></form><?php endif; ?>
    </main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
    <?php if ($cart['items'] !== []): ?><script>if (window.analytics_event) window.analytics_event('begin_checkout', {currency: '<?= escape_html((string) ($cart['currency'] ?? 'INR')) ?>', value: <?= json_encode((float) $cart['total']) ?>});</script><?php endif; ?>
</body>
</html>
<script src="<?= escape_html(asset_url('js/checkout.js')) ?>" defer></script>
<script>
    document.querySelector('input[name="payment_method"][value="online_pending"]')?.click();
</script>