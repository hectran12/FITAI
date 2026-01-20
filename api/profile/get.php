<?php
/**
 * Get User Profile Endpoint
 * 
 * GET /api/profile
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

$userId = getCurrentUserId();

// Get user and profile data
$user = Database::fetchOne(
    'SELECT u.id, u.email, u.created_at,
            p.display_name, p.avatar, p.bio, p.social_links,
            p.goal, p.level, p.days_per_week, p.session_minutes,
            p.equipment, p.constraints_text, p.availability
     FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE u.id = ?',
    [$userId]
);

if (!$user) {
    errorResponse('User not found', 404);
}

// Parse JSON fields
$availability = $user['availability'] ? json_decode($user['availability'], true) : null;
$socialLinks = $user['social_links'] ? json_decode($user['social_links'], true) : null;

jsonResponse([
    'success' => true,
    'profile' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'display_name' => $user['display_name'],
        'avatar' => $user['avatar'],
        'bio' => $user['bio'],
        'social_links' => $socialLinks,
        'goal' => $user['goal'],
        'level' => $user['level'],
        'days_per_week' => (int) $user['days_per_week'],
        'session_minutes' => (int) $user['session_minutes'],
        'equipment' => $user['equipment'],
        'constraints' => $user['constraints_text'],
        'availability' => $availability,
        'created_at' => $user['created_at']
    ]
]);
