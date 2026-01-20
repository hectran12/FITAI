<?php
/**
 * Admin Market Products API
 * 
 * GET - List products
 * POST - Create/Update product (with images)
 * DELETE - Delete product
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
            handleGetProducts();
            break;
        case 'POST':
            handleSaveProduct();
            break;
        case 'DELETE':
            handleDeleteProduct();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin products error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu: ' . $e->getMessage(), 500);
}

function handleGetProducts()
{
    $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $whereClause = '';
    $params = [];

    if ($categoryId) {
        $whereClause = 'WHERE p.category_id = ?';
        $params[] = $categoryId;
    }

    $sql = "SELECT p.*, c.name as category_name,
                   (SELECT image_path FROM market_product_images 
                    WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM market_products p
            LEFT JOIN market_categories c ON p.category_id = c.id
            $whereClause
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";

    $queryParams = array_merge($params, [$limit, $offset]);
    $products = Database::fetchAll($sql, $queryParams);

    $countSql = "SELECT COUNT(*) as total FROM market_products p $whereClause";
    $total = Database::fetchOne($countSql, $params);

    // Get categories for filter
    $categories = Database::fetchAll('SELECT id, name FROM market_categories ORDER BY name');

    jsonResponse([
        'success' => true,
        'products' => $products,
        'categories' => $categories,
        'total' => (int) $total['total'],
        'page' => $page,
        'total_pages' => ceil($total['total'] / $limit)
    ]);
}

function handleSaveProduct()
{
    // Check if multipart form data or JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'multipart/form-data') !== false) {
        $data = $_POST;
        $files = $_FILES['images'] ?? null;
    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        $files = null;
    }

    $id = (int) ($data['id'] ?? 0);
    $categoryId = (int) ($data['category_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $price = (int) ($data['price'] ?? 0);
    $salePrice = !empty($data['sale_price']) ? (int) $data['sale_price'] : null;
    $stock = (int) ($data['stock'] ?? 0);
    $tags = trim($data['tags'] ?? '');
    $isActive = (int) ($data['is_active'] ?? 1);

    if (!$name || !$categoryId || !$price) {
        errorResponse('Tên, danh mục và giá là bắt buộc', 400);
    }

    $slug = createProductSlug($name);

    if ($id) {
        // Update product
        Database::update(
            'UPDATE market_products 
             SET category_id = ?, name = ?, slug = ?, description = ?, 
                 price = ?, sale_price = ?, stock = ?, tags = ?, is_active = ?
             WHERE id = ?',
            [$categoryId, $name, $slug, $description, $price, $salePrice, $stock, $tags, $isActive, $id]
        );
        $message = 'Đã cập nhật sản phẩm';
    } else {
        // Insert product
        $id = Database::insert(
            'INSERT INTO market_products 
             (category_id, name, slug, description, price, sale_price, stock, tags, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$categoryId, $name, $slug, $description, $price, $salePrice, $stock, $tags, $isActive]
        );
        $message = 'Đã thêm sản phẩm mới';
    }

    // Handle image uploads
    if ($files && isset($files['tmp_name'])) {
        $uploadDir = __DIR__ . '/../../../uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];

        foreach ($tmpNames as $i => $tmpName) {
            if (empty($tmpName))
                continue;

            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                continue;

            $filename = 'product_' . $id . '_' . time() . '_' . $i . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($tmpName, $filepath)) {
                $imagePath = '/uploads/products/' . $filename;
                $isPrimary = ($i === 0) ? 1 : 0;

                // If this is primary, reset other primary images
                if ($isPrimary) {
                    Database::update(
                        'UPDATE market_product_images SET is_primary = 0 WHERE product_id = ?',
                        [$id]
                    );
                }

                Database::insert(
                    'INSERT INTO market_product_images (product_id, image_path, is_primary, sort_order)
                     VALUES (?, ?, ?, ?)',
                    [$id, $imagePath, $isPrimary, $i]
                );
            }
        }
    }

    jsonResponse([
        'success' => true,
        'message' => $message,
        'id' => $id
    ]);
}

function handleDeleteProduct()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($data['id'] ?? 0);

    if (!$id) {
        errorResponse('ID sản phẩm là bắt buộc', 400);
    }

    // Delete images from disk
    $images = Database::fetchAll(
        'SELECT image_path FROM market_product_images WHERE product_id = ?',
        [$id]
    );

    foreach ($images as $img) {
        $filepath = __DIR__ . '/../../../' . $img['image_path'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    // Delete product (cascade will delete images from DB)
    Database::delete('DELETE FROM market_products WHERE id = ?', [$id]);

    jsonResponse([
        'success' => true,
        'message' => 'Đã xóa sản phẩm'
    ]);
}

function createProductSlug($string)
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
    return trim($slug, '-') . '-' . time();
}
