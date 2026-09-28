<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/components/product-card.php';

$user = null;
$addresses = [];
$orders = [];
$wishlist = [];
$errors = [];
$notice = '';
$database_error = false;

try {
    $connection = database_connection();
    $user = auth_require_user($connection);
    if (request_is_post()) {
        $action = (string) ($_POST['action'] ?? '');
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Your session expired. Please try again.';
        } elseif ($action === 'profile') {
            $firstName = trim((string) ($_POST['first_name'] ?? ''));
            $lastName = trim((string) ($_POST['last_name'] ?? ''));
            $email = auth_normalize_email((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            if ($firstName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['form'] = 'Enter a valid name and email address.';
            } elseif (mb_strlen($phone) > 30) {
                $errors['form'] = 'Enter a shorter phone number.';
            } else {
                $statement = $connection->prepare('UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, email_verified_at = IF(email = :email_check, email_verified_at, NULL) WHERE id = :id');
                $statement->execute(['first_name' => $firstName, 'last_name' => $lastName ?: null, 'email' => $email, 'email_check' => $user['email'], 'phone' => $phone ?: null, 'id' => $user['id']]);
                $notice = 'Your profile has been updated.';
            }
        } elseif ($action === 'password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            if (mb_strlen($newPassword) < 8 || mb_strlen($newPassword) > 72 || $newPassword !== (string) ($_POST['new_password_confirmation'] ?? '')) {
                $errors['form'] = 'Your new passwords must match and be between 8 and 72 characters.';
            } else {
                $statement = $connection->prepare('SELECT password_hash FROM users WHERE id = :id');
                $statement->execute(['id' => $user['id']]);
                $hash = (string) $statement->fetchColumn();
                if (!password_verify($currentPassword, $hash)) {
                    $errors['form'] = 'Your current password is incorrect.';
                } else {
                    $connection->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id')->execute(['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $user['id']]);
                    $connection->prepare('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE user_id = :id AND revoked_at IS NULL')->execute(['id' => $user['id']]);
                    auth_login($connection, $user['email'], $newPassword);
                    $notice = 'Your password has been updated and other sessions were signed out.';
                }
            }
        } elseif ($action === 'address') {
            $required = ['recipient_name', 'phone', 'line_1', 'city'];
            foreach ($required as $field) {
                if (trim((string) ($_POST[$field] ?? '')) === '') $errors['form'] = 'Complete the required address fields.';
            }
            if ($errors === []) {
                $statement = $connection->prepare('INSERT INTO addresses (user_id, label, recipient_name, phone, line_1, line_2, city, state, postal_code, country_code) VALUES (:user_id, :label, :recipient_name, :phone, :line_1, :line_2, :city, :state, :postal_code, :country_code)');
                $statement->execute(['user_id' => $user['id'], 'label' => trim((string) ($_POST['label'] ?? 'Address')), 'recipient_name' => trim((string) $_POST['recipient_name']), 'phone' => trim((string) $_POST['phone']), 'line_1' => trim((string) $_POST['line_1']), 'line_2' => trim((string) ($_POST['line_2'] ?? '')) ?: null, 'city' => trim((string) $_POST['city']), 'state' => trim((string) ($_POST['state'] ?? '')) ?: null, 'postal_code' => trim((string) ($_POST['postal_code'] ?? '')) ?: null, 'country_code' => strtoupper(substr(trim((string) ($_POST['country_code'] ?? 'BD')), 0, 2))]);
                $notice = 'Your address has been saved.';
            }
        }
    }
    $user = auth_current_user($connection) ?? $user;
    $addressStatement = $connection->prepare('SELECT * FROM addresses WHERE user_id = :user_id ORDER BY created_at DESC');
    $addressStatement->execute(['user_id' => $user['id']]);
    $addresses = $addressStatement->fetchAll();
    $orderStatement = $connection->prepare('SELECT id, order_number, status, grand_total, currency, created_at FROM orders WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10');
    $orderStatement->execute(['user_id' => $user['id']]);
    $orders = $orderStatement->fetchAll();
    $wishlistStatement = $connection->prepare("SELECT product.id, product.name, product.slug, product.base_price, product.compare_at_price, product.discount_type, product.discount_value, product.currency, product.is_featured, variant.id AS wishlist_variant_id, image.file_path AS image_path, image.alt_text AS image_alt, CASE WHEN product.discount_type = 'percentage' THEN GREATEST(product.base_price - (product.base_price * product.discount_value / 100), 0) WHEN product.discount_type = 'fixed' THEN GREATEST(product.base_price - product.discount_value, 0) ELSE product.base_price END AS selling_price FROM wishlists wishlist INNER JOIN wishlist_items item ON item.wishlist_id = wishlist.id INNER JOIN product_variants variant ON variant.id = item.variant_id INNER JOIN products product ON product.id = variant.product_id AND product.status = 'active' LEFT JOIN product_images image ON image.product_id = product.id AND image.is_primary = 1 WHERE wishlist.user_id = :user_id GROUP BY product.id, product.name, product.slug, product.base_price, product.compare_at_price, product.discount_type, product.discount_value, product.currency, product.is_featured, variant.id, image.file_path, image.alt_text ORDER BY item.created_at DESC LIMIT 8");
    $wishlistStatement->execute(['user_id' => $user['id']]);
    $wishlist = $wishlistStatement->fetchAll();
} catch (Throwable $exception) {
    if ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
        $errors['form'] = 'That email address is already in use.';
    } else {
        error_log($exception->getMessage());
        $database_error = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My account | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="account-page" data-cart-api="<?= escape_html(base_url('api/cart.php')) ?>" data-wishlist-api="<?= escape_html(base_url('api/wishlist.php')) ?>">
    <?php $page_title = 'My account'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="account-main">
        <?php if ($database_error || $user === null): ?><section class="empty-state"><p class="eyebrow">Account unavailable</p><h1>We could not load your account.</h1><p>Please try again when the account service is available.</p><a class="button" href="<?= escape_html(base_url('auth/login.php')) ?>">Return to sign in</a></section><?php else: ?>
            <section class="account-heading"><div><p class="eyebrow">Your closet</p><h1>Welcome, <em><?= escape_html((string) $user['first_name']) ?>.</em></h1><p>Manage your details, saved pieces, and order history.</p></div><form method="post" action="<?= escape_html(base_url('auth/logout.php')) ?>"><?= csrf_field() ?><button class="button button--outline" type="submit">Sign out</button></form></section>
            <?php if (isset($errors['form'])): ?><div class="alert alert--soft" role="alert"><?= escape_html($errors['form']) ?></div><?php endif; ?><?php if ($notice !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($notice) ?></div><?php endif; ?>
            <div class="account-grid">
                <section class="account-panel" aria-labelledby="profile-title"><p class="eyebrow">Profile</p><h2 id="profile-title">Your details</h2><form class="account-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><div class="form-grid"><div class="field"><label class="field__label" for="account-first-name">First name</label><input class="input" id="account-first-name" name="first_name" value="<?= escape_html((string) $user['first_name']) ?>" required></div><div class="field"><label class="field__label" for="account-last-name">Last name</label><input class="input" id="account-last-name" name="last_name" value="<?= escape_html((string) ($user['last_name'] ?? '')) ?>"></div><div class="field"><label class="field__label" for="account-email">Email</label><input class="input" id="account-email" name="email" type="email" value="<?= escape_html((string) $user['email']) ?>" autocomplete="email" required></div><div class="field"><label class="field__label" for="account-phone">Phone</label><input class="input" id="account-phone" name="phone" type="tel" value="<?= escape_html((string) ($user['phone'] ?? '')) ?>" autocomplete="tel"></div></div><button class="button" type="submit">Save details</button></form></section>
                <section class="account-panel" aria-labelledby="security-title"><p class="eyebrow">Security</p><h2 id="security-title">Keep it private.</h2><form class="account-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password"><div class="field"><label class="field__label" for="current-password">Current password</label><input class="input" id="current-password" name="current_password" type="password" autocomplete="current-password" required></div><div class="field"><label class="field__label" for="new-password">New password</label><input class="input" id="new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" required></div><div class="field"><label class="field__label" for="new-password-confirmation">Confirm new password</label><input class="input" id="new-password-confirmation" name="new_password_confirmation" type="password" minlength="8" autocomplete="new-password" required></div><button class="button" type="submit">Update password</button></form></section>
                <section class="account-panel account-panel--wide" aria-labelledby="address-title"><div class="section__header"><div><p class="eyebrow">Addresses</p><h2 id="address-title">Your places.</h2></div></div><?php if ($addresses !== []): ?><div class="address-list"><?php foreach ($addresses as $address): ?><address class="address-card"><strong><?= escape_html((string) $address['label']) ?></strong><span><?= escape_html((string) $address['recipient_name']) ?></span><span><?= escape_html((string) $address['line_1']) ?><?= $address['line_2'] ? ', ' . escape_html((string) $address['line_2']) : '' ?></span><span><?= escape_html((string) $address['city']) ?><?= $address['postal_code'] ? ' ' . escape_html((string) $address['postal_code']) : '' ?></span><span><?= escape_html((string) $address['phone']) ?></span></address><?php endforeach; ?></div><?php endif; ?><form class="account-form address-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="address"><div class="form-grid"><div class="field"><label class="field__label" for="address-label">Label</label><input class="input" id="address-label" name="label" placeholder="Home"></div><div class="field"><label class="field__label" for="address-recipient">Recipient name</label><input class="input" id="address-recipient" name="recipient_name" required></div><div class="field"><label class="field__label" for="address-phone">Phone</label><input class="input" id="address-phone" name="phone" required></div><div class="field"><label class="field__label" for="address-line-1">Address line</label><input class="input" id="address-line-1" name="line_1" required></div><div class="field"><label class="field__label" for="address-line-2">Apartment / suite</label><input class="input" id="address-line-2" name="line_2"></div><div class="field"><label class="field__label" for="address-city">City</label><input class="input" id="address-city" name="city" required></div></div><button class="button button--outline" type="submit">Save address</button></form></section>
                <section class="account-panel account-panel--wide" aria-labelledby="orders-title"><div class="section__header"><div><p class="eyebrow">Order history</p><h2 id="orders-title">Your orders.</h2></div></div><?php if ($orders !== []): ?><div class="order-list"><div class="order-row order-row--head"><span>Order</span><span>Status</span><span>Total</span><span>Date</span></div><?php foreach ($orders as $order): ?><div class="order-row"><strong><a class="link" href="<?= escape_html(base_url('orders/view.php?order=' . rawurlencode((string) $order['order_number']))) ?>"><?= escape_html((string) $order['order_number']) ?></a></strong><span class="badge"><?= escape_html((string) $order['status']) ?></span><span><?= escape_html((string) $order['currency']) ?> <?= escape_html(number_format((float) $order['grand_total'], 2)) ?></span><time datetime="<?= escape_html((string) $order['created_at']) ?>"><?= escape_html(date('j M Y', strtotime((string) $order['created_at']))) ?></time></div><?php endforeach; ?></div><?php else: ?><p class="muted">Your completed orders will appear here.</p><?php endif; ?></section>
                <section class="account-panel account-panel--wide" aria-labelledby="wishlist-title"><div class="section__header"><div><p class="eyebrow">Saved pieces</p><h2 id="wishlist-title">Your wishlist.</h2></div></div><?php if ($wishlist !== []): ?><div class="product-grid product-grid--catalogue"><?php foreach ($wishlist as $item) { render_product_card($item); } ?></div><?php else: ?><p class="muted">Pieces you save will appear here.</p><?php endif; ?></section>
                <section class="account-panel account-panel--wide" data-recent-container hidden aria-labelledby="account-recent-title"><div class="section__header"><div><p class="eyebrow">Your browsing</p><h2 id="account-recent-title">Recently viewed.</h2></div></div><div class="product-grid product-grid--catalogue" data-recent-grid></div></section>
            </div>
        <?php endif; ?>
    </main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
    <script src="<?= escape_html(asset_url('js/catalogue.js')) ?>" defer></script><script src="<?= escape_html(asset_url('js/cart.js')) ?>" defer></script>
</body>
</html>