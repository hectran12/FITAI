<?php
/**
 * Market Cart API
 * 
 * GET - Get cart items
 * POST - Add/Update cart item
 * DELETE - Remove cart item
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetCart($userId);
            break;
        case 'POST':
            handleAddToCart($userId);
            break;
        case 'DELETE':
            handleRemoveFromCart($userId);
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Cart API error: ' . $e->getMessage());
    errorResponse('Lỗi giỏ hàng', 500);
}

function handleGetCart($userId)
{
    $items = Database::fetchAll(
        'SELECT c.*, p.name, p.price, p.sale_price, p.stock, p.is_active,
                (SELECT image_path FROM market_product_images 
                 WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
         FROM market_cart c
         JOIN market_products p ON c.product_id = p.id
         WHERE c.user_id = ?
         ORDER BY c.created_at DESC',
        [$userId]
    );

    $total = 0;
    foreach ($items as &$item) {
        $unitPrice = $item['sale_price'] ?: $item['price'];
        $item['unit_price'] = $unitPrice;
        $item['subtotal'] = $unitPrice * $item['quantity'];
        $item['price_formatted'] = number_format($unitPrice) . 'đ';
        $item['subtotal_formatted'] = number_format($item['subtotal']) . 'đ';
        $total += $item['subtotal'];
    }

    jsonResponse([
        'success' => true,
        'items' => $items,
        'total' => $total,
        'total_formatted' => number_format($total) . 'đ',
        'item_count' => count($items)
    ]);
}

function handleAddToCart($userId)
{
    $data = json_decode(file_get_contents('php://input'), true);

    $productId = (int) ($data['product_id'] ?? 0);
    $quantity = max(1, (int) ($data['quantity'] ?? 1));
    $action = $data['action'] ?? 'add'; // 'add', 'set', 'increment'

    if (!$productId) {
        errorResponse('Product ID là bắt buộc', 400);
    }

    // Check product exists and active
    $product = Database::fetchOne(
        'SELECT id, stock, is_active FROM market_products WHERE id = ?',
        [$productId]
    );

    if (!$product || !$product['is_active']) {
        errorResponse('Sản phẩm không tồn tại hoặc đã ngừng bán', 404);
    }

    // Check existing cart item
    $existing = Database::fetchOne(
        'SELECT * FROM market_cart WHERE user_id = ? AND product_id = ?',
        [$userId, $productId]
    );

    if ($existing) {
        if ($action === 'set') {
            $newQty = $quantity;
        } else {
            $newQty = $existing['quantity'] + $quantity;
        }

        // Check stock
        if ($newQty > $product['stock']) {
            $newQty = $product['stock'];
        }

        if ($newQty <= 0) {
            Database::delete(
                'DELETE FROM market_cart WHERE user_id = ? AND product_id = ?',
                [$userId, $productId]
            );
        } else {
            Database::update(
                'UPDATE market_cart SET quantity = ? WHERE user_id = ? AND product_id = ?',
                [$newQty, $userId, $productId]
            );
        }
    } else {
        if ($quantity > $product['stock']) {
            $quantity = $product['stock'];
        }

        Database::insert(
            'INSERT INTO market_cart (user_id, product_id, quantity) VALUES (?, ?, ?)',
            [$userId, $productId, $quantity]
        );
    }

    // Get updated cart count
    $count = Database::fetchOne(
        'SELECT SUM(quantity) as total FROM market_cart WHERE user_id = ?',
        [$userId]
    );

    jsonResponse([
        'success' => true,
        'message' => 'Đã cập nhật giỏ hàng',
        'cart_count' => (int) ($count['total'] ?? 0)
    ]);
}

function handleRemoveFromCart($userId)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $productId = (int) ($data['product_id'] ?? 0);

    if (!$productId) {
        errorResponse('Product ID là bắt buộc', 400);
    }

    Database::delete(
        'DELETE FROM market_cart WHERE user_id = ? AND product_id = ?',
        [$userId, $productId]
    );

    jsonResponse([
        'success' => true,
        'message' => 'Đã xóa khỏi giỏ hàng'
    ]);
}
