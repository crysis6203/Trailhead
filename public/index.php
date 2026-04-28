<?php
// Trailhead v1 — Entry Point
// Route all requests through here

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

// Load environment config
if (file_exists(APP_ROOT . '/.env')) {
    $lines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        $_ENV[$name]    = $value;
        $_SERVER[$name] = $value;
    }
}

// Autoload (if using Composer)
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}

// Bootstrap the app
require APP_ROOT . '/src/bootstrap.php';
