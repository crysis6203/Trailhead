<?php
// Trailhead v1 — Entry Point
// Route all requests through here

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

// Load environment config
if (file_exists(APP_ROOT . '/.env')) {
    $lines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        putenv($line);
    }
}

// Autoload (if using Composer)
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}

// Bootstrap the app
require APP_ROOT . '/src/bootstrap.php';
