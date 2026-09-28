<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$email = trim((string) (getenv('ADMIN_EMAIL') ?: ($argv[1] ?? '')));
$password = (string) (getenv('ADMIN_PASSWORD') ?: ($argv[2] ?? ''));
$displayName = trim((string) (getenv('ADMIN_NAME') ?: 'Store administrator'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD='strong-password' php database/create-admin.php\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "ADMIN_PASSWORD must be at least 12 characters.\n");
    exit(1);
}

$connection = database_connection();
$connection->beginTransaction();
try {
    $role = $connection->prepare("INSERT INTO roles (name, slug, description) VALUES ('Store owner', 'owner', 'Full store administration') ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
    $role->execute();
    $roleId = (int) $connection->lastInsertId();
    $admin = $connection->prepare('INSERT INTO admins (role_id, email, password_hash, display_name, status) VALUES (:role_id, :email, :password_hash, :display_name, \'active\') ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), password_hash = VALUES(password_hash), display_name = VALUES(display_name), status = \'active\'');
    $admin->execute(['role_id' => $roleId, 'email' => strtolower($email), 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'display_name' => $displayName]);
    $connection->commit();
    fwrite(STDOUT, "Admin account created or updated for {$email}.\n");
} catch (Throwable $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    fwrite(STDERR, "Admin setup failed: {$exception->getMessage()}\n");
    exit(1);
}
