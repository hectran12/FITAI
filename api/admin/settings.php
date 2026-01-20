<?php
/**
 * Settings API for Admin
 * GET - Get all settings
 * POST - Update settings
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

handleCorsPreflightRequest();
startSecureSession();
requireAuth();

// Check if user is admin
$user = Database::fetchOne("SELECT is_admin FROM users WHERE id = ?", [getCurrentUserId()]);
if (!$user || !$user['is_admin']) {
    errorResponse('Admin access required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getSettings();
            break;
        case 'POST':
            updateSettings();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Settings error: ' . $e->getMessage());
    errorResponse('Server error', 500);
}

function getSettings()
{
    $settings = Database::fetchAll("SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key");

    // Convert to key-value object
    $settingsObj = [];
    foreach ($settings as $setting) {
        $settingsObj[$setting['setting_key']] = [
            'value' => $setting['setting_value'],
            'description' => $setting['description']
        ];
    }

    jsonResponse([
        'success' => true,
        'settings' => $settingsObj
    ]);
}

function updateSettings()
{
    $data = getJsonInput();
    $settings = $data['settings'] ?? [];

    if (empty($settings)) {
        errorResponse('No settings provided', 400);
    }

    Database::beginTransaction();

    try {
        foreach ($settings as $key => $value) {
            Database::update(
                "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                [$value, $key]
            );
        }

        Database::commit();

        jsonResponse([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    } catch (Exception $e) {
        Database::rollback();
        throw $e;
    }
}
