<?php
/**
 * Friends API
 * 
 * GET - List friends
 * POST - Send friend request
 * DELETE - Unfriend
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
            getFriends();
            break;
        case 'POST':
            sendFriendRequest();
            break;
        case 'DELETE':
            unfriend();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Friends error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

function getFriends()
{
    global $userId;

    $friends = Database::fetchAll("
        SELECT u.id, up.display_name, up.avatar, f.created_at as friends_since,
               (SELECT COUNT(*) FROM messages m 
                WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count
        FROM friendships f
        JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id) AND u.id != ?
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted'
        ORDER BY up.display_name
    ", [$userId, $userId, $userId, $userId]);

    jsonResponse([
        'success' => true,
        'friends' => $friends
    ]);
}

function sendFriendRequest()
{
    global $userId;

    $data = json_decode(file_get_contents('php://input'), true);
    $friendId = (int) ($data['friend_id'] ?? 0);

    if (!$friendId || $friendId == $userId) {
        errorResponse('Invalid friend ID', 400);
    }

    // Check if already friends or request exists
    $existing = Database::fetchOne("
        SELECT * FROM friendships 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ", [$userId, $friendId, $friendId, $userId]);

    if ($existing) {
        if ($existing['status'] === 'accepted') {
            errorResponse('Đã là bạn bè', 400);
        } elseif ($existing['status'] === 'pending') {
            errorResponse('Đã có lời mời kết bạn', 400);
        } elseif ($existing['status'] === 'blocked') {
            errorResponse('Không thể kết bạn', 400);
        }
    }

    Database::insert(
        "INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'pending')",
        [$userId, $friendId]
    );

    jsonResponse(['success' => true, 'message' => 'Đã gửi lời mời kết bạn']);
}

function unfriend()
{
    global $userId;

    $data = json_decode(file_get_contents('php://input'), true);
    $friendId = (int) ($data['friend_id'] ?? 0);

    if (!$friendId) {
        errorResponse('Friend ID required', 400);
    }

    Database::delete("
        DELETE FROM friendships 
        WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
        AND status = 'accepted'
    ", [$userId, $friendId, $friendId, $userId]);

    jsonResponse(['success' => true, 'message' => 'Đã hủy kết bạn']);
}
