<?php
/**
 * Increment Play Count
 * 
 * POST /api/music/play - Increment play count for a track
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$input = getJsonInput();
$trackId = (int) ($input['track_id'] ?? 0);

if (!$trackId) {
    errorResponse('Track ID required', 400);
}

try {
    Database::update(
        'UPDATE music_tracks SET play_count = play_count + 1 WHERE id = ?',
        [$trackId]
    );

    jsonResponse(['success' => true]);

} catch (Exception $e) {
    error_log('Play count error: ' . $e->getMessage());
    errorResponse('Failed to update play count', 500);
}
