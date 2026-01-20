<?php
/**
 * Avatar Upload API
 * POST: Upload new avatar
 * DELETE: Remove avatar
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Start session and require authentication
startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$user = ['id' => $userId];

// Handle different methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        handleUpload($user);
        break;
    case 'DELETE':
        handleDelete($user);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

/**
 * Handle avatar upload
 */
function handleUpload($user)
{
    // Check if file was uploaded
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Không có file được tải lên']);
        return;
    }

    $file = $_FILES['avatar'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WebP)']);
        return;
    }

    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File quá lớn. Tối đa 5MB']);
        return;
    }

    // Define upload directory
    $uploadDir = __DIR__ . '/../../public/uploads/avatars/';

    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => 'jpg'
    };
    $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Delete old avatar if exists
    deleteOldAvatar($user['id']);

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu file']);
        return;
    }

    // Resize image if needed (max 400x400)
    resizeImage($filepath, 400, 400);

    // Update database
    $avatarPath = '/uploads/avatars/' . $filename;
    $db = Database::getConnection();

    $stmt = $db->prepare("
        UPDATE user_profiles 
        SET avatar = ? 
        WHERE user_id = ?
    ");
    $stmt->execute([$avatarPath, $user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Đã cập nhật ảnh đại diện',
        'avatar' => $avatarPath
    ]);
}

/**
 * Handle avatar delete
 */
function handleDelete($user)
{
    deleteOldAvatar($user['id']);

    $db = Database::getConnection();
    $stmt = $db->prepare("
        UPDATE user_profiles 
        SET avatar = NULL 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa ảnh đại diện'
    ]);
}

/**
 * Delete old avatar file
 */
function deleteOldAvatar($userId)
{
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT avatar FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile && $profile['avatar']) {
        $oldPath = __DIR__ . '/../../public' . $profile['avatar'];
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }
}

/**
 * Resize image to max dimensions
 */
function resizeImage($filepath, $maxWidth, $maxHeight)
{
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        error_log('Warning: GD extension not loaded. Image resizing skipped.');
        return;
    }

    $imageInfo = getimagesize($filepath);
    if (!$imageInfo)
        return;

    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];

    // Skip if already small enough
    if ($width <= $maxWidth && $height <= $maxHeight) {
        return;
    }

    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int) ($width * $ratio);
    $newHeight = (int) ($height * $ratio);

    // Create image resource
    $source = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($filepath),
        IMAGETYPE_PNG => imagecreatefrompng($filepath),
        IMAGETYPE_GIF => imagecreatefromgif($filepath),
        IMAGETYPE_WEBP => imagecreatefromwebp($filepath),
        default => null
    };

    if (!$source)
        return;

    // Create resized image
    $resized = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG and GIF
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Save resized image
    match ($type) {
        IMAGETYPE_JPEG => imagejpeg($resized, $filepath, 90),
        IMAGETYPE_PNG => imagepng($resized, $filepath, 9),
        IMAGETYPE_GIF => imagegif($resized, $filepath),
        IMAGETYPE_WEBP => imagewebp($resized, $filepath, 90),
        default => null
    };

    imagedestroy($source);
    imagedestroy($resized);
}
