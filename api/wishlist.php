<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/cart.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $connection = database_connection();
    $user = auth_current_user($connection);
    if ($user === null) {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '/products/');
        $refererPath = parse_url($referer, PHP_URL_PATH) ?: '/products/';
        $refererQuery = parse_url($referer, PHP_URL_QUERY);
        $next = str_starts_with($refererPath, '/') && !str_starts_with($refererPath, '//') ? $refererPath . ($refererQuery ? '?' . $refererQuery : '') : '/products/';
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sign in to use your wishlist.', 'login_url' => base_url('auth/login.php?next=' . rawurlencode($next))], JSON_THROW_ON_ERROR);
        exit;
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        throw new InvalidArgumentException('Your session expired. Refresh and try again.');
    }
    $wishlist = $connection->prepare("SELECT id FROM wishlists WHERE user_id = :user_id ORDER BY id ASC LIMIT 1");
    $wishlist->execute(['user_id' => $user['id']]);
    $wishlistId = (int) ($wishlist->fetchColumn() ?: 0);
    if ($wishlistId <= 0) {
        $connection->prepare("INSERT INTO wishlists (user_id, name) VALUES (:user_id, 'My wishlist')")->execute(['user_id' => $user['id']]);
        $wishlistId = (int) $connection->lastInsertId();
    }
    $variantId = (int) ($_POST['variant_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'add');
    if (cart_item_snapshot($connection, $variantId) === null) throw new InvalidArgumentException('This product variant is unavailable.');
    if ($action === 'remove') {
        $connection->prepare('DELETE FROM wishlist_items WHERE wishlist_id = :wishlist_id AND variant_id = :variant_id')->execute(['wishlist_id' => $wishlistId, 'variant_id' => $variantId]);
    } elseif ($action === 'move_to_cart') {
        cart_add_item($connection, cart_active_id($connection, (int) $user['id']), $variantId, 1);
        $connection->prepare('DELETE FROM wishlist_items WHERE wishlist_id = :wishlist_id AND variant_id = :variant_id')->execute(['wishlist_id' => $wishlistId, 'variant_id' => $variantId]);
    } else {
        $connection->prepare('INSERT IGNORE INTO wishlist_items (wishlist_id, variant_id) VALUES (:wishlist_id, :variant_id)')->execute(['wishlist_id' => $wishlistId, 'variant_id' => $variantId]);
    }
    echo json_encode(['success' => true, 'message' => $action === 'remove' ? 'Removed from your wishlist.' : ($action === 'move_to_cart' ? 'Moved to your bag.' : 'Saved to your wishlist.')], JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Wishlist service is temporarily unavailable.'], JSON_THROW_ON_ERROR);
}