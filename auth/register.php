<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$errors = [];
$values = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];

try {
    $connection = database_connection();
    auth_require_guest($connection);
    if (request_is_post()) {
        foreach ($values as $key => $value) {
            $values[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        $errors = auth_validate_registration($_POST);
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            $errors['form'] = 'Your session expired. Please try again.';
        }
        if ($values['phone'] !== '' && mb_strlen($values['phone']) > 30) {
            $errors['phone'] = 'Enter a shorter phone number.';
        }
        if ($errors === []) {
            try {
                $statement = $connection->prepare('INSERT INTO users (email, password_hash, first_name, last_name, phone) VALUES (:email, :password_hash, :first_name, :last_name, :phone)');
                $statement->execute(['email' => auth_normalize_email($values['email']), 'password_hash' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT), 'first_name' => $values['first_name'], 'last_name' => $values['last_name'] ?: null, 'phone' => $values['phone'] ?: null]);
                $result = auth_login($connection, $values['email'], (string) $_POST['password']);
                if ($result['success']) {
                    header('Location: ' . base_url('account/'));
                    exit;
                }
                $errors['form'] = 'Your account was created. Please sign in.';
            } catch (PDOException $exception) {
                $errors['email'] = ((int) $exception->errorInfo[1] ?? 0) === 1062 ? 'An account with this email already exists.' : 'We could not create your account.';
            }
        }
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $errors['form'] = 'Registration is temporarily unavailable. Please try again later.';
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create account | <?= escape_html(APP_NAME) ?></title><link rel="stylesheet" href="<?= escape_html(asset_url('css/site.css')) ?>"></head>
<body class="auth-page">
    <?php $page_title = 'Create account'; require dirname(__DIR__) . '/components/catalogue-header.php'; ?>
    <main class="auth-main"><section class="auth-card auth-card--wide" aria-labelledby="register-title"><p class="eyebrow">A place for your edit</p><h1 id="register-title">Create your<br><em>account.</em></h1><p class="auth-card__intro">Save your details and keep your favourite pieces close.</p><?php if (isset($errors['form'])): ?><div class="alert alert--soft" role="alert"><?= escape_html($errors['form']) ?></div><?php endif; ?><form class="auth-form form-grid" method="post" novalidate><?= csrf_field() ?><div class="field"><label class="field__label" for="register-first-name">First name</label><input class="input" id="register-first-name" name="first_name" value="<?= escape_html($values['first_name']) ?>" autocomplete="given-name" required><?php if (isset($errors['first_name'])): ?><small class="field-error"><?= escape_html($errors['first_name']) ?></small><?php endif; ?></div><div class="field"><label class="field__label" for="register-last-name">Last name</label><input class="input" id="register-last-name" name="last_name" value="<?= escape_html($values['last_name']) ?>" autocomplete="family-name"></div><div class="field"><label class="field__label" for="register-email">Email address</label><input class="input" id="register-email" name="email" type="email" value="<?= escape_html($values['email']) ?>" autocomplete="email" required><?php if (isset($errors['email'])): ?><small class="field-error"><?= escape_html($errors['email']) ?></small><?php endif; ?></div><div class="field"><label class="field__label" for="register-phone">Phone <span class="muted">(optional)</span></label><input class="input" id="register-phone" name="phone" type="tel" value="<?= escape_html($values['phone']) ?>" autocomplete="tel"><?php if (isset($errors['phone'])): ?><small class="field-error"><?= escape_html($errors['phone']) ?></small><?php endif; ?></div><div class="field"><label class="field__label" for="register-password">Password</label><input class="input" id="register-password" name="password" type="password" autocomplete="new-password" minlength="8" required><?php if (isset($errors['password'])): ?><small class="field-error"><?= escape_html($errors['password']) ?></small><?php endif; ?></div><div class="field"><label class="field__label" for="register-password-confirmation">Confirm password</label><input class="input" id="register-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required><?php if (isset($errors['password_confirmation'])): ?><small class="field-error"><?= escape_html($errors['password_confirmation']) ?></small><?php endif; ?></div><div class="auth-form__submit"><button class="button" type="submit">Create account <span aria-hidden="true">&rarr;</span></button></div></form><p class="auth-card__footer">Already have an account? <a class="link" href="<?= escape_html(base_url('auth/login.php')) ?>">Sign in</a></p></section></main>
    <?php require dirname(__DIR__) . '/components/catalogue-footer.php'; ?>
</body>
</html>