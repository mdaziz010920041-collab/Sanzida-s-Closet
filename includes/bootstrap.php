<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/database.php';

configure_error_handling();
header_remove('X-Powered-By');
security_headers();
start_secure_session();
