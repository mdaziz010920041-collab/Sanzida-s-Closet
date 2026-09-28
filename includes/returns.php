<?php

declare(strict_types=1);

function return_policy(PDO $connection): ?array
{
    $statement = $connection->query("SELECT * FROM return_policies WHERE status = 'active' ORDER BY id ASC LIMIT 1");
    return $statement->fetch() ?: null;
}

function return_number(): string
{
    return 'RET-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function create_return_request(PDO $connection, int $userId, int $orderItemId, int $quantity, string $reason, string $returnType, string $resolution, string $note): array
{
    $policy = return_policy($connection);
    if (!$policy) throw new RuntimeException('Return policy is not configured.');
    $allowedReasons = ['changed_mind', 'damaged_item', 'wrong_item', 'missing_item', 'defective', 'other'];
    if (!in_array($reason, $allowedReasons, true) || !in_array($returnType, ['return', 'exchange'], true) || !in_array($resolution, ['refund', 'exchange', 'store_credit'], true)) throw new InvalidArgumentException('Choose a valid return option.');
    $eligibleStatuses = json_decode((string) $policy['eligible_statuses'], true) ?: ['delivered', 'completed'];
    $allowedResolutions = json_decode((string) $policy['allowed_resolutions'], true) ?: ['refund'];
    if (!in_array($resolution, $allowedResolutions, true)) throw new InvalidArgumentException('That resolution is not available under the current return policy.');
    $reasonFlags = ['damaged_item' => 'allow_damaged_item', 'wrong_item' => 'allow_wrong_item', 'missing_item' => 'allow_missing_item'];
    if (isset($reasonFlags[$reason]) && empty($policy[$reasonFlags[$reason]])) throw new InvalidArgumentException('That return reason is not covered by the current policy.');
    $statement = $connection->prepare('SELECT item.id, item.order_id, item.quantity, orders.order_number, orders.status, shipments.delivered_at FROM order_items item INNER JOIN orders ON orders.id = item.order_id LEFT JOIN shipments ON shipments.order_id = orders.id WHERE item.id = :item_id AND orders.user_id = :user_id ORDER BY shipments.id DESC LIMIT 1');
    $statement->execute(['item_id' => $orderItemId, 'user_id' => $userId]);
    $item = $statement->fetch();
    if (!$item || !in_array($item['status'], $eligibleStatuses, true) || $quantity < 1 || $quantity > (int) $item['quantity']) throw new InvalidArgumentException('This order item is not eligible for that return request.');
    if ((int) $policy['days_from_delivery'] > 0 && (empty($item['delivered_at']) || strtotime((string) $item['delivered_at']) < strtotime('-' . (int) $policy['days_from_delivery'] . ' days'))) throw new InvalidArgumentException('This order is outside the return window.');
    $usedStatement = $connection->prepare("SELECT COALESCE(SUM(return_items.quantity), 0) FROM return_items INNER JOIN returns ON returns.id = return_items.return_id WHERE return_items.order_item_id = :order_item_id AND returns.status NOT IN ('rejected', 'cancelled')");
    $usedStatement->execute(['order_item_id' => $orderItemId]);
    if ($quantity > (int) $item['quantity'] - (int) $usedStatement->fetchColumn()) throw new InvalidArgumentException('That quantity has already been included in a return request.');
    $connection->beginTransaction();
    try {
        $returnStatement = $connection->prepare("INSERT INTO returns (order_id, user_id, return_number, return_type, status, reason, resolution, customer_note) VALUES (:order_id, :user_id, :return_number, :return_type, 'requested', :reason, :resolution, :customer_note)");
        $returnStatement->execute(['order_id' => $item['order_id'], 'user_id' => $userId, 'return_number' => return_number(), 'return_type' => $returnType, 'reason' => $reason, 'resolution' => $resolution, 'customer_note' => $note ?: null]);
        $returnId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO return_items (return_id, order_item_id, quantity, reason) VALUES (:return_id, :order_item_id, :quantity, :reason)')->execute(['return_id' => $returnId, 'order_item_id' => $orderItemId, 'quantity' => $quantity, 'reason' => $reason]);
        $connection->commit();
        return ['id' => $returnId, 'order_number' => $item['order_number']];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}