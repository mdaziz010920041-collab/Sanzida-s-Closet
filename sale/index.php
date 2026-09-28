<?php

declare(strict_types=1);

$_GET['sale'] = '1';
$_GET['discount'] = 'on-sale';
require dirname(__DIR__) . '/products/index.php';
