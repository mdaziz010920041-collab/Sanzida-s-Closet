<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$rows = [];
$editing = null;
$message = '';
$error = '';

try {
    $connection = database_connection();
    $admin = admin_require($connection, 'coupons.manage');
    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Please try again.');
        $action = (string) ($_POST['action'] ?? '');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($action === 'delete') {
            if ($id <= 0) throw new InvalidArgumentException('A coupon is required.');
            $connection->prepare("UPDATE coupons SET status = 'inactive' WHERE id = :id")->execute(['id' => $id]);
            admin_audit($connection, (int) $admin['id'], 'coupon.deactivate', 'coupon', $id);
            $message = 'Coupon deactivated.';
        } elseif (in_array($action, ['create', 'update'], true)) {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $discountType = (string) ($_POST['discount_type'] ?? 'percentage');
            $discountValue = max(0, (float) ($_POST['discount_value'] ?? 0));
            $minimum = max(0, (float) ($_POST['minimum_order_amount'] ?? 0));
            $maximum = ($_POST['maximum_discount_amount'] ?? '') === '' ? null : max(0, (float) $_POST['maximum_discount_amount']);
            $usageLimit = ($_POST['usage_limit'] ?? '') === '' ? null : max(0, (int) $_POST['usage_limit']);
            $usagePerUser = ($_POST['usage_limit_per_user'] ?? '') === '' ? null : max(0, (int) $_POST['usage_limit_per_user']);
            $startsAt = trim((string) ($_POST['starts_at'] ?? '')) ?: null;
            $endsAt = trim((string) ($_POST['ends_at'] ?? '')) ?: null;
            if ($startsAt !== null) $startsAt = str_replace('T', ' ', $startsAt) . (strlen($startsAt) === 16 ? ':00' : '');
            if ($endsAt !== null) $endsAt = str_replace('T', ' ', $endsAt) . (strlen($endsAt) === 16 ? ':00' : '');
            $status = in_array((string) ($_POST['status'] ?? ''), ['active', 'inactive', 'expired'], true) ? (string) $_POST['status'] : 'inactive';
            if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,80}$/', $code)) throw new InvalidArgumentException('Code must use 3-80 letters, numbers, underscores, or hyphens.');
            if (!in_array($discountType, ['percentage', 'fixed'], true) || $discountValue <= 0) throw new InvalidArgumentException('Choose a valid discount greater than zero.');
            if ($discountType === 'percentage' && $discountValue > 100) throw new InvalidArgumentException('Percentage discounts cannot exceed 100.');
            if ($maximum !== null && $maximum <= 0) throw new InvalidArgumentException('Maximum discount must be greater than zero.');
            if ($action === 'create') {
                $statement = $connection->prepare('INSERT INTO coupons (code, discount_type, discount_value, minimum_order_amount, maximum_discount_amount, usage_limit, usage_limit_per_user, starts_at, ends_at, status) VALUES (:code, :discount_type, :discount_value, :minimum, :maximum, :usage_limit, :usage_per_user, :starts_at, :ends_at, :status)');
                $statement->execute(['code' => $code, 'discount_type' => $discountType, 'discount_value' => $discountValue, 'minimum' => $minimum, 'maximum' => $maximum, 'usage_limit' => $usageLimit, 'usage_per_user' => $usagePerUser, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => $status]);
                $id = (int) $connection->lastInsertId();
                admin_audit($connection, (int) $admin['id'], 'coupon.create', 'coupon', $id);
                $message = 'Coupon created.';
            } else {
                if ($id <= 0) throw new InvalidArgumentException('A coupon is required.');
                $statement = $connection->prepare('UPDATE coupons SET code = :code, discount_type = :discount_type, discount_value = :discount_value, minimum_order_amount = :minimum, maximum_discount_amount = :maximum, usage_limit = :usage_limit, usage_limit_per_user = :usage_per_user, starts_at = :starts_at, ends_at = :ends_at, status = :status WHERE id = :id');
                $statement->execute(['code' => $code, 'discount_type' => $discountType, 'discount_value' => $discountValue, 'minimum' => $minimum, 'maximum' => $maximum, 'usage_limit' => $usageLimit, 'usage_per_user' => $usagePerUser, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => $status, 'id' => $id]);
                admin_audit($connection, (int) $admin['id'], 'coupon.update', 'coupon', $id);
                $message = 'Coupon updated.';
            }
        }
    }
    $rows = $connection->query('SELECT c.*, (SELECT COUNT(*) FROM coupon_usage usage_log WHERE usage_log.coupon_id = c.id) AS used_count FROM coupons c ORDER BY c.created_at DESC, c.id DESC')->fetchAll();
    $editId = max(0, (int) ($_GET['edit'] ?? 0));
    if ($editId > 0) {
        $statement = $connection->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editId]);
        $editing = $statement->fetch() ?: null;
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = str_contains($exception->getMessage(), 'Duplicate entry') ? 'That coupon code already exists.' : 'Coupon management is temporarily unavailable.';
}

$adminLinks = ['Overview' => 'admin/', 'Products' => 'admin/products.php', 'Categories' => 'admin/module.php?name=categories', 'Orders' => 'admin/module.php?name=orders', 'Customers' => 'admin/module.php?name=customers', 'Inventory' => 'admin/module.php?name=inventory', 'Coupons' => 'admin/coupons.php', 'Reviews' => 'admin/module.php?name=reviews', 'Returns' => 'admin/module.php?name=returns', 'Refunds' => 'admin/module.php?name=refunds', 'Content' => 'admin/content.php', 'SEO' => 'admin/seo.php', 'Settings' => 'admin/settings.php', 'Audit logs' => 'admin/module.php?name=audit'];
$input = static fn (string $key, mixed $default = ''): string => escape_html((string) ($editing[$key] ?? $default));
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Coupons | Admin</title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="admin-page"><aside class="admin-sidebar"><a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a><nav aria-label="Admin navigation"><?php foreach ($adminLinks as $label => $path): ?><a class="<?= $label === 'Coupons' ? 'is-active' : '' ?>" href="<?= escape_html(base_url($path)) ?>"><?= escape_html($label) ?></a><?php endforeach; ?></nav></aside>
<main class="admin-main"><header class="admin-topbar"><div><p class="eyebrow">Promotions</p><h1>Coupons</h1></div><a class="button button--outline" href="<?= escape_html(base_url('admin/')) ?>">Overview</a></header>
<?php if ($message !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?>
<section class="admin-panel"><div class="section__header"><div><p class="eyebrow"><?= $editing ? 'Edit promotion' : 'New promotion' ?></p><h2><?= $editing ? 'Update coupon' : 'Create coupon' ?></h2></div></div><form class="admin-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= $input('id') ?>"><?php endif; ?><div class="form-grid"><div class="field"><label class="field__label" for="coupon-code">Code</label><input class="input" id="coupon-code" name="code" value="<?= $input('code') ?>" placeholder="SANZIDA50" maxlength="80" required></div><div class="field"><label class="field__label" for="coupon-status">Status</label><select class="select" id="coupon-status" name="status"><option value="active" <?= ($editing['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($editing['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="expired" <?= ($editing['status'] ?? '') === 'expired' ? 'selected' : '' ?>>Expired</option></select></div><div class="field"><label class="field__label" for="discount-type">Discount type</label><select class="select" id="discount-type" name="discount_type"><option value="percentage" <?= ($editing['discount_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentage</option><option value="fixed" <?= ($editing['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed amount</option></select></div><div class="field"><label class="field__label" for="discount-value">Discount value</label><input class="input" id="discount-value" name="discount_value" type="number" min="0.01" step="0.01" value="<?= $input('discount_value') ?>" required></div><div class="field"><label class="field__label" for="minimum-order">Minimum order amount</label><input class="input" id="minimum-order" name="minimum_order_amount" type="number" min="0" step="0.01" value="<?= $input('minimum_order_amount', '0') ?>"></div><div class="field"><label class="field__label" for="maximum-discount">Maximum discount amount</label><input class="input" id="maximum-discount" name="maximum_discount_amount" type="number" min="0" step="0.01" value="<?= $input('maximum_discount_amount') ?>"></div><div class="field"><label class="field__label" for="usage-limit">Total usage limit</label><input class="input" id="usage-limit" name="usage_limit" type="number" min="1" step="1" value="<?= $input('usage_limit') ?>"></div><div class="field"><label class="field__label" for="usage-per-user">Usage limit per customer</label><input class="input" id="usage-per-user" name="usage_limit_per_user" type="number" min="1" step="1" value="<?= $input('usage_limit_per_user') ?>"></div><div class="field"><label class="field__label" for="starts-at">Starts at</label><input class="input" id="starts-at" name="starts_at" type="datetime-local" value="<?= $input('starts_at') ? str_replace(' ', 'T', substr($input('starts_at'), 0, 16)) : '' ?>"></div><div class="field"><label class="field__label" for="ends-at">Ends at</label><input class="input" id="ends-at" name="ends_at" type="datetime-local" value="<?= $input('ends_at') ? str_replace(' ', 'T', substr($input('ends_at'), 0, 16)) : '' ?>"></div></div><div class="admin-inline-form"><button class="button" type="submit"><?= $editing ? 'Update coupon' : 'Create coupon' ?></button><?php if ($editing): ?><a class="button button--outline" href="<?= escape_html(base_url('admin/coupons.php')) ?>">Cancel</a><?php endif; ?></div></form></section>
<section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Promotion library</p><h2>All coupons</h2></div><span class="badge"><?= count($rows) ?> coupons</span></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Code</th><th>Discount</th><th>Rules</th><th>Validity</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php if ($rows === []): ?><tr><td colspan="6">No coupons found.</td></tr><?php else: foreach ($rows as $row): ?><tr><td><strong><?= escape_html((string) $row['code']) ?></strong><br><span class="muted"><?= escape_html((string) $row['used_count']) ?> used</span></td><td><?= escape_html(number_format((float) $row['discount_value'], 2)) ?><?= $row['discount_type'] === 'percentage' ? '%' : ' ' . escape_html((string) ($row['currency'] ?? 'INR')) ?></td><td>Min <?= escape_html(number_format((float) $row['minimum_order_amount'], 2)) ?><?php if ($row['maximum_discount_amount'] !== null): ?><br>Cap <?= escape_html(number_format((float) $row['maximum_discount_amount'], 2)) ?><?php endif; ?></td><td><?= escape_html((string) ($row['starts_at'] ?: 'Now')) ?><br><?= escape_html((string) ($row['ends_at'] ?: 'No expiry')) ?></td><td><?= escape_html(ucfirst((string) $row['status'])) ?></td><td><a class="button button--outline" href="<?= escape_html(base_url('admin/coupons.php?edit=' . (int) $row['id'])) ?>">Edit</a><?php if ($row['status'] === 'active'): ?><form method="post" class="admin-inline-form" onsubmit="return confirm('Deactivate this coupon?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= escape_html((string) $row['id']) ?>"><button class="button button--outline" type="submit">Deactivate</button></form><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section></main></body></html>
