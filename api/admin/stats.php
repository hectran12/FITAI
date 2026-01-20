<?php
/**
 * Admin Statistics API
 * 
 * GET /api/admin/stats.php
 * Returns overall system statistics
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/middleware.php';

handleCorsPreflightRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();
requireAdmin();

try {
    // Total users
    $totalUsers = Database::fetchOne('SELECT COUNT(*) as count FROM users');

    // Users today
    $usersToday = Database::fetchOne(
        'SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()'
    );

    // Banned users
    $bannedUsers = Database::fetchOne(
        'SELECT COUNT(*) as count FROM users WHERE is_banned = 1'
    );

    // Total posts (if table exists)
    $totalPosts = 0;
    $postsToday = 0;
    try {
        $result = Database::fetchOne('SELECT COUNT(*) as count FROM posts');
        $totalPosts = $result['count'] ?? 0;

        $result = Database::fetchOne(
            'SELECT COUNT(*) as count FROM posts WHERE DATE(created_at) = CURDATE()'
        );
        $postsToday = $result['count'] ?? 0;
    } catch (Exception $e) {
        // Posts table doesn't exist
    }

    // Total workout plans
    $totalPlans = 0;
    try {
        $result = Database::fetchOne('SELECT COUNT(*) as count FROM workout_plans');
        $totalPlans = $result['count'] ?? 0;
    } catch (Exception $e) {
        // Table doesn't exist
    }

    // Total exercises
    $totalExercises = 0;
    try {
        $result = Database::fetchOne('SELECT COUNT(*) as count FROM exercises');
        $totalExercises = $result['count'] ?? 0;
    } catch (Exception $e) {
        // Table doesn't exist
    }

    // Recent users (last 5)
    $recentUsers = Database::fetchAll(
        'SELECT id, email, created_at FROM users ORDER BY created_at DESC LIMIT 5'
    );

    jsonResponse([
        'success' => true,
        'stats' => [
            'total_users' => (int) $totalUsers['count'],
            'users_today' => (int) $usersToday['count'],
            'banned_users' => (int) $bannedUsers['count'],
            'total_posts' => (int) $totalPosts,
            'posts_today' => (int) $postsToday,
            'total_plans' => (int) $totalPlans,
            'total_exercises' => (int) $totalExercises
        ],
        'recent_users' => $recentUsers
    ]);

} catch (Exception $e) {
    error_log('Admin stats error: ' . $e->getMessage());
    errorResponse('Không thể tải thống kê', 500);
}
