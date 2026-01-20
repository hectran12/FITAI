<?php
/**
 * Admin Users Management API
 * 
 * GET /api/admin/users.php - List users
 * POST /api/admin/users.php - Ban/Unban user
 * DELETE /api/admin/users.php - Delete user
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/middleware.php';

handleCorsPreflightRequest();

startSecureSession();
requireAuth();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetUsers();
            break;
        case 'POST':
            handleToggleBan();
            break;
        case 'DELETE':
            handleDeleteUser();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin users error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

/**
 * Get users list with pagination and search
 */
function handleGetUsers()
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';

    $whereClause = '';
    $params = [];

    if ($search) {
        $whereClause = 'WHERE email LIKE ? OR id = ?';
        $params = ["%$search%", (int) $search];
    }

    // Get users with profile info
    $sql = "SELECT u.id, u.email, u.is_admin, u.is_banned, u.created_at,
                   up.display_name, up.avatar
            FROM users u
            LEFT JOIN user_profiles up ON u.id = up.user_id
            $whereClause
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?";

    $queryParams = array_merge($params, [$limit, $offset]);
    $users = Database::fetchAll($sql, $queryParams);

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM users $whereClause";
    $total = Database::fetchOne($countSql, $params);

    // Format dates
    foreach ($users as &$user) {
        $user['created_at_formatted'] = date('d/m/Y H:i', strtotime($user['created_at']));
        $user['display_name'] = $user['display_name'] ?: 'User ' . $user['id'];
    }

    jsonResponse([
        'success' => true,
        'users' => $users,
        'total' => (int) $total['total'],
        'page' => $page,
        'total_pages' => ceil($total['total'] / $limit)
    ]);
}

/**
 * Toggle user ban status
 */
function handleToggleBan()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = (int) ($data['user_id'] ?? 0);
    $action = $data['action'] ?? '';

    if (!$userId) {
        errorResponse('User ID required', 400);
    }

    // Don't allow banning yourself
    if ($userId == getCurrentUserId()) {
        errorResponse('Không thể tự khóa tài khoản của mình', 400);
    }

    // Check if user exists
    $user = Database::fetchOne('SELECT id, is_banned FROM users WHERE id = ?', [$userId]);
    if (!$user) {
        errorResponse('User not found', 404);
    }

    $newStatus = $action === 'ban' ? 1 : 0;

    Database::update(
        'UPDATE users SET is_banned = ? WHERE id = ?',
        [$newStatus, $userId]
    );

    jsonResponse([
        'success' => true,
        'message' => $newStatus ? 'Đã khóa tài khoản' : 'Đã mở khóa tài khoản'
    ]);
}

/**
 * Delete user and all related data
 */
function handleDeleteUser()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = (int) ($data['user_id'] ?? 0);

    if (!$userId) {
        errorResponse('User ID required', 400);
    }

    // Don't allow deleting yourself
    if ($userId == getCurrentUserId()) {
        errorResponse('Không thể xóa tài khoản của mình', 400);
    }

    // Check if user exists
    $user = Database::fetchOne('SELECT id FROM users WHERE id = ?', [$userId]);
    if (!$user) {
        errorResponse('User not found', 404);
    }

    // Delete user (cascade will handle related data)
    Database::delete('DELETE FROM users WHERE id = ?', [$userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Đã xóa tài khoản thành công'
    ]);
}
