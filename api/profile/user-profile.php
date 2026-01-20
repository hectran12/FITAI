<?php
/**
 * Get Public User Profile
 * 
 * GET /api/profile/user-profile.php?user_id=X
 * 
 * Returns public profile data for any user
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if (!$userId) {
    errorResponse('User ID required', 400);
}

try {
    // Get user profile - join with user_profiles table for display_name and avatar
    $user = Database::fetchOne(
        'SELECT u.id, u.created_at, 
                up.display_name,
                up.avatar,
                up.bio, up.social_links
         FROM users u
         LEFT JOIN user_profiles up ON u.id = up.user_id
         WHERE u.id = ?',
        [$userId]
    );

    if (!$user) {
        errorResponse('User not found', 404);
    }

    // Count user's posts - handle if posts table doesn't exist
    $postCount = 0;
    try {
        $result = Database::fetchOne(
            'SELECT COUNT(*) as count FROM posts WHERE user_id = ?',
            [$userId]
        );
        $postCount = $result ? (int) $result['count'] : 0;
    } catch (Exception $e) {
        // Posts table might not exist
        $postCount = 0;
    }

    // Parse social links safely
    $socialLinks = [];
    if (!empty($user['social_links'])) {
        $decoded = json_decode($user['social_links'], true);
        if (is_array($decoded)) {
            $socialLinks = $decoded;
        }
    }

    // Format member since
    $memberSince = !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'N/A';

    jsonResponse([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'display_name' => !empty($user['display_name']) ? $user['display_name'] : 'User ' . $user['id'],
            'avatar' => $user['avatar'] ?? null,
            'bio' => $user['bio'] ?? null,
            'social_links' => $socialLinks,
            'member_since' => $memberSince,
            'post_count' => $postCount
        ]
    ]);

} catch (Exception $e) {
    error_log('Get user profile error: ' . $e->getMessage());
    errorResponse('Cannot load user profile: ' . $e->getMessage(), 500);
}
