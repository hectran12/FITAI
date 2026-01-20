# Password Reset Feature - Setup Guide

## 1. Install PHPMailer

Run this command in your project root:

```bash
composer require phpmailer/phpmailer
```

If you don't have Composer, download it from: https://getcomposer.org/

## 2. Run Database Migration

Execute the SQL migration file:

```sql
-- In MySQL/phpMyAdmin, run:
SOURCE f:/HF_Project/db/password_reset_migration.sql;
```

Or manually run the SQL commands in `db/password_reset_migration.sql`

## 3. Configure Gmail SMTP

### Step 1: Enable 2-Factor Authentication
1. Go to your Google Account: https://myaccount.google.com/
2. Navigate to Security
3. Enable 2-Step Verification

### Step 2: Generate App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Select "Mail" and "Other (Custom name)"
3. Enter "FitAI" as the name
4. Click "Generate"
5. Copy the 16-character password

### Step 3: Update config.php
Edit `api/config.php` and replace:

```php
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'your-app-password'); // The 16-char password from step 2
```

## 4. Test the Feature

1. Start PHP server: `php -S localhost:8000 -t public public/router.php`
2. Navigate to login page
3. Click "Quên mật khẩu?"
4. Enter your email
5. Check your Gmail inbox for the 6-digit code
6. Enter the code and set new password

## Troubleshooting

### Email not sending?
- Check SMTP credentials in `config.php`
- Verify App Password is correct (no spaces)
- Check PHP error logs for details
- Ensure port 587 is not blocked by firewall

### Code not received?
- Check spam folder
- Verify email address is correct
- Check database for token: `SELECT * FROM password_reset_tokens ORDER BY created_at DESC LIMIT 5;`

### Rate limiting?
- Max 3 requests per hour per email
- Wait 1 hour or manually delete tokens: `DELETE FROM password_reset_tokens WHERE user_id = X;`
