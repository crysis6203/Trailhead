<?php
// Trailhead Bootstrap
// Initialize session, DB connection, and route the request

session_start();

// DB connection
require_once APP_ROOT . '/src/db.php';

// Simple front controller router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

$routes = [
    ''            => 'controllers/dashboard.php',
    '/login'      => 'controllers/login.php',
    '/logout'     => 'controllers/logout.php',
    '/sessions'   => 'controllers/sessions.php',
    '/scouts'     => 'controllers/scouts.php',
    '/queue'      => 'controllers/queue.php',
    '/run'        => 'controllers/run.php',
];

$controller = $routes[$uri] ?? null;

if ($controller && file_exists(APP_ROOT . '/src/' . $controller)) {
    require APP_ROOT . '/src/' . $controller;
} else {
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
}
