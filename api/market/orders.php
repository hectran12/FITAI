<?php
/**
 * Market Orders API (User)
 * 
 * GET - Get user's orders
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

$userId = getCurrentUserId();

try {
    $orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($orderId) {
        // Get single order with items
        $order = Database::fetchOne(
            'SELECT * FROM market_orders WHERE id = ? AND user_id = ?',
            [$orderId, $userId]
        );

        if (!$order) {
            errorResponse('Đơn hàng không tồn tại', 404);
        }

        // Get order items
        $items = Database::fetchAll(
            'SELECT * FROM market_order_items WHERE order_id = ?',
            [$orderId]
        );

        foreach ($items as &$item) {
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $item['price_formatted'] = number_format($item['price']) . 'đ';
            $item['subtotal_formatted'] = number_format($item['subtotal']) . 'đ';
        }

        $order['items'] = $items;
        $order['total_formatted'] = number_format($order['total_amount']) . 'đ';
        $order['created_at_formatted'] = date('d/m/Y H:i', strtotime($order['created_at']));

        $statusLabels = [
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'shipping' => 'Đang giao hàng',
            'delivered' => 'Đã giao',
            'cancelled' => 'Đã hủy'
        ];
        $order['status_label'] = $statusLabels[$order['status']] ?? $order['status'];

        jsonResponse([
            'success' => true,
            'order' => $order
        ]);
    } else {
        // List orders
        $orders = Database::fetchAll(
            'SELECT o.*, 
                    (SELECT COUNT(*) FROM market_order_items WHERE order_id = o.id) as item_count,
                    (SELECT product_image FROM market_order_items WHERE order_id = o.id LIMIT 1) as first_image
             FROM market_orders o
             WHERE o.user_id = ?
             ORDER BY o.created_at DESC',
            [$userId]
        );

        $statusLabels = [
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'shipping' => 'Đang giao hàng',
            'delivered' => 'Đã giao',
            'cancelled' => 'Đã hủy'
        ];

        foreach ($orders as &$order) {
            $order['total_formatted'] = number_format($order['total_amount']) . 'đ';
            $order['created_at_formatted'] = date('d/m/Y H:i', strtotime($order['created_at']));
            $order['status_label'] = $statusLabels[$order['status']] ?? $order['status'];
        }

        jsonResponse([
            'success' => true,
            'orders' => $orders
        ]);
    }
} catch (Exception $e) {
    error_log('Orders API error: ' . $e->getMessage());
    errorResponse('Lỗi tải đơn hàng', 500);
}
