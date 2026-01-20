<?php
/**
 * Market Products API (User)
 * 
 * GET - List products or get single product
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    if ($productId) {
        // Get single product with details
        $product = Database::fetchOne(
            'SELECT p.*, c.name as category_name
             FROM market_products p
             LEFT JOIN market_categories c ON p.category_id = c.id
             WHERE p.id = ? AND p.is_active = 1',
            [$productId]
        );

        if (!$product) {
            errorResponse('Sản phẩm không tồn tại', 404);
        }

        // Increment view count
        Database::update(
            'UPDATE market_products SET view_count = view_count + 1 WHERE id = ?',
            [$productId]
        );

        // Get images
        $images = Database::fetchAll(
            'SELECT * FROM market_product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order',
            [$productId]
        );

        // Get reviews
        $reviews = Database::fetchAll(
            'SELECT r.*, up.display_name, up.avatar
             FROM market_reviews r
             LEFT JOIN user_profiles up ON r.user_id = up.user_id
             WHERE r.product_id = ?
             ORDER BY r.created_at DESC
             LIMIT 10',
            [$productId]
        );

        foreach ($reviews as &$review) {
            $review['created_at_formatted'] = date('d/m/Y', strtotime($review['created_at']));
        }

        $product['images'] = $images;
        $product['reviews'] = $reviews;
        $product['price_formatted'] = number_format($product['price']) . 'đ';
        $product['sale_price_formatted'] = $product['sale_price']
            ? number_format($product['sale_price']) . 'đ'
            : null;

        jsonResponse([
            'success' => true,
            'product' => $product
        ]);
    } else {
        // List products
        $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
        $search = $_GET['search'] ?? '';
        $sort = $_GET['sort'] ?? 'newest';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 12;
        $offset = ($page - 1) * $limit;

        $whereClause = 'WHERE p.is_active = 1';
        $params = [];

        if ($categoryId) {
            $whereClause .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }

        if ($search) {
            $whereClause .= ' AND (p.name LIKE ? OR p.tags LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // Sort options
        $orderBy = match ($sort) {
            'price_asc' => 'COALESCE(p.sale_price, p.price) ASC',
            'price_desc' => 'COALESCE(p.sale_price, p.price) DESC',
            'popular' => 'p.sold_count DESC',
            'rating' => 'p.rating_avg DESC',
            default => 'p.created_at DESC'
        };

        $sql = "SELECT p.*, c.name as category_name,
                       (SELECT image_path FROM market_product_images 
                        WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM market_products p
                LEFT JOIN market_categories c ON p.category_id = c.id
                $whereClause
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";

        $queryParams = array_merge($params, [$limit, $offset]);
        $products = Database::fetchAll($sql, $queryParams);

        $countSql = "SELECT COUNT(*) as total FROM market_products p $whereClause";
        $total = Database::fetchOne($countSql, $params);

        // Format prices
        foreach ($products as &$product) {
            $product['price_formatted'] = number_format($product['price']) . 'đ';
            $product['sale_price_formatted'] = $product['sale_price']
                ? number_format($product['sale_price']) . 'đ'
                : null;
        }

        // Get categories
        $categories = Database::fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM market_products WHERE category_id = c.id AND is_active = 1) as count
             FROM market_categories c
             WHERE c.is_active = 1
             ORDER BY c.sort_order, c.name'
        );

        jsonResponse([
            'success' => true,
            'products' => $products,
            'categories' => $categories,
            'total' => (int) $total['total'],
            'page' => $page,
            'total_pages' => ceil($total['total'] / $limit)
        ]);
    }
} catch (Exception $e) {
    error_log('Products API error: ' . $e->getMessage());
    errorResponse('Lỗi tải sản phẩm', 500);
}
