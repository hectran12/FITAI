<?php
/**
 * Reset Password API
 * POST /api/auth/reset-password
 * Body: { reset_token: string, new_password: string }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$resetToken = trim($data['reset_token'] ?? '');
$newPassword = trim($data['new_password'] ?? '');

if (!$resetToken || !$newPassword) {
    errorResponse('Token và mật khẩu mới là bắt buộc', 400);
}

// Validate password strength
if (strlen($newPassword) < 6) {
    errorResponse('Mật khẩu phải có ít nhất 6 ký tự', 400);
}

try {
    // Verify session token
    if (
        !isset($_SESSION['password_reset_token']) ||
        $_SESSION['password_reset_token'] !== $resetToken ||
        !isset($_SESSION['password_reset_expires']) ||
        $_SESSION['password_reset_expires'] < time()
    ) {
        errorResponse('Token không hợp lệ hoặc đã hết hạn', 400);
    }

    $userId = $_SESSION['password_reset_user_id'];
    $dbTokenId = $_SESSION['password_reset_db_token_id'];

    // Update password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    Database::update(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        [$hashedPassword, $userId]
    );

    // Mark token as used
    Database::update(
        "UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?",
        [$dbTokenId]
    );

    // Clear reset session
    unset($_SESSION['password_reset_token']);
    unset($_SESSION['password_reset_user_id']);
    unset($_SESSION['password_reset_db_token_id']);
    unset($_SESSION['password_reset_expires']);

    // Auto-login user
    $user = Database::fetchOne(
        "SELECT u.id, u.email, u.is_admin, up.display_name, up.avatar 
         FROM users u 
         LEFT JOIN user_profiles up ON u.id = up.user_id 
         WHERE u.id = ?",
        [$userId]
    );

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];

    jsonResponse([
        'success' => true,
        'message' => 'Đặt lại mật khẩu thành công',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'display_name' => $user['display_name'] ?? $user['email'],
            'avatar' => $user['avatar'],
            'is_admin' => (bool) $user['is_admin']
        ]
    ]);

} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage());
    errorResponse('Có lỗi xảy ra. Vui lòng thử lại sau.', 500);
}
