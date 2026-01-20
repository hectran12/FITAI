<?php
/**
 * FitAI Configuration File
 * 
 * Database and service configuration settings
 * Copy this to config.php and update values for your environment
 */

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ioyqxjwslp_fitai');
define('DB_USER', 'ioyqxjwslp_nhanhoane');
define('DB_PASS', 'nhanhoane@A123'); // Set your MySQL password

// AI Service Configuration
define('AI_SERVICE_URL', 'http://localhost:8001');
define('AI_SERVICE_TIMEOUT', 30); // seconds

// Session Configuration
define('SESSION_LIFETIME', 86400); // 24 hours in seconds
define('SESSION_NAME', 'fitai_session');

// Security Configuration
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);

// Application Settings
define('APP_NAME', 'FitAI');
define('APP_URL', 'https://fitai.one');

// SMTP Email Configuration (Gmail)
// To use Gmail SMTP, you need to:
// 1. Enable 2-Factor Authentication on your Gmail account
// 2. Generate an App Password: https://myaccount.google.com/apppasswords
// 3. Replace the placeholders below with your credentials
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Replace with your Gmail
define('SMTP_PASSWORD', 'your-app-password'); // Replace with your App Password
define('SMTP_FROM_EMAIL', 'noreply@fitai.com');
define('SMTP_FROM_NAME', 'FitAI');

// CORS Settings (for API access)
define('CORS_ALLOWED_ORIGINS', ['https://fitai.one', 'https://fitai.one', 'https://fitai.one', 'https://fitai.one']);

// Timezone
date_default_timezone_set('UTC');

/**
 * Start session with secure settings
 */
function startSecureSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => false, // Set to true in production with HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_name(SESSION_NAME);
        session_start();
    }
}

/**
 * Set JSON response headers
 */
function setJsonHeaders()
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    // CORS headers
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, CORS_ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
}

/**
 * Handle preflight OPTIONS request
 */
function handleCorsPreflightRequest()
{
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setJsonHeaders();
        http_response_code(200);
        exit;
    }
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200)
{
    setJsonHeaders();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 */
function errorResponse($message, $statusCode = 400)
{
    jsonResponse(['error' => true, 'message' => $message], $statusCode);
}

/**
 * Get JSON request body
 */
function getJsonInput()
{
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Validate required fields in input
 */
function validateRequired($input, $fields)
{
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token)
{
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    // Check if token has expired
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is authenticated
 */
function isAuthenticated()
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Require authentication for endpoint
 */
function requireAuth()
{
    if (!isAuthenticated()) {
        errorResponse('Authentication required', 401);
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Sanitize string input
 */
function sanitizeString($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get week start date (Monday) for a given date
 */
function getWeekStart($date = null)
{
    $date = $date ?? date('Y-m-d');
    $timestamp = strtotime($date);
    $dayOfWeek = date('N', $timestamp); // 1 = Monday, 7 = Sunday
    $mondayOffset = $dayOfWeek - 1;
    return date('Y-m-d', strtotime("-{$mondayOffset} days", $timestamp));
}
