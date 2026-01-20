<?php
/**
 * Like/Unlike Post Endpoint
 * 
 * POST /api/community/like
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$input = getJsonInput();

$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    errorResponse('Invalid CSRF token', 403);
}

$postId = (int) ($input['post_id'] ?? 0);
if (!$postId) {
    errorResponse('Post ID is required', 400);
}

try {
    // Check if post exists
    $post = Database::fetchOne('SELECT id, likes_count FROM posts WHERE id = ?', [$postId]);
    if (!$post) {
        errorResponse('Bài viết không tồn tại', 404);
    }

    // Check if already liked
    $existingLike = Database::fetchOne(
        'SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?',
        [$postId, $userId]
    );

    if ($existingLike) {
        // Unlike
        Database::update(
            'DELETE FROM post_likes WHERE post_id = ? AND user_id = ?',
            [$postId, $userId]
        );
        Database::update(
            'UPDATE posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?',
            [$postId]
        );

        $newCount = max(0, $post['likes_count'] - 1);
        $isLiked = false;

    } else {
        // Like
        Database::insert(
            'INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)',
            [$postId, $userId]
        );
        Database::update(
            'UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?',
            [$postId]
        );

        $newCount = $post['likes_count'] + 1;
        $isLiked = true;
    }

    jsonResponse([
        'success' => true,
        'is_liked' => $isLiked,
        'likes_count' => $newCount
    ]);

} catch (Exception $e) {
    error_log('Like error: ' . $e->getMessage());
    errorResponse('Không thể thực hiện', 500);
}
