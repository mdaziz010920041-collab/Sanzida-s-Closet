<?php

declare(strict_types=1);

interface MarketplaceAdapter
{
    public function code(): string;
    public function name(): string;
    public function isConfigured(): bool;
    public function configurationMessage(): string;
    public function publishProduct(PDO $connection, int $productId): array;
    public function pushInventory(PDO $connection, int $variantId): array;
    public function pushPrice(PDO $connection, int $variantId): array;
    public function importOrders(PDO $connection): array;
    public function pushOrderStatus(PDO $connection, int $orderId): array;
}

function omnichannel_product_payload(PDO $connection, int $productId): array
{
    $productStatement = $connection->prepare('SELECT p.id, p.name, p.slug, p.short_description, p.description, p.base_price, p.compare_at_price, p.currency, b.name AS brand_name FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.id = :id AND p.status <> \'archived\' LIMIT 1');
    $productStatement->execute(['id' => $productId]);
    $product = $productStatement->fetch();
    if (!$product) throw new InvalidArgumentException('Product not found.');
    $variantStatement = $connection->prepare('SELECT v.id, v.sku, v.barcode, v.price_override, v.compare_at_price_override, v.size_id, v.color_id, COALESCE(i.quantity_on_hand - i.quantity_reserved, 0) AS available_quantity FROM product_variants v LEFT JOIN inventory i ON i.variant_id = v.id WHERE v.product_id = :product_id AND v.status = \'active\' ORDER BY v.id');
    $variantStatement->execute(['product_id' => $productId]);
    $imageStatement = $connection->prepare('SELECT file_path, alt_text, sort_order, is_primary FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, sort_order, id');
    $imageStatement->execute(['product_id' => $productId]);
    $product['variants'] = $variantStatement->fetchAll();
    $product['images'] = $imageStatement->fetchAll();
    return $product;
}

final class UnavailableMarketplaceAdapter implements MarketplaceAdapter
{
    public function __construct(private string $channelCode, private string $channelName, private array $requiredKeys)
    {
    }

    public function code(): string { return $this->channelCode; }
    public function name(): string { return $this->channelName; }
    public function isConfigured(): bool
    {
        foreach ($this->requiredKeys as $key) if (trim((string) env_value($key, '')) === '') return false;
        return true;
    }
    public function configurationMessage(): string
    {
        return $this->isConfigured() ? 'Credentials are present; provider-specific approval and adapter implementation are still required.' : 'Not configured. Required credentials are missing.';
    }
    public function publishProduct(PDO $connection, int $productId): array { return $this->unavailable('product publication'); }
    public function pushInventory(PDO $connection, int $variantId): array { return $this->unavailable('inventory synchronization'); }
    public function pushPrice(PDO $connection, int $variantId): array { return $this->unavailable('price synchronization'); }
    public function importOrders(PDO $connection): array { return $this->unavailable('order import'); }
    public function pushOrderStatus(PDO $connection, int $orderId): array { return $this->unavailable('order status synchronization'); }
    private function unavailable(string $operation): array { throw new RuntimeException($this->name() . ' ' . $operation . ' is not enabled in this installation.'); }
}

final class OwnWebsiteAdapter implements MarketplaceAdapter
{
    public function code(): string { return 'website'; }
    public function name(): string { return 'Own website'; }
    public function isConfigured(): bool { return true; }
    public function configurationMessage(): string { return 'The website is the central product, price, order, and inventory source of truth.'; }
    public function publishProduct(PDO $connection, int $productId): array { return ['status' => 'source_of_truth', 'product_id' => $productId]; }
    public function pushInventory(PDO $connection, int $variantId): array { return ['status' => 'source_of_truth', 'variant_id' => $variantId]; }
    public function pushPrice(PDO $connection, int $variantId): array { return ['status' => 'source_of_truth', 'variant_id' => $variantId]; }
    public function importOrders(PDO $connection): array { return ['status' => 'source_of_truth', 'imported' => 0]; }
    public function pushOrderStatus(PDO $connection, int $orderId): array { return ['status' => 'source_of_truth', 'order_id' => $orderId]; }
}

final class MarketplaceRegistry
{
    public static function all(): array
    {
        return [
            'website' => new OwnWebsiteAdapter(),
            'amazon' => new UnavailableMarketplaceAdapter('amazon', 'Amazon', ['AMAZON_SELLER_ID', 'AMAZON_CLIENT_ID', 'AMAZON_CLIENT_SECRET', 'AMAZON_REFRESH_TOKEN']),
            'flipkart' => new UnavailableMarketplaceAdapter('flipkart', 'Flipkart', ['FLIPKART_SELLER_ID', 'FLIPKART_API_KEY', 'FLIPKART_API_SECRET']),
            'meesho' => new UnavailableMarketplaceAdapter('meesho', 'Meesho', ['MEESHO_SUPPLIER_ID', 'MEESHO_API_KEY', 'MEESHO_API_SECRET']),
            'myntra' => new UnavailableMarketplaceAdapter('myntra', 'Myntra', ['MYNTRA_PARTNER_ID', 'MYNTRA_API_KEY', 'MYNTRA_API_SECRET']),
        ];
    }

    public static function get(string $channel): MarketplaceAdapter
    {
        $adapter = self::all()[$channel] ?? null;
        if (!$adapter) throw new InvalidArgumentException('Unknown marketplace channel.');
        return $adapter;
    }
}

final class MarketplaceSyncService
{
    public function __construct(private PDO $connection)
    {
    }

    public function queue(string $channel, string $syncType, string $entityType, int $entityId, array $payload = []): int
    {
        $adapter = MarketplaceRegistry::get($channel);
        $statement = $this->connection->prepare("INSERT INTO marketplace_sync_jobs (channel_code, sync_type, entity_type, entity_id, status, payload) VALUES (:channel, :sync_type, :entity_type, :entity_id, :status, :payload)");
        $statement->execute(['channel' => $adapter->code(), 'sync_type' => $syncType, 'entity_type' => $entityType, 'entity_id' => $entityId, 'status' => $adapter->isConfigured() ? 'queued' : 'blocked', 'payload' => $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR)]);
        return (int) $this->connection->lastInsertId();
    }

    public function status(): array
    {
        $status = [];
        foreach (MarketplaceRegistry::all() as $adapter) $status[$adapter->code()] = ['name' => $adapter->name(), 'configured' => $adapter->isConfigured(), 'message' => $adapter->configurationMessage()];
        return $status;
    }

    public function syncProduct(string $channel, int $productId): array { return $this->dispatch($channel, 'product', 'product', $productId, fn (MarketplaceAdapter $adapter): array => $adapter->publishProduct($this->connection, $productId)); }
    public function syncInventory(string $channel, int $variantId): array { return $this->dispatch($channel, 'inventory', 'variant', $variantId, fn (MarketplaceAdapter $adapter): array => $adapter->pushInventory($this->connection, $variantId)); }
    public function syncPrice(string $channel, int $variantId): array { return $this->dispatch($channel, 'price', 'variant', $variantId, fn (MarketplaceAdapter $adapter): array => $adapter->pushPrice($this->connection, $variantId)); }
    public function importOrders(string $channel): array { return $this->dispatch($channel, 'order_import', 'channel', 0, fn (MarketplaceAdapter $adapter): array => $adapter->importOrders($this->connection)); }
    public function syncOrderStatus(string $channel, int $orderId): array { return $this->dispatch($channel, 'order_status', 'order', $orderId, fn (MarketplaceAdapter $adapter): array => $adapter->pushOrderStatus($this->connection, $orderId)); }

    private function dispatch(string $channel, string $syncType, string $entityType, int $entityId, Closure $operation): array
    {
        $adapter = MarketplaceRegistry::get($channel);
        $jobId = $this->queue($channel, $syncType, $entityType, $entityId);
        if (!$adapter->isConfigured()) return ['status' => 'blocked', 'job_id' => $jobId, 'message' => $adapter->configurationMessage()];
        $this->connection->prepare("UPDATE marketplace_sync_jobs SET status = 'running', started_at = CURRENT_TIMESTAMP(6) WHERE id = :id")->execute(['id' => $jobId]);
        try {
            $result = $operation($adapter);
            $this->connection->prepare("UPDATE marketplace_sync_jobs SET status = 'completed', completed_at = CURRENT_TIMESTAMP(6), payload = :payload WHERE id = :id")->execute(['id' => $jobId, 'payload' => json_encode($result, JSON_THROW_ON_ERROR)]);
            return ['status' => 'completed', 'job_id' => $jobId, 'result' => $result];
        } catch (Throwable $exception) {
            $this->connection->prepare("UPDATE marketplace_sync_jobs SET status = 'failed', completed_at = CURRENT_TIMESTAMP(6), error_message = :error WHERE id = :id")->execute(['id' => $jobId, 'error' => substr($exception->getMessage(), 0, 500)]);
            throw $exception;
        }
    }
}
