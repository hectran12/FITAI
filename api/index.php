<?php
/**
 * FitAI API Router (Front Controller)
 * 
 * Routes all API requests to appropriate endpoint handlers
 * 
 * Usage: php -S localhost:8000 -t public api/index.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Handle CORS preflight
handleCorsPreflightRequest();

// Start secure session
startSecureSession();

// Get request path and method
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove /api prefix if present
$path = preg_replace('#^/api#', '', $path);

// Route mapping
$routes = [
    // Auth routes
    'POST /auth/register' => 'auth/register.php',
    'POST /auth/login' => 'auth/login.php',
    'POST /auth/logout' => 'auth/logout.php',
    'GET /auth/session' => 'auth/session.php',

    // Profile routes
    'GET /profile' => 'profile/get.php',
    'POST /profile' => 'profile/update.php',

    // Plan routes
    'GET /plans' => 'plans/get.php',
    'GET /plans/current' => 'plans/get.php',
    'POST /plans/generate' => 'plans/generate.php',
    'POST /plans/regenerate' => 'plans/regenerate.php',
    'POST /plans/adjust' => 'plans/adjust.php',

    // Log routes
    'POST /logs' => 'logs/save.php',
    'GET /logs' => 'logs/get.php',

    // Dashboard routes
    'GET /dashboard/stats' => 'dashboard/stats.php',

    // Exercises routes
    'GET /exercises' => 'exercises/list.php',
];

// Find matching route
$routeKey = "$requestMethod $path";
$routeFile = null;

foreach ($routes as $route => $file) {
    if ($route === $routeKey) {
        $routeFile = $file;
        break;
    }
}

// Check if route exists
if ($routeFile === null) {
    // Check if file exists directly (for simple endpoint access)
    $directFile = __DIR__ . $path . '.php';
    if (file_exists($directFile)) {
        require $directFile;
        exit;
    }

    errorResponse('Endpoint not found', 404);
}

// Include the route handler
$handlerPath = __DIR__ . '/' . $routeFile;

if (!file_exists($handlerPath)) {
    errorResponse('Handler not found', 500);
}

require $handlerPath;
