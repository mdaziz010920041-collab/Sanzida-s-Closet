<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/returns.php';

$error = '';
$success = '';
$order = null;
$policy = null;
try {
    $connection = database_connection();
    $user = auth_require_user($connection);
    $order = order_find_for_view($connection, trim((string) ($_GET['order'] ?? $_POST['order'] ?? '')), (int) $user['id']);
    $policy = return_policy($connection);
    if (!$order) $error = 'Order not found.';
    if (request_is_post() && $order) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) throw new InvalidArgumentException('Your session expired. Please try again.');
        $result = create_return_request($connection, (int) $user['id'], (int) ($_POST['order_item_id'] ?? 0), (int) ($_POST['quantity'] ?? 0), (string) ($_POST['reason'] ?? ''), (string) ($_POST['return_type'] ?? 'return'), (string) ($_POST['resolution'] ?? 'refund'), trim((string) ($_POST['note'] ?? '')));
        $success = 'Return request submitted for order ' . $result['order_number'] . '.';
    }
} catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); } catch (Throwable $exception) { error_log($exception->getMessage()); $error = 'Return service is temporarily unavailable.'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Request return | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head><body class="checkout-page"><?php $page_title = 'Request return'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?><main class="auth-main"><section class="auth-card auth-card--wide"><p class="eyebrow">Aftercare</p><h1>Request a<br><em>return.</em></h1><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?><?php if ($success !== ''): ?><div class="alert alert--soft" role="status"><?= escape_html($success) ?></div><?php elseif ($order && $policy): ?><p class="auth-card__intro">Choose the piece and tell us what happened. Your request will be reviewed before pickup or refund.</p><form class="auth-form" method="post"><?= csrf_field() ?><input type="hidden" name="order" value="<?= escape_html((string) $order['order_number']) ?>"><div class="field"><label class="field__label" for="return-item">Order item</label><select class="select" id="return-item" name="order_item_id" required><?php foreach ($order['items'] as $item): ?><option value="<?= escape_html((string) $item['id']) ?>"><?= escape_html((string) $item['product_name']) ?> &times; <?= escape_html((string) $item['quantity']) ?></option><?php endforeach; ?></select></div><div class="form-grid"><div class="field"><label class="field__label" for="return-quantity">Quantity</label><input class="input" id="return-quantity" name="quantity" type="number" min="1" value="1" required></div><div class="field"><label class="field__label" for="return-reason">Reason</label><select class="select" id="return-reason" name="reason" required><option value="damaged_item">Damaged item</option><option value="wrong_item">Wrong item</option><option value="missing_item">Missing item</option><option value="defective">Defective</option><option value="changed_mind">Changed mind</option><option value="other">Other</option></select></div><div class="field"><label class="field__label" for="return-type">Request type</label><select class="select" id="return-type" name="return_type"><option value="return">Return</option><option value="exchange">Exchange</option></select></div><div class="field"><label class="field__label" for="return-resolution">Resolution</label><select class="select" id="return-resolution" name="resolution"><option value="refund">Refund</option><option value="exchange">Exchange</option><option value="store_credit">Store credit</option></select></div></div><div class="field"><label class="field__label" for="return-note">Note <span class="muted">(optional)</span></label><textarea class="textarea" id="return-note" name="note"></textarea></div><button class="button" type="submit">Submit request <span aria-hidden="true">&rarr;</span></button></form><?php elseif (!$order): ?><p>Return requests are only available for your own orders.</p><?php else: ?><p>Return policy is not configured yet.</p><?php endif; ?></section></main><?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?></body></html>