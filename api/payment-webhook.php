<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/payments.php';

$rawBody = file_get_contents('php://input') ?: '';
$signature = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
$eventId = (string) ($_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? '');

try {
    payment_process_webhook(database_connection(), $rawBody, $signature, $eventId);
    http_response_code(200);
    echo 'ok';
} catch (InvalidArgumentException $exception) {
    error_log($exception->getMessage());
    http_response_code(400);
    echo 'invalid';
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo 'retry';
}