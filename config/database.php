<?php

declare(strict_types=1);

function database_connection(): PDO
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = (string) env_value('DB_HOST', '127.0.0.1');
    $port = (string) env_value('DB_PORT', '3306');
    $name = (string) env_value('DB_NAME', 'sanzidas_closet');
    $user = (string) env_value('DB_USER', '');
    $password = (string) env_value('DB_PASSWORD', '');

    if ($user === '') {
        throw new RuntimeException('Database credentials are not configured.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);

    return $connection;
}
