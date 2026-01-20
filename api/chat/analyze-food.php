<?php
/**
 * Analyze Food Image Endpoint
 * 
 * POST /api/chat/analyze-food
 * 
 * Upload food image and get calorie analysis
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

$userId = getCurrentUserId();

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    errorResponse('Invalid CSRF token', 403);
}

// Check for uploaded file
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    errorResponse('No image uploaded', 400);
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$maxSize = 10 * 1024 * 1024; // 10MB

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    errorResponse('Invalid image type. Allowed: JPEG, PNG, WebP', 400);
}

if ($file['size'] > $maxSize) {
    errorResponse('Image too large. Maximum 10MB', 400);
}

try {
    // Get or create active conversation
    $conversation = Database::fetchOne(
        'SELECT id FROM chat_conversations 
         WHERE user_id = ? AND is_active = TRUE 
         ORDER BY updated_at DESC LIMIT 1',
        [$userId]
    );

    if (!$conversation) {
        $conversationId = Database::insert(
            'INSERT INTO chat_conversations (user_id, title) VALUES (?, ?)',
            [$userId, 'Phân tích thức ăn']
        );
    } else {
        $conversationId = $conversation['id'];
    }

    // Save image
    $uploadDir = __DIR__ . '/../../public/uploads/food/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'food_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.jpg';
    $imagePath = $uploadDir . $filename;

    // Convert to JPEG if needed
    if ($mimeType !== 'image/jpeg') {
        $image = imagecreatefromstring(file_get_contents($file['tmp_name']));
        imagejpeg($image, $imagePath, 85);
        imagedestroy($image);
    } else {
        move_uploaded_file($file['tmp_name'], $imagePath);
    }

    $imageUrl = '/uploads/food/' . $filename;

    // Call AI service
    $aiUrl = AI_SERVICE_URL . '/analyze_food';
    $ch = curl_init($aiUrl);

    $cfile = new CURLFile($imagePath, 'image/jpeg', 'image.jpg');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => $cfile],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        error_log("AI food analysis error: $curlError, HTTP: $httpCode");
        errorResponse('Không thể phân tích ảnh', 503);
    }

    $result = json_decode($response, true);
    $analysisData = $result['data'] ?? $result;

    // Save to chat
    $summaryText = sprintf(
        "📸 Phân tích bữa ăn:\n• Calories: %d kcal\n• Protein: %dg\n• Carbs: %dg\n• Fat: %dg",
        $analysisData['total_calories'] ?? 0,
        $analysisData['protein_g'] ?? 0,
        $analysisData['carbs_g'] ?? 0,
        $analysisData['fat_g'] ?? 0
    );

    // Save user message
    Database::insert(
        'INSERT INTO chat_messages (conversation_id, role, content, image_path, message_type, metadata) VALUES (?, ?, ?, ?, ?, ?)',
        [$conversationId, 'user', 'Phân tích bữa ăn này', $imageUrl, 'calorie_analysis', null]
    );

    // Save AI analysis
    Database::insert(
        'INSERT INTO chat_messages (conversation_id, role, content, message_type, metadata) VALUES (?, ?, ?, ?, ?)',
        [$conversationId, 'assistant', $summaryText, 'calorie_analysis', json_encode($analysisData)]
    );

    // Update message count
    Database::update(
        'UPDATE chat_conversations SET message_count = message_count + 2, updated_at = NOW() WHERE id = ?',
        [$conversationId]
    );

    jsonResponse([
        'success' => true,
        'image_url' => $imageUrl,
        'analysis' => $analysisData,
        'conversation_id' => $conversationId
    ]);

} catch (Exception $e) {
    error_log('Food analysis error: ' . $e->getMessage());
    errorResponse('Không thể phân tích ảnh', 500);
}
