<?php
/**
 * Friend Requests API
 * 
 * GET - List pending requests
 * POST - Accept/reject request
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();
startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getRequests();
            break;
        case 'POST':
            handleRequest();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Friend requests error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

function getRequests()
{
    global $userId;

    // Incoming requests (people who sent me requests)
    $incoming = Database::fetchAll("
        SELECT f.id, f.user_id as from_user_id, f.created_at,
               u.email, up.display_name, up.avatar
        FROM friendships f
        JOIN users u ON f.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE f.friend_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ", [$userId]);

    // Outgoing requests (requests I sent)
    $outgoing = Database::fetchAll("
        SELECT f.id, f.friend_id as to_user_id, f.created_at,
               u.email, up.display_name, up.avatar
        FROM friendships f
        JOIN users u ON f.friend_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE f.user_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ", [$userId]);

    jsonResponse([
        'success' => true,
        'incoming' => $incoming,
        'outgoing' => $outgoing
    ]);
}

function handleRequest()
{
    global $userId;

    $data = json_decode(file_get_contents('php://input'), true);
    $requestId = (int) ($data['request_id'] ?? 0);
    $action = $data['action'] ?? ''; // accept or reject

    if (!$requestId || !in_array($action, ['accept', 'reject'])) {
        errorResponse('Invalid request', 400);
    }

    // Get the request
    $request = Database::fetchOne("
        SELECT * FROM friendships WHERE id = ? AND friend_id = ? AND status = 'pending'
    ", [$requestId, $userId]);

    if (!$request) {
        errorResponse('Lời mời không tồn tại', 404);
    }

    if ($action === 'accept') {
        Database::update(
            "UPDATE friendships SET status = 'accepted' WHERE id = ?",
            [$requestId]
        );
        $message = 'Đã chấp nhận lời mời kết bạn';
    } else {
        Database::delete("DELETE FROM friendships WHERE id = ?", [$requestId]);
        $message = 'Đã từ chối lời mời kết bạn';
    }

    jsonResponse(['success' => true, 'message' => $message]);
}
