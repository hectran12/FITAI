<?php
/**
 * Author Avatar Upload API (Admin Only)
 * POST /api/admin/author-avatar
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();
startSecureSession();
requireAuth();

// Check admin
$user = Database::fetchOne("SELECT is_admin FROM users WHERE id = ?", [getCurrentUserId()]);
if (!$user || !$user['is_admin']) {
    errorResponse('Admin access required', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

try {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        errorResponse('No file uploaded', 400);
    }

    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($file['type'], $allowedTypes)) {
        errorResponse('Invalid file type. Only JPG, PNG, GIF, WEBP allowed', 400);
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        errorResponse('File too large. Max 5MB', 400);
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/../../public/uploads/author';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . time() . '.' . $extension;
    $filepath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        errorResponse('Failed to save file', 500);
    }

    $avatarUrl = '/uploads/author/' . $filename;

    // Update database
    Database::update(
        "UPDATE settings SET setting_value = ? WHERE setting_key = 'author_avatar'",
        [$avatarUrl]
    );

    jsonResponse([
        'success' => true,
        'avatar_url' => $avatarUrl
    ]);

} catch (Exception $e) {
    error_log('Author avatar upload error: ' . $e->getMessage());
    errorResponse('Upload failed', 500);
}
