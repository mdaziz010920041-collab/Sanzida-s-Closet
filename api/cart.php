<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/cart.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $connection = database_connection();
    $user = auth_current_user($connection);
    $userId = $user ? (int) $user['id'] : null;
    $cartId = cart_active_id($connection, $userId);
    $action = (string) ($_POST['action'] ?? $_GET['action'] ?? 'summary');
    $mutating = in_array($action, ['add', 'update', 'remove', 'apply_coupon', 'clear_coupon'], true);

    if ($mutating && !verify_csrf_token($_POST['csrf_token'] ?? null)) {
        throw new InvalidArgumentException('Your session expired. Refresh and try again.');
    }

    if ($action === 'add') {
        $result = cart_add_item($connection, $cartId, (int) ($_POST['variant_id'] ?? 0), (int) ($_POST['quantity'] ?? 1));
        $response = ['message' => 'Added to your bag.', 'added' => $result];
    } elseif ($action === 'update') {
        cart_update_item($connection, $cartId, (int) ($_POST['variant_id'] ?? 0), (int) ($_POST['quantity'] ?? 0));
        $response = ['message' => 'Bag updated.'];
    } elseif ($action === 'remove') {
        cart_remove_item($connection, $cartId, (int) ($_POST['variant_id'] ?? 0));
        $response = ['message' => 'Removed from your bag.'];
    } elseif ($action === 'apply_coupon') {
        $response = ['message' => 'Coupon applied.', 'coupon' => cart_apply_coupon($connection, $cartId, (string) ($_POST['code'] ?? ''))];
    } elseif ($action === 'clear_coupon') {
        cart_clear_coupon($connection, $cartId);
        $response = ['message' => 'Coupon removed.'];
    } else {
        $response = ['message' => ''];
    }

    $response['cart'] = cart_summary($connection, $userId);
    echo json_encode(['success' => true, ...$response], JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Cart service is temporarily unavailable.'], JSON_THROW_ON_ERROR);
}