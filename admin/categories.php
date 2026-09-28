<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/seo.php';

$rows = [];
$editing = null;
$message = '';
$error = '';

function admin_category_upload(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 8 * 1024 * 1024) throw new InvalidArgumentException('Images must be valid files no larger than 8 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize((string) $file['tmp_name']) === false) throw new InvalidArgumentException('Only valid JPG, PNG, or WEBP images are allowed.');
    $directory = UPLOADS_PATH . '/categories';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('The category upload directory is unavailable.');
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $name)) throw new RuntimeException('The category image could not be saved.');
    return 'uploads/categories/' . $name;
}

ob_start(static function (string $html): string {
    $html = str_replace('<form class="admin-form" method="post">', '<form class="admin-form" method="post" enctype="multipart/form-data">', $html);
    return str_replace('<div class="field"><label class="field__label">Status</label>', '<div class="field"><label class="field__label" for="category-image">Upload category banner</label><input class="input" id="category-image" name="category_image" type="file" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG, or WEBP up to 8 MB. Uploading a file replaces the image path.</small></div><div class="field"><label class="field__label">Status</label>', $html);
});

try {
    $connection = database_connection();
    $admin = admin_require($connection, 'categories.manage');
    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Please try again.');
        $action = (string) ($_POST['action'] ?? '');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = seo_slug((string) ($_POST['slug'] ?? '') ?: $name);
        $description = trim((string) ($_POST['description'] ?? '')) ?: null;
        $imagePath = trim((string) ($_POST['image_path'] ?? '')) ?: null;
        $uploadedImagePath = admin_category_upload($_FILES['category_image'] ?? null);
        if ($uploadedImagePath !== null) $imagePath = $uploadedImagePath;
        $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        if (in_array($action, ['create', 'update'], true)) {
            if ($name === '') throw new InvalidArgumentException('Category name is required.');
            if ($action === 'create') {
                $statement = $connection->prepare('INSERT INTO categories (name, slug, description, image_path, status) VALUES (:name, :slug, :description, :image_path, :status)');
                $statement->execute(['name' => $name, 'slug' => $slug, 'description' => $description, 'image_path' => $imagePath, 'status' => $status]);
                $id = (int) $connection->lastInsertId();
                admin_audit($connection, (int) $admin['id'], 'category.create', 'category', $id);
                $message = 'Category created.';
            } else {
                if ($id <= 0) throw new InvalidArgumentException('A category is required.');
                $statement = $connection->prepare('UPDATE categories SET name = :name, slug = :slug, description = :description, image_path = :image_path, status = :status WHERE id = :id');
                $statement->execute(['name' => $name, 'slug' => $slug, 'description' => $description, 'image_path' => $imagePath, 'status' => $status, 'id' => $id]);
                admin_audit($connection, (int) $admin['id'], 'category.update', 'category', $id);
                $message = 'Category updated.';
            }
        } elseif ($action === 'delete') {
            if ($id <= 0) throw new InvalidArgumentException('A category is required.');
            $connection->prepare("UPDATE categories SET status = 'inactive' WHERE id = :id")->execute(['id' => $id]);
            admin_audit($connection, (int) $admin['id'], 'category.deactivate', 'category', $id);
            $message = 'Category deactivated.';
        }
    }
    $rows = $connection->query('SELECT c.*, COUNT(DISTINCT pc.product_id) AS product_count FROM categories c LEFT JOIN product_categories pc ON pc.category_id = c.id LEFT JOIN products p ON p.id = pc.product_id AND p.status = \'active\' GROUP BY c.id ORDER BY c.sort_order, c.name')->fetchAll();
    $editId = max(0, (int) ($_GET['edit'] ?? 0));
    if ($editId > 0) { $statement = $connection->prepare('SELECT * FROM categories WHERE id = :id'); $statement->execute(['id' => $editId]); $editing = $statement->fetch() ?: null; }
} catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); } catch (Throwable $exception) { error_log($exception->getMessage()); $error = str_contains($exception->getMessage(), 'Duplicate entry') ? 'That category name or slug already exists.' : 'Category management is temporarily unavailable.'; }
$links = ['Overview' => 'admin/', 'Products' => 'admin/products.php', 'Categories' => 'admin/categories.php', 'Orders' => 'admin/module.php?name=orders', 'Customers' => 'admin/module.php?name=customers', 'Inventory' => 'admin/module.php?name=inventory', 'Coupons' => 'admin/coupons.php', 'Reviews' => 'admin/reviews.php', 'Content' => 'admin/content.php', 'Banners' => 'admin/banners.php', 'SEO' => 'admin/seo.php', 'Settings' => 'admin/settings.php'];
$value = static fn (string $key): string => escape_html((string) ($GLOBALS['editing'][$key] ?? ''));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Categories | Admin</title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head><body class="admin-page"><aside class="admin-sidebar"><a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a><nav aria-label="Admin navigation"><?php foreach ($links as $label => $path): ?><a class="<?= $label === 'Categories' ? 'is-active' : '' ?>" href="<?= escape_html(base_url($path)) ?>"><?= escape_html($label) ?></a><?php endforeach; ?></nav></aside><main class="admin-main"><header class="admin-topbar"><div><p class="eyebrow">Catalogue structure</p><h1>Categories</h1></div><a class="button button--outline" href="<?= escape_html(base_url('admin/')) ?>">Overview</a></header><?php if ($message !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?><section class="admin-panel"><div class="section__header"><div><p class="eyebrow"><?= $editing ? 'Edit category' : 'New category' ?></p><h2><?= $editing ? 'Update category' : 'Create category' ?></h2></div></div><form class="admin-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= $value('id') ?>"><?php endif; ?><div class="form-grid"><div class="field"><label class="field__label">Name</label><input class="input" name="name" value="<?= $value('name') ?>" required></div><div class="field"><label class="field__label">Slug</label><input class="input" name="slug" value="<?= $value('slug') ?>" placeholder="Generated from name"></div><div class="field field--wide"><label class="field__label">Description</label><textarea class="textarea" name="description" rows="3"><?= $value('description') ?></textarea></div><div class="field field--wide"><label class="field__label">Category image path</label><input class="input" name="image_path" value="<?= $value('image_path') ?>" placeholder="uploads/categories/dresses-editorial.svg"></div><div class="field"><label class="field__label">Status</label><select class="select" name="status"><option value="active" <?= ($editing['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($editing['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div></div><button class="button" type="submit"><?= $editing ? 'Update category' : 'Create category' ?></button></form></section><section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Collection library</p><h2>All categories</h2></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Products</th><th>Image</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><strong><?= escape_html((string) $row['name']) ?></strong><br><span class="muted"><?= escape_html((string) $row['slug']) ?></span></td><td><?= escape_html((string) $row['product_count']) ?></td><td><?= escape_html((string) ($row['image_path'] ?: '—')) ?></td><td><?= escape_html(ucfirst((string) $row['status'])) ?></td><td><a class="button button--outline" href="<?= escape_html(base_url('admin/categories.php?edit=' . (int) $row['id'])) ?>">Edit</a><?php if ($row['status'] === 'active'): ?><form class="admin-inline-form" method="post" onsubmit="return confirm('Deactivate this category?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="button button--outline" type="submit">Deactivate</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
