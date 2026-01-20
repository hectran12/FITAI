<?php
/**
 * Get friendship status between current user and another user
 * GET /api/friends/status?user_id={id}
 * Returns: { status: 'none' | 'pending_sent' | 'pending_received' | 'accepted' }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();
startSecureSession();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

// Require authentication
requireAuth();

$currentUserId = getCurrentUserId();
$targetUserId = $_GET['user_id'] ?? null;

if (!$targetUserId) {
    errorResponse('user_id is required', 400);
}

// Same user check
if ($currentUserId == $targetUserId) {
    jsonResponse(['status' => 'self']);
}

// Check friendship status
$friendship = Database::fetchOne("
    SELECT status, user_id, friend_id 
    FROM friendships 
    WHERE (user_id = ? AND friend_id = ?) 
       OR (user_id = ? AND friend_id = ?)
", [$currentUserId, $targetUserId, $targetUserId, $currentUserId]);

if (!$friendship) {
    jsonResponse(['status' => 'none']);
}

// Determine the status
if ($friendship['status'] === 'accepted') {
    $status = 'accepted';
} elseif ($friendship['status'] === 'pending') {
    // Check who sent the request
    if ($friendship['user_id'] == $currentUserId) {
        $status = 'pending_sent';
    } else {
        $status = 'pending_received';
    }
} else {
    $status = 'none';
}

jsonResponse(['status' => $status]);
