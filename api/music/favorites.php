<?php
/**
 * Music Favorites API
 * 
 * GET /api/music/favorites - List user's favorite tracks
 * POST /api/music/favorites - Add to favorites
 * DELETE /api/music/favorites?track_id=X - Remove from favorites
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $tracks = Database::fetchAll(
            'SELECT t.*, c.name as category_name, c.icon as category_icon, 1 as is_favorite
             FROM music_favorites f
             JOIN music_tracks t ON f.track_id = t.id
             JOIN music_categories c ON t.category_id = c.id
             WHERE f.user_id = ? AND t.is_active = 1
             ORDER BY f.created_at DESC',
            [$userId]
        );

        jsonResponse([
            'success' => true,
            'tracks' => $tracks
        ]);

    } catch (Exception $e) {
        error_log('Favorites error: ' . $e->getMessage());
        errorResponse('Failed to load favorites', 500);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    $trackId = (int) ($input['track_id'] ?? 0);

    if (!$trackId) {
        errorResponse('Track ID required', 400);
    }

    try {
        // Check if already favorited
        $exists = Database::fetchOne(
            'SELECT id FROM music_favorites WHERE user_id = ? AND track_id = ?',
            [$userId, $trackId]
        );

        if ($exists) {
            jsonResponse(['success' => true, 'message' => 'Already in favorites']);
            return;
        }

        Database::insert(
            'INSERT INTO music_favorites (user_id, track_id) VALUES (?, ?)',
            [$userId, $trackId]
        );

        jsonResponse([
            'success' => true,
            'message' => 'Added to favorites'
        ]);

    } catch (Exception $e) {
        error_log('Add favorite error: ' . $e->getMessage());
        errorResponse('Failed to add favorite', 500);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $trackId = (int) ($_GET['track_id'] ?? 0);

    if (!$trackId) {
        errorResponse('Track ID required', 400);
    }

    try {
        Database::delete(
            'DELETE FROM music_favorites WHERE user_id = ? AND track_id = ?',
            [$userId, $trackId]
        );

        jsonResponse([
            'success' => true,
            'message' => 'Removed from favorites'
        ]);

    } catch (Exception $e) {
        error_log('Remove favorite error: ' . $e->getMessage());
        errorResponse('Failed to remove favorite', 500);
    }
} else {
    errorResponse('Method not allowed', 405);
}
