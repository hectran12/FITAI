<?php
/**
 * Market Checkout API
 * 
 * POST - Create order from cart
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

$userId = getCurrentUserId();

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate shipping info
    $recipientName = trim($data['recipient_name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $note = trim($data['note'] ?? '');

    if (!$recipientName || !$phone || !$address) {
        errorResponse('Tên, số điện thoại và địa chỉ là bắt buộc', 400);
    }

    // Validate phone
    if (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        errorResponse('Số điện thoại không hợp lệ', 400);
    }

    // Get cart items
    $cartItems = Database::fetchAll(
        'SELECT c.*, p.name, p.price, p.sale_price, p.stock, p.is_active,
                (SELECT image_path FROM market_product_images 
                 WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
         FROM market_cart c
         JOIN market_products p ON c.product_id = p.id
         WHERE c.user_id = ?',
        [$userId]
    );

    if (empty($cartItems)) {
        errorResponse('Giỏ hàng trống', 400);
    }

    // Validate items and calculate total
    $totalAmount = 0;
    $orderItems = [];

    foreach ($cartItems as $item) {
        if (!$item['is_active']) {
            errorResponse("Sản phẩm '{$item['name']}' đã ngừng bán", 400);
        }

        if ($item['quantity'] > $item['stock']) {
            errorResponse("Sản phẩm '{$item['name']}' không đủ hàng (còn {$item['stock']})", 400);
        }

        $price = $item['sale_price'] ?: $item['price'];
        $subtotal = $price * $item['quantity'];
        $totalAmount += $subtotal;

        $orderItems[] = [
            'product_id' => $item['product_id'],
            'product_name' => $item['name'],
            'product_image' => $item['image'],
            'price' => $price,
            'quantity' => $item['quantity']
        ];
    }

    // Generate order code
    $orderCode = 'FIT' . date('ymd') . strtoupper(substr(uniqid(), -6));

    // Create order
    $orderId = Database::insert(
        'INSERT INTO market_orders 
         (user_id, order_code, total_amount, recipient_name, phone, address, note)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$userId, $orderCode, $totalAmount, $recipientName, $phone, $address, $note]
    );

    // Create order items
    foreach ($orderItems as $item) {
        Database::insert(
            'INSERT INTO market_order_items 
             (order_id, product_id, product_name, product_image, price, quantity)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $orderId,
                $item['product_id'],
                $item['product_name'],
                $item['product_image'],
                $item['price'],
                $item['quantity']
            ]
        );

        // Decrease stock
        Database::update(
            'UPDATE market_products SET stock = stock - ? WHERE id = ?',
            [$item['quantity'], $item['product_id']]
        );
    }

    // Clear cart
    Database::delete('DELETE FROM market_cart WHERE user_id = ?', [$userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Đặt hàng thành công!',
        'order' => [
            'id' => $orderId,
            'order_code' => $orderCode,
            'total' => $totalAmount,
            'total_formatted' => number_format($totalAmount) . 'đ'
        ]
    ]);

} catch (Exception $e) {
    error_log('Checkout error: ' . $e->getMessage());
    errorResponse('Lỗi đặt hàng: ' . $e->getMessage(), 500);
}
