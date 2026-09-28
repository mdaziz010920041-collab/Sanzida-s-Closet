<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$settings = [
    'store' => [
        'store.support_email' => ['label' => 'Customer support email', 'type' => 'email', 'placeholder' => 'hello@example.com'],
        'store.support_phone' => ['label' => 'Customer support phone', 'type' => 'tel', 'placeholder' => '+91 98765 43210'],
        'store.default_currency' => ['label' => 'Default currency', 'type' => 'text', 'placeholder' => 'INR'],
    ],
    'analytics' => [
        'integrations.ga_measurement_id' => ['label' => 'Google Analytics measurement ID', 'type' => 'text', 'placeholder' => 'G-XXXXXXXXXX'],
        'integrations.meta_pixel_id' => ['label' => 'Meta Pixel ID', 'type' => 'text', 'placeholder' => '123456789012345'],
    ],
    'payments' => [
        'payments.provider' => ['label' => 'Payment provider', 'type' => 'select', 'options' => ['razorpay' => 'Razorpay', 'manual' => 'Manual / offline']],
        'payments.mode' => ['label' => 'Razorpay mode', 'type' => 'select', 'options' => ['test' => 'Test / sandbox', 'live' => 'Live']],
        'payments.currency' => ['label' => 'Payment currency', 'type' => 'text', 'placeholder' => 'INR'],
        'payments.razorpay_key_id' => ['label' => 'Razorpay key ID', 'type' => 'text', 'placeholder' => 'rzp_test_...', 'secret' => true],
        'payments.razorpay_secret' => ['label' => 'Razorpay secret', 'type' => 'password', 'placeholder' => 'Leave blank to keep the saved secret', 'secret' => true],
        'payments.razorpay_webhook_secret' => ['label' => 'Razorpay webhook secret', 'type' => 'password', 'placeholder' => 'Leave blank to keep the saved secret', 'secret' => true],
    ],
    'delivery' => [
        'shipping.provider' => ['label' => 'Delivery provider', 'type' => 'select', 'options' => ['manual' => 'Manual shipping', 'shiprocket' => 'Shiprocket']],
        'shipping.api_base_url' => ['label' => 'Delivery API base URL', 'type' => 'url', 'placeholder' => 'https://apiv2.shiprocket.in/v1/external'],
        'shipping.api_key' => ['label' => 'Delivery API key / token', 'type' => 'password', 'placeholder' => 'Leave blank to keep the saved key', 'secret' => true],
        'shipping.webhook_secret' => ['label' => 'Delivery webhook secret', 'type' => 'password', 'placeholder' => 'Leave blank to keep the saved secret', 'secret' => true],
    ],
    'seo' => [
        'seo.google_site_verification' => ['label' => 'Google Search Console verification code', 'type' => 'text', 'placeholder' => 'Paste the content value from Google'],
        'seo.sitemap_url' => ['label' => 'Sitemap URL', 'type' => 'url', 'placeholder' => 'https://example.com/sitemap.xml'],
    ],
];
$secretKeys = ['payments.razorpay_key_id', 'payments.razorpay_secret', 'payments.razorpay_webhook_secret', 'shipping.api_key', 'shipping.webhook_secret'];
$values = [];
$message = '';
$error = '';

try {
    $connection = database_connection();
    $admin = admin_require($connection, 'settings.manage');
    foreach ($settings as $fields) foreach ($fields as $key => $field) {
        $statement = $connection->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $stored = $statement->fetchColumn();
        $values[$key] = $stored === false ? '' : (json_decode((string) $stored, true) ?? (string) $stored);
    }
    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Please try again.');
        foreach ($settings as $fields) foreach ($fields as $key => $field) {
            $value = trim((string) ($_POST[str_replace('.', '_', $key)] ?? ''));
            if (in_array($key, $secretKeys, true) && $value === '') $value = (string) ($values[$key] ?? '');
            $statement = $connection->prepare('INSERT INTO settings (setting_key, setting_value, is_public, updated_by_admin_id) VALUES (:key, :value, 0, :admin_id) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_public = 0, updated_by_admin_id = VALUES(updated_by_admin_id)');
            $statement->execute(['key' => $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'admin_id' => $admin['id']]);
            $values[$key] = $value;
        }
        admin_audit($connection, (int) $admin['id'], 'settings.update', 'settings');
        $message = 'Settings saved.';
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'Settings are temporarily unavailable.';
}

$adminLinks = [
    'Overview' => 'admin/', 'Products' => 'admin/products.php', 'Categories' => 'admin/module.php?name=categories', 'Orders' => 'admin/module.php?name=orders',
    'Customers' => 'admin/module.php?name=customers', 'Inventory' => 'admin/module.php?name=inventory', 'Coupons' => 'admin/module.php?name=coupons', 'Reviews' => 'admin/module.php?name=reviews',
    'Returns' => 'admin/module.php?name=returns', 'Refunds' => 'admin/module.php?name=refunds', 'Content' => 'admin/content.php', 'SEO' => 'admin/seo.php', 'Settings' => 'admin/settings.php', 'Audit logs' => 'admin/module.php?name=audit',
];
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Settings | Admin</title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="admin-page">
<aside class="admin-sidebar"><a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a><nav aria-label="Admin navigation"><?php foreach ($adminLinks as $label => $path): ?><a class="<?= $label === 'Settings' ? 'is-active' : '' ?>" href="<?= escape_html(base_url($path)) ?>"><?= escape_html($label) ?></a><?php endforeach; ?></nav></aside>
<main class="admin-main"><header class="admin-topbar"><div><p class="eyebrow">Store operations</p><h1>Settings</h1></div><a class="button button--outline" href="<?= escape_html(base_url('admin/')) ?>">Overview</a></header>
<?php if ($message !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?>
<form class="admin-form" method="post"><?= csrf_field(); ?>
<?php $titles = ['store' => ['Store basics', 'Customer-facing contact and currency defaults.'], 'analytics' => ['Analytics', 'Measure visits and campaigns after adding your provider IDs.'], 'payments' => ['Razorpay payments', 'Use test mode first. Live payments require verified credentials and webhooks.'], 'delivery' => ['Delivery aggregator', 'Shiprocket credentials are stored privately. The carrier adapter must be enabled before live fulfillment.'], 'seo' => ['Google Search Console', 'Paste the verification content value and keep the sitemap URL current.']]; foreach ($settings as $section => $fields): ?><section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Configuration</p><h2><?= escape_html($titles[$section][0]) ?></h2><p class="muted"><?= escape_html($titles[$section][1]) ?></p></div></div><div class="form-grid"><?php foreach ($fields as $key => $field): ?><?php $fieldName = str_replace('.', '_', $key); ?><div class="field"><label class="field__label" for="setting-<?= escape_html(str_replace('.', '-', $key)) ?>"><?= escape_html($field['label']) ?><?php if (!empty($field['secret'])): ?> <span class="muted">private</span><?php endif; ?></label><?php if ($field['type'] === 'select'): ?><select class="select" id="setting-<?= escape_html(str_replace('.', '-', $key)) ?>" name="<?= escape_html($fieldName) ?>"><?php foreach ($field['options'] as $optionValue => $optionLabel): ?><option value="<?= escape_html($optionValue) ?>" <?= (string) ($values[$key] ?? '') === (string) $optionValue ? 'selected' : '' ?>><?= escape_html($optionLabel) ?></option><?php endforeach; ?></select><?php else: ?><input class="input" id="setting-<?= escape_html(str_replace('.', '-', $key)) ?>" name="<?= escape_html($fieldName) ?>" type="<?= escape_html($field['type']) ?>" value="<?= !empty($field['secret']) ? '' : escape_html((string) ($values[$key] ?? '')) ?>" placeholder="<?= escape_html($field['placeholder'] ?? '') ?>" autocomplete="off"><?php endif; ?><?php if (!empty($field['secret']) && ($values[$key] ?? '') !== ''): ?><span class="muted">A saved value is active. Leave blank to keep it.</span><?php endif; ?></div><?php endforeach; ?></div></section><?php endforeach; ?>
<button class="button" type="submit">Save all settings <span aria-hidden="true">&rarr;</span></button></form>
</main></body></html>
