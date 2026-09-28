<?php

declare(strict_types=1);

require_once __DIR__ . '/shipping.php';

function cart_guest_token(): string
{
    start_secure_session();
    if (!isset($_SESSION['cart_guest_token'])) {
        $_SESSION['cart_guest_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['cart_guest_token'];
}

function cart_active_id(PDO $connection, ?int $userId = null): int
{
    $params = ['status' => 'active'];
    $where = 'user_id IS NULL AND session_token = :session_token';
    if ($userId !== null) {
        $where = 'user_id = :user_id';
        $params['user_id'] = $userId;
    } else {
        $params['session_token'] = cart_guest_token();
    }

    $statement = $connection->prepare("SELECT id FROM carts WHERE {$where} AND status = :status ORDER BY id DESC LIMIT 1");
    $statement->execute($params);
    $id = $statement->fetchColumn();
    if ($id) {
        return (int) $id;
    }

    $statement = $connection->prepare('INSERT INTO carts (user_id, session_token, status, expires_at) VALUES (:user_id, :session_token, \'active\', UTC_TIMESTAMP() + INTERVAL 90 DAY)');
        $statement->execute(['user_id' => $userId, 'session_token' => $userId === null ? cart_guest_token() : bin2hex(random_bytes(32))]);
    return (int) $connection->lastInsertId();
}

function cart_merge_guest_into_user(PDO $connection, int $userId): void
{
    $guestToken = cart_guest_token();
    $guest = $connection->prepare("SELECT id FROM carts WHERE user_id IS NULL AND session_token = :session_token AND status = 'active' LIMIT 1");
    $guest->execute(['session_token' => $guestToken]);
    $guestId = (int) ($guest->fetchColumn() ?: 0);
    if ($guestId <= 0) {
        return;
    }

    $userCartId = cart_active_id($connection, $userId);
    $items = $connection->prepare('SELECT variant_id, quantity FROM cart_items WHERE cart_id = :cart_id');
    $items->execute(['cart_id' => $guestId]);
    foreach ($items->fetchAll() as $item) {
        try {
            cart_add_item($connection, $userCartId, (int) $item['variant_id'], (int) $item['quantity']);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }
    $connection->prepare("UPDATE carts SET status = 'converted' WHERE id = :id")->execute(['id' => $guestId]);
}

function cart_price_expression(string $alias = 'p'): string
{
    return "CASE WHEN {$alias}.discount_type = 'percentage' THEN GREATEST({$alias}.base_price - ({$alias}.base_price * {$alias}.discount_value / 100), 0) WHEN {$alias}.discount_type = 'fixed' THEN GREATEST({$alias}.base_price - {$alias}.discount_value, 0) ELSE {$alias}.base_price END";
}

function cart_item_snapshot(PDO $connection, int $variantId): ?array
{
    $priceExpression = cart_price_expression();
    $statement = $connection->prepare("SELECT v.id AS variant_id, v.product_id, v.sku, v.price_override, p.name, p.slug, p.currency, image.file_path AS image_path, image.alt_text AS image_alt, size.name AS size_name, color.name AS color_name, COALESCE(GREATEST(inventory.quantity_on_hand - inventory.quantity_reserved, 0), 0) AS available_stock, {$priceExpression} AS product_price FROM product_variants v INNER JOIN products p ON p.id = v.product_id AND p.status = 'active' LEFT JOIN inventory ON inventory.variant_id = v.id LEFT JOIN sizes size ON size.id = v.size_id LEFT JOIN colors color ON color.id = v.color_id LEFT JOIN product_images image ON image.product_id = p.id AND image.is_primary = 1 WHERE v.id = :variant_id AND v.status = 'active' LIMIT 1");
    $statement->execute(['variant_id' => $variantId]);
    $item = $statement->fetch();
    if (!$item) {
        return null;
    }
    $item['unit_price'] = $item['price_override'] !== null ? (float) $item['price_override'] : (float) $item['product_price'];
    $item['available_stock'] = (int) $item['available_stock'];
    return $item;
}

function cart_add_item(PDO $connection, int $cartId, int $variantId, int $quantity): array
{
    $quantity = max(1, min(99, $quantity));
    $item = cart_item_snapshot($connection, $variantId);
    if ($item === null) {
        throw new InvalidArgumentException('This product variant is unavailable.');
    }

    $existing = $connection->prepare('SELECT quantity FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id');
    $existing->execute(['cart_id' => $cartId, 'variant_id' => $variantId]);
    $newQuantity = (int) ($existing->fetchColumn() ?: 0) + $quantity;
    if ($newQuantity > $item['available_stock']) {
        throw new InvalidArgumentException('Only ' . $item['available_stock'] . ' available.');
    }

    $statement = $connection->prepare('INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (:cart_id, :variant_id, :quantity) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP(6)');
    $statement->execute(['cart_id' => $cartId, 'variant_id' => $variantId, 'quantity' => $newQuantity]);
    return ['item' => $item, 'quantity' => $newQuantity];
}

function cart_update_item(PDO $connection, int $cartId, int $variantId, int $quantity): void
{
    if ($quantity <= 0) {
        cart_remove_item($connection, $cartId, $variantId);
        return;
    }
    $item = cart_item_snapshot($connection, $variantId);
    if ($item === null || $quantity > $item['available_stock']) {
        throw new InvalidArgumentException('The requested quantity is not available.');
    }
    $statement = $connection->prepare('UPDATE cart_items SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP(6) WHERE cart_id = :cart_id AND variant_id = :variant_id');
    $statement->execute(['quantity' => min(99, $quantity), 'cart_id' => $cartId, 'variant_id' => $variantId]);
}

function cart_remove_item(PDO $connection, int $cartId, int $variantId): void
{
    $statement = $connection->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id');
    $statement->execute(['cart_id' => $cartId, 'variant_id' => $variantId]);
}

function cart_validate_coupon(PDO $connection, string $code, float $subtotal, ?int $userId = null): ?array
{
    $statement = $connection->prepare("SELECT * FROM coupons WHERE code = :code AND status = 'active' AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP()) LIMIT 1");
    $statement->execute(['code' => strtoupper(trim($code))]);
    $coupon = $statement->fetch();
    if (!$coupon || $subtotal < (float) $coupon['minimum_order_amount']) {
        return null;
    }
    $usageStatement = $connection->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = :coupon_id');
    $usageStatement->execute(['coupon_id' => $coupon['id']]);
    if ($coupon['usage_limit'] !== null && (int) $usageStatement->fetchColumn() >= (int) $coupon['usage_limit']) return null;
    if ($userId !== null && $coupon['usage_limit_per_user'] !== null) {
        $userUsage = $connection->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = :coupon_id AND user_id = :user_id');
        $userUsage->execute(['coupon_id' => $coupon['id'], 'user_id' => $userId]);
        if ((int) $userUsage->fetchColumn() >= (int) $coupon['usage_limit_per_user']) return null;
    }
    $discount = $coupon['discount_type'] === 'percentage' ? $subtotal * ((float) $coupon['discount_value'] / 100) : (float) $coupon['discount_value'];
    if ($coupon['maximum_discount_amount'] !== null) $discount = min($discount, (float) $coupon['maximum_discount_amount']);
    return ['id' => (int) $coupon['id'], 'code' => $coupon['code'], 'discount' => min($subtotal, max(0, $discount))];
}

function cart_subtotal(PDO $connection, int $cartId): float
{
    $statement = $connection->prepare("SELECT COALESCE(SUM(item.quantity * COALESCE(v.price_override, CASE WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0) WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0) ELSE p.base_price END)), 0) FROM cart_items item INNER JOIN product_variants v ON v.id = item.variant_id INNER JOIN products p ON p.id = v.product_id AND p.status = 'active' WHERE item.cart_id = :cart_id");
    $statement->execute(['cart_id' => $cartId]);
    return (float) $statement->fetchColumn();
}

function cart_summary(PDO $connection, ?int $userId = null): array
{
    $cartId = cart_active_id($connection, $userId);
    $statement = $connection->prepare("SELECT item.variant_id, item.quantity, p.name, p.slug, p.currency, image.file_path AS image_path, image.alt_text AS image_alt, size.name AS size_name, color.name AS color_name, CASE WHEN p.discount_type = 'percentage' THEN GREATEST(p.base_price - (p.base_price * p.discount_value / 100), 0) WHEN p.discount_type = 'fixed' THEN GREATEST(p.base_price - p.discount_value, 0) ELSE p.base_price END AS product_price, v.price_override, COALESCE(GREATEST(inventory.quantity_on_hand - inventory.quantity_reserved, 0), 0) AS available_stock FROM cart_items item INNER JOIN product_variants v ON v.id = item.variant_id INNER JOIN products p ON p.id = v.product_id AND p.status = 'active' LEFT JOIN inventory ON inventory.variant_id = v.id LEFT JOIN sizes size ON size.id = v.size_id LEFT JOIN colors color ON color.id = v.color_id LEFT JOIN product_images image ON image.product_id = p.id AND image.is_primary = 1 WHERE item.cart_id = :cart_id ORDER BY item.created_at DESC");
    $statement->execute(['cart_id' => $cartId]);
    $items = [];
    $subtotal = 0.0;
    foreach ($statement->fetchAll() as $item) {
        $item['unit_price'] = $item['price_override'] !== null ? (float) $item['price_override'] : (float) $item['product_price'];
        $item['line_total'] = $item['unit_price'] * (int) $item['quantity'];
        $item['available_stock'] = (int) $item['available_stock'];
        $subtotal += $item['line_total'];
        $items[] = $item;
    }
    $couponCode = '';
    $couponId = null;
    $coupon = $connection->prepare('SELECT coupon_id FROM carts WHERE id = :id');
    $coupon->execute(['id' => $cartId]);
    $couponId = $coupon->fetchColumn() ?: null;
    if ($couponId) {
        $couponStatement = $connection->prepare('SELECT code FROM coupons WHERE id = :id');
        $couponStatement->execute(['id' => $couponId]);
        $couponCode = (string) ($couponStatement->fetchColumn() ?: '');
    }
    $cartOwner = $connection->prepare('SELECT user_id FROM carts WHERE id = :id');
    $cartOwner->execute(['id' => $cartId]);
    $cartUserId = $cartOwner->fetchColumn();
    $couponData = $couponCode !== '' ? cart_validate_coupon($connection, $couponCode, $subtotal, $cartUserId ? (int) $cartUserId : null) : null;
    $discount = $couponData['discount'] ?? 0.0;
    $shipping = 0.0;
    try {
        $shippingMethods = shipping_methods($connection);
        if ($shippingMethods !== []) $shipping = shipping_method($connection, (string) $shippingMethods[0]['code'], $subtotal)['amount'];
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }
    return ['cart_id' => $cartId, 'items' => $items, 'item_count' => array_sum(array_column($items, 'quantity')), 'subtotal' => $subtotal, 'discount' => $discount, 'shipping' => $shipping, 'total' => max(0, $subtotal - $discount + $shipping), 'coupon' => $couponData];
}

function cart_apply_coupon(PDO $connection, int $cartId, string $code): array
{
    $owner = $connection->prepare('SELECT user_id FROM carts WHERE id = :id');
    $owner->execute(['id' => $cartId]);
    $userId = $owner->fetchColumn();
    $coupon = cart_validate_coupon($connection, $code, cart_subtotal($connection, $cartId), $userId ? (int) $userId : null);
    if ($coupon === null) throw new InvalidArgumentException('This coupon is invalid or does not apply to this cart.');
    $connection->prepare('UPDATE carts SET coupon_id = :coupon_id WHERE id = :cart_id')->execute(['coupon_id' => $coupon['id'], 'cart_id' => $cartId]);
    return $coupon;
}

function cart_clear_coupon(PDO $connection, int $cartId): void
{
    $connection->prepare('UPDATE carts SET coupon_id = NULL WHERE id = :cart_id')->execute(['cart_id' => $cartId]);
}