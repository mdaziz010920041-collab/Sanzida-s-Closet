<?php

declare(strict_types=1);

function auth_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function auth_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function auth_validate_registration(array $input): array
{
    $errors = [];
    $email = auth_normalize_email((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $firstName = trim((string) ($input['first_name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($firstName === '' || mb_strlen($firstName) > 80) {
        $errors['first_name'] = 'Enter your first name.';
    }
    if (mb_strlen($password) < 8 || mb_strlen($password) > 72) {
        $errors['password'] = 'Password must be between 8 and 72 characters.';
    }
    if ($password !== (string) ($input['password_confirmation'] ?? '')) {
        $errors['password_confirmation'] = 'Passwords do not match.';
    }

    return $errors;
}

function auth_is_rate_limited(PDO $connection, string $email, string $ip): bool
{
    $statement = $connection->prepare("SELECT COUNT(*) FROM auth_login_attempts WHERE attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE AND succeeded = 0 AND email = :email");
    $statement->execute(['email' => $email]);
    if ((int) $statement->fetchColumn() >= 5) {
        return true;
    }

    if ($ip === '') {
        return false;
    }

    $statement = $connection->prepare("SELECT COUNT(*) FROM auth_login_attempts WHERE attempted_at >= UTC_TIMESTAMP() - INTERVAL 15 MINUTE AND succeeded = 0 AND ip_address = :ip_address");
    $statement->execute(['ip_address' => $ip]);
    return (int) $statement->fetchColumn() >= 15;
}

function auth_record_login_attempt(PDO $connection, string $email, string $ip, bool $succeeded): void
{
    $statement = $connection->prepare('INSERT INTO auth_login_attempts (email, ip_address, succeeded) VALUES (:email, :ip_address, :succeeded)');
    $statement->execute(['email' => $email, 'ip_address' => $ip !== '' ? $ip : null, 'succeeded' => $succeeded ? 1 : 0]);
}

function auth_login(PDO $connection, string $email, string $password): array
{
    $email = auth_normalize_email($email);
    $ip = auth_client_ip();

    if (auth_is_rate_limited($connection, $email, $ip)) {
        return ['success' => false, 'message' => 'Too many attempts. Please try again in a few minutes.'];
    }

    $statement = $connection->prepare("SELECT id, email, password_hash, status FROM users WHERE email = :email LIMIT 1");
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    $valid = $user && $user['status'] === 'active' && password_verify($password, (string) $user['password_hash']);

    auth_record_login_attempt($connection, $email, $ip, $valid);
    if (!$valid) {
        return ['success' => false, 'message' => 'Email or password is incorrect.'];
    }

    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $rawToken = bin2hex(random_bytes(32));
    $session = $connection->prepare("INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, expires_at) VALUES (:user_id, :token_hash, :ip_address, :user_agent, UTC_TIMESTAMP() + INTERVAL 30 DAY)");
    $session->execute([
        'user_id' => $user['id'],
        'token_hash' => hash('sha256', $rawToken),
        'ip_address' => $ip !== '' ? $ip : null,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);
    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['auth_session_token'] = $rawToken;

    require_once __DIR__ . '/cart.php';
    cart_merge_guest_into_user($connection, (int) $user['id']);

    $connection->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP(6) WHERE id = :id')->execute(['id' => $user['id']]);
    return ['success' => true, 'message' => ''];
}

function auth_current_user(PDO $connection): ?array
{
    start_secure_session();
    $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
    $sessionToken = (string) ($_SESSION['auth_session_token'] ?? '');
    if ($userId <= 0 || $sessionToken === '') {
        return null;
    }

    $statement = $connection->prepare("SELECT users.id, users.email, users.first_name, users.last_name, users.phone, users.status, users.email_verified_at, user_sessions.id AS session_id FROM users INNER JOIN user_sessions ON user_sessions.user_id = users.id WHERE users.id = :user_id AND users.status = 'active' AND user_sessions.token_hash = :token_hash AND user_sessions.revoked_at IS NULL AND user_sessions.expires_at > UTC_TIMESTAMP() LIMIT 1");
    $statement->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $sessionToken)]);
    $user = $statement->fetch();
    if (!$user) {
        unset($_SESSION['auth_user_id'], $_SESSION['auth_session_token']);
        return null;
    }

    $connection->prepare('UPDATE user_sessions SET last_seen_at = CURRENT_TIMESTAMP(6) WHERE id = :id')->execute(['id' => $user['session_id']]);
    return $user;
}

function auth_logout(PDO $connection): void
{
    start_secure_session();
    $sessionToken = (string) ($_SESSION['auth_session_token'] ?? '');
    if ($sessionToken !== '') {
        $statement = $connection->prepare('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE token_hash = :token_hash AND revoked_at IS NULL');
        $statement->execute(['token_hash' => hash('sha256', $sessionToken)]);
    }
    $_SESSION = [];
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

function auth_require_user(PDO $connection): array
{
    $user = auth_current_user($connection);
    if ($user === null) {
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '/account/');
        $next = security_safe_next($next);
        header('Location: ' . base_url('auth/login.php?next=' . rawurlencode($next)));
        exit;
    }
    return $user;
}

function auth_require_guest(PDO $connection): void
{
    if (auth_current_user($connection) !== null) {
        header('Location: ' . base_url('account/'));
        exit;
    }
}

function auth_create_password_reset(PDO $connection, string $email): void
{
    $email = auth_normalize_email($email);
    $statement = $connection->prepare("SELECT id FROM users WHERE email = :email AND status = 'active' LIMIT 1");
    $statement->execute(['email' => $email]);
    $userId = $statement->fetchColumn();
    if (!$userId) {
        return;
    }

    $connection->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id OR expires_at <= UTC_TIMESTAMP()')->execute(['user_id' => $userId]);
    $rawToken = bin2hex(random_bytes(32));
    $statement = $connection->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, UTC_TIMESTAMP() + INTERVAL 1 HOUR)");
    $statement->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $rawToken)]);

    // A mail provider will consume this token in a later integration phase.
    if (APP_DEBUG) {
        error_log('Password reset token generated for configured mail integration.');
    }
}

function auth_reset_password(PDO $connection, string $rawToken, string $password): bool
{
    if (mb_strlen($password) < 8 || mb_strlen($password) > 72 || !preg_match('/\S/', $rawToken)) {
        return false;
    }
    $statement = $connection->prepare("SELECT id, user_id FROM password_reset_tokens WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() LIMIT 1");
    $statement->execute(['token_hash' => hash('sha256', $rawToken)]);
    $token = $statement->fetch();
    if (!$token) {
        return false;
    }

    $connection->beginTransaction();
    try {
        $connection->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :user_id')->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'user_id' => $token['user_id']]);
        $connection->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP(6) WHERE id = :id')->execute(['id' => $token['id']]);
        $connection->prepare('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE user_id = :user_id AND revoked_at IS NULL')->execute(['user_id' => $token['user_id']]);
        $connection->commit();
        return true;
    } catch (Throwable $exception) {
        $connection->rollBack();
        throw $exception;
    }
}

function auth_safe_next(string $next): string
{
    return security_safe_next($next);
}