<?php
/**
 * Chat History Endpoint
 * 
 * GET /api/chat/history
 * 
 * Get chat messages for current conversation
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$conversationId = isset($_GET['conversation_id']) ? (int) $_GET['conversation_id'] : null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

try {
    // If no conversation ID, get active conversation
    if (!$conversationId) {
        $conversation = Database::fetchOne(
            'SELECT id, title, message_count, created_at FROM chat_conversations 
             WHERE user_id = ? AND is_active = TRUE 
             ORDER BY updated_at DESC LIMIT 1',
            [$userId]
        );

        if (!$conversation) {
            jsonResponse([
                'success' => true,
                'conversation' => null,
                'messages' => [],
                'total' => 0
            ]);
            return;
        }

        $conversationId = $conversation['id'];
    } else {
        // Verify conversation belongs to user
        $conversation = Database::fetchOne(
            'SELECT id, title, message_count, created_at FROM chat_conversations 
             WHERE id = ? AND user_id = ?',
            [$conversationId, $userId]
        );

        if (!$conversation) {
            errorResponse('Conversation not found', 404);
        }
    }

    // Get messages
    $messages = Database::fetchAll(
        'SELECT id, role, content, image_path, message_type, metadata, created_at 
         FROM chat_messages 
         WHERE conversation_id = ? 
         ORDER BY created_at DESC 
         LIMIT ? OFFSET ?',
        [$conversationId, $limit, $offset]
    );

    // Reverse to get chronological order
    $messages = array_reverse($messages);

    // Parse metadata JSON
    foreach ($messages as &$msg) {
        if ($msg['metadata']) {
            $msg['metadata'] = json_decode($msg['metadata'], true);
        }
    }

    // Get total count
    $total = Database::fetchOne(
        'SELECT COUNT(*) as total FROM chat_messages WHERE conversation_id = ?',
        [$conversationId]
    );

    jsonResponse([
        'success' => true,
        'conversation' => [
            'id' => $conversation['id'],
            'title' => $conversation['title'],
            'message_count' => $conversation['message_count'],
            'created_at' => $conversation['created_at']
        ],
        'messages' => $messages,
        'total' => $total['total'],
        'page' => $page,
        'has_more' => ($offset + count($messages)) < $total['total']
    ]);

} catch (Exception $e) {
    error_log('Chat history error: ' . $e->getMessage());
    errorResponse('Failed to load chat history', 500);
}
