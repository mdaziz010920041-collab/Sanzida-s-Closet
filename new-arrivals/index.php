<?php

declare(strict_types=1);

$_GET['new_arrivals'] = '1';
$_GET['sort'] = 'newest';
require dirname(__DIR__) . '/products/index.php';
