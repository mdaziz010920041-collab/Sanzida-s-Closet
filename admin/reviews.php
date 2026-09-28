<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$products = [];
$users = [];
$rows = [];
$editing = null;
$message = '';
$error = '';

try {
    $connection = database_connection();
    $admin = admin_require($connection, 'reviews.manage');
    $products = $connection->query("SELECT id, name FROM products WHERE status = 'active' ORDER BY name LIMIT 200")->fetchAll();
    $users = $connection->query('SELECT id, first_name, last_name, email FROM users ORDER BY first_name, last_name LIMIT 300')->fetchAll();
    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Please try again.');
        $action = (string) ($_POST['action'] ?? '');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($action === 'delete') {
            if ($id <= 0) throw new InvalidArgumentException('A review is required.');
            $connection->prepare('DELETE FROM reviews WHERE id = :id')->execute(['id' => $id]);
            admin_audit($connection, (int) $admin['id'], 'review.delete', 'review', $id);
            $message = 'Review deleted.';
        } elseif (in_array($action, ['create', 'update'], true)) {
            $productId = max(0, (int) ($_POST['product_id'] ?? 0));
            $userId = max(0, (int) ($_POST['user_id'] ?? 0));
            $rating = min(5, max(1, (int) ($_POST['rating'] ?? 0)));
            $title = trim((string) ($_POST['title'] ?? '')) ?: null;
            $body = trim((string) ($_POST['body'] ?? '')) ?: null;
            $status = in_array((string) ($_POST['status'] ?? ''), ['pending', 'approved', 'rejected'], true) ? (string) $_POST['status'] : 'pending';
            $verified = !empty($_POST['verified_purchase']) ? 1 : 0;
            if ($productId <= 0 || $userId <= 0 || $rating < 1) throw new InvalidArgumentException('Choose a product, customer, and rating.');
            if ($action === 'create') {
                $statement = $connection->prepare('INSERT INTO reviews (product_id, user_id, rating, title, body, status, verified_purchase) VALUES (:product_id, :user_id, :rating, :title, :body, :status, :verified)');
                $statement->execute(['product_id' => $productId, 'user_id' => $userId, 'rating' => $rating, 'title' => $title, 'body' => $body, 'status' => $status, 'verified' => $verified]);
                $id = (int) $connection->lastInsertId();
                admin_audit($connection, (int) $admin['id'], 'review.create', 'review', $id);
                $message = 'Review created.';
            } else {
                if ($id <= 0) throw new InvalidArgumentException('A review is required.');
                $statement = $connection->prepare('UPDATE reviews SET product_id = :product_id, user_id = :user_id, rating = :rating, title = :title, body = :body, status = :status, verified_purchase = :verified WHERE id = :id');
                $statement->execute(['product_id' => $productId, 'user_id' => $userId, 'rating' => $rating, 'title' => $title, 'body' => $body, 'status' => $status, 'verified' => $verified, 'id' => $id]);
                admin_audit($connection, (int) $admin['id'], 'review.update', 'review', $id);
                $message = 'Review updated.';
            }
        }
    }
    $rows = $connection->query('SELECT reviews.*, products.name AS product_name, CONCAT(users.first_name, \' \', COALESCE(users.last_name, \'\')) AS customer_name, users.email AS customer_email FROM reviews INNER JOIN products ON products.id = reviews.product_id INNER JOIN users ON users.id = reviews.user_id ORDER BY reviews.created_at DESC, reviews.id DESC LIMIT 200')->fetchAll();
    $editId = max(0, (int) ($_GET['edit'] ?? 0));
    if ($editId > 0) {
        $statement = $connection->prepare('SELECT * FROM reviews WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editId]);
        $editing = $statement->fetch() ?: null;
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = str_contains($exception->getMessage(), 'Duplicate entry') ? 'That customer has already reviewed this product.' : 'Review management is temporarily unavailable.';
}

$adminLinks = ['Overview' => 'admin/', 'Products' => 'admin/products.php', 'Categories' => 'admin/module.php?name=categories', 'Orders' => 'admin/module.php?name=orders', 'Customers' => 'admin/module.php?name=customers', 'Inventory' => 'admin/module.php?name=inventory', 'Coupons' => 'admin/coupons.php', 'Reviews' => 'admin/reviews.php', 'Returns' => 'admin/module.php?name=returns', 'Refunds' => 'admin/module.php?name=refunds', 'Content' => 'admin/content.php', 'SEO' => 'admin/seo.php', 'Settings' => 'admin/settings.php', 'Audit logs' => 'admin/module.php?name=audit'];
$input = static fn (string $key, mixed $default = ''): string => escape_html((string) ($editing[$key] ?? $default));
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reviews | Admin</title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="admin-page"><aside class="admin-sidebar"><a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a><nav aria-label="Admin navigation"><?php foreach ($adminLinks as $label => $path): ?><a class="<?= $label === 'Reviews' ? 'is-active' : '' ?>" href="<?= escape_html(base_url($path)) ?>"><?= escape_html($label) ?></a><?php endforeach; ?></nav></aside>
<main class="admin-main"><header class="admin-topbar"><div><p class="eyebrow">Customer voice</p><h1>Reviews</h1></div><a class="button button--outline" href="<?= escape_html(base_url('admin/')) ?>">Overview</a></header>
<?php if ($message !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?>
<section class="admin-panel"><div class="section__header"><div><p class="eyebrow"><?= $editing ? 'Edit review' : 'New review' ?></p><h2><?= $editing ? 'Update review' : 'Add review' ?></h2></div></div><form class="admin-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= $input('id') ?>"><?php endif; ?><div class="form-grid"><div class="field"><label class="field__label" for="review-product">Product</label><select class="select" id="review-product" name="product_id" required><option value="">Choose product</option><?php foreach ($products as $product): ?><option value="<?= escape_html((string) $product['id']) ?>" <?= (int) ($editing['product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>><?= escape_html((string) $product['name']) ?></option><?php endforeach; ?></select></div><div class="field"><label class="field__label" for="review-user">Customer</label><select class="select" id="review-user" name="user_id" required><option value="">Choose customer</option><?php foreach ($users as $user): ?><option value="<?= escape_html((string) $user['id']) ?>" <?= (int) ($editing['user_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>><?= escape_html(trim($user['first_name'] . ' ' . $user['last_name']) . ' · ' . $user['email']) ?></option><?php endforeach; ?></select></div><div class="field"><label class="field__label" for="review-rating">Rating</label><select class="select" id="review-rating" name="rating" required><?php for ($rating = 5; $rating >= 1; $rating--): ?><option value="<?= $rating ?>" <?= (int) ($editing['rating'] ?? 5) === $rating ? 'selected' : '' ?>><?= $rating ?> / 5</option><?php endfor; ?></select></div><div class="field"><label class="field__label" for="review-status">Status</label><select class="select" id="review-status" name="status"><option value="pending" <?= ($editing['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option><option value="approved" <?= ($editing['status'] ?? 'approved') === 'approved' ? 'selected' : '' ?>>Approved</option><option value="rejected" <?= ($editing['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option></select></div><div class="field field--wide"><label class="field__label" for="review-title">Review title</label><input class="input" id="review-title" name="title" maxlength="160" value="<?= $input('title') ?>" placeholder="Beautiful details"></div><div class="field field--wide"><label class="field__label" for="review-body">Review</label><textarea class="textarea" id="review-body" name="body" rows="5" placeholder="Share the customer's experience."><?= $input('body') ?></textarea></div></div><label class="check-option"><input type="checkbox" name="verified_purchase" value="1" <?= !empty($editing['verified_purchase']) ? 'checked' : '' ?>> Verified purchase</label><div class="admin-inline-form"><button class="button" type="submit"><?= $editing ? 'Update review' : 'Create review' ?></button><?php if ($editing): ?><a class="button button--outline" href="<?= escape_html(base_url('admin/reviews.php')) ?>">Cancel</a><?php endif; ?></div></form></section>
<section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Moderation queue</p><h2>All reviews</h2></div><span class="badge"><?= count($rows) ?> reviews</span></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Customer</th><th>Product</th><th>Rating</th><th>Review</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php if ($rows === []): ?><tr><td colspan="6">No reviews found.</td></tr><?php else: foreach ($rows as $row): ?><tr><td><?= escape_html((string) $row['customer_name']) ?><br><span class="muted"><?= escape_html((string) $row['customer_email']) ?></span></td><td><?= escape_html((string) $row['product_name']) ?></td><td><?= escape_html((string) $row['rating']) ?> / 5<?php if ($row['verified_purchase']): ?><br><span class="muted">Verified</span><?php endif; ?></td><td><strong><?= escape_html((string) ($row['title'] ?: 'Untitled review')) ?></strong><br><?= escape_html((string) ($row['body'] ?: '—')) ?></td><td><?= escape_html(ucfirst((string) $row['status'])) ?></td><td><a class="button button--outline" href="<?= escape_html(base_url('admin/reviews.php?edit=' . (int) $row['id'])) ?>">Edit</a><form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this review?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= escape_html((string) $row['id']) ?>"><button class="button button--outline" type="submit">Delete</button></form></td></tr><?php endforeach; endif; ?></tbody></table></div></section></main></body></html>
