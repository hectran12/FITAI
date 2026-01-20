<?php
/**
 * User Login Endpoint
 * 
 * POST /api/auth/login
 * 
 * Request body:
 * {
 *   "email": "user@example.com",
 *   "password": "password123"
 * }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// Get input
$input = getJsonInput();

// Validate required fields
$missing = validateRequired($input, ['email', 'password']);
if (!empty($missing)) {
    errorResponse('Missing required fields: ' . implode(', ', $missing));
}

$email = strtolower(trim($input['email']));
$password = $input['password'];

// Validate email format
if (!isValidEmail($email)) {
    errorResponse('Invalid email format');
}

// Find user by email
$user = Database::fetchOne(
    'SELECT id, email, password_hash FROM users WHERE email = ?',
    [$email]
);

if (!$user) {
    // Use generic message to prevent email enumeration
    errorResponse('Invalid email or password', 401);
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    errorResponse('Invalid email or password', 401);
}

// Start session
startSecureSession();
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['login_time'] = time();

// Check if user has completed profile
$profile = Database::fetchOne(
    'SELECT goal, level, equipment FROM user_profiles WHERE user_id = ?',
    [$user['id']]
);

$profileComplete = $profile && $profile['goal'] && $profile['level'] && $profile['equipment'];

jsonResponse([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => $user['id'],
        'email' => $user['email']
    ],
    'profile_complete' => $profileComplete,
    'csrf_token' => generateCsrfToken()
]);
