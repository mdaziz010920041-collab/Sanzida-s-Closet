<?php

declare(strict_types=1);

function load_environment(string $file): array
{
    if (!is_readable($file)) {
        return [];
    }

    $values = [];

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $value = trim($value, "\"'");
        }

        if ($key !== '') {
            $values[$key] = $value;
        }
    }

    return $values;
}

$environment = load_environment(dirname(__DIR__) . '/.env');

function env_value(string $key, mixed $default = null): mixed
{
    global $environment;

    $value = $environment[$key] ?? getenv($key);

    return $value === false || $value === null || $value === '' ? $default : $value;
}

function env_boolean(string $key, bool $default = false): bool
{
    return filter_var(env_value($key, $default), FILTER_VALIDATE_BOOLEAN);
}

date_default_timezone_set((string) env_value('APP_TIMEZONE', 'Asia/Dhaka'));

define('APP_NAME', (string) env_value('APP_NAME', "Sanzida's Closet"));
define('APP_ENV', (string) env_value('APP_ENV', 'production'));
define('APP_DEBUG', env_boolean('APP_DEBUG', false));
define('APP_URL', rtrim((string) env_value('APP_URL', ''), '/'));

if (APP_ENV === 'production' && APP_DEBUG) {
    throw new RuntimeException('Production deployments must set APP_DEBUG=false.');
}

if (APP_ENV === 'production' && APP_URL === '') {
    throw new RuntimeException('Production deployments must configure APP_URL.');
}

if (APP_ENV === 'production' && !preg_match('/^https:\/\//i', APP_URL)) {
    throw new RuntimeException('Production APP_URL must use HTTPS.');
}

define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_PATH', APP_ROOT . '/public');
define('UPLOADS_PATH', APP_ROOT . '/uploads');
define('PAYMENT_PROVIDER', (string) env_value('PAYMENT_PROVIDER', 'razorpay'));
define('PAYMENT_MODE', (string) env_value('PAYMENT_MODE', 'test'));
define('PAYMENT_CURRENCY', (string) env_value('PAYMENT_CURRENCY', 'INR'));