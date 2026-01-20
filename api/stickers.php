<?php
/**
 * Stickers API
 * 
 * GET - Get sticker packs and stickers
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

handleCorsPreflightRequest();
startSecureSession();
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    errorResponse('Method not allowed', 405);
}

try {
    $packs = Database::fetchAll("
        SELECT * FROM sticker_packs 
        WHERE is_active = 1 
        ORDER BY sort_order, id
    ");

    foreach ($packs as &$pack) {
        $pack['stickers'] = Database::fetchAll("
            SELECT id, image_url, emoji 
            FROM stickers 
            WHERE pack_id = ? 
            ORDER BY sort_order, id
        ", [$pack['id']]);
    }

    jsonResponse([
        'success' => true,
        'packs' => $packs
    ]);
} catch (Exception $e) {
    error_log('Stickers error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}
