<?php
/**
 * Comments Endpoint
 * 
 * GET /api/community/comments?post_id=X - Get comments
 * POST /api/community/comments - Add comment
 * DELETE /api/community/comments - Delete comment
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $postId = (int) ($_GET['post_id'] ?? 0);

    if (!$postId) {
        errorResponse('Post ID is required', 400);
    }

    try {
        // Get top-level comments (no parent)
        $comments = Database::fetchAll(
            'SELECT c.id, c.user_id, c.content, c.created_at, c.parent_id,
                    up.display_name, up.avatar
             FROM post_comments c
             LEFT JOIN user_profiles up ON c.user_id = up.user_id
             WHERE c.post_id = ? AND c.parent_id IS NULL
             ORDER BY c.created_at ASC',
            [$postId]
        );

        foreach ($comments as &$comment) {
            $comment['is_owner'] = $comment['user_id'] == $userId;
            $comment['display_name'] = $comment['display_name'] ?: 'Người dùng';
            $comment['time_ago'] = timeAgo($comment['created_at']);
            $comment['post_id'] = $postId;

            // Get replies for this comment
            $replies = Database::fetchAll(
                'SELECT c.id, c.user_id, c.content, c.created_at, c.parent_id,
                        up.display_name, up.avatar
                 FROM post_comments c
                 LEFT JOIN user_profiles up ON c.user_id = up.user_id
                 WHERE c.parent_id = ?
                 ORDER BY c.created_at ASC',
                [$comment['id']]
            );

            foreach ($replies as &$reply) {
                $reply['is_owner'] = $reply['user_id'] == $userId;
                $reply['display_name'] = $reply['display_name'] ?: 'Người dùng';
                $reply['time_ago'] = timeAgo($reply['created_at']);
                $reply['post_id'] = $postId;
            }

            $comment['replies'] = $replies;
        }

        jsonResponse([
            'success' => true,
            'comments' => $comments
        ]);

    } catch (Exception $e) {
        error_log('Get comments error: ' . $e->getMessage());
        errorResponse('Không thể tải bình luận', 500);
    }

} elseif ($method === 'POST') {
    $input = getJsonInput();

    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        errorResponse('Invalid CSRF token', 403);
    }

    $postId = (int) ($input['post_id'] ?? 0);
    $content = trim($input['content'] ?? '');

    if (!$postId) {
        errorResponse('Post ID is required', 400);
    }

    if (empty($content)) {
        errorResponse('Nội dung không được để trống', 400);
    }

    if (strlen($content) > 1000) {
        errorResponse('Bình luận quá dài (tối đa 1000 ký tự)', 400);
    }

    try {
        // Check if post exists
        $post = Database::fetchOne('SELECT id FROM posts WHERE id = ?', [$postId]);
        if (!$post) {
            errorResponse('Bài viết không tồn tại', 404);
        }

        // Insert comment
        $parentId = isset($input['parent_id']) && $input['parent_id'] > 0 ? (int) $input['parent_id'] : null;

        $commentId = Database::insert(
            'INSERT INTO post_comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)',
            [$postId, $userId, $content, $parentId]
        );

        // Update comment count
        Database::update(
            'UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?',
            [$postId]
        );

        // Get created comment
        $comment = Database::fetchOne(
            'SELECT c.*, up.display_name, up.avatar
             FROM post_comments c
             LEFT JOIN user_profiles up ON c.user_id = up.user_id
             WHERE c.id = ?',
            [$commentId]
        );

        $comment['is_owner'] = true;
        $comment['display_name'] = $comment['display_name'] ?: 'Người dùng';
        $comment['time_ago'] = 'Vừa xong';

        jsonResponse([
            'success' => true,
            'comment' => $comment
        ]);

    } catch (Exception $e) {
        error_log('Add comment error: ' . $e->getMessage());
        errorResponse('Không thể thêm bình luận', 500);
    }

} elseif ($method === 'DELETE') {
    $input = getJsonInput();
    $commentId = (int) ($input['comment_id'] ?? 0);

    if (!$commentId) {
        errorResponse('Comment ID is required', 400);
    }

    try {
        $comment = Database::fetchOne(
            'SELECT id, post_id, user_id FROM post_comments WHERE id = ?',
            [$commentId]
        );

        if (!$comment) {
            errorResponse('Bình luận không tồn tại', 404);
        }

        if ($comment['user_id'] != $userId) {
            errorResponse('Bạn không có quyền xóa bình luận này', 403);
        }

        Database::update('DELETE FROM post_comments WHERE id = ?', [$commentId]);

        Database::update(
            'UPDATE posts SET comments_count = GREATEST(0, comments_count - 1) WHERE id = ?',
            [$comment['post_id']]
        );

        jsonResponse([
            'success' => true,
            'message' => 'Đã xóa bình luận'
        ]);

    } catch (Exception $e) {
        error_log('Delete comment error: ' . $e->getMessage());
        errorResponse('Không thể xóa bình luận', 500);
    }

} else {
    errorResponse('Method not allowed', 405);
}

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
