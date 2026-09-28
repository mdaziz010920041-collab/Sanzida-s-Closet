<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/seo.php';

$entities = [];
$rows = [];
$editing = null;
$message = '';
$error = '';

try {
    $connection = database_connection();
    $admin = admin_require($connection, 'seo.manage');
    $entities = [
        'product' => $connection->query("SELECT id, name AS label, slug FROM products WHERE status = 'active' ORDER BY name LIMIT 200")->fetchAll(),
        'category' => $connection->query("SELECT id, name AS label, slug FROM categories WHERE status = 'active' ORDER BY sort_order, name LIMIT 100")->fetchAll(),
        'brand' => $connection->query("SELECT id, name AS label, slug FROM brands WHERE status = 'active' ORDER BY name LIMIT 100")->fetchAll(),
        'page' => $connection->query("SELECT id, title AS label, slug FROM pages WHERE status <> 'archived' ORDER BY title LIMIT 100")->fetchAll(),
    ];

    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Please try again.');
        $action = (string) ($_POST['action'] ?? '');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($action === 'delete') {
            if ($id <= 0) throw new InvalidArgumentException('A metadata record is required.');
            $connection->prepare('DELETE FROM seo_metadata WHERE id = :id')->execute(['id' => $id]);
            admin_audit($connection, (int) $admin['id'], 'seo.delete', 'seo_metadata', $id);
            $message = 'SEO metadata deleted.';
        } elseif (in_array($action, ['create', 'update'], true)) {
            $entityType = (string) ($_POST['entity_type'] ?? '');
            $entityId = max(0, (int) ($_POST['entity_id'] ?? 0));
            $structuredData = trim((string) ($_POST['structured_data'] ?? ''));
            if (!isset($entities[$entityType]) || $entityId <= 0) throw new InvalidArgumentException('Choose a valid content item.');
            if ($structuredData !== '') {
                json_decode($structuredData, true, 512, JSON_THROW_ON_ERROR);
            }
            $values = [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'meta_title' => trim((string) ($_POST['meta_title'] ?? '')) ?: null,
                'meta_description' => trim((string) ($_POST['meta_description'] ?? '')) ?: null,
                'meta_keywords' => trim((string) ($_POST['meta_keywords'] ?? '')) ?: null,
                'canonical_url' => trim((string) ($_POST['canonical_url'] ?? '')) ?: null,
                'og_image_path' => trim((string) ($_POST['og_image_path'] ?? '')) ?: null,
                'structured_data' => $structuredData !== '' ? $structuredData : null,
            ];
            if ($action === 'create') {
                $statement = $connection->prepare('INSERT INTO seo_metadata (entity_type, entity_id, meta_title, meta_description, meta_keywords, canonical_url, og_image_path, structured_data) VALUES (:entity_type, :entity_id, :meta_title, :meta_description, :meta_keywords, :canonical_url, :og_image_path, :structured_data)');
                $statement->execute($values);
                $id = (int) $connection->lastInsertId();
                admin_audit($connection, (int) $admin['id'], 'seo.create', 'seo_metadata', $id);
                $message = 'SEO metadata created.';
            } else {
                if ($id <= 0) throw new InvalidArgumentException('A metadata record is required.');
                $values['id'] = $id;
                $statement = $connection->prepare('UPDATE seo_metadata SET entity_type = :entity_type, entity_id = :entity_id, meta_title = :meta_title, meta_description = :meta_description, meta_keywords = :meta_keywords, canonical_url = :canonical_url, og_image_path = :og_image_path, structured_data = :structured_data WHERE id = :id');
                $statement->execute($values);
                admin_audit($connection, (int) $admin['id'], 'seo.update', 'seo_metadata', $id);
                $message = 'SEO metadata updated.';
            }
        }
    }

    $rows = $connection->query("SELECT seo.id, seo.entity_type, seo.entity_id, seo.meta_title, seo.canonical_url, seo.updated_at, CASE seo.entity_type WHEN 'product' THEN products.name WHEN 'category' THEN categories.name WHEN 'brand' THEN brands.name WHEN 'page' THEN pages.title END AS entity_label FROM seo_metadata seo LEFT JOIN products ON seo.entity_type = 'product' AND products.id = seo.entity_id LEFT JOIN categories ON seo.entity_type = 'category' AND categories.id = seo.entity_id LEFT JOIN brands ON seo.entity_type = 'brand' AND brands.id = seo.entity_id LEFT JOIN pages ON seo.entity_type = 'page' AND pages.id = seo.entity_id ORDER BY seo.updated_at DESC, seo.id DESC LIMIT 200")->fetchAll();
    $editId = max(0, (int) ($_GET['edit'] ?? 0));
    if ($editId > 0) {
        $statement = $connection->prepare('SELECT * FROM seo_metadata WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editId]);
        $editing = $statement->fetch() ?: null;
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $error = str_contains($exception->getMessage(), 'Duplicate entry') ? 'SEO metadata already exists for that content item.' : 'SEO management is temporarily unavailable.';
}

$adminLinks = [
    'Overview' => 'admin/', 'Products' => 'admin/products.php', 'Categories' => 'admin/module.php?name=categories',
    'Orders' => 'admin/module.php?name=orders', 'Customers' => 'admin/module.php?name=customers', 'Inventory' => 'admin/module.php?name=inventory',
    'Coupons' => 'admin/module.php?name=coupons', 'Reviews' => 'admin/module.php?name=reviews', 'Returns' => 'admin/module.php?name=returns',
    'Refunds' => 'admin/module.php?name=refunds', 'Content' => 'admin/content.php', 'SEO' => 'admin/seo.php',
    'Settings' => 'admin/module.php?name=settings', 'Audit logs' => 'admin/module.php?name=audit',
];
$selectedType = (string) ($editing['entity_type'] ?? 'product');
$selectedId = (int) ($editing['entity_id'] ?? 0);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SEO | Admin</title>
    <link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>">
</head>
<body class="admin-page">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a>
        <nav aria-label="Admin navigation"><?php foreach ($adminLinks as $label => $path): ?><a class="<?= $label === 'SEO' ? 'is-active' : '' ?>" href="<?= escape_html(base_url($path)) ?>"><?= escape_html($label) ?></a><?php endforeach; ?></nav>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar"><div><p class="eyebrow">Search appearance</p><h1>SEO</h1></div><a class="button button--outline" href="<?= escape_html(base_url('admin/')) ?>">Overview</a></header>
        <?php if ($message !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($message) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?>
        <section class="admin-panel" aria-labelledby="seo-form-title">
            <div class="section__header"><div><p class="eyebrow"><?= $editing ? 'Edit record' : 'New record' ?></p><h2 id="seo-form-title"><?= $editing ? 'Update metadata' : 'Add metadata' ?></h2></div></div>
            <form class="admin-form" method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>"><?php if ($editing): ?><input type="hidden" name="id" value="<?= escape_html((string) $editing['id']) ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="field"><label class="field__label" for="seo-entity-type">Content type</label><select class="select" id="seo-entity-type" name="entity_type" required><?php foreach ($entities as $type => $items): ?><option value="<?= escape_html($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= escape_html(ucfirst($type)) ?> (<?= count($items) ?>)</option><?php endforeach; ?></select></div>
                    <div class="field"><label class="field__label" for="seo-entity-id">Content item</label><select class="select" id="seo-entity-id" name="entity_id" required><?php foreach ($entities as $type => $items): ?><optgroup label="<?= escape_html(ucfirst($type)) ?>"><?php foreach ($items as $item): ?><option data-entity-type="<?= escape_html($type) ?>" value="<?= escape_html((string) $item['id']) ?>" <?= $selectedType === $type && $selectedId === (int) $item['id'] ? 'selected' : '' ?>><?= escape_html((string) $item['label']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
                    <div class="field field--wide"><label class="field__label" for="meta-title">Meta title <span class="muted">Recommended: 50-60 characters</span></label><input class="input" id="meta-title" name="meta_title" maxlength="180" value="<?= escape_html((string) ($editing['meta_title'] ?? '')) ?>" required></div>
                    <div class="field field--wide"><label class="field__label" for="meta-description">Meta description <span class="muted">Recommended: 120-160 characters</span></label><textarea class="textarea" id="meta-description" name="meta_description" maxlength="320" rows="4" required><?= escape_html((string) ($editing['meta_description'] ?? '')) ?></textarea></div>
                    <div class="field field--wide"><label class="field__label" for="meta-keywords">Keywords</label><input class="input" id="meta-keywords" name="meta_keywords" value="<?= escape_html((string) ($editing['meta_keywords'] ?? '')) ?>"></div>
                    <div class="field field--wide"><label class="field__label" for="canonical-url">Canonical URL</label><input class="input" id="canonical-url" name="canonical_url" type="url" value="<?= escape_html((string) ($editing['canonical_url'] ?? '')) ?>"></div>
                    <div class="field field--wide"><label class="field__label" for="og-image-path">Social image path</label><input class="input" id="og-image-path" name="og_image_path" value="<?= escape_html((string) ($editing['og_image_path'] ?? '')) ?>"></div>
                    <div class="field field--wide"><label class="field__label" for="structured-data">Structured data JSON-LD</label><textarea class="textarea" id="structured-data" name="structured_data" rows="7"><?= escape_html((string) ($editing['structured_data'] ?? '')) ?></textarea></div>
                </div>
                <div class="admin-inline-form"><button class="button" type="submit"><?= $editing ? 'Update metadata' : 'Create metadata' ?></button><?php if ($editing): ?><a class="button button--outline" href="<?= escape_html(base_url('admin/seo.php')) ?>">Cancel</a><?php endif; ?></div>
            </form>
        </section>
        <section class="admin-panel"><div class="section__header"><div><p class="eyebrow">Search index coverage</p><h2>Metadata records</h2></div><span class="badge"><?= count($rows) ?> records</span></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Entity</th><th>Meta title</th><th>Canonical URL</th><th>Updated</th><th>Actions</th></tr></thead><tbody><?php if ($rows === []): ?><tr><td colspan="5">No records found.</td></tr><?php else: foreach ($rows as $row): ?><tr><td><?= escape_html(ucfirst((string) $row['entity_type'])) ?><br><span class="muted"><?= escape_html((string) ($row['entity_label'] ?: ('#' . $row['entity_id']))) ?></span></td><td><?= escape_html((string) ($row['meta_title'] ?: '—')) ?></td><td><?= escape_html((string) ($row['canonical_url'] ?: '—')) ?></td><td><?= escape_html((string) $row['updated_at']) ?></td><td><a class="button button--outline" href="<?= escape_html(base_url('admin/seo.php?edit=' . (int) $row['id'])) ?>">Edit</a><form method="post" class="admin-inline-form" onsubmit="return confirm('Delete this SEO record?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= escape_html((string) $row['id']) ?>"><button class="button button--outline" type="submit">Delete</button></form></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
    </main>
    <script>
        const typeSelect = document.querySelector('#seo-entity-type');
        const itemSelect = document.querySelector('#seo-entity-id');
        const syncEntityOptions = () => {
            const type = typeSelect.value;
            [...itemSelect.options].forEach((option) => {
                option.hidden = option.dataset.entityType !== type;
            });
            const selected = [...itemSelect.options].find((option) => option.dataset.entityType === type && !option.hidden);
            if (selected && !selected.selected) itemSelect.value = selected.value;
        };
        typeSelect?.addEventListener('change', syncEntityOptions);
        syncEntityOptions();
    </script>
</body>
</html>
