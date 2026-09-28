<?php

declare(strict_types=1);

function payment_config(): array
{
    return [
        'provider' => (string) app_setting('payments.provider', PAYMENT_PROVIDER),
        'mode' => (string) app_setting('payments.mode', PAYMENT_MODE),
        'currency' => (string) app_setting('payments.currency', PAYMENT_CURRENCY),
        'key_id' => trim((string) app_setting('payments.razorpay_key_id', env_value('RAZORPAY_KEY_ID', ''))),
        'secret' => trim((string) app_setting('payments.razorpay_secret', env_value('RAZORPAY_SECRET', ''))),
        'webhook_secret' => trim((string) app_setting('payments.razorpay_webhook_secret', env_value('RAZORPAY_WEBHOOK_SECRET', ''))),
        'base_url' => PAYMENT_MODE === 'live' ? 'https://api.razorpay.com' : 'https://api.razorpay.com',
    ];
}

function payment_is_configured(): bool
{
    $config = payment_config();
    return $config['provider'] === 'razorpay' && $config['key_id'] !== '' && $config['secret'] !== '';
}

function payment_gateway_request(string $method, string $path, ?array $payload = null): array
{
    $config = payment_config();
    if (!payment_is_configured()) throw new RuntimeException('Payment gateway credentials are not configured.');
    if (!function_exists('curl_init')) throw new RuntimeException('The PHP cURL extension is required for payment integration.');

    $handle = curl_init($config['base_url'] . $path);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_USERPWD => $config['key_id'] . ':' . $config['secret'], CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    ]);
    if ($payload !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($body === false || $error !== '') throw new RuntimeException('Payment gateway request failed.');
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded) || $status < 200 || $status >= 300) throw new RuntimeException('Payment gateway returned an invalid response.');
    return $decoded;
}

function payment_authorized_order(PDO $connection, string $orderNumber, ?int $userId): ?array
{
    return order_find_for_view($connection, $orderNumber, $userId);
}

function payment_initiate(PDO $connection, string $orderNumber, ?int $userId): array
{
    $order = payment_authorized_order($connection, $orderNumber, $userId);
    if (!$order) throw new InvalidArgumentException('Order not found.');
    if (($order['payment']['status'] ?? '') === 'paid') return ['status' => 'paid', 'order_number' => $orderNumber];
    if ((string) $order['currency'] !== PAYMENT_CURRENCY) throw new InvalidArgumentException('Payment currency is not configured for this order.');
    if (!payment_is_configured()) throw new RuntimeException('Payment gateway is not configured. Add sandbox credentials before initiating payment.');

    $paymentStatement = $connection->prepare("SELECT * FROM payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1");
    $paymentStatement->execute(['order_id' => $order['id']]);
    $payment = $paymentStatement->fetch();
    if (!$payment) throw new RuntimeException('Payment record is missing for this order.');
    if (!empty($payment['provider_order_id'])) return ['status' => 'pending', 'key_id' => payment_config()['key_id'], 'provider_order_id' => $payment['provider_order_id'], 'amount' => (int) round((float) $order['grand_total'] * 100), 'currency' => $order['currency'], 'order_number' => $orderNumber];

    $gatewayOrder = payment_gateway_request('POST', '/v1/orders', ['amount' => (int) round((float) $order['grand_total'] * 100), 'currency' => $order['currency'], 'receipt' => $order['order_number'], 'notes' => ['order_number' => $order['order_number']]]);
    if (empty($gatewayOrder['id'])) throw new RuntimeException('Payment gateway did not return an order ID.');
    $connection->prepare("UPDATE payments SET provider = 'razorpay', provider_order_id = :provider_order_id, status = 'pending', metadata = :metadata WHERE id = :id")->execute(['provider_order_id' => $gatewayOrder['id'], 'metadata' => json_encode(['mode' => PAYMENT_MODE], JSON_THROW_ON_ERROR), 'id' => $payment['id']]);
    return ['status' => 'pending', 'key_id' => payment_config()['key_id'], 'provider_order_id' => $gatewayOrder['id'], 'amount' => (int) round((float) $order['grand_total'] * 100), 'currency' => $order['currency'], 'order_number' => $orderNumber];
}

function payment_reconcile_order(PDO $connection, int $orderId): void
{
    $statement = $connection->prepare('SELECT order_id, status FROM payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
    $statement->execute(['order_id' => $orderId]);
    $payment = $statement->fetch();
    if (!$payment) return;
    if ($payment['status'] === 'paid') {
        $connection->prepare("UPDATE orders SET status = CASE WHEN status IN ('pending', 'confirmed') THEN 'confirmed' ELSE status END WHERE id = :id")->execute(['id' => $orderId]);
    }
}

function payment_verify(PDO $connection, string $orderNumber, ?int $userId, string $providerOrderId, string $providerPaymentId, string $signature): array
{
    $order = payment_authorized_order($connection, $orderNumber, $userId);
    if (!$order || $providerOrderId === '' || $providerPaymentId === '' || $signature === '') throw new InvalidArgumentException('Payment verification data is incomplete.');
    $localPayment = $connection->prepare('SELECT provider_order_id, provider_payment_id, status FROM payments WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
    $localPayment->execute(['order_id' => $order['id']]);
    $localPayment = $localPayment->fetch();
    if (!$localPayment || (string) $localPayment['provider_order_id'] !== $providerOrderId || ($localPayment['status'] ?? '') === 'paid') throw new InvalidArgumentException('Payment does not match the order.');
    $config = payment_config();
    if (!payment_is_configured()) throw new RuntimeException('Payment gateway is not configured.');
    $expected = hash_hmac('sha256', $providerOrderId . '|' . $providerPaymentId, $config['secret']);
    if (!hash_equals($expected, $signature)) throw new InvalidArgumentException('Payment signature could not be verified.');
    $gatewayPayment = payment_gateway_request('GET', '/v1/payments/' . rawurlencode($providerPaymentId));
    if (($gatewayPayment['order_id'] ?? '') !== $providerOrderId || ($gatewayPayment['status'] ?? '') !== 'captured' || (int) ($gatewayPayment['amount'] ?? 0) !== (int) round((float) $order['grand_total'] * 100)) throw new InvalidArgumentException('Payment gateway confirmation did not match the order.');

    $connection->beginTransaction();
    try {
        $statement = $connection->prepare("UPDATE payments SET provider = 'razorpay', provider_order_id = :provider_order_id, provider_payment_id = :provider_payment_id, provider_signature = :provider_signature, status = 'paid', paid_at = CURRENT_TIMESTAMP(6), metadata = :metadata WHERE order_id = :order_id AND status <> 'paid'");
        $statement->execute(['provider_order_id' => $providerOrderId, 'provider_payment_id' => $providerPaymentId, 'provider_signature' => $signature, 'metadata' => json_encode($gatewayPayment, JSON_THROW_ON_ERROR), 'order_id' => $order['id']]);
        payment_reconcile_order($connection, (int) $order['id']);
        $connection->commit();
    } catch (Throwable $exception) {
        $connection->rollBack();
        throw $exception;
    }
    return ['status' => 'paid', 'order_number' => $orderNumber];
}

function payment_process_webhook(PDO $connection, string $rawBody, string $signature, string $eventId): void
{
    $config = payment_config();
    if ($config['webhook_secret'] === '' || !hash_equals(hash_hmac('sha256', $rawBody, $config['webhook_secret']), $signature)) throw new InvalidArgumentException('Webhook signature could not be verified.');
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    $eventType = (string) ($payload['event'] ?? 'unknown');
    $eventId = $eventId !== '' ? $eventId : hash('sha256', $rawBody);
    try {
        $insert = $connection->prepare('INSERT INTO payment_webhook_events (provider, event_id, event_type, payload) VALUES (:provider, :event_id, :event_type, :payload)');
        $insert->execute(['provider' => $config['provider'], 'event_id' => $eventId, 'event_type' => $eventType, 'payload' => $rawBody]);
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) return;
        throw $exception;
    }

    $entity = $payload['payload']['payment']['entity'] ?? [];
    $providerOrderId = (string) ($entity['order_id'] ?? '');
    $providerPaymentId = (string) ($entity['id'] ?? '');
    $status = $eventType === 'payment.captured' ? 'paid' : ($eventType === 'payment.failed' ? 'failed' : null);
    if ($providerOrderId !== '' && $status !== null) {
        $connection->beginTransaction();
        try {
            $statement = $connection->prepare("UPDATE payments SET provider = 'razorpay', provider_order_id = :provider_order_id, provider_payment_id = :provider_payment_id, status = :status, failure_reason = :failure_reason, paid_at = CASE WHEN :paid = 1 THEN CURRENT_TIMESTAMP(6) ELSE paid_at END, metadata = :metadata WHERE provider_order_id = :provider_order_id_update");
            $statement->execute(['provider_order_id' => $providerOrderId, 'provider_payment_id' => $providerPaymentId ?: null, 'status' => $status, 'failure_reason' => $status === 'failed' ? (string) ($entity['error_description'] ?? 'Payment failed') : null, 'paid' => $status === 'paid' ? 1 : 0, 'metadata' => $rawBody, 'provider_order_id_update' => $providerOrderId]);
            $orderStatement = $connection->prepare('SELECT order_id FROM payments WHERE provider_order_id = :provider_order_id LIMIT 1');
            $orderStatement->execute(['provider_order_id' => $providerOrderId]);
            $orderId = (int) $orderStatement->fetchColumn();
            if ($orderId > 0) payment_reconcile_order($connection, $orderId);
            $connection->prepare("UPDATE payment_webhook_events SET status = 'processed', processed_at = CURRENT_TIMESTAMP(6) WHERE provider = :provider AND event_id = :event_id")->execute(['provider' => $config['provider'], 'event_id' => $eventId]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            $connection->prepare("UPDATE payment_webhook_events SET status = 'failed' WHERE provider = :provider AND event_id = :event_id")->execute(['provider' => $config['provider'], 'event_id' => $eventId]);
            throw $exception;
        }
    } else {
        $connection->prepare("UPDATE payment_webhook_events SET status = 'ignored', processed_at = CURRENT_TIMESTAMP(6) WHERE provider = :provider AND event_id = :event_id")->execute(['provider' => $config['provider'], 'event_id' => $eventId]);
    }
}