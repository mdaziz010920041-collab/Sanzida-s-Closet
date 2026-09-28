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
    $admin = admin_require($connection, 'content.manage');
    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Please try again.');
        $action = (string) ($_POST['action'] ?? '');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($action === 'delete') {
            $connection->prepare("UPDATE banners SET status = 'inactive' WHERE id = :id")->execute(['id' => $id]);
            admin_audit($connection, (int) $admin['id'], 'banner.deactivate', 'banner', $id);
            $message = 'Banner deactivated.';
        } elseif (in_array($action, ['create', 'update'], true)) {
            $title = trim((string) ($_POST['title'] ?? ''));
            $subtitle = trim((string) ($_POST['subtitle'] ?? '')) ?: null;
            $imagePath = trim((string) ($_POST['image_path'] ?? ''));
            $mobileImagePath = trim((string) ($_POST['mobile_image_path'] ?? '')) ?: null;
            $altText = trim((string) ($_POST['alt_text'] ?? ''));
            $linkUrl = trim((string) ($_POST['link_url'] ?? '')) ?: null;
            $placement = trim((string) ($_POST['placement'] ?? 'homepage')) ?: 'homepage';
            $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));
            $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
            if ($title === '' || $imagePath === '' || $altText === '') throw new InvalidArgumentException('Title, desktop image path, and alt text are required.');
            $linkUrl = $linkUrl !== null ? security_safe_url($linkUrl) : null;
            if ($action === 'create') {
                $statement = $connection->prepare('INSERT INTO banners (title, subtitle, image_path, mobile_image_path, alt_text, link_url, placement, sort_order, status) VALUES (:title, :subtitle, :image_path, :mobile_image_path, :alt_text, :link_url, :placement, :sort_order, :status)');
                $statement->execute(['title' => $title, 'subtitle' => $subtitle, 'image_path' => $imagePath, 'mobile_image_path' => $mobileImagePath, 'alt_text' => $altText, 'link_url' => $linkUrl, 'placement' => $placement, 'sort_order' => $sortOrder, 'status' => $status]);
                $id = (int) $connection->lastInsertId();
                admin_audit($connection, (int) $admin['id'], 'banner.create', 'banner', $id);
                $message = 'Banner created.';
            } else {
                if ($id <= 0) throw new InvalidArgumentException('A banner is required.');
                $statement = $connection->prepare('UPDATE banners SET title = :title, subtitle = :subtitle, image_path = :image_path, mobile_image_path = :mobile_image_path, alt_text = :alt_text, link_url = :link_url, placement = :placement, sort_order = :sort_order, status = :status WHERE id = :id');
                $statement->execute(['title' => $title, 'subtitle' => $subtitle, 'image_path' => $imagePath, 'mobile_image_path' => $mobileImagePath, 'alt_text' => $altText, 'link_url' => $linkUrl, 'placement' => $placement, 'sort_order' => $sortOrder, 'status' => $status, 'id' => $id]);
                admin_audit($connection, (int) $admin['id'], 'banner.update', 'banner', $id);
                $message = 'Banner updated.';
            }
        }
    }
    $rows = $connection->query('SELECT * FROM banners ORDER BY placement, sort_order, id')->fetchAll();
    $editId = max(0, (int) ($_GET['edit'] ?? 0));
    if ($editId > 0) { $statement = $connection->prepare('SELECT * FROM banners WHERE id = :id'); $statement->execute(['id' => $editId]); $editing = $statement->fetch() ?: null; }
} catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); } catch (Throwable $exception) { error_log($exception->getMessage()); $error = 'Banner management is temporarily unavailable.'; }
$links = ['Overview' => 'admin/', 'Products' => 'admin/products.php', 'Categories' => 'admin/categories.php', 'Orders' => 'admin/module.php?name=orders', 'Customers' => 'admin/module.php?name=customers', 'Inventory' => 'admin/module.php?name=inventory', 'Coupons' => 'admin/coupons.php', 'Reviews' => 'admin/reviews.php', 'Content' => 'admin/content.php', 'Banners' => 'admin/banners.php', 'SEO' => 'admin/seo.php', 'Settings' => 'admin/settings.php'];
$value = static fn (string $key): string => escape_html((string) ($GLOBALS['editing'][$key] ?? ''));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Banners | Admin</title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head><body class="admin-page"><aside class="admin-sidebar"><a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a><nav aria-label="Admin navigation"><?php foreach ($links as $label => $path): ?><a class="<?= $label === 'Banners' ? 'is-active' : '' ?>" href="<?= escape_html(base_url($path)) ?>"><?= escape_html($label) ?></a><?php endforeach; ?></nav></aside><main class="admin-main"><header class="admin-topbar"><div><p class="eyebrow">Visual content</p><h1>Banners</h1></div><a class="button button--outline" href="<?= escape_html(base_url('admin/content.php')) ?>">Content studio</a></header><?php if ($message !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?><section class="admin-panel"><div class="section__header"><div><p class="eyebrow"><?= $editing ? 'Edit banner' : 'New banner' ?></p><h2><?= $editing ? 'Update banner' : 'Create banner' ?></h2></div></div><form class="admin-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= $value('id') ?>"><?php endif; ?><div class="form-grid"><div class="field"><label class="field__label">Title</label><input class="input" name="title" value="<?= $value('title') ?>" required></div><div class="field"><label class="field__label">Placement</label><input class="input" name="placement" value="<?= $value('placement') ?: 'homepage' ?>" required></div><div class="field field--wide"><label class="field__label">Subtitle</label><input class="input" name="subtitle" value="<?= $value('subtitle') ?>"></div><div class="field field--wide"><label class="field__label">Desktop image path</label><input class="input" name="image_path" value="<?= $value('image_path') ?>" placeholder="uploads/content/banner.webp" required></div><div class="field field--wide"><label class="field__label">Mobile image path</label><input class="input" name="mobile_image_path" value="<?= $value('mobile_image_path') ?>" placeholder="uploads/content/banner-mobile.webp"></div><div class="field"><label class="field__label">Alt text</label><input class="input" name="alt_text" value="<?= $value('alt_text') ?>" required></div><div class="field"><label class="field__label">Link URL</label><input class="input" name="link_url" value="<?= $value('link_url') ?>"></div><div class="field"><label class="field__label">Sort order</label><input class="input" name="sort_order" type="number" min="0" value="<?= $value('sort_order') ?: '0' ?>"></div><div class="field"><label class="field__label">Status</label><select class="select" name="status"><option value="active" <?= ($editing['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($editing['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div></div><button class="button" type="submit"><?= $editing ? 'Update banner' : 'Create banner' ?></button></form></section><section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Banner library</p><h2>All banners</h2></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Title</th><th>Placement</th><th>Desktop</th><th>Mobile</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php if ($rows === []): ?><tr><td colspan="6">No banners found.</td></tr><?php else: foreach ($rows as $row): ?><tr><td><?= escape_html((string) $row['title']) ?><br><span class="muted"><?= escape_html((string) $row['alt_text']) ?></span></td><td><?= escape_html((string) $row['placement']) ?></td><td><?= escape_html((string) $row['image_path']) ?></td><td><?= escape_html((string) ($row['mobile_image_path'] ?: '—')) ?></td><td><?= escape_html(ucfirst((string) $row['status'])) ?></td><td><a class="button button--outline" href="<?= escape_html(base_url('admin/banners.php?edit=' . (int) $row['id'])) ?>">Edit</a><?php if ($row['status'] === 'active'): ?><form class="admin-inline-form" method="post" onsubmit="return confirm('Deactivate this banner?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="button button--outline" type="submit">Deactivate</button></form><?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section></main></body></html>
