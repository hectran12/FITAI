<?php
/**
 * Market Reviews API
 * 
 * GET - Get product reviews
 * POST - Submit review (only for delivered orders)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

startSecureSession();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetReviews();
            break;
        case 'POST':
            requireAuth();
            handleSubmitReview();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Reviews API error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

function handleGetReviews()
{
    $productId = (int) ($_GET['product_id'] ?? 0);

    if (!$productId) {
        errorResponse('Product ID là bắt buộc', 400);
    }

    $reviews = Database::fetchAll(
        'SELECT r.*, up.display_name, up.avatar
         FROM market_reviews r
         LEFT JOIN user_profiles up ON r.user_id = up.user_id
         WHERE r.product_id = ?
         ORDER BY r.created_at DESC',
        [$productId]
    );

    foreach ($reviews as &$review) {
        $review['created_at_formatted'] = date('d/m/Y', strtotime($review['created_at']));
        $review['display_name'] = $review['display_name'] ?: 'Người dùng';
    }

    // Get rating summary
    $summary = Database::fetchOne(
        'SELECT COUNT(*) as total, AVG(rating) as avg_rating
         FROM market_reviews WHERE product_id = ?',
        [$productId]
    );

    jsonResponse([
        'success' => true,
        'reviews' => $reviews,
        'summary' => [
            'total' => (int) $summary['total'],
            'average' => round($summary['avg_rating'], 1)
        ]
    ]);
}

function handleSubmitReview()
{
    $userId = getCurrentUserId();
    $data = json_decode(file_get_contents('php://input'), true);

    $productId = (int) ($data['product_id'] ?? 0);
    $orderId = (int) ($data['order_id'] ?? 0);
    $rating = (int) ($data['rating'] ?? 0);
    $comment = trim($data['comment'] ?? '');

    if (!$productId || !$orderId || !$rating) {
        errorResponse('Product ID, Order ID và rating là bắt buộc', 400);
    }

    if ($rating < 1 || $rating > 5) {
        errorResponse('Rating phải từ 1-5', 400);
    }

    // Check order belongs to user and is delivered
    $order = Database::fetchOne(
        'SELECT * FROM market_orders WHERE id = ? AND user_id = ?',
        [$orderId, $userId]
    );

    if (!$order) {
        errorResponse('Đơn hàng không tồn tại', 404);
    }

    if ($order['status'] !== 'delivered') {
        errorResponse('Chỉ có thể đánh giá sau khi nhận hàng', 400);
    }

    // Check product is in order
    $orderItem = Database::fetchOne(
        'SELECT * FROM market_order_items WHERE order_id = ? AND product_id = ?',
        [$orderId, $productId]
    );

    if (!$orderItem) {
        errorResponse('Sản phẩm không có trong đơn hàng này', 400);
    }

    // Check not already reviewed
    $existing = Database::fetchOne(
        'SELECT * FROM market_reviews WHERE order_id = ? AND product_id = ?',
        [$orderId, $productId]
    );

    if ($existing) {
        errorResponse('Bạn đã đánh giá sản phẩm này rồi', 400);
    }

    // Insert review
    Database::insert(
        'INSERT INTO market_reviews (product_id, user_id, order_id, rating, comment)
         VALUES (?, ?, ?, ?, ?)',
        [$productId, $userId, $orderId, $rating, $comment]
    );

    // Update product rating
    $stats = Database::fetchOne(
        'SELECT COUNT(*) as count, AVG(rating) as avg FROM market_reviews WHERE product_id = ?',
        [$productId]
    );

    Database::update(
        'UPDATE market_products SET rating_avg = ?, rating_count = ? WHERE id = ?',
        [round($stats['avg'], 1), $stats['count'], $productId]
    );

    jsonResponse([
        'success' => true,
        'message' => 'Cảm ơn bạn đã đánh giá!'
    ]);
}
