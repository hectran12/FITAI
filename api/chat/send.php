<?php
/**
 * Chat Send Message Endpoint
 * 
 * POST /api/chat/send
 * 
 * Sends a message to PT AI and returns response
 * Handles conversation management with 150 message limit
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
$input = getJsonInput();

// Validate CSRF token
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    errorResponse('Invalid CSRF token', 403);
}

$message = trim($input['message'] ?? '');
if (empty($message)) {
    errorResponse('Message is required', 400);
}

try {
    // Get or create active conversation
    $conversation = Database::fetchOne(
        'SELECT id, message_count FROM chat_conversations 
         WHERE user_id = ? AND is_active = TRUE 
         ORDER BY updated_at DESC LIMIT 1',
        [$userId]
    );

    if (!$conversation) {
        // Create new conversation
        $conversationId = Database::insert(
            'INSERT INTO chat_conversations (user_id, title) VALUES (?, ?)',
            [$userId, 'Cuộc trò chuyện mới']
        );
        $messageCount = 0;
    } else {
        $conversationId = $conversation['id'];
        $messageCount = $conversation['message_count'];
    }

    // Check if we need to summarize and create new conversation
    if ($messageCount >= 150) {
        // Get messages for summarization
        $messages = Database::fetchAll(
            'SELECT role, content FROM chat_messages 
             WHERE conversation_id = ? ORDER BY created_at',
            [$conversationId]
        );

        // Call AI to summarize
        $summaryResponse = callAIService('/summarize', [
            'messages' => $messages
        ]);

        $summary = $summaryResponse['summary'] ?? 'Tóm tắt cuộc trò chuyện';

        // Update old conversation
        Database::update(
            'UPDATE chat_conversations SET is_active = FALSE, summary = ? WHERE id = ?',
            [$summary, $conversationId]
        );

        // Create new conversation
        $conversationId = Database::insert(
            'INSERT INTO chat_conversations (user_id, title) VALUES (?, ?)',
            [$userId, 'Cuộc trò chuyện mới (tiếp nối)']
        );
        $messageCount = 0;
    }

    // Get conversation history for context (last 20 messages)
    $history = Database::fetchAll(
        'SELECT role, content FROM chat_messages 
         WHERE conversation_id = ? 
         ORDER BY created_at DESC LIMIT 20',
        [$conversationId]
    );
    $history = array_reverse($history);

    // Save user message
    Database::insert(
        'INSERT INTO chat_messages (conversation_id, role, content, message_type) VALUES (?, ?, ?, ?)',
        [$conversationId, 'user', $message, 'text']
    );

    // Call AI service
    $aiResponse = callAIService('/chat', [
        'message' => $message,
        'conversation_history' => $history
    ]);

    $responseText = $aiResponse['response'] ?? 'Xin lỗi, có lỗi xảy ra.';

    // Save AI response
    Database::insert(
        'INSERT INTO chat_messages (conversation_id, role, content, message_type) VALUES (?, ?, ?, ?)',
        [$conversationId, 'assistant', $responseText, 'text']
    );

    // Update message count
    Database::update(
        'UPDATE chat_conversations SET message_count = message_count + 2, updated_at = NOW() WHERE id = ?',
        [$conversationId]
    );

    jsonResponse([
        'success' => true,
        'response' => $responseText,
        'conversation_id' => $conversationId,
        'message_count' => $messageCount + 2
    ]);

} catch (Exception $e) {
    error_log('Chat error: ' . $e->getMessage());
    errorResponse('Failed to process chat', 500);
}

/**
 * Call AI service endpoint
 */
function callAIService(string $endpoint, array $data): array
{
    $aiUrl = AI_SERVICE_URL . $endpoint;
    $ch = curl_init($aiUrl);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('AI service unavailable: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new Exception('AI service error: HTTP ' . $httpCode);
    }

    return json_decode($response, true) ?? [];
}
