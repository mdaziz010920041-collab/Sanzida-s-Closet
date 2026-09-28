<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$error = '';
try {
    $connection = database_connection();
    if (admin_current($connection)) { header('Location: ' . base_url('admin/')); exit; }
    if (request_is_post()) {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Please try again.';
        else {
            $result = admin_login($connection, (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
            if ($result['success']) { header('Location: ' . base_url('admin/')); exit; }
            $error = $result['message'];
        }
    }
} catch (Throwable $exception) { error_log($exception->getMessage()); $error = APP_ENV === 'development' ? 'Admin login needs the configured MySQL database. Import the schema and create an admin account first.' : 'Admin authentication is temporarily unavailable.'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin sign in | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head><body class="admin-auth-page"><main class="admin-auth-card"><p class="eyebrow">Private workspace</p><h1>Admin<br><em>sign in.</em></h1><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?><form class="auth-form" method="post"><?= csrf_field() ?><div class="field"><label class="field__label" for="admin-email">Email</label><input class="input" id="admin-email" name="email" type="email" autocomplete="username" required></div><div class="field"><label class="field__label" for="admin-password">Password</label><input class="input" id="admin-password" name="password" type="password" autocomplete="current-password" required></div><button class="button" type="submit">Enter workspace <span aria-hidden="true">&rarr;</span></button></form></main></body></html>