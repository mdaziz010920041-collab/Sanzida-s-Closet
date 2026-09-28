<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$settings = [
    'hero' => ['heading' => '', 'description' => '', 'cta_text' => '', 'cta_url' => '', 'image_path' => '', 'alt_text' => '', 'enabled' => true],
    'announcement' => ['items' => ['Complimentary delivery on orders over ₹ 5,000'], 'enabled' => true],
    'contact' => ['email' => '', 'phone' => '', 'address' => ''],
    'footer' => ['tagline' => '', 'copyright' => ''],
    'social' => ['instagram' => '', 'facebook' => '', 'pinterest' => '', 'tiktok' => ''],
    'newsletter' => ['eyebrow' => '', 'heading' => '', 'description' => '', 'enabled' => true],
];
$banners = [];
$message = '';
$error = '';

function admin_content_upload(?array $file, string $folder = 'content'): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 8 * 1024 * 1024) throw new InvalidArgumentException('Images must be valid files no larger than 8 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize((string) $file['tmp_name']) === false) throw new InvalidArgumentException('Only valid JPG, PNG, or WEBP images are allowed.');
    $directory = UPLOADS_PATH . '/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('The upload directory is unavailable.');
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $name)) throw new RuntimeException('The image could not be saved.');
    return 'uploads/' . $folder . '/' . $name;
}

try {
    $connection = database_connection();
    $admin = admin_require($connection, 'content.manage');
    foreach ($settings as $key => $default) {
        $statement = $connection->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $statement->execute(['key' => 'cms.' . $key]);
        $value = json_decode((string) $statement->fetchColumn(), true);
        if (is_array($value)) $settings[$key] = array_merge($default, $value);
    }

    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Please try again.');
        $action = (string) ($_POST['action'] ?? '');
        if (str_starts_with($action, 'announcement_')) {
            $items = array_values(array_filter(array_map('strval', $settings['announcement']['items'] ?? [])));
            $index = max(0, (int) ($_POST['index'] ?? 0));
            $text = trim((string) ($_POST['text'] ?? ''));
            if ($action === 'announcement_create') {
                if ($text === '') throw new InvalidArgumentException('Announcement text is required.');
                $items[] = $text;
                $message = 'Announcement added.';
            } elseif ($action === 'announcement_update') {
                if ($text === '' || !array_key_exists($index, $items)) throw new InvalidArgumentException('Announcement text is required.');
                $items[$index] = $text;
                $message = 'Announcement updated.';
            } elseif ($action === 'announcement_delete') {
                if (array_key_exists($index, $items)) unset($items[$index]);
                $items = array_values($items);
                $message = 'Announcement deleted.';
            }
            $settings['announcement']['items'] = $items;
            $statement = $connection->prepare("INSERT INTO settings (setting_key, setting_value, is_public, updated_by_admin_id) VALUES ('cms.announcement', :value, 1, :admin_id) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_public = 1, updated_by_admin_id = VALUES(updated_by_admin_id)");
            $statement->execute(['value' => json_encode($settings['announcement'], JSON_THROW_ON_ERROR), 'admin_id' => $admin['id']]);
            admin_audit($connection, (int) $admin['id'], 'content.announcement_' . substr($action, 14), 'setting');
        } elseif ($action === 'setting') {
            $key = (string) ($_POST['key'] ?? '');
            if (!array_key_exists($key, $settings)) throw new InvalidArgumentException('Unknown content section.');
            $value = [];
            foreach ($settings[$key] as $field => $default) $value[$field] = trim((string) ($_POST[$field] ?? ''));
            foreach (['cta_url', 'instagram', 'facebook', 'pinterest', 'tiktok'] as $urlField) if (array_key_exists($urlField, $value)) $value[$urlField] = security_safe_url($value[$urlField]);
            $value['enabled'] = !empty($_POST['enabled']);
            $image = admin_content_upload($_FILES['image'] ?? null);
            if ($image !== null) $value['image_path'] = $image;
            $statement = $connection->prepare("INSERT INTO settings (setting_key, setting_value, is_public, updated_by_admin_id) VALUES (:key, :value, 1, :admin_id) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_public = 1, updated_by_admin_id = VALUES(updated_by_admin_id)");
            $statement->execute(['key' => 'cms.' . $key, 'value' => json_encode($value, JSON_THROW_ON_ERROR), 'admin_id' => $admin['id']]);
            admin_audit($connection, (int) $admin['id'], 'content.setting_update', 'setting', null, ['key' => $key]);
            $message = 'Content section saved.';
        } elseif ($action === 'banner') {
            $image = admin_content_upload($_FILES['image'] ?? null, 'content');
            $mobileImage = admin_content_upload($_FILES['mobile_image'] ?? null, 'content');
            if (!$image) throw new InvalidArgumentException('A desktop banner image is required.');
            $connection->prepare('INSERT INTO banners (title, subtitle, image_path, mobile_image_path, alt_text, link_url, placement, sort_order, status) VALUES (:title, :subtitle, :image_path, :mobile_image_path, :alt_text, :link_url, :placement, :sort_order, :status)')->execute(['title' => trim((string) $_POST['title']), 'subtitle' => trim((string) ($_POST['subtitle'] ?? '')) ?: null, 'image_path' => $image, 'mobile_image_path' => $mobileImage, 'alt_text' => trim((string) $_POST['alt_text']), 'link_url' => security_safe_url((string) ($_POST['link_url'] ?? '')), 'placement' => trim((string) $_POST['placement']), 'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)), 'status' => ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive']);
            admin_audit($connection, (int) $admin['id'], 'content.banner_save', 'banner', (int) $connection->lastInsertId());
            $message = 'Banner saved.';
        }
    }
    $banners = $connection->query('SELECT id, title, image_path, mobile_image_path, placement, sort_order, status FROM banners ORDER BY placement, sort_order, id')->fetchAll();
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = 'Content management is temporarily unavailable.';
}
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Content | Admin</title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="admin-page">
<aside class="admin-sidebar"><a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a><nav aria-label="Admin navigation"><a href="<?= escape_html(base_url('admin/')) ?>">Overview</a><a class="is-active" href="<?= escape_html(base_url('admin/content.php')) ?>">Content</a><a href="<?= escape_html(base_url('admin/products.php')) ?>">Products</a><a href="<?= escape_html(base_url('admin/module.php?name=settings')) ?>">Settings</a></nav></aside>
<main class="admin-main"><header class="admin-topbar"><div><p class="eyebrow">Database-driven CMS</p><h1>Content studio</h1></div><a class="button button--outline" href="<?= escape_html(base_url('/')) ?>" target="_blank" rel="noopener">Preview storefront</a></header>
<?php if ($message !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?>
<section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Storefront top bar</p><h2>Announcement messages</h2><p class="muted">Messages scroll continuously from left to right.</p></div></div><form class="admin-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="announcement_create"><div class="form-grid"><input class="input" name="text" placeholder="Announcement text" maxlength="180" required></div><button class="button" type="submit">Add announcement</button></form><?php foreach (($settings['announcement']['items'] ?? []) as $index => $item): ?><form class="admin-inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="index" value="<?= escape_html((string) $index) ?>"><input class="input" name="text" value="<?= escape_html((string) $item) ?>" maxlength="180" required><button class="button button--outline" name="action" value="announcement_update" type="submit">Update</button><button class="button button--outline" name="action" value="announcement_delete" type="submit">Delete</button></form><?php endforeach; ?></section>
<section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Homepage hero</p><h2>Hero content</h2></div></div><form class="admin-form" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="setting"><input type="hidden" name="key" value="hero"><div class="form-grid"><input class="input" name="heading" value="<?= escape_html($settings['hero']['heading']) ?>" placeholder="Hero heading" required><input class="input" name="cta_text" value="<?= escape_html($settings['hero']['cta_text']) ?>" placeholder="CTA text"><input class="input" name="cta_url" value="<?= escape_html($settings['hero']['cta_url']) ?>" placeholder="CTA URL"><input class="input" name="alt_text" value="<?= escape_html($settings['hero']['alt_text']) ?>" placeholder="Image alt text"></div><textarea class="textarea" name="description" placeholder="Hero description"><?= escape_html($settings['hero']['description']) ?></textarea><label class="field__label">Hero image <input class="input" name="image" type="file" accept="image/jpeg,image/png,image/webp"></label><label class="checkbox"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['hero']['enabled']) ? 'checked' : '' ?>> Enabled</label><button class="button" type="submit">Save hero</button></form></section>
<section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Homepage carousel</p><h2>Banner library</h2><p class="muted">Desktop: 1920x1080. Mobile: 1080x1350. Add multiple active banners for infinite rotation.</p></div></div><form class="admin-form" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="banner"><div class="form-grid"><input class="input" name="title" placeholder="Banner title" required><input class="input" name="subtitle" placeholder="Optional subtitle"><input class="input" name="placement" value="homepage" required><input class="input" name="sort_order" type="number" value="0" min="0" placeholder="Display order"><input class="input" name="alt_text" placeholder="Image alt text" required><input class="input" name="link_url" placeholder="Optional link URL"></div><label class="field__label">Desktop banner · 1920x1080 <input class="input" name="image" type="file" accept="image/jpeg,image/png,image/webp" required></label><label class="field__label">Mobile banner · 1080x1350 <input class="input" name="mobile_image" type="file" accept="image/jpeg,image/png,image/webp"></label><select class="select" name="status"><option value="active">Enabled</option><option value="inactive">Disabled</option></select><button class="button" type="submit">Add banner</button></form><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Title</th><th>Desktop</th><th>Mobile</th><th>Placement</th><th>Status</th></tr></thead><tbody><?php if ($banners === []): ?><tr><td colspan="5">No banners found.</td></tr><?php else: ?><?php foreach ($banners as $banner): ?><tr><td><?= escape_html((string) $banner['title']) ?></td><td><?= escape_html((string) $banner['image_path']) ?></td><td><?= escape_html((string) ($banner['mobile_image_path'] ?? '')) ?></td><td><?= escape_html((string) $banner['placement']) ?></td><td><?= escape_html((string) $banner['status']) ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></section>
</main></body></html>
