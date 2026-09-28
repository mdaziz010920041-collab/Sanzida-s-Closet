<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = false;
if (request_is_post()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirmation'] ?? '')) {
        $error = 'Passwords do not match.';
    } elseif (mb_strlen((string) ($_POST['password'] ?? '')) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $success = auth_reset_password(database_connection(), $token, (string) $_POST['password']);
            if (!$success) $error = 'This reset link is invalid or has expired.';
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'We could not reset your password right now.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Choose a new password | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="auth-page">
    <?php $page_title = 'New password'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="auth-main"><section class="auth-card" aria-labelledby="reset-title"><p class="eyebrow">Account security</p><h1 id="reset-title">Choose a new<br><em>password.</em></h1><?php if ($success): ?><div class="alert alert--soft" role="status">Your password has been updated. You can sign in now.</div><a class="button" href="<?= escape_html(base_url('auth/login.php')) ?>">Sign in <span aria-hidden="true">&rarr;</span></a><?php elseif ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?><?php if (!$success): ?><form class="auth-form" method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= escape_html($token) ?>"><div class="field"><label class="field__label" for="reset-password">New password</label><input class="input" id="reset-password" name="password" type="password" minlength="8" autocomplete="new-password" required></div><div class="field"><label class="field__label" for="reset-password-confirmation">Confirm password</label><input class="input" id="reset-password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required></div><button class="button" type="submit">Update password <span aria-hidden="true">&rarr;</span></button></form><?php endif; ?></section></main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
</body>
</html>