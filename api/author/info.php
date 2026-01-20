<?php
/**
 * Author Info API
 * GET /api/author/info
 * Returns public author information
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

try {
    // Fetch all author settings
    $settings = Database::fetchAll(
        "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'author_%'"
    );

    $author = [
        'name' => '',
        'bio' => '',
        'avatar' => '',
        'email' => '',
        'website' => '',
        'location' => '',
        'social_links' => []
    ];

    foreach ($settings as $setting) {
        $key = str_replace('author_', '', $setting['setting_key']);

        if (in_array($key, ['facebook', 'instagram', 'twitter', 'linkedin', 'github'])) {
            if (!empty($setting['setting_value'])) {
                $author['social_links'][$key] = $setting['setting_value'];
            }
        } else {
            $author[$key] = $setting['setting_value'];
        }
    }

    jsonResponse([
        'success' => true,
        'author' => $author
    ]);

} catch (Exception $e) {
    error_log('Author info error: ' . $e->getMessage());
    errorResponse('Server error', 500);
}
