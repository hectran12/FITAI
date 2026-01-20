<?php
/**
 * Forgot Password API
 * POST /api/auth/forgot-password
 * Body: { email: string }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils/EmailService.php';

handleCorsPreflightRequest();
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    errorResponse('Email không hợp lệ', 400);
}

try {
    // Check if user exists
    $user = Database::fetchOne(
        "SELECT id, email FROM users WHERE email = ?",
        [$email]
    );

    if (!$user) {
        // Don't reveal if email exists or not for security
        jsonResponse([
            'success' => true,
            'message' => 'Nếu email tồn tại, mã xác thực đã được gửi'
        ]);
    }

    // Rate limiting: Check recent requests (max 3 per hour)
    $recentCount = Database::fetchOne(
        "SELECT COUNT(*) as count FROM password_reset_tokens 
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        [$user['id']]
    );

    if ($recentCount['count'] >= 3) {
        errorResponse('Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau 1 giờ.', 429);
    }

    // Generate 6-digit code
    $resetCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Store token (expires in 15 minutes)
    Database::insert(
        "INSERT INTO password_reset_tokens (user_id, token, expires_at) 
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))",
        [$user['id'], $resetCode]
    );

    // Get user display name
    $profile = Database::fetchOne(
        "SELECT display_name FROM user_profiles WHERE user_id = ?",
        [$user['id']]
    );
    $displayName = $profile['display_name'] ?? $email;

    // Send email
    $emailSent = EmailService::sendResetCode($email, $displayName, $resetCode);

    if (!$emailSent) {
        error_log("Failed to send reset code to: $email");
        errorResponse('Không thể gửi email. Vui lòng thử lại sau.', 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Mã xác thực đã được gửi đến email của bạn'
    ]);

} catch (Exception $e) {
    error_log('Forgot password error: ' . $e->getMessage());
    errorResponse('Có lỗi xảy ra. Vui lòng thử lại sau.', 500);
}
