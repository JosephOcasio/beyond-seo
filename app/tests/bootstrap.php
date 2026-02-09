<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap File
 *
 * Sets up the test environment and ensures all necessary files are loaded.
 */

// Define base path
if (!defined('RANKINGCOACH_DIR')) {
    define('RANKINGCOACH_DIR', dirname(__DIR__));
}

// Load Composer autoloader
$autoload_path = RANKINGCOACH_DIR . '/vendor/autoload.php';
if (!file_exists($autoload_path)) {
    throw new RuntimeException('Composer autoloader not found. Run: composer install');
}
require_once $autoload_path;

// Load application autoloader
$app_autoload = RANKINGCOACH_DIR . '/app/vendor/autoload.php';
if (file_exists($app_autoload)) {
    require_once $app_autoload;
}
