<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/returns.php';

$page_title = 'Returns & Exchanges | ' . APP_NAME;

try {
    $connection = database_connection();
    $policy = return_policy($connection) ?? [
        'return_window_days' => 7,
        'allowed_resolutions' => ['refund', 'exchange'],
        'return_type' => 'return',
    ];
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $policy = ['return_window_days' => 7, 'allowed_resolutions' => ['refund', 'exchange'], 'return_type' => 'return'];
}

$allowed = is_array($policy['allowed_resolutions'] ?? null) ? $policy['allowed_resolutions'] : ['refund', 'exchange'];
$windowDays = (int) ($policy['return_window_days'] ?? 7);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape_html($page_title) ?></title>
    <link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>">
</head>
<body class="checkout-page">
    <?php require __DIR__ . '/components/catalogue-header.php'; ?>

    <main class="auth-main">
        <section class="auth-card auth-card--wide">
            <p class="eyebrow">Customer care</p>
            <h1>Returns &amp;<br><em>exchanges.</em></h1>

            <div class="alert alert--soft" role="status">
                We want every order to feel right. If something arrives damaged, incorrect, or not as expected, you can request a return or exchange within <?= escape_html((string) $windowDays) ?> days of delivery.
            </div>

            <div class="auth-form">
                <div class="field">
                    <label class="field__label">Return window</label>
                    <p class="muted">Items must be returned in their original condition, with tags and packaging intact where applicable.</p>
                </div>

                <div class="field">
                    <label class="field__label">Available resolutions</label>
                    <p class="muted"><?= escape_html(implode(', ', array_map('ucfirst', $allowed))) ?></p>
                </div>

                <div class="field">
                    <label class="field__label">How to request</label>
                    <p class="muted">Visit your order details, select the item you wish to return, and submit your request through the return form. Our team will review the request and confirm the next step.</p>
                </div>

                <div class="field">
                    <label class="field__label">Not covered</label>
                    <p class="muted">Final sale items, worn products, damaged items caused after delivery, or return requests outside the eligible window may not qualify for a refund or exchange.</p>
                </div>

                <div class="form-actions">
                    <a class="button" href="<?= escape_html(base_url('/')) ?>">Back to home</a>
                    <a class="button button--outline" href="<?= escape_html(base_url('orders/view.php')) ?>">Track an order</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
