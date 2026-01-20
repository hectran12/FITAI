<?php
/**
 * Post Create/Delete Endpoint
 * 
 * POST /api/community/post - Create post
 * DELETE /api/community/post - Delete post
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Check if multipart form or JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'multipart/form-data') !== false) {
        $content = trim($_POST['content'] ?? '');
        $postType = $_POST['post_type'] ?? 'general';
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    } else {
        $input = getJsonInput();
        $content = trim($input['content'] ?? '');
        $postType = $input['post_type'] ?? 'general';
        $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    // Validate CSRF
    if (!validateCsrfToken($csrfToken)) {
        errorResponse('Invalid CSRF token', 403);
    }

    // Validate content
    if (empty($content)) {
        errorResponse('Nội dung không được để trống', 400);
    }

    if (strlen($content) > 5000) {
        errorResponse('Nội dung quá dài (tối đa 5000 ký tự)', 400);
    }

    // Validate post type
    if (!in_array($postType, ['question', 'achievement', 'general'])) {
        $postType = 'general';
    }

    $imagePath = null;
    $imagePaths = [];

    // Handle multiple image uploads
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB per image
        $maxImages = 5; // Max 5 images per post

        $uploadDir = __DIR__ . '/../../public/uploads/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileCount = min(count($_FILES['images']['name']), $maxImages);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = $_FILES['images']['tmp_name'][$i];
            $fileSize = $_FILES['images']['size'][$i];

            // Validate type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                continue;
            }

            if ($fileSize > $maxSize) {
                continue;
            }

            // Determine extension
            $ext = 'jpg';
            if ($mimeType === 'image/png')
                $ext = 'png';
            elseif ($mimeType === 'image/webp')
                $ext = 'webp';

            $filename = 'post_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $i . '.' . $ext;
            $fullPath = $uploadDir . $filename;

            // Move file
            if (move_uploaded_file($tmpName, $fullPath)) {
                $imagePaths[] = '/uploads/posts/' . $filename;
            }
        }

        // Store as JSON if multiple images, or single path for backward compatibility
        if (count($imagePaths) > 0) {
            $imagePath = json_encode($imagePaths);
        }
    }
    // Backward compatibility: single image upload
    elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (in_array($mimeType, $allowedTypes) && $file['size'] <= $maxSize) {
            $uploadDir = __DIR__ . '/../../public/uploads/posts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = 'jpg';
            if ($mimeType === 'image/png')
                $ext = 'png';
            elseif ($mimeType === 'image/webp')
                $ext = 'webp';

            $filename = 'post_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $fullPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                $imagePath = '/uploads/posts/' . $filename;
            }
        }
    }

    try {
        $postId = Database::insert(
            'INSERT INTO posts (user_id, post_type, content, image_path) VALUES (?, ?, ?, ?)',
            [$userId, $postType, $content, $imagePath]
        );

        // Get the created post
        $post = Database::fetchOne(
            'SELECT p.*, up.display_name, up.avatar 
             FROM posts p 
             LEFT JOIN user_profiles up ON p.user_id = up.user_id 
             WHERE p.id = ?',
            [$postId]
        );

        $post['is_liked'] = false;
        $post['is_owner'] = true;
        $post['time_ago'] = 'Vừa xong';
        $post['display_name'] = $post['display_name'] ?: 'Người dùng';

        jsonResponse([
            'success' => true,
            'message' => 'Đăng bài thành công!',
            'post' => $post
        ]);

    } catch (Exception $e) {
        error_log('Create post error: ' . $e->getMessage());
        errorResponse('Không thể đăng bài', 500);
    }

} elseif ($method === 'DELETE') {
    $input = getJsonInput();
    $postId = (int) ($input['post_id'] ?? 0);

    if (!$postId) {
        errorResponse('Post ID is required', 400);
    }

    try {
        // Check ownership
        $post = Database::fetchOne(
            'SELECT id, user_id, image_path FROM posts WHERE id = ?',
            [$postId]
        );

        if (!$post) {
            errorResponse('Bài viết không tồn tại', 404);
        }

        if ($post['user_id'] != $userId) {
            errorResponse('Bạn không có quyền xóa bài này', 403);
        }

        // Delete image if exists
        if ($post['image_path']) {
            $imagePath = __DIR__ . '/../../public' . $post['image_path'];
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }

        Database::update('DELETE FROM posts WHERE id = ?', [$postId]);

        jsonResponse([
            'success' => true,
            'message' => 'Đã xóa bài viết'
        ]);

    } catch (Exception $e) {
        error_log('Delete post error: ' . $e->getMessage());
        errorResponse('Không thể xóa bài viết', 500);
    }

} else {
    errorResponse('Method not allowed', 405);
}
