<?php
/**
 * Public Ads API
 * 
 * GET - Get active ads for display
 * POST - Track ad click
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

handleCorsPreflightRequest();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getActiveAds();
            break;
        case 'POST':
            trackClick();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Ads error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

function getActiveAds()
{
    $position = $_GET['position'] ?? null;

    $sql = "
        SELECT id, title, image_url, link_url, position
        FROM ads 
        WHERE is_active = 1 
          AND (start_date IS NULL OR start_date <= CURDATE())
          AND (end_date IS NULL OR end_date >= CURDATE())
    ";

    if ($position) {
        $sql .= " AND position = ?";
        $ads = Database::fetchAll($sql . " ORDER BY sort_order, id", [$position]);
    } else {
        $ads = Database::fetchAll($sql . " ORDER BY position, sort_order, id");
    }

    // Track views
    if (!empty($ads)) {
        $ids = array_column($ads, 'id');
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        Database::update("UPDATE ads SET view_count = view_count + 1 WHERE id IN ($placeholders)", $ids);
    }

    jsonResponse([
        'success' => true,
        'ads' => $ads
    ]);
}

function trackClick()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($data['id'] ?? 0);

    if ($id) {
        Database::update("UPDATE ads SET click_count = click_count + 1 WHERE id = ?", [$id]);
    }

    jsonResponse(['success' => true]);
}
