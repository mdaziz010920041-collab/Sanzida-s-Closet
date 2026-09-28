<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$products = [];
$categories = [];
$error = '';

try {
    $connection = database_connection();
    admin_require($connection, 'products.manage');
    $products = $connection->query('SELECT id, name, slug, base_price, status, updated_at FROM products ORDER BY updated_at DESC LIMIT 100')->fetchAll();
    $categories = $connection->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY sort_order, name")->fetchAll();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'Product data is temporarily unavailable.';
}

$adminLinks = [
    'Overview' => 'admin/',
    'Products' => 'admin/products.php',
    'Categories' => 'admin/module.php?name=categories',
    'Orders' => 'admin/module.php?name=orders',
    'Customers' => 'admin/module.php?name=customers',
    'Inventory' => 'admin/module.php?name=inventory',
    'Coupons' => 'admin/module.php?name=coupons',
    'Reviews' => 'admin/module.php?name=reviews',
    'Returns' => 'admin/module.php?name=returns',
    'Refunds' => 'admin/module.php?name=refunds',
    'Content' => 'admin/content.php',
    'SEO' => 'admin/module.php?name=seo',
    'Settings' => 'admin/module.php?name=settings',
    'Audit logs' => 'admin/module.php?name=audit',
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products | Admin</title>
    <link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>">
</head>
<body class="admin-page">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a>
        <nav aria-label="Admin navigation">
            <?php foreach ($adminLinks as $label => $path): ?><a class="<?= $label === 'Products' ? 'is-active' : '' ?>" href="<?= escape_html(base_url($path)) ?>"><?= escape_html($label) ?></a><?php endforeach; ?>
        </nav>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar">
            <div><p class="eyebrow">Catalogue management</p><h1>Products</h1></div>
            <a class="button button--outline" href="<?= escape_html(base_url('admin/')) ?>">Overview</a>
        </header>
        <?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?>
        <section class="admin-panel">
            <div class="section__header"><div><p class="eyebrow">Add to Shop</p><h2>Create a product</h2></div><span class="badge">Active products appear in Shop</span></div>
            <form class="account-form" method="post" enctype="multipart/form-data" action="<?= escape_html(base_url('admin/module.php?name=products')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="field"><label class="field__label" for="product-name">Product name</label><input class="input" id="product-name" name="name" required maxlength="180"></div>
                    <div class="field"><label class="field__label" for="product-slug">Slug <span class="muted">optional</span></label><input class="input" id="product-slug" name="slug" maxlength="200" placeholder="Generated from name"></div>
                    <div class="field"><label class="field__label" for="product-price">Price</label><input class="input" id="product-price" name="base_price" type="number" min="0" step="0.01" required></div>
                    <div class="field"><label class="field__label" for="product-compare-price">Compare-at price</label><input class="input" id="product-compare-price" name="compare_at_price" type="number" min="0" step="0.01"></div>
                    <div class="field"><label class="field__label" for="product-stock">Initial stock</label><input class="input" id="product-stock" name="stock" type="number" min="0" step="1" value="0"></div>
                    <div class="field"><label class="field__label" for="product-category">Category</label><select class="select" id="product-category" name="category_id"><option value="0">No category</option><?php foreach ($categories as $category): ?><option value="<?= escape_html((string) $category['id']) ?>"><?= escape_html((string) $category['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label class="field__label" for="product-status">Status</label><select class="select" id="product-status" name="status"><option value="active">Active - publish to Shop</option><option value="draft">Draft</option><option value="archived">Archived</option></select></div>
                    <div class="field field--wide"><span class="field__label">Product images <span class="muted">JPG, PNG, or WEBP, max 8 MB each</span></span><div class="form-grid"><div class="field"><label class="field__label" for="product-image-front">Front image <span class="muted">primary</span></label><input class="input" id="product-image-front" name="image_front" type="file" accept="image/jpeg,image/png,image/webp" required></div><div class="field"><label class="field__label" for="product-image-back">Back image</label><input class="input" id="product-image-back" name="image_back" type="file" accept="image/jpeg,image/png,image/webp"></div><div class="field"><label class="field__label" for="product-image-right">Right-side image</label><input class="input" id="product-image-right" name="image_right" type="file" accept="image/jpeg,image/png,image/webp"></div><div class="field"><label class="field__label" for="product-image-left">Left-side image</label><input class="input" id="product-image-left" name="image_left" type="file" accept="image/jpeg,image/png,image/webp"></div></div></div>
                    <div class="field field--wide"><label class="field__label" for="product-short-description">Short description</label><input class="input" id="product-short-description" name="short_description" maxlength="500"></div>
                    <div class="field field--wide"><label class="field__label" for="product-description">Description</label><textarea class="input" id="product-description" name="description" rows="5"></textarea></div>
                </div>
                <label class="check-option"><input type="checkbox" name="is_featured" value="1"> Feature this product</label>
                <button class="button" type="submit">Add product to Shop <span aria-hidden="true">&rarr;</span></button>
            </form>
        </section>
        <section class="admin-panel">
            <div class="section__header"><div><p class="eyebrow">Live catalogue</p><h2>Current products</h2></div></div>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Slug</th><th>Price</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php if ($products === []): ?><tr><td colspan="5">No products found.</td></tr><?php else: ?><?php foreach ($products as $product): ?><tr><td><?= escape_html((string) $product['name']) ?></td><td><?= escape_html((string) $product['slug']) ?></td><td><?= escape_html((string) $product['base_price']) ?></td><td><span class="badge"><?= escape_html((string) $product['status']) ?></span></td><td><?= escape_html((string) $product['updated_at']) ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div>
        </section>
    </main>
</body>
</html>
