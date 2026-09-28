<?php

declare(strict_types=1);

require_once __DIR__ . '/shipping.php';

function order_number(): string
{
    return 'SC-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function checkout_address(array $input): array
{
    $fields = ['recipient_name', 'phone', 'line_1', 'city', 'country_code'];
    foreach ($fields as $field) {
        if (trim((string) ($input[$field] ?? '')) === '') {
            throw new InvalidArgumentException('Complete the required delivery address fields.');
        }
    }

    $countryCode = strtoupper(trim((string) $input['country_code']));
    if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
        throw new InvalidArgumentException('Enter a valid country code.');
    }

    return [
        'recipient_name' => trim((string) $input['recipient_name']),
        'phone' => trim((string) $input['phone']),
        'line_1' => trim((string) $input['line_1']),
        'line_2' => trim((string) ($input['line_2'] ?? '')) ?: null,
        'city' => trim((string) $input['city']),
        'state' => trim((string) ($input['state'] ?? '')) ?: null,
        'postal_code' => trim((string) ($input['postal_code'] ?? '')) ?: null,
        'country_code' => $countryCode,
    ];
}

function checkout_shipping(PDO $connection, string $method, float $subtotal): array
{
    return shipping_method($connection, $method, $subtotal);
}

function checkout_payment(string $method): array
{
    $methods = [
        'cash_on_delivery' => ['provider' => 'cash_on_delivery', 'label' => 'Cash on delivery'],
        'online_pending' => ['provider' => 'online_pending', 'label' => 'Razorpay secure online payment'],
    ];
    if (!isset($methods[$method])) {
        throw new InvalidArgumentException('Choose an available payment method.');
    }
    return $methods[$method];
}

function checkout_create_order(PDO $connection, int $cartId, ?int $userId, string $email, array $input): array
{
    $email = auth_normalize_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    $address = checkout_address($input);
    $shipping = checkout_shipping($connection, (string) ($input['shipping_method'] ?? ''), 0.0);
    $payment = checkout_payment((string) ($input['payment_method'] ?? ''));

    $connection->beginTransaction();
    try {
        $cartStatement = $connection->prepare("SELECT id, coupon_id FROM carts WHERE id = :cart_id AND status = 'active' FOR UPDATE");
        $cartStatement->execute(['cart_id' => $cartId]);
        $cart = $cartStatement->fetch();
        if (!$cart) {
            throw new InvalidArgumentException('Your cart is no longer active.');
        }

        $itemStatement = $connection->prepare("SELECT item.variant_id, item.quantity, v.product_id, v.sku, v.price_override, p.name, p.currency, p.base_price, p.discount_type, p.discount_value, p.compare_at_price, size.name AS size_name, color.name AS color_name FROM cart_items item INNER JOIN product_variants v ON v.id = item.variant_id AND v.status = 'active' INNER JOIN products p ON p.id = v.product_id AND p.status = 'active' LEFT JOIN sizes size ON size.id = v.size_id LEFT JOIN colors color ON color.id = v.color_id WHERE item.cart_id = :cart_id FOR UPDATE");
        $itemStatement->execute(['cart_id' => $cartId]);
        $cartItems = $itemStatement->fetchAll();
        if ($cartItems === []) {
            throw new InvalidArgumentException('Your cart is empty.');
        }

        $subtotal = 0.0;
        $orderItems = [];
        foreach ($cartItems as $item) {
            $inventoryStatement = $connection->prepare('SELECT id, quantity_on_hand, quantity_reserved FROM inventory WHERE variant_id = :variant_id FOR UPDATE');
            $inventoryStatement->execute(['variant_id' => $item['variant_id']]);
            $inventory = $inventoryStatement->fetch();
            $available = $inventory ? (int) $inventory['quantity_on_hand'] - (int) $inventory['quantity_reserved'] : 0;
            if (!$inventory || $available < (int) $item['quantity']) {
                throw new InvalidArgumentException('One or more pieces no longer have enough stock. Please review your cart.');
            }
            $basePrice = (float) $item['base_price'];
            $productPrice = $item['discount_type'] === 'percentage' ? max(0, $basePrice - ($basePrice * (float) $item['discount_value'] / 100)) : ($item['discount_type'] === 'fixed' ? max(0, $basePrice - (float) $item['discount_value']) : $basePrice);
            $unitPrice = $item['price_override'] !== null ? (float) $item['price_override'] : $productPrice;
            $lineTotal = $unitPrice * (int) $item['quantity'];
            $subtotal += $lineTotal;
            $orderItems[] = ['source' => $item, 'unit_price' => $unitPrice, 'line_total' => $lineTotal, 'inventory' => $inventory];
        }

        $shipping = checkout_shipping($connection, (string) ($input['shipping_method'] ?? ''), $subtotal);
        $coupon = null;
        if (!empty($cart['coupon_id'])) {
            $couponStatement = $connection->prepare('SELECT code FROM coupons WHERE id = :id');
            $couponStatement->execute(['id' => $cart['coupon_id']]);
            $couponCode = (string) ($couponStatement->fetchColumn() ?: '');
            $coupon = $couponCode !== '' ? cart_validate_coupon($connection, $couponCode, $subtotal, $userId) : null;
        }
        $discount = $coupon['discount'] ?? 0.0;
        $grandTotal = max(0, $subtotal - $discount + $shipping['amount']);
        $orderNumber = order_number();
        $orderStatement = $connection->prepare("INSERT INTO orders (user_id, coupon_id, shipping_method_id, shipping_method_code, shipping_method_name, order_number, email, status, currency, subtotal, discount_total, shipping_total, tax_total, grand_total, shipping_address_snapshot, billing_address_snapshot, notes, placed_at) VALUES (:user_id, :coupon_id, :shipping_method_id, :shipping_method_code, :shipping_method_name, :order_number, :email, 'pending', :currency, :subtotal, :discount_total, :shipping_total, 0, :grand_total, :shipping_snapshot, :billing_snapshot, :notes, CURRENT_TIMESTAMP(6))");
        $orderStatement->execute(['user_id' => $userId, 'coupon_id' => $coupon['id'] ?? null, 'shipping_method_id' => $shipping['id'], 'shipping_method_code' => $shipping['code'], 'shipping_method_name' => $shipping['name'], 'order_number' => $orderNumber, 'email' => $email, 'currency' => $orderItems[0]['source']['currency'], 'subtotal' => $subtotal, 'discount_total' => $discount, 'shipping_total' => $shipping['amount'], 'grand_total' => $grandTotal, 'shipping_snapshot' => json_encode($address, JSON_THROW_ON_ERROR), 'billing_snapshot' => json_encode($address, JSON_THROW_ON_ERROR), 'notes' => 'Shipping: ' . $shipping['name'] . '; Payment: ' . $payment['label']]);
        $orderId = (int) $connection->lastInsertId();

        $lineStatement = $connection->prepare('INSERT INTO order_items (order_id, product_id, variant_id, sku, product_name, variant_description, unit_price, quantity, line_total) VALUES (:order_id, :product_id, :variant_id, :sku, :product_name, :variant_description, :unit_price, :quantity, :line_total)');
        $reserveStatement = $connection->prepare('UPDATE inventory SET quantity_reserved = quantity_reserved + :quantity WHERE id = :id AND quantity_on_hand - quantity_reserved >= :available_quantity');
        $transactionStatement = $connection->prepare("INSERT INTO inventory_transactions (variant_id, transaction_type, quantity_change, quantity_after, reference_type, reference_id, note) VALUES (:variant_id, 'reservation', 0, :quantity_after, 'order', :order_id, 'Stock reserved at order creation')");
        foreach ($orderItems as $orderItem) {
            $source = $orderItem['source'];
            $description = trim(implode(' / ', array_filter([(string) ($source['size_name'] ?? ''), (string) ($source['color_name'] ?? '')]))) ?: null;
            $lineStatement->execute(['order_id' => $orderId, 'product_id' => $source['product_id'], 'variant_id' => $source['variant_id'], 'sku' => $source['sku'], 'product_name' => $source['name'], 'variant_description' => $description, 'unit_price' => $orderItem['unit_price'], 'quantity' => $source['quantity'], 'line_total' => $orderItem['line_total']]);
            $newReserved = (int) $orderItem['inventory']['quantity_reserved'] + (int) $source['quantity'];
            $reserveStatement->execute(['quantity' => $source['quantity'], 'id' => $orderItem['inventory']['id'], 'available_quantity' => $source['quantity']]);
            if ($reserveStatement->rowCount() !== 1) throw new InvalidArgumentException('Stock changed while placing your order. Please try again.');
            $transactionStatement->execute(['variant_id' => $source['variant_id'], 'quantity_after' => (int) $orderItem['inventory']['quantity_on_hand'] - $newReserved, 'order_id' => $orderId]);
        }

        $paymentStatement = $connection->prepare('INSERT INTO payments (order_id, provider, amount, currency, status) VALUES (:order_id, :provider, :amount, :currency, \'pending\')');
        $paymentStatement->execute(['order_id' => $orderId, 'provider' => $payment['provider'], 'amount' => $grandTotal, 'currency' => $orderItems[0]['source']['currency']]);
        $connection->prepare("INSERT INTO shipments (order_id, shipping_method_id, provider, status) VALUES (:order_id, :shipping_method_id, :provider, 'pending')")->execute(['order_id' => $orderId, 'shipping_method_id' => $shipping['id'], 'provider' => $shipping['provider']]);
        if ($coupon) $connection->prepare('INSERT INTO coupon_usage (coupon_id, order_id, user_id, discount_amount) VALUES (:coupon_id, :order_id, :user_id, :discount_amount)')->execute(['coupon_id' => $coupon['id'], 'order_id' => $orderId, 'user_id' => $userId, 'discount_amount' => $discount]);
        $connection->prepare("UPDATE carts SET status = 'converted' WHERE id = :cart_id")->execute(['cart_id' => $cartId]);
        $connection->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')->execute(['cart_id' => $cartId]);
        $connection->commit();

        start_secure_session();
        if ($userId === null) $_SESSION['guest_order_ids'] = array_values(array_unique(array_merge($_SESSION['guest_order_ids'] ?? [], [$orderId])));
        return ['id' => $orderId, 'order_number' => $orderNumber, 'total' => $grandTotal, 'currency' => $orderItems[0]['source']['currency']];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function order_find_for_view(PDO $connection, string $orderNumber, ?int $userId = null): ?array
{
    $statement = $connection->prepare('SELECT id, user_id, order_number, email, status, currency, subtotal, discount_total, shipping_total, tax_total, grand_total, shipping_address_snapshot, billing_address_snapshot, notes, placed_at, created_at FROM orders WHERE order_number = :order_number LIMIT 1');
    $statement->execute(['order_number' => $orderNumber]);
    $order = $statement->fetch();
    if (!$order) return null;
    $allowed = $userId !== null && (int) $order['user_id'] === $userId;
    start_secure_session();
    if (!$allowed && $userId === null) $allowed = in_array((int) $order['id'], array_map('intval', $_SESSION['guest_order_ids'] ?? []), true);
    if (!$allowed) return null;
    $items = $connection->prepare('SELECT id, product_id, variant_id, product_name, sku, variant_description, unit_price, quantity, line_total FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
    $items->execute(['order_id' => $order['id']]);
    $order['items'] = $items->fetchAll();
    $payment = $connection->prepare('SELECT provider, amount, status, paid_at FROM payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
    $payment->execute(['order_id' => $order['id']]);
    $order['payment'] = $payment->fetch() ?: null;
    $shipment = $connection->prepare('SELECT carrier, tracking_number, status, shipped_at, delivered_at FROM shipments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
    $shipment->execute(['order_id' => $order['id']]);
    $order['shipment'] = $shipment->fetch() ?: null;
    $refund = $connection->prepare("SELECT id, amount, currency, status FROM refunds WHERE order_id = :order_id AND status = 'processed' ORDER BY id DESC LIMIT 1");
    $refund->execute(['order_id' => $order['id']]);
    $order['refund'] = $refund->fetch() ?: null;
    return $order;
}

function release_order_inventory(PDO $connection, int $orderId, ?int $adminId = null): void
{
    $items = $connection->prepare('SELECT variant_id, quantity FROM order_items WHERE order_id = :order_id AND variant_id IS NOT NULL');
    $items->execute(['order_id' => $orderId]);
    $startedTransaction = !$connection->inTransaction();
    if ($startedTransaction) $connection->beginTransaction();
    try {
        foreach ($items->fetchAll() as $item) {
            $inventory = $connection->prepare('SELECT id, quantity_reserved, quantity_on_hand FROM inventory WHERE variant_id = :variant_id FOR UPDATE');
            $inventory->execute(['variant_id' => $item['variant_id']]);
            $stock = $inventory->fetch();
            if (!$stock) continue;
            $release = min((int) $item['quantity'], (int) $stock['quantity_reserved']);
            if ($release <= 0) continue;
            $newReserved = (int) $stock['quantity_reserved'] - $release;
            $connection->prepare('UPDATE inventory SET quantity_reserved = :reserved WHERE id = :id')->execute(['reserved' => $newReserved, 'id' => $stock['id']]);
            $connection->prepare("INSERT INTO inventory_transactions (variant_id, admin_id, transaction_type, quantity_change, quantity_after, reference_type, reference_id, note) VALUES (:variant_id, :admin_id, 'release', 0, :quantity_after, 'order', :order_id, 'Reservation released after order cancellation')")->execute(['variant_id' => $item['variant_id'], 'admin_id' => $adminId, 'quantity_after' => (int) $stock['quantity_on_hand'] - $newReserved, 'order_id' => $orderId]);
        }
        if ($startedTransaction) $connection->commit();
    } catch (Throwable $exception) {
        if ($startedTransaction && $connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}