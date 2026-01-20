<?php
/**
 * User Logout Endpoint
 * 
 * POST /api/auth/logout
 */

require_once __DIR__ . '/../config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// Start session to destroy it
startSecureSession();

// Clear session data
$_SESSION = [];

// Delete session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

jsonResponse([
    'success' => true,
    'message' => 'Logged out successfully'
]);
