<?php
/**
 * Verify Reset Code API
 * POST /api/auth/verify-reset-code
 * Body: { email: string, code: string }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$code = trim($data['code'] ?? '');

if (!$email || !$code) {
    errorResponse('Email và mã xác thực là bắt buộc', 400);
}

if (strlen($code) !== 6 || !ctype_digit($code)) {
    errorResponse('Mã xác thực không hợp lệ', 400);
}

try {
    // Find user
    $user = Database::fetchOne(
        "SELECT id FROM users WHERE email = ?",
        [$email]
    );

    if (!$user) {
        errorResponse('Mã xác thực không đúng', 400);
    }

    // Verify code
    $token = Database::fetchOne(
        "SELECT id, user_id FROM password_reset_tokens 
         WHERE user_id = ? AND token = ? 
         AND expires_at > NOW() AND used_at IS NULL
         ORDER BY created_at DESC LIMIT 1",
        [$user['id'], $code]
    );

    if (!$token) {
        errorResponse('Mã xác thực không đúng hoặc đã hết hạn', 400);
    }

    // Generate temporary session token for password reset
    $resetToken = bin2hex(random_bytes(32));

    // Store in session
    $_SESSION['password_reset_token'] = $resetToken;
    $_SESSION['password_reset_user_id'] = $user['id'];
    $_SESSION['password_reset_db_token_id'] = $token['id'];
    $_SESSION['password_reset_expires'] = time() + 600; // 10 minutes

    jsonResponse([
        'success' => true,
        'reset_token' => $resetToken,
        'message' => 'Mã xác thực đúng'
    ]);

} catch (Exception $e) {
    error_log('Verify reset code error: ' . $e->getMessage());
    errorResponse('Có lỗi xảy ra. Vui lòng thử lại sau.', 500);
}
