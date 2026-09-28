<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_once dirname(__DIR__) . '/includes/orders.php';

$labels = [
    'products' => 'Products', 'categories' => 'Categories', 'orders' => 'Orders',
    'customers' => 'Customers', 'inventory' => 'Inventory', 'coupons' => 'Coupons',
    'reviews' => 'Reviews', 'returns' => 'Returns', 'refunds' => 'Refunds',
    'content' => 'Content', 'seo' => 'SEO', 'settings' => 'Settings', 'audit' => 'Audit logs',
];
$permissions = [
    'products' => 'products.manage', 'categories' => 'categories.manage', 'orders' => 'orders.manage',
    'customers' => 'customers.manage', 'inventory' => 'inventory.manage', 'coupons' => 'coupons.manage',
    'reviews' => 'reviews.manage', 'returns' => 'orders.manage', 'refunds' => 'orders.manage',
    'content' => 'content.manage', 'seo' => 'seo.manage', 'settings' => 'settings.manage', 'audit' => 'audit.view',
];
$module = (string) ($_GET['name'] ?? 'products');
$module = array_key_exists($module, $labels) ? $module : 'products';
$isProductPost = $module === 'products' && request_is_post();
if ($module === 'products' && !$isProductPost) {
    header('Location: ' . base_url('admin/products.php'));
    exit;
}
if ($module === 'seo' && !request_is_post()) {
    header('Location: ' . base_url('admin/seo.php'));
    exit;
}
if ($module === 'settings' && !request_is_post()) {
    header('Location: ' . base_url('admin/settings.php'));
    exit;
}
if ($module === 'coupons' && !request_is_post()) {
    header('Location: ' . base_url('admin/coupons.php'));
    exit;
}
if ($module === 'reviews' && !request_is_post()) {
    header('Location: ' . base_url('admin/reviews.php'));
    exit;
}
if ($module === 'categories' && !request_is_post()) {
    header('Location: ' . base_url('admin/categories.php'));
    exit;
}
$rows = [];
$message = '';
$error = '';

function admin_product_upload(?array $file, string $productName): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 8 * 1024 * 1024) throw new InvalidArgumentException('Product images must be valid files no larger than 8 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize((string) $file['tmp_name']) === false) throw new InvalidArgumentException('Product images must be valid JPG, PNG, or WEBP files.');
    $directory = UPLOADS_PATH . '/products';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('The product upload directory is unavailable.');
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $name)) throw new RuntimeException('The product image could not be saved.');
    return 'uploads/products/' . $name;
}

try {
    $connection = database_connection();
    $admin = admin_require($connection, $permissions[$module]);
    if ($module === 'content' && !request_is_post()) {
        header('Location: ' . base_url('admin/content.php'));
        exit;
    }

    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            throw new InvalidArgumentException('Your session expired. Please try again.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $status = (string) ($_POST['status'] ?? '');

        if ($module === 'products' && $action === 'create') {
            $status = in_array($status, ['draft', 'active', 'archived'], true) ? $status : 'draft';
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = seo_slug((string) ($_POST['slug'] ?? '') ?: $name);
            $basePrice = max(0, (float) ($_POST['base_price'] ?? 0));
            $compareAtPrice = ($_POST['compare_at_price'] ?? '') === '' ? null : max(0, (float) $_POST['compare_at_price']);
            if ($name === '' || $slug === '') throw new InvalidArgumentException('Product name is required.');
            if ($compareAtPrice !== null && $compareAtPrice < $basePrice) throw new InvalidArgumentException('Compare-at price must be greater than or equal to the base price.');
            $connection->beginTransaction();
            $statement = $connection->prepare("INSERT INTO products (name, slug, short_description, description, base_price, compare_at_price, status, is_featured, published_at) VALUES (:name, :slug, :short_description, :description, :base_price, :compare_at_price, :status, :featured, IF(:status_publish = 'active', CURRENT_TIMESTAMP(6), NULL))");
            $statement->execute(['name' => $name, 'slug' => $slug, 'short_description' => trim((string) ($_POST['short_description'] ?? '')) ?: null, 'description' => trim((string) ($_POST['description'] ?? '')) ?: null, 'base_price' => $basePrice, 'compare_at_price' => $compareAtPrice, 'status' => $status, 'status_publish' => $status, 'featured' => !empty($_POST['is_featured']) ? 1 : 0]);
            $id = (int) $connection->lastInsertId();
            $categoryId = max(0, (int) ($_POST['category_id'] ?? 0));
            if ($categoryId > 0) $connection->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (:product_id, :category_id)')->execute(['product_id' => $id, 'category_id' => $categoryId]);
            $variant = $connection->prepare('INSERT INTO product_variants (product_id, sku, status) VALUES (:product_id, :sku, \'active\')');
            $variant->execute(['product_id' => $id, 'sku' => 'SC-' . $id]);
            $variantId = (int) $connection->lastInsertId();
            $connection->prepare('INSERT INTO inventory (variant_id, quantity_on_hand) VALUES (:variant_id, :quantity)')->execute(['variant_id' => $variantId, 'quantity' => max(0, (int) ($_POST['stock'] ?? 0))]);
            if (empty($_FILES['image_front']) || ($_FILES['image_front']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new InvalidArgumentException('A front image is required.');
            $imageUploads = [
                ['key' => 'image_front', 'label' => 'Front', 'sort_order' => 0, 'is_primary' => 1],
                ['key' => 'image_back', 'label' => 'Back', 'sort_order' => 1, 'is_primary' => 0],
                ['key' => 'image_right', 'label' => 'Right side', 'sort_order' => 2, 'is_primary' => 0],
                ['key' => 'image_left', 'label' => 'Left side', 'sort_order' => 3, 'is_primary' => 0],
            ];
            foreach ($imageUploads as $imageUpload) {
                $imagePath = admin_product_upload($_FILES[$imageUpload['key']] ?? null, $name);
                if ($imagePath !== null) $connection->prepare('INSERT INTO product_images (product_id, variant_id, file_path, alt_text, sort_order, is_primary) VALUES (:product_id, :variant_id, :file_path, :alt_text, :sort_order, :is_primary)')->execute(['product_id' => $id, 'variant_id' => $variantId, 'file_path' => $imagePath, 'alt_text' => $name . ' - ' . $imageUpload['label'], 'sort_order' => $imageUpload['sort_order'], 'is_primary' => $imageUpload['is_primary']]);
            }
            $connection->commit();
            admin_audit($connection, (int) $admin['id'], 'product.create', 'product', $id);
            $message = 'Product created.';
        } elseif ($module === 'products' && in_array($action, ['update', 'delete'], true)) {
            if ($id <= 0) throw new InvalidArgumentException('A product is required.');
            if ($action === 'delete') {
                $connection->prepare("UPDATE products SET status = 'archived' WHERE id = :id")->execute(['id' => $id]);
            } else {
                $connection->prepare('UPDATE products SET name = :name, slug = :slug, base_price = :price, status = :status WHERE id = :id')->execute(['name' => trim((string) $_POST['name']), 'slug' => trim((string) $_POST['slug']), 'price' => max(0, (float) $_POST['base_price']), 'status' => in_array($status, ['draft', 'active', 'archived'], true) ? $status : 'draft', 'id' => $id]);
            }
            admin_audit($connection, (int) $admin['id'], 'product.' . $action, 'product', $id);
            $message = 'Product updated.';
        } elseif ($module === 'categories' && $action === 'create') {
            $connection->prepare('INSERT INTO categories (name, slug, description, parent_id, image_path, status) VALUES (:name, :slug, :description, :parent_id, :image_path, :status)')->execute(['name' => trim((string) $_POST['name']), 'slug' => trim((string) $_POST['slug']), 'description' => trim((string) ($_POST['description'] ?? '')) ?: null, 'parent_id' => (int) ($_POST['parent_id'] ?? 0) ?: null, 'image_path' => trim((string) ($_POST['image_path'] ?? '')) ?: null, 'status' => $status === 'inactive' ? 'inactive' : 'active']);
            $id = (int) $connection->lastInsertId();
            admin_audit($connection, (int) $admin['id'], 'category.create', 'category', $id);
            $message = 'Category created.';
        } elseif ($module === 'orders' && in_array($action, ['status', 'payment_status', 'shipping_status'], true)) {
            if ($id <= 0) throw new InvalidArgumentException('An order is required.');
            if ($action === 'status') {
                if (!in_array($status, ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'completed', 'returned'], true)) throw new InvalidArgumentException('Invalid order status.');
                $previousStatus = $connection->prepare('SELECT status FROM orders WHERE id = :id');
                $previousStatus->execute(['id' => $id]);
                $previousStatus = (string) $previousStatus->fetchColumn();
                $connection->beginTransaction();
                $connection->prepare('UPDATE orders SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $id]);
                if ($status === 'cancelled' && $previousStatus !== 'cancelled') release_order_inventory($connection, $id, (int) $admin['id']);
                $connection->commit();
            } elseif ($action === 'payment_status') {
                if (!in_array($status, ['pending', 'authorized', 'paid', 'failed', 'refunded', 'partially_refunded'], true)) throw new InvalidArgumentException('Invalid payment status.');
                $connection->prepare('UPDATE payments SET status = :status WHERE order_id = :id')->execute(['status' => $status, 'id' => $id]);
            } else {
                if (!in_array($status, ['pending', 'packed', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled'], true)) throw new InvalidArgumentException('Invalid shipping status.');
                $shipment = $connection->prepare('SELECT id FROM shipments WHERE order_id = :id ORDER BY id DESC LIMIT 1');
                $shipment->execute(['id' => $id]);
                $shipmentId = (int) $shipment->fetchColumn();
                if ($shipmentId > 0) $connection->prepare('UPDATE shipments SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $shipmentId]);
                else $connection->prepare('INSERT INTO shipments (order_id, status) VALUES (:id, :status)')->execute(['id' => $id, 'status' => $status]);
            }
            admin_audit($connection, (int) $admin['id'], 'order.' . $action, 'order', $id, ['status' => $status]);
            $message = 'Order status updated.';
        } elseif ($module === 'customers' && $action === 'status') {
            if (!in_array($status, ['active', 'inactive', 'suspended'], true)) throw new InvalidArgumentException('Invalid customer status.');
            $connection->prepare('UPDATE users SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $id]);
            admin_audit($connection, (int) $admin['id'], 'customer.status_update', 'user', $id, ['status' => $status]);
            $message = 'Customer status updated.';
        } elseif ($module === 'inventory' && $action === 'adjust') {
            $variantId = max(0, (int) ($_POST['variant_id'] ?? 0));
            $change = (int) ($_POST['quantity_change'] ?? 0);
            $connection->beginTransaction();
            $statement = $connection->prepare('SELECT id, quantity_on_hand, quantity_reserved FROM inventory WHERE variant_id = :variant_id FOR UPDATE');
            $statement->execute(['variant_id' => $variantId]);
            $item = $statement->fetch();
            if (!$item || (int) $item['quantity_on_hand'] + $change < (int) $item['quantity_reserved']) throw new InvalidArgumentException('Adjustment would make available stock negative.');
            $newQuantity = (int) $item['quantity_on_hand'] + $change;
            $connection->prepare('UPDATE inventory SET quantity_on_hand = :quantity WHERE id = :id')->execute(['quantity' => $newQuantity, 'id' => $item['id']]);
            $connection->prepare("INSERT INTO inventory_transactions (variant_id, admin_id, transaction_type, quantity_change, quantity_after, note) VALUES (:variant_id, :admin_id, 'adjustment', :change, :after, :note)")->execute(['variant_id' => $variantId, 'admin_id' => $admin['id'], 'change' => $change, 'after' => $newQuantity - (int) $item['quantity_reserved'], 'note' => trim((string) ($_POST['note'] ?? 'Admin adjustment'))]);
            $connection->commit();
            admin_audit($connection, (int) $admin['id'], 'inventory.adjust', 'variant', $variantId, ['change' => $change]);
            $message = 'Inventory adjusted.';
        } elseif ($module === 'reviews' && in_array($action, ['moderate', 'delete'], true)) {
            if ($action === 'delete') {
                $connection->prepare('DELETE FROM reviews WHERE id = :id')->execute(['id' => $id]);
            } else {
                if (!in_array($status, ['approved', 'rejected', 'pending'], true)) throw new InvalidArgumentException('Invalid review status.');
                $connection->prepare('UPDATE reviews SET status = :status WHERE id = :id')->execute(['status' => $status, 'id' => $id]);
            }
            admin_audit($connection, (int) $admin['id'], 'review.' . $action, 'review', $id, ['status' => $status]);
            $message = 'Review updated.';
        } elseif (in_array($module, ['returns', 'refunds'], true) && $action === 'status') {
            $allowedStatuses = $module === 'returns' ? ['requested', 'approved', 'rejected', 'received', 'inspecting', 'refunded', 'completed', 'cancelled'] : ['pending', 'processed', 'failed', 'cancelled'];
            if (!in_array($status, $allowedStatuses, true)) throw new InvalidArgumentException('Invalid workflow status.');
            $connection->prepare("UPDATE {$module} SET status = :status WHERE id = :id")->execute(['status' => $status, 'id' => $id]);
            admin_audit($connection, (int) $admin['id'], $module . '.status_update', $module, $id, ['status' => $status]);
            $message = ucfirst($module) . ' updated.';
        }
    }

    $search = trim((string) ($_GET['q'] ?? ''));
    $queries = [
        'products' => 'SELECT id, name, slug, base_price, status, updated_at FROM products ORDER BY updated_at DESC LIMIT 100',
        'categories' => 'SELECT id, name, slug, status, parent_id, image_path, updated_at FROM categories ORDER BY sort_order, name LIMIT 100',
        'orders' => 'SELECT o.id, o.order_number, o.email, o.status, o.grand_total, o.currency, o.created_at, (SELECT p.status FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS payment_status, (SELECT s.status FROM shipments s WHERE s.order_id = o.id ORDER BY s.id DESC LIMIT 1) AS shipping_status FROM orders o ORDER BY o.created_at DESC LIMIT 100',
        'customers' => 'SELECT id, email, first_name, last_name, status, created_at FROM users WHERE email LIKE :search OR first_name LIKE :search OR last_name LIKE :search ORDER BY created_at DESC LIMIT 100',
        'inventory' => 'SELECT inventory.variant_id, products.name, product_variants.sku, inventory.quantity_on_hand, inventory.quantity_reserved, inventory.reorder_level FROM inventory INNER JOIN product_variants ON product_variants.id = inventory.variant_id INNER JOIN products ON products.id = product_variants.product_id ORDER BY (inventory.quantity_on_hand - inventory.quantity_reserved <= inventory.reorder_level) DESC, products.name LIMIT 100',
        'coupons' => 'SELECT id, code, discount_type, discount_value, status, starts_at, ends_at FROM coupons ORDER BY created_at DESC LIMIT 100',
        'reviews' => 'SELECT reviews.id, products.name AS product_name, reviews.rating, reviews.status, reviews.created_at FROM reviews INNER JOIN products ON products.id = reviews.product_id ORDER BY reviews.created_at DESC LIMIT 100',
        'returns' => 'SELECT id, return_number, order_id, return_type, status, reason, requested_at FROM returns ORDER BY requested_at DESC LIMIT 100',
        'refunds' => 'SELECT id, order_id, amount, currency, status, reason, created_at FROM refunds ORDER BY created_at DESC LIMIT 100',
        'content' => 'SELECT id, title, slug, status, updated_at FROM pages ORDER BY updated_at DESC LIMIT 100',
        'seo' => 'SELECT id, entity_type, entity_id, meta_title, canonical_url, updated_at FROM seo_metadata ORDER BY updated_at DESC LIMIT 100',
        'settings' => 'SELECT id, setting_key, is_public, updated_at FROM settings ORDER BY setting_key LIMIT 100',
        'audit' => 'SELECT id, admin_id, action, entity_type, entity_id, ip_address, created_at FROM admin_audit_logs ORDER BY created_at DESC LIMIT 150',
    ];
    $statement = $connection->prepare($queries[$module]);
    $module === 'customers' ? $statement->execute(['search' => '%' . $search . '%']) : $statement->execute();
    $rows = $statement->fetchAll();
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
    error_log($exception->getMessage());
    $error = 'Module data is temporarily unavailable.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= escape_html($labels[$module]) ?> | Admin</title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head><body class="admin-page"><aside class="admin-sidebar"><a class="admin-brand" href="<?= escape_html(base_url('admin/')) ?>">SC <span>Admin</span></a><nav aria-label="Admin navigation"><?php foreach ($labels as $key => $label): ?><a class="<?= $key === $module ? 'is-active' : '' ?>" href="<?= escape_html(base_url('admin/module.php?name=' . $key)) ?>"><?= escape_html($label) ?></a><?php endforeach; ?></nav></aside><main class="admin-main"><header class="admin-topbar"><div><p class="eyebrow">Admin module</p><h1><?= escape_html($labels[$module]) ?></h1></div><a class="button button--outline" href="<?= escape_html(base_url('admin/')) ?>">Overview</a></header><?php if ($message !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?><?php if ($module === 'customers'): ?><form class="admin-inline-form" method="get"><input type="hidden" name="name" value="customers"><input class="input" name="q" value="<?= escape_html($search) ?>" placeholder="Search customers" type="search"><button class="button" type="submit">Search</button></form><?php endif; ?><section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><?php foreach (array_keys($rows[0] ?? ['No records' => '']) as $column): ?><th><?= escape_html(ucwords(str_replace('_', ' ', $column))) ?></th><?php endforeach; ?></tr></thead><tbody><?php if ($rows === []): ?><tr><td colspan="12">No records found.</td></tr><?php else: foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?= escape_html((string) ($value ?? '—')) ?></td><?php endforeach; ?></tr><?php endforeach; endif; ?></tbody></table></div></section></main></body></html>
