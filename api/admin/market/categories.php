<?php
/**
 * Admin Market Categories API
 * 
 * GET - List categories
 * POST - Create/Update category
 * DELETE - Delete category
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../middleware.php';

handleCorsPreflightRequest();

startSecureSession();
requireAuth();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetCategories();
            break;
        case 'POST':
            handleSaveCategory();
            break;
        case 'DELETE':
            handleDeleteCategory();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin categories error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

function handleGetCategories()
{
    $categories = Database::fetchAll(
        'SELECT c.*, 
                (SELECT COUNT(*) FROM market_products WHERE category_id = c.id) as product_count
         FROM market_categories c 
         ORDER BY c.sort_order, c.name'
    );

    jsonResponse([
        'success' => true,
        'categories' => $categories
    ]);
}

function handleSaveCategory()
{
    $data = json_decode(file_get_contents('php://input'), true);

    $id = (int) ($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $image = trim($data['image'] ?? '');
    $sortOrder = (int) ($data['sort_order'] ?? 0);
    $isActive = (int) ($data['is_active'] ?? 1);

    if (!$name) {
        errorResponse('Tên danh mục là bắt buộc', 400);
    }

    // Generate slug
    $slug = createSlug($name);

    if ($id) {
        // Update
        Database::update(
            'UPDATE market_categories 
             SET name = ?, slug = ?, description = ?, image = ?, sort_order = ?, is_active = ?
             WHERE id = ?',
            [$name, $slug, $description, $image, $sortOrder, $isActive, $id]
        );
        $message = 'Đã cập nhật danh mục';
    } else {
        // Insert
        $id = Database::insert(
            'INSERT INTO market_categories (name, slug, description, image, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $slug, $description, $image, $sortOrder, $isActive]
        );
        $message = 'Đã thêm danh mục mới';
    }

    jsonResponse([
        'success' => true,
        'message' => $message,
        'id' => $id
    ]);
}

function handleDeleteCategory()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($data['id'] ?? 0);

    if (!$id) {
        errorResponse('ID danh mục là bắt buộc', 400);
    }

    // Check if has products
    $count = Database::fetchOne(
        'SELECT COUNT(*) as count FROM market_products WHERE category_id = ?',
        [$id]
    );

    if ($count['count'] > 0) {
        errorResponse('Không thể xóa danh mục có sản phẩm', 400);
    }

    Database::delete('DELETE FROM market_categories WHERE id = ?', [$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Đã xóa danh mục'
    ]);
}

function createSlug($string)
{
    $slug = strtolower($string);
    $slug = preg_replace('/[àáạảãâầấậẩẫăằắặẳẵ]/u', 'a', $slug);
    $slug = preg_replace('/[èéẹẻẽêềếệểễ]/u', 'e', $slug);
    $slug = preg_replace('/[ìíịỉĩ]/u', 'i', $slug);
    $slug = preg_replace('/[òóọỏõôồốộổỗơờớợởỡ]/u', 'o', $slug);
    $slug = preg_replace('/[ùúụủũưừứựửữ]/u', 'u', $slug);
    $slug = preg_replace('/[ỳýỵỷỹ]/u', 'y', $slug);
    $slug = preg_replace('/đ/u', 'd', $slug);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}
