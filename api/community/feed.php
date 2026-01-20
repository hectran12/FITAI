<?php
/**
 * Community Feed Endpoint
 * 
 * GET /api/community/feed
 * 
 * Get posts list with pagination and filtering
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$type = $_GET['type'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Build query based on filter
    $whereClause = '';
    $params = [];

    if ($type !== 'all' && in_array($type, ['question', 'achievement', 'general'])) {
        $whereClause = 'WHERE p.post_type = ?';
        $params[] = $type;
    }

    // Get posts with user info and like status
    $sql = "SELECT 
                p.id,
                p.user_id,
                p.post_type,
                p.content,
                p.image_path,
                p.likes_count,
                p.comments_count,
                p.created_at,
                up.display_name,
                up.avatar,
                CASE WHEN pl.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
            FROM posts p
            LEFT JOIN user_profiles up ON p.user_id = up.user_id
            LEFT JOIN post_likes pl ON p.id = pl.post_id AND pl.user_id = ?
            $whereClause
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";

    $queryParams = array_merge([$userId], $params, [$limit, $offset]);
    $posts = Database::fetchAll($sql, $queryParams);

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM posts p $whereClause";
    $total = Database::fetchOne($countSql, $params);

    // Format dates
    foreach ($posts as &$post) {
        $post['is_liked'] = (bool) $post['is_liked'];
        $post['is_owner'] = $post['user_id'] == $userId;
        $post['time_ago'] = timeAgo($post['created_at']);
        $post['display_name'] = $post['display_name'] ?: 'Người dùng';
    }

    jsonResponse([
        'success' => true,
        'posts' => $posts,
        'total' => $total['total'],
        'page' => $page,
        'has_more' => ($offset + count($posts)) < $total['total']
    ]);

} catch (Exception $e) {
    error_log('Feed error: ' . $e->getMessage());
    errorResponse('Không thể tải bài viết', 500);
}

/**
 * Format time ago in Vietnamese
 */
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'Vừa xong';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' phút trước';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' giờ trước';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' ngày trước';
    } else {
        return date('d/m/Y', $time);
    }
}
