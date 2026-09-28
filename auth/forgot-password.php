<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$submitted = false;
$email = '';
$error = '';
if (request_is_post()) {
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            auth_create_password_reset(database_connection(), $email);
            $submitted = true;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $error = 'We could not process that request right now.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset password | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="auth-page">
    <?php $page_title = 'Reset password'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="auth-main"><section class="auth-card" aria-labelledby="forgot-title"><p class="eyebrow">Account security</p><h1 id="forgot-title">Find your<br><em>way back.</em></h1><?php if ($submitted): ?><div class="alert alert--soft" role="status">If an account matches that email, reset instructions will be sent when email delivery is configured.</div><?php else: ?><p class="auth-card__intro">Enter your account email. We will never reveal whether an address is registered.</p><?php if ($error !== ''): ?><div class="alert alert--soft" role="alert"><?= escape_html($error) ?></div><?php endif; ?><form class="auth-form" method="post"><?= csrf_field() ?><div class="field"><label class="field__label" for="forgot-email">Email address</label><input class="input" id="forgot-email" name="email" type="email" value="<?= escape_html($email) ?>" autocomplete="email" required></div><button class="button" type="submit">Request reset <span aria-hidden="true">&rarr;</span></button></form><?php endif; ?><p class="auth-card__footer"><a class="link" href="<?= escape_html(base_url('auth/login.php')) ?>">Return to sign in</a></p></section></main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
</body>
</html>