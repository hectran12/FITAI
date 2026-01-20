<?php
/**
 * Search Users API
 * 
 * GET - Search users to add as friends
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

handleCorsPreflightRequest();
startSecureSession();
requireAuth();

$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

try {
    $query = trim($_GET['q'] ?? '');

    if (strlen($query) < 2) {
        jsonResponse(['success' => true, 'users' => []]);
    }

    $users = Database::fetchAll("
        SELECT u.id, u.email, up.display_name, up.avatar,
               (SELECT status FROM friendships f 
                WHERE (f.user_id = ? AND f.friend_id = u.id) 
                   OR (f.user_id = u.id AND f.friend_id = ?)
                LIMIT 1) as friendship_status
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id != ? 
          AND u.is_banned = 0
          AND (up.display_name LIKE ? OR u.email LIKE ?)
        LIMIT 20
    ", [$userId, $userId, $userId, "%$query%", "%$query%"]);

    jsonResponse([
        'success' => true,
        'users' => $users
    ]);
} catch (Exception $e) {
    error_log('Search users error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}
