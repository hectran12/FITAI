<?php
/**
 * Messages API
 * 
 * GET - Get messages with a user
 * POST - Send a message
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

handleCorsPreflightRequest();
startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getMessages();
            break;
        case 'POST':
            sendMessage();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Messages error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

function getMessages()
{
    global $userId;

    $friendId = (int) ($_GET['friend_id'] ?? 0);
    $beforeId = (int) ($_GET['before_id'] ?? 0);
    $limit = min(50, (int) ($_GET['limit'] ?? 30));

    if (!$friendId) {
        errorResponse('Friend ID required', 400);
    }

    // Check if they are friends
    $areFriends = Database::fetchOne("
        SELECT id FROM friendships 
        WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
        AND status = 'accepted'
    ", [$userId, $friendId, $friendId, $userId]);

    if (!$areFriends) {
        errorResponse('Chưa phải bạn bè', 403);
    }

    $whereClause = $beforeId ? "AND m.id < ?" : "";
    $params = $beforeId
        ? [$userId, $friendId, $friendId, $userId, $beforeId, $limit]
        : [$userId, $friendId, $friendId, $userId, $limit];

    $messages = Database::fetchAll("
        SELECT m.*, 
               s.image_url as sticker_url,
               up.display_name as sender_name, up.avatar as sender_avatar
        FROM messages m
        LEFT JOIN stickers s ON m.sticker_id = s.id
        LEFT JOIN user_profiles up ON m.sender_id = up.user_id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        $whereClause
        ORDER BY m.id DESC
        LIMIT ?
    ", $params);

    // Mark as read
    Database::update("
        UPDATE messages SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ", [$friendId, $userId]);

    jsonResponse([
        'success' => true,
        'messages' => array_reverse($messages)
    ]);
}

function sendMessage()
{
    global $userId;

    $friendId = (int) ($_POST['friend_id'] ?? 0);
    $messageType = $_POST['message_type'] ?? 'text';
    $content = trim($_POST['content'] ?? '');
    $stickerId = (int) ($_POST['sticker_id'] ?? 0);

    if (!$friendId) {
        errorResponse('Friend ID required', 400);
    }

    // Check if they are friends
    $areFriends = Database::fetchOne("
        SELECT id FROM friendships 
        WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
        AND status = 'accepted'
    ", [$userId, $friendId, $friendId, $userId]);

    if (!$areFriends) {
        errorResponse('Chưa phải bạn bè', 403);
    }

    $fileUrl = null;
    $fileName = null;
    $fileSize = null;

    // Handle file/image/voice upload
    if (in_array($messageType, ['image', 'file', 'voice']) && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/messages/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $newName = $messageType . '_' . time() . '_' . uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $fileUrl = '/uploads/messages/' . $newName;
                $fileName = $file['name'];
                $fileSize = $file['size'];
            }
        }
    }

    // Validate message
    if ($messageType === 'text' && empty($content)) {
        errorResponse('Nội dung tin nhắn không được trống', 400);
    }
    if ($messageType === 'sticker' && !$stickerId) {
        errorResponse('Sticker ID required', 400);
    }
    if (in_array($messageType, ['image', 'file', 'voice']) && !$fileUrl) {
        errorResponse('File upload failed', 400);
    }

    $msgId = Database::insert("
        INSERT INTO messages (sender_id, receiver_id, message_type, content, file_url, file_name, file_size, sticker_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ", [$userId, $friendId, $messageType, $content, $fileUrl, $fileName, $fileSize, $stickerId ?: null]);

    // Get the inserted message
    $message = Database::fetchOne("
        SELECT m.*, s.image_url as sticker_url,
               up.display_name as sender_name, up.avatar as sender_avatar
        FROM messages m
        LEFT JOIN stickers s ON m.sticker_id = s.id
        LEFT JOIN user_profiles up ON m.sender_id = up.user_id
        WHERE m.id = ?
    ", [$msgId]);

    jsonResponse([
        'success' => true,
        'message' => $message
    ]);
}
