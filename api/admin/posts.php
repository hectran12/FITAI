<?php
/**
 * Admin Posts Management API
 * 
 * GET /api/admin/posts.php - List posts
 * DELETE /api/admin/posts.php - Delete post
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/middleware.php';

handleCorsPreflightRequest();

startSecureSession();
requireAuth();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetPosts();
            break;
        case 'DELETE':
            handleDeletePost();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin posts error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

/**
 * Get posts list with pagination
 */
function handleGetPosts()
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $type = $_GET['type'] ?? 'all';

    $whereClause = '';
    $params = [];

    if ($type !== 'all' && in_array($type, ['question', 'achievement', 'general'])) {
        $whereClause = 'WHERE p.post_type = ?';
        $params = [$type];
    }

    $sql = "SELECT p.id, p.user_id, p.post_type, p.content, p.image_path,
                   p.likes_count, p.comments_count, p.created_at,
                   up.display_name, up.avatar
            FROM posts p
            LEFT JOIN user_profiles up ON p.user_id = up.user_id
            $whereClause
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";

    $queryParams = array_merge($params, [$limit, $offset]);
    $posts = Database::fetchAll($sql, $queryParams);

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM posts p $whereClause";
    $total = Database::fetchOne($countSql, $params);

    // Format data
    foreach ($posts as &$post) {
        $post['created_at_formatted'] = date('d/m/Y H:i', strtotime($post['created_at']));
        $post['display_name'] = $post['display_name'] ?: 'User ' . $post['user_id'];
        $post['content_preview'] = mb_substr($post['content'], 0, 100) . (mb_strlen($post['content']) > 100 ? '...' : '');
    }

    jsonResponse([
        'success' => true,
        'posts' => $posts,
        'total' => (int) $total['total'],
        'page' => $page,
        'total_pages' => ceil($total['total'] / $limit)
    ]);
}

/**
 * Delete a post
 */
function handleDeletePost()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $postId = (int) ($data['post_id'] ?? 0);

    if (!$postId) {
        errorResponse('Post ID required', 400);
    }

    // Check if post exists
    $post = Database::fetchOne('SELECT id FROM posts WHERE id = ?', [$postId]);
    if (!$post) {
        errorResponse('Post not found', 404);
    }

    // Delete post (cascade will handle likes/comments)
    Database::delete('DELETE FROM posts WHERE id = ?', [$postId]);

    jsonResponse([
        'success' => true,
        'message' => 'Đã xóa bài viết thành công'
    ]);
}
