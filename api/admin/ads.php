<?php
/**
 * Admin Ads Management API
 * 
 * GET - List all ads
 * POST - Create/update ad
 * DELETE - Delete ad
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
            getAds();
            break;
        case 'POST':
            saveAd();
            break;
        case 'DELETE':
            deleteAd();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin ads error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

function getAds()
{
    $ads = Database::fetchAll("
        SELECT * FROM ads 
        ORDER BY position, sort_order, id DESC
    ");

    jsonResponse([
        'success' => true,
        'ads' => $ads
    ]);
}

function saveAd()
{
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');
    $position = $_POST['position'] ?? 'sidebar_top';
    $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
    $sort_order = (int) ($_POST['sort_order'] ?? 0);

    if (empty($title)) {
        errorResponse('Tiêu đề không được để trống', 400);
    }

    // Handle image upload
    $image_url = $_POST['existing_image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/ads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            errorResponse('Định dạng ảnh không hợp lệ', 400);
        }

        $filename = 'ad_' . time() . '_' . uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $image_url = '/uploads/ads/' . $filename;
        }
    }

    if ($id) {
        Database::update(
            "UPDATE ads SET title = ?, image_url = ?, link_url = ?, position = ?, is_active = ?, sort_order = ? WHERE id = ?",
            [$title, $image_url, $link_url, $position, $is_active, $sort_order, $id]
        );
        $message = 'Đã cập nhật quảng cáo';
    } else {
        Database::insert(
            "INSERT INTO ads (title, image_url, link_url, position, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)",
            [$title, $image_url, $link_url, $position, $is_active, $sort_order]
        );
        $message = 'Đã thêm quảng cáo mới';
    }

    jsonResponse(['success' => true, 'message' => $message]);
}

function deleteAd()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($data['id'] ?? 0);

    if (!$id) {
        errorResponse('Thiếu ID', 400);
    }

    Database::delete("DELETE FROM ads WHERE id = ?", [$id]);

    jsonResponse(['success' => true, 'message' => 'Đã xóa quảng cáo']);
}
