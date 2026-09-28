<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

try { $connection = database_connection(); if (request_is_post() && verify_csrf_token($_POST['csrf_token'] ?? null)) admin_logout($connection); } catch (Throwable $exception) { error_log($exception->getMessage()); }
header('Location: ' . base_url('admin/login.php')); exit;