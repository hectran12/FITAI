<?php
/**
 * Music Categories & Tracks API
 * 
 * GET /api/music/tracks - List tracks (with optional category filter)
 * GET /api/music/tracks?category_id=X - Filter by category
 * POST /api/music/tracks - Admin add new track
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Public endpoint - list tracks
    $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    try {
        // Get categories
        $categories = Database::fetchAll(
            'SELECT id, name, icon, description FROM music_categories 
             WHERE is_active = 1 ORDER BY sort_order'
        );

        // Get tracks
        $params = [];
        $where = 'WHERE t.is_active = 1';

        if ($categoryId) {
            $where .= ' AND t.category_id = ?';
            $params[] = $categoryId;
        }

        $sql = "SELECT t.*, c.name as category_name, c.icon as category_icon
                FROM music_tracks t
                JOIN music_categories c ON t.category_id = c.id
                $where
                ORDER BY t.play_count DESC, t.created_at DESC
                LIMIT $limit OFFSET $offset";

        $tracks = Database::fetchAll($sql, $params);

        // Add favorite status if logged in
        $userId = getCurrentUserId();
        if ($userId) {
            $favoriteIds = Database::fetchAll(
                'SELECT track_id FROM music_favorites WHERE user_id = ?',
                [$userId]
            );
            $favoriteIds = array_column($favoriteIds, 'track_id');

            foreach ($tracks as &$track) {
                $track['is_favorite'] = in_array($track['id'], $favoriteIds);
            }
        }

        jsonResponse([
            'success' => true,
            'categories' => $categories,
            'tracks' => $tracks,
            'page' => $page
        ]);

    } catch (Exception $e) {
        error_log('Music tracks error: ' . $e->getMessage());
        errorResponse('Failed to load music', 500);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin only - add new track
    requireAuth();

    $userId = getCurrentUserId();
    $user = Database::fetchOne('SELECT is_admin FROM users WHERE id = ?', [$userId]);
    if (!$user || !$user['is_admin']) {
        errorResponse('Admin access required', 403);
    }

    // Handle file upload
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        errorResponse('Audio file required', 400);
    }

    $file = $_FILES['audio'];
    $allowedTypes = ['audio/mpeg', 'audio/mp3'];
    $maxSize = 50 * 1024 * 1024; // 50MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        errorResponse('Only MP3 files allowed', 400);
    }

    if ($file['size'] > $maxSize) {
        errorResponse('File too large. Maximum 50MB', 400);
    }

    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $artist = trim($_POST['artist'] ?? '');
    $bpm = (int) ($_POST['bpm'] ?? 0);

    if (!$categoryId || !$title) {
        errorResponse('Category and title required', 400);
    }

    try {
        // Save audio file
        $uploadDir = __DIR__ . '/../../uploads/music/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'track_' . time() . '_' . bin2hex(random_bytes(8)) . '.mp3';
        $filePath = $uploadDir . $filename;
        move_uploaded_file($file['tmp_name'], $filePath);

        $fileUrl = '/uploads/music/' . $filename;

        // Handle cover image if provided
        $coverUrl = null;
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $coverDir = __DIR__ . '/../../uploads/music/covers/';
            if (!is_dir($coverDir)) {
                mkdir($coverDir, 0755, true);
            }
            $coverFilename = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
            move_uploaded_file($_FILES['cover']['tmp_name'], $coverDir . $coverFilename);
            $coverUrl = '/uploads/music/covers/' . $coverFilename;
        }

        // Get duration using getID3 or estimate
        $duration = 0; // Would need getID3 library for accurate duration

        $trackId = Database::insert(
            'INSERT INTO music_tracks (category_id, title, artist, file_url, cover_image, duration, bpm) 
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$categoryId, $title, $artist, $fileUrl, $coverUrl, $duration, $bpm]
        );

        jsonResponse([
            'success' => true,
            'message' => 'Track added successfully',
            'track_id' => $trackId
        ]);

    } catch (Exception $e) {
        error_log('Add track error: ' . $e->getMessage());
        errorResponse('Failed to add track', 500);
    }
} else {
    errorResponse('Method not allowed', 405);
}
