<?php

putenv('DB_NAME=nestdecor_test');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/exceptions.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/CategoryService.php';
require_once __DIR__ . '/../includes/ProductService.php';
require_once __DIR__ . '/../includes/SkuService.php';
require_once __DIR__ . '/TestCase.php';

$GLOBALS['pdo'] = $pdo;