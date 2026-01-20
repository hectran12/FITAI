<?php
/**
 * Admin Market Orders API
 * 
 * GET - List orders
 * POST - Update order status
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
            handleGetOrders();
            break;
        case 'POST':
            handleUpdateOrderStatus();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin orders error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

function handleGetOrders()
{
    $status = $_GET['status'] ?? '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $whereClause = '';
    $params = [];

    if ($status && in_array($status, ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'])) {
        $whereClause = 'WHERE o.status = ?';
        $params[] = $status;
    }

    $sql = "SELECT o.*, 
                   u.email as user_email,
                   up.display_name,
                   (SELECT COUNT(*) FROM market_order_items WHERE order_id = o.id) as item_count
            FROM market_orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles up ON o.user_id = up.user_id
            $whereClause
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?";

    $queryParams = array_merge($params, [$limit, $offset]);
    $orders = Database::fetchAll($sql, $queryParams);

    $countSql = "SELECT COUNT(*) as total FROM market_orders o $whereClause";
    $total = Database::fetchOne($countSql, $params);

    // Format dates and get status counts
    foreach ($orders as &$order) {
        $order['created_at_formatted'] = date('d/m/Y H:i', strtotime($order['created_at']));
        $order['total_formatted'] = number_format($order['total_amount']) . 'đ';
    }

    // Get status counts
    $statusCounts = Database::fetchAll(
        'SELECT status, COUNT(*) as count FROM market_orders GROUP BY status'
    );

    jsonResponse([
        'success' => true,
        'orders' => $orders,
        'status_counts' => array_column($statusCounts, 'count', 'status'),
        'total' => (int) $total['total'],
        'page' => $page,
        'total_pages' => ceil($total['total'] / $limit)
    ]);
}

function handleUpdateOrderStatus()
{
    $data = json_decode(file_get_contents('php://input'), true);

    $orderId = (int) ($data['order_id'] ?? 0);
    $status = $data['status'] ?? '';

    if (!$orderId || !$status) {
        errorResponse('Order ID và status là bắt buộc', 400);
    }

    $validStatuses = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        errorResponse('Status không hợp lệ', 400);
    }

    // Get order
    $order = Database::fetchOne(
        'SELECT * FROM market_orders WHERE id = ?',
        [$orderId]
    );

    if (!$order) {
        errorResponse('Đơn hàng không tồn tại', 404);
    }

    // Update status
    Database::update(
        'UPDATE market_orders SET status = ? WHERE id = ?',
        [$status, $orderId]
    );

    // If delivered, update sold_count
    if ($status === 'delivered' && $order['status'] !== 'delivered') {
        $items = Database::fetchAll(
            'SELECT product_id, quantity FROM market_order_items WHERE order_id = ?',
            [$orderId]
        );

        foreach ($items as $item) {
            Database::update(
                'UPDATE market_products SET sold_count = sold_count + ? WHERE id = ?',
                [$item['quantity'], $item['product_id']]
            );
        }
    }

    $statusLabels = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao hàng',
        'delivered' => 'Đã giao',
        'cancelled' => 'Đã hủy'
    ];

    jsonResponse([
        'success' => true,
        'message' => 'Đã cập nhật trạng thái: ' . $statusLabels[$status]
    ]);
}
