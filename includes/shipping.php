<?php

declare(strict_types=1);

function shipping_methods(PDO $connection): array
{
    $statement = $connection->query("SELECT id, code, name, description, carrier, charge, free_shipping_threshold, estimated_days_min, estimated_days_max, provider FROM shipping_methods WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
    return $statement->fetchAll();
}

function shipping_method(PDO $connection, string $code, float $subtotal): array
{
    $statement = $connection->prepare("SELECT id, code, name, description, carrier, charge, free_shipping_threshold, estimated_days_min, estimated_days_max, provider FROM shipping_methods WHERE code = :code AND status = 'active' LIMIT 1");
    $statement->execute(['code' => $code]);
    $method = $statement->fetch();
    if (!$method) throw new InvalidArgumentException('Choose an available shipping method.');
    $threshold = $method['free_shipping_threshold'] === null ? null : (float) $method['free_shipping_threshold'];
    $amount = $threshold !== null && $subtotal >= $threshold ? 0.0 : (float) $method['charge'];
    $method['amount'] = $amount;
    $method['free_shipping'] = $amount === 0.0;
    return $method;
}

function shipping_provider_config(): array
{
    return [
        'provider' => (string) app_setting('shipping.provider', env_value('SHIPPING_PROVIDER', 'manual')),
        'base_url' => (string) app_setting('shipping.api_base_url', env_value('SHIPPING_API_BASE_URL', '')),
        'api_key' => (string) app_setting('shipping.api_key', env_value('SHIPPING_API_KEY', '')),
        'webhook_secret' => (string) app_setting('shipping.webhook_secret', env_value('SHIPPING_WEBHOOK_SECRET', '')),
    ];
}

function shipping_provider_ready(): bool
{
    $config = shipping_provider_config();
    return $config['provider'] !== 'manual' && $config['base_url'] !== '' && $config['api_key'] !== '';
}

function shipping_create_provider_shipment(PDO $connection, int $orderId): array
{
    if (!shipping_provider_ready()) throw new RuntimeException('Shipping provider integration is not configured.');
    throw new RuntimeException('Shipping provider adapter is awaiting carrier-specific implementation.');
}