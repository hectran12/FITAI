<?php
/**
 * Community Stats Endpoint
 * 
 * GET /api/community/stats.php - Get community stats
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

startSecureSession();

try {
    // Count total members
    $members = Database::fetchOne(
        'SELECT COUNT(*) as count FROM users'
    );

    // Count total posts
    $posts = Database::fetchOne(
        'SELECT COUNT(*) as count FROM posts'
    );

    jsonResponse([
        'success' => true,
        'members' => (int) $members['count'],
        'posts' => (int) $posts['count']
    ]);

} catch (Exception $e) {
    error_log('Get community stats error: ' . $e->getMessage());
    errorResponse('Không thể tải thống kê', 500);
}
