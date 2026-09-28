<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$errors = [];
$email = '';
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '/account/');
$next = auth_safe_next($next);

try {
    $connection = database_connection();
    auth_require_guest($connection);
    if (request_is_post()) {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Your session expired. Please try again.';
        } elseif ($email === '' || (string) ($_POST['password'] ?? '') === '') {
            $errors['form'] = 'Enter your email and password.';
        } else {
            $result = auth_login($connection, $email, (string) $_POST['password']);
            if ($result['success']) {
                header('Location: ' . base_url(ltrim($next, '/')));
                exit;
            }
            $errors['form'] = $result['message'];
        }
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $errors['form'] = 'Sign in is temporarily unavailable. Please try again later.';
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="auth-page">
    <?php $page_title = 'Sign in'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="auth-main"><section class="auth-card" aria-labelledby="login-title"><p class="eyebrow">Welcome back</p><h1 id="login-title">Sign in to<br><em>your closet.</em></h1><p class="auth-card__intro">Keep your details, wishlist, and orders together in one quiet place.</p><?php if (isset($errors['form'])): ?><div class="alert alert--soft" role="alert"><?= escape_html($errors['form']) ?></div><?php endif; ?><form class="auth-form" method="post" novalidate><?= csrf_field() ?><input type="hidden" name="next" value="<?= escape_html($next) ?>"><div class="field"><label class="field__label" for="login-email">Email address</label><input class="input" id="login-email" name="email" type="email" value="<?= escape_html($email) ?>" autocomplete="email" required></div><div class="field"><label class="field__label" for="login-password">Password</label><input class="input" id="login-password" name="password" type="password" autocomplete="current-password" required></div><div class="auth-form__meta"><a class="link" href="<?= escape_html(base_url('auth/forgot-password.php')) ?>">Forgot password?</a></div><button class="button" type="submit">Sign in <span aria-hidden="true">&rarr;</span></button></form><p class="auth-card__footer">New here? <a class="link" href="<?= escape_html(base_url('auth/register.php')) ?>">Create an account</a></p></section></main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
</body>
</html>