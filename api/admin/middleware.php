<?php
/**
 * Admin Middleware
 * 
 * Checks if current user has admin privileges
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin(): bool
{
    $userId = getCurrentUserId();
    if (!$userId)
        return false;

    $user = Database::fetchOne(
        'SELECT is_admin FROM users WHERE id = ?',
        [$userId]
    );

    return $user && $user['is_admin'] == 1;
}

/**
 * Require admin access - exits if not admin
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bạn không có quyền truy cập trang này'
        ]);
        exit;
    }
}
