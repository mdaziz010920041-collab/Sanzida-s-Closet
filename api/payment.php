<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/payments.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!request_is_post() || !verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Refresh and try again.');
    $connection = database_connection();
    $user = auth_current_user($connection);
    $orderNumber = trim((string) ($_POST['order_number'] ?? ''));
    $action = (string) ($_POST['action'] ?? 'initiate');
    if ($action === 'verify') {
        $result = payment_verify($connection, $orderNumber, $user ? (int) $user['id'] : null, trim((string) ($_POST['razorpay_order_id'] ?? '')), trim((string) ($_POST['razorpay_payment_id'] ?? '')), trim((string) ($_POST['razorpay_signature'] ?? '')));
    } else {
        $result = payment_initiate($connection, $orderNumber, $user ? (int) $user['id'] : null);
    }
    echo json_encode(['success' => true, ...$result], JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (RuntimeException $exception) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Payment service is temporarily unavailable.'], JSON_THROW_ON_ERROR);
}