<?php
/**
 * Email Service using Gmail SMTP
 * Requires PHPMailer library
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private static $mailer = null;

    /**
     * Initialize PHPMailer with Gmail SMTP settings from database
     */
    private static function getMailer()
    {
        if (self::$mailer !== null) {
            return self::$mailer;
        }

        // Load PHPMailer
        require_once __DIR__ . '/../../vendor/autoload.php';
        require_once __DIR__ . '/../db.php';

        $mail = new PHPMailer(true);

        try {
            // Load SMTP settings from database
            $settings = Database::fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
            $config = [];
            foreach ($settings as $setting) {
                $config[$setting['setting_key']] = $setting['setting_value'];
            }

            // Fallback to config constants if database is empty
            $host = $config['smtp_host'] ?? (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com');
            $port = $config['smtp_port'] ?? (defined('SMTP_PORT') ? SMTP_PORT : 587);
            $username = $config['smtp_username'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
            $password = $config['smtp_password'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
            $fromEmail = $config['smtp_from_email'] ?? (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@fitai.com');
            $fromName = $config['smtp_from_name'] ?? (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'FitAI');

            // Server settings
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $port;
            $mail->CharSet = 'UTF-8';

            // Sender
            $mail->setFrom($fromEmail, $fromName);

            self::$mailer = $mail;
            return $mail;
        } catch (Exception $e) {
            error_log("Email service initialization failed: " . $e->getMessage());
            throw new Exception("Failed to initialize email service");
        }
    }

    /**
     * Send password reset code email
     */
    public static function sendResetCode($toEmail, $toName, $resetCode)
    {
        try {
            $mail = self::getMailer();

            // Recipients
            $mail->clearAddresses();
            $mail->addAddress($toEmail, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'FitAI - Mã xác thực đặt lại mật khẩu';

            $mail->Body = self::getResetCodeTemplate($toName, $resetCode);
            $mail->AltBody = "Xin chào $toName,\n\nMã xác thực đặt lại mật khẩu của bạn là: $resetCode\n\nMã này có hiệu lực trong 15 phút.\n\nNếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.\n\nTrân trọng,\nĐội ngũ FitAI";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Failed to send reset code email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * HTML template for reset code email
     */
    private static function getResetCodeTemplate($name, $code)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .code-box { background: white; border: 2px dashed #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 8px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Đặt lại mật khẩu</h1>
                </div>
                <div class='content'>
                    <p>Xin chào <strong>$name</strong>,</p>
                    <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản FitAI của bạn.</p>
                    <p>Mã xác thực của bạn là:</p>
                    <div class='code-box'>
                        <div class='code'>$code</div>
                    </div>
                    <p><strong>Lưu ý:</strong></p>
                    <ul>
                        <li>Mã này có hiệu lực trong <strong>15 phút</strong></li>
                        <li>Không chia sẻ mã này với bất kỳ ai</li>
                        <li>Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>Email này được gửi tự động, vui lòng không trả lời.</p>
                    <p>&copy; 2026 FitAI. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
