# FitAI - Nền tảng Thể hình Thông minh Tích hợp AI

> Ứng dụng web toàn diện hỗ trợ tập luyện, dinh dưỡng và cộng đồng với trợ lý ảo AI (Google Gemini). Được tối ưu hóa để triển khai dễ dàng trên cPanel.

![FitAI](public/images/cover.png)

## 🌟 Tính Năng Nổi Bật

### 🤖 Trợ lý AI (PT AI Chat)
- **Hỏi đáp thông minh**: Giải đáp mọi thắc mắc về kỹ thuật tập luyện và dinh dưỡng qua Google Gemini AI.
- **Phân tích hình thể**: Gửi ảnh để AI đánh giá BMI, % mỡ thừa và khối lượng cơ bắp.
- **Phân tích thực đơn**: Chụp ảnh món ăn để AI tính toán Calories và Macros (Protein, Carbs, Fat).
- **Chế độ đa nhiệm**: Tự động vô hiệu hóa input khi AI đang xử lý để đảm bảo logic dòng tin nhắn.

### 💪 Lập kế hoạch & Theo dõi Tập luyện
- **Cá nhân hóa tối đa**: Tạo kế hoạch 7 ngày dựa trên mục tiêu, trình độ, thiết bị và thời gian.
- **Lịch tập luyện**: Hiển thị kế hoạch dưới dạng danh sách hoặc lịch trực quan.
- **Ghi nhật ký bài tập**: Đánh giá độ mệt mỏi và tiến độ sau mỗi buổi tập.
- **Thư viện bài tập**: Hàng trăm bài tập với hướng dẫn chi tiết (SQL Seed sẵn có).

### 👥 Cộng đồng & Kết nối
- **Social Feed**: Đăng bài chia sẻ thành tích, đặt câu hỏi cho cộng đồng.
- **Tương tác**: Tính năng Thích, Bình luận và Kết bạn thời gian thực.
- **Hệ thống Tin nhắn**: Chat 1-1 với bạn bè (hỗ trợ văn bản, hình ảnh, file, ghi âm và stickers).

### 🛒 Marketplace & Tiện ích
- **Cửa hàng Fitness**: Danh mục sản phẩm đa dạng (TPBS, dụng cụ, phụ kiện).
- **Giỏ hàng & Đơn hàng**: Quy trình mua sắm hoàn chỉnh từ giỏ hàng đến quản lý đơn hàng.
- **Thư viện Nhạc**: Nghe nhạc tập luyện theo thể loại (Cardio, Gym, Yoga...) với Player nổi hiện đại.

### 👤 Trang Tác giả & Admin
- **About Author**: Trang giới thiệu người phát triển với thông tin liên hệ và mạng xã hội.
- **Admin Panel**: Quản lý người dùng, bài đăng, đơn hàng, âm nhạc và cấu hình website (SMTP, Author info).

## 🛠️ Công Nghệ Sử Dụng

| Thành phần | Công nghệ |
|-----------|------------|
| **Frontend** | HTML5, CSS3, Vanilla JavaScript (Single Page Application) |
| **Backend** | PHP 8.x (Native) |
| **Database** | MySQL 8.x |
| **AI Layer** | Python 3.11 + FastAPI + Google Gemini API |
| **Email** | PHPMailer + Gmail SMTP |
| **Media** | RecordRTC (Ghi âm), SweetAlert2 (Thông báo) |

## 📁 Cấu Trúc Dự Án (Cấu trúc Phẳng cho cPanel)

```
/
├── api/                    # Quản lý Backend (PHP)
│   ├── auth/               # Đăng ký, Đăng nhập, Quên mật khẩu
│   ├── chat/               # Xử lý tin nhắn & AI Chat
│   ├── community/          # Bài đăng, Like, Bình luận
│   ├── market/             # Sản phẩm, Đơn hàng
│   └── admin/              # Dashboard quản trị
├── ai/                     # Python AI Service (FastAPI)
├── db/                     # Database migrations & SQL Scripts
├── css/                    # Stylesheets
├── js/                     # Application logic (SPA)
├── uploads/                # Lưu trữ file người dùng (Avatars, Posts...)
├── .htaccess               # Cấu hình Routing & Bảo mật chính
├── index.html              # Entry point của ứng dụng
└── README.md               # Hướng dẫn này
```

## 🚀 Hướng Dẫn Cài Đặt

### 1. Database
- Tạo database mới trên MySQL.
- Import file `db/database.sql` để có đầy đủ cấu trúc và dữ liệu bài tập mẫu.

### 2. Cấu hình Backend
- Chỉnh sửa file `api/config.php`:
  ```php
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'your_db_name');
  define('DB_USER', 'your_db_user');
  define('DB_PASS', 'your_db_password');
  ```

### 3. AI Service (Python)
- Cài đặt dependencies: `pip install -r ai/requirements.txt`
- Thiết lập Gemini API Key: `export GEMINI_API_KEY=your_key`
- Khởi chạy service: `python -m uvicorn ai.main:app --port 8001`

### 4. Gửi Mail (SMTP)
- Chụp vào trang Quản trị (Admin) -> Cài đặt.
- Cấu hình SMTP Gmail (Sử dụng Mật khẩu ứng dụng).

## 🌍 Triển Khai Trên cPanel

Ứng dụng đã được cấu hình sẵn cho **cPanel** với cấu trúc phẳng.

1. **Upload**: Nén toàn bộ project (trừ thư mục `node_modules` hoặc venv) và upload vào `public_html`.
2. **.htaccess**: File `.htaccess` ở thư mục gốc sẽ tự động xử lý:
   - Routing cho SPA (giữ người dùng ở `index.html`).
   - Route URL `/uploads/` tới đúng thư mục dù cấu trúc thay đổi.
   - Chặn truy cập trái phép vào các thư mục nhạy cảm (`api/`, `db/`, `.env`).
3. **Link Uploads**: Nếu bạn di chuyển thư mục `uploads/` vào `public/uploads/`, hệ thống đã có rewrite rule tự động trỏ `/uploads/` về đúng vị trí.

## 🔒 Bảo Mật
- **Mã hóa mật khẩu**: Sử dụng BCRYPT.
- **Chống SQL Injection**: Sử dụng PDO Prepared Statements.
- **Chống CSRF**: Token-based protection.
- **Bảo mật thư mục**: Chặn thực thi PHP trong thư mục `uploads/`.

## 👨‍💻 Tác giả
- Phát triển bởi: **Nhân Hòa**
- Website: [fitai.one](https://fitai.one)

---
© 2026 FitAI - Trải nghiệm Thể hình thời đại Công nghệ số.
