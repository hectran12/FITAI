# Hướng Dẫn Deploy FitAI lên cPanel

## 📋 Yêu Cầu

- **Hosting**: cPanel hosting với PHP 7.4+ và MySQL 8.0+
- **Domain**: Tên miền đã trỏ về hosting
- **Composer**: Để cài đặt PHPMailer
- **Python**: Nếu muốn chạy AI service (tùy chọn)

## 🚀 Các Bước Deploy

### 1. Chuẩn Bị Files

#### 1.1. Nén Project
```bash
# Trên máy local, nén toàn bộ project (trừ thư mục không cần thiết)
zip -r fitai.zip . -x "*.git*" "node_modules/*" "__pycache__/*" "*.md"
```

Hoặc sử dụng WinRAR/7-Zip để tạo file `fitai.zip`

#### 1.2. Upload lên cPanel
1. Đăng nhập vào **cPanel**
2. Mở **File Manager**
3. Vào thư mục `public_html` (hoặc thư mục domain của bạn)
4. Click **Upload** và upload file `fitai.zip`
5. Sau khi upload xong, click chuột phải vào file → **Extract**
6. Xóa file `fitai.zip` sau khi giải nén

### 2. Cấu Hình Database

#### 2.1. Tạo Database MySQL
1. Trong cPanel, mở **MySQL Databases**
2. Tạo database mới:
   - Database Name: `fitai_db` (hoặc tên khác)
   - Click **Create Database**
3. Tạo user:
   - Username: `fitai_user`
   - Password: Tạo password mạnh
   - Click **Create User**
4. Gán quyền:
   - Chọn user và database vừa tạo
   - Tick **ALL PRIVILEGES**
   - Click **Make Changes**

#### 2.2. Import Database
1. Mở **phpMyAdmin** từ cPanel
2. Chọn database `fitai_db`
3. Click tab **Import**
4. Chọn file `db/database.sql`
5. Click **Go** để import

> ✅ File `database.sql` đã gộp tất cả các bảng, bạn chỉ cần import 1 lần!

### 3. Cấu Hình PHP

#### 3.1. Cập Nhật `api/config.php`
```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpanel_user_fitai_db'); // Thay bằng tên database đầy đủ
define('DB_USER', 'cpanel_user_fitai_user'); // Thay bằng username đầy đủ
define('DB_PASS', 'your_password_here'); // Password database

// Application Settings
define('APP_URL', 'https://yourdomain.com'); // Thay bằng domain của bạn

// CORS Settings
define('CORS_ALLOWED_ORIGINS', [
    'https://yourdomain.com',
    'https://www.yourdomain.com'
]);
```

> **Lưu ý**: cPanel thường thêm prefix vào tên database và user. Ví dụ: `cpanel_user_fitai_db`

#### 3.2. Cài Đặt PHPMailer
1. Mở **Terminal** trong cPanel (nếu có)
2. Di chuyển đến thư mục project:
```bash
cd public_html
composer require phpmailer/phpmailer
```

**Nếu không có Terminal**, upload thủ công:
1. Tải PHPMailer từ: https://github.com/PHPMailer/PHPMailer/releases
2. Giải nén vào thư mục `vendor/phpmailer/phpmailer/`

### 4. Cấu Hình Email (Gmail SMTP)

#### 4.1. Tạo Gmail App Password
1. Vào Google Account: https://myaccount.google.com/
2. Bật **2-Step Verification**
3. Tạo App Password: https://myaccount.google.com/apppasswords
4. Chọn **Mail** → **Other (Custom name)** → Nhập "FitAI"
5. Copy mã 16 ký tự

#### 4.2. Cấu Hình trong Admin Panel
1. Đăng nhập vào website
2. Vào **Admin** → **Settings**
3. Nhập thông tin Gmail SMTP:
   - Gmail Address: `your-email@gmail.com`
   - Gmail App Password: Paste mã 16 ký tự
4. Click **Lưu cài đặt**

### 5. Cấu Hình .htaccess (Routing)

Tạo file `.htaccess` trong thư mục `public/`:

```apache
# Enable Rewrite Engine
RewriteEngine On

# Route all requests through router.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ router.php [QSA,L]

# Security Headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"

# Enable CORS (if needed)
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type, X-CSRF-Token"
```

### 6. Cấu Hình Uploads Folder

Tạo thư mục uploads và set permissions:

```bash
mkdir -p public/uploads/posts
mkdir -p public/uploads/avatars
mkdir -p public/uploads/products
mkdir -p public/uploads/music
mkdir -p public/uploads/chat
mkdir -p public/uploads/stickers

chmod 755 public/uploads
chmod 755 public/uploads/*
```

Hoặc qua File Manager:
1. Tạo các thư mục trên
2. Click chuột phải → **Change Permissions**
3. Set: `755` (rwxr-xr-x)

### 7. Tạo Admin User

Sau khi import database, tạo user admin đầu tiên:

1. Mở **phpMyAdmin**
2. Chọn database `fitai_db`
3. Chạy SQL:

```sql
-- Tạo user admin (email: admin@fitai.com, password: admin123)
INSERT INTO users (email, password_hash, is_admin) VALUES 
('admin@fitai.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
```

> **Mật khẩu mặc định**: `admin123` - **Hãy đổi ngay sau khi đăng nhập!**

### 8. Deploy AI Service (Tùy Chọn)

Nếu muốn chạy AI service:

#### 8.1. Trên VPS/Server riêng
```bash
cd ai/
pip install -r requirements.txt
python -m uvicorn main:app --host 0.0.0.0 --port 8001
```

#### 8.2. Cập nhật `api/config.php`
```php
define('AI_SERVICE_URL', 'http://your-vps-ip:8001');
```

> **Lưu ý**: cPanel shared hosting thường không hỗ trợ chạy Python service. Bạn cần VPS riêng hoặc sử dụng dịch vụ như Railway, Render, Heroku.

### 9. SSL Certificate (HTTPS)

1. Trong cPanel, mở **SSL/TLS Status**
2. Chọn domain
3. Click **Run AutoSSL** để cài Let's Encrypt miễn phí
4. Sau khi có SSL, cập nhật `config.php`:
```php
define('APP_URL', 'https://yourdomain.com');
```

### 10. Kiểm Tra

✅ **Checklist sau khi deploy:**

- [ ] Website mở được: `https://yourdomain.com`
- [ ] Đăng ký tài khoản mới hoạt động
- [ ] Đăng nhập thành công
- [ ] Upload ảnh avatar hoạt động
- [ ] Tạo workout plan hoạt động
- [ ] Community posts hoạt động
- [ ] Chat/Messages hoạt động
- [ ] Market hoạt động
- [ ] Admin panel truy cập được
- [ ] Email reset password hoạt động (sau khi cấu hình SMTP)

## 🔧 Troubleshooting

### Lỗi Database Connection
```
Error: SQLSTATE[HY000] [1045] Access denied
```
**Giải pháp**: Kiểm tra lại DB_HOST, DB_NAME, DB_USER, DB_PASS trong `config.php`

### Lỗi 500 Internal Server Error
**Giải pháp**: 
1. Kiểm tra file `.htaccess`
2. Xem error log trong cPanel → **Error Log**
3. Đảm bảo PHP version >= 7.4

### Upload File Không Hoạt Động
**Giải pháp**:
1. Kiểm tra permissions thư mục `uploads/` (phải là 755)
2. Tăng `upload_max_filesize` trong PHP settings (cPanel → Select PHP Version → Options)

### Email Không Gửi Được
**Giải pháp**:
1. Kiểm tra Gmail App Password đã đúng chưa
2. Kiểm tra PHPMailer đã cài đặt chưa
3. Xem error log: `error_log('Email error: ' . $e->getMessage());`

## 📞 Hỗ Trợ

Nếu gặp vấn đề:
1. Kiểm tra error log trong cPanel
2. Xem file `error_log` trong thư mục project
3. Kiểm tra database có import đầy đủ không

## 🎉 Hoàn Thành!

Website FitAI của bạn đã sẵn sàng! 

**Bước tiếp theo:**
1. Đổi mật khẩu admin
2. Cấu hình Gmail SMTP trong Admin → Settings
3. Upload logo/cover images
4. Thêm sản phẩm vào Market
5. Upload nhạc vào Music Library
6. Tạo nội dung mẫu cho Community

**Chúc bạn thành công! 💪🔥**
