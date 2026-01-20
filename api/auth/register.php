<?php
/**
 * User Registration Endpoint
 * 
 * POST /api/auth/register
 * 
 * Request body:
 * {
 *   "email": "user@example.com",
 *   "password": "password123",
 *   "password_confirm": "password123"
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
$missing = validateRequired($input, ['email', 'password', 'password_confirm']);
if (!empty($missing)) {
    errorResponse('Missing required fields: ' . implode(', ', $missing));
}

$email = strtolower(trim($input['email']));
$password = $input['password'];
$passwordConfirm = $input['password_confirm'];

// Validate email format
if (!isValidEmail($email)) {
    errorResponse('Invalid email format');
}

// Validate password length
if (strlen($password) < PASSWORD_MIN_LENGTH) {
    errorResponse('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
}

// Validate password confirmation
if ($password !== $passwordConfirm) {
    errorResponse('Passwords do not match');
}

// Check if email already exists
$existingUser = Database::fetchOne(
    'SELECT id FROM users WHERE email = ?',
    [$email]
);

if ($existingUser) {
    errorResponse('Email already registered');
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

try {
    Database::beginTransaction();

    // Create user
    $userId = Database::insert(
        'INSERT INTO users (email, password_hash) VALUES (?, ?)',
        [$email, $passwordHash]
    );

    // Create empty profile
    Database::insert(
        'INSERT INTO user_profiles (user_id) VALUES (?)',
        [$userId]
    );

    Database::commit();

    // Start session for new user
    startSecureSession();
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $userId,
            'email' => $email
        ],
        'csrf_token' => generateCsrfToken()
    ], 201);

} catch (Exception $e) {
    Database::rollback();
    error_log('Registration error: ' . $e->getMessage());
    errorResponse('Registration failed', 500);
}
