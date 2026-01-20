<?php
/**
 * Session Check Endpoint
 * 
 * GET /api/auth/session
 * 
 * Checks if user is logged in and returns user info
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();

if (!isAuthenticated()) {
    jsonResponse([
        'authenticated' => false,
        'csrf_token' => generateCsrfToken()
    ]);
}

$userId = getCurrentUserId();

// Get user info including is_admin
$user = Database::fetchOne(
    'SELECT id, email, is_admin, created_at FROM users WHERE id = ?',
    [$userId]
);

if (!$user) {
    // Session exists but user doesn't - clear session
    $_SESSION = [];
    session_destroy();
    jsonResponse([
        'authenticated' => false,
        'csrf_token' => generateCsrfToken()
    ]);
}

// Check if profile is complete
$profile = Database::fetchOne(
    'SELECT goal, level, equipment FROM user_profiles WHERE user_id = ?',
    [$userId]
);

$profileComplete = $profile && $profile['goal'] && $profile['level'] && $profile['equipment'];

jsonResponse([
    'authenticated' => true,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'is_admin' => (bool) ($user['is_admin'] ?? false),
        'created_at' => $user['created_at']
    ],
    'profile_complete' => $profileComplete,
    'csrf_token' => generateCsrfToken()
]);

