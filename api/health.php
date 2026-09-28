<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'ok', 'application' => APP_NAME];

try {
    database_connection()->query('SELECT 1');
    $response['database'] = 'ok';
} catch (Throwable $exception) {
    $response['status'] = 'degraded';
    $response['database'] = 'unavailable';
    error_log($exception->getMessage());
    http_response_code(503);
}

echo json_encode($response, JSON_THROW_ON_ERROR);