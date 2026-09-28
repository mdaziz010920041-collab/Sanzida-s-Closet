<?php

declare(strict_types=1);

function admin_login(PDO $connection, string $email, string $password): array
{
    $email = auth_normalize_email($email);
    $ip = auth_client_ip();
    if (auth_is_rate_limited($connection, $email, $ip)) return ['success' => false, 'message' => 'Too many attempts. Please try again in a few minutes.'];
    $statement = $connection->prepare("SELECT admins.id, admins.email, admins.password_hash, admins.display_name, admins.status, roles.slug AS role_slug FROM admins INNER JOIN roles ON roles.id = admins.role_id WHERE admins.email = :email LIMIT 1");
    $statement->execute(['email' => $email]);
    $admin = $statement->fetch();
    $valid = $admin && $admin['status'] === 'active' && password_verify($password, (string) $admin['password_hash']);
    auth_record_login_attempt($connection, $email, $ip, (bool) $valid);
    if (!$valid) return ['success' => false, 'message' => 'Email or password is incorrect.'];

    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $rawToken = bin2hex(random_bytes(32));
    $session = $connection->prepare("INSERT INTO admin_sessions (admin_id, token_hash, ip_address, user_agent, expires_at) VALUES (:admin_id, :token_hash, :ip_address, :user_agent, UTC_TIMESTAMP() + INTERVAL 8 HOUR)");
    $session->execute(['admin_id' => $admin['id'], 'token_hash' => hash('sha256', $rawToken), 'ip_address' => $ip !== '' ? $ip : null, 'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)]);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_session_token'] = $rawToken;
    $connection->prepare('UPDATE admins SET last_login_at = CURRENT_TIMESTAMP(6) WHERE id = :id')->execute(['id' => $admin['id']]);
    admin_audit($connection, (int) $admin['id'], 'admin.login', 'admin', (int) $admin['id']);
    return ['success' => true, 'message' => ''];
}

function admin_current(PDO $connection): ?array
{
    start_secure_session();
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    $token = (string) ($_SESSION['admin_session_token'] ?? '');
    if ($adminId <= 0 || $token === '') return null;
    $statement = $connection->prepare("SELECT admins.id, admins.email, admins.display_name, roles.id AS role_id, roles.name AS role_name, roles.slug AS role_slug, admin_sessions.id AS session_id FROM admins INNER JOIN roles ON roles.id = admins.role_id INNER JOIN admin_sessions ON admin_sessions.admin_id = admins.id WHERE admins.id = :admin_id AND admins.status = 'active' AND admin_sessions.token_hash = :token_hash AND admin_sessions.revoked_at IS NULL AND admin_sessions.expires_at > UTC_TIMESTAMP() LIMIT 1");
    $statement->execute(['admin_id' => $adminId, 'token_hash' => hash('sha256', $token)]);
    $admin = $statement->fetch();
    if (!$admin) { unset($_SESSION['admin_id'], $_SESSION['admin_session_token']); return null; }
    $connection->prepare('UPDATE admin_sessions SET last_seen_at = CURRENT_TIMESTAMP(6) WHERE id = :id')->execute(['id' => $admin['session_id']]);
    return $admin;
}

function admin_audit(PDO $connection, ?int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
{
    $statement = $connection->prepare('INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id, ip_address, metadata) VALUES (:admin_id, :action, :entity_type, :entity_id, :ip_address, :metadata)');
    $statement->execute(['admin_id' => $adminId, 'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId, 'ip_address' => auth_client_ip() ?: null, 'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR)]);
}

function admin_has_permission(PDO $connection, array $admin, string $permission): bool
{
    if (in_array($admin['role_slug'] ?? '', ['super-admin', 'owner'], true)) return true;
    $statement = $connection->prepare('SELECT 1 FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :role_id AND p.slug = :permission LIMIT 1');
    $statement->execute(['role_id' => $admin['role_id'], 'permission' => $permission]);
    return (bool) $statement->fetchColumn();
}

function admin_require(PDO $connection, ?string $permission = null): array
{
    $admin = admin_current($connection);
    if (!$admin) { header('Location: ' . base_url('admin/login.php')); exit; }
    if ($permission !== null && !admin_has_permission($connection, $admin, $permission)) { http_response_code(403); exit('Forbidden'); }
    return $admin;
}

function admin_logout(PDO $connection): void
{
    $token = (string) ($_SESSION['admin_session_token'] ?? '');
    if ($token !== '') $connection->prepare('UPDATE admin_sessions SET revoked_at = CURRENT_TIMESTAMP(6) WHERE token_hash = :token_hash')->execute(['token_hash' => hash('sha256', $token)]);
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId > 0) admin_audit($connection, $adminId, 'admin.logout', 'admin', $adminId);
    $_SESSION = [];
    session_regenerate_id(true);
}