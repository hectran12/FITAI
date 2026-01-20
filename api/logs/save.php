<?php
/**
 * Save Workout Log Endpoint
 * 
 * POST /api/logs
 * 
 * Request body:
 * {
 *   "plan_day_id": 1,
 *   "status": "done",
 *   "fatigue_rating": 3,
 *   "notes": "Felt good today"
 * }
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

// Validate required fields
if (!isset($input['plan_day_id']) || !isset($input['status'])) {
    errorResponse('plan_day_id and status are required');
}

$planDayId = (int) $input['plan_day_id'];
$status = $input['status'];
$fatigueRating = isset($input['fatigue_rating']) ? (int) $input['fatigue_rating'] : null;
$notes = $input['notes'] ?? null;

// Validate status
$allowedStatuses = ['done', 'skipped', 'partial'];
if (!in_array($status, $allowedStatuses)) {
    errorResponse('Invalid status. Must be one of: ' . implode(', ', $allowedStatuses));
}

// Validate fatigue rating
if ($fatigueRating !== null && ($fatigueRating < 1 || $fatigueRating > 5)) {
    errorResponse('fatigue_rating must be between 1 and 5');
}

// Verify plan day exists and belongs to user
$planDay = Database::fetchOne(
    'SELECT pd.id, pd.date, p.user_id
     FROM plan_days pd
     JOIN plans p ON p.id = pd.plan_id
     WHERE pd.id = ?',
    [$planDayId]
);

if (!$planDay) {
    errorResponse('Plan day not found', 404);
}

if ($planDay['user_id'] != $userId) {
    errorResponse('Access denied', 403);
}

// Check if log already exists (update) or create new
$existingLog = Database::fetchOne(
    'SELECT id FROM workout_logs WHERE plan_day_id = ? AND user_id = ?',
    [$planDayId, $userId]
);

if ($existingLog) {
    // Update existing log
    Database::update(
        'UPDATE workout_logs SET status = ?, fatigue_rating = ?, notes = ?, logged_at = NOW()
         WHERE id = ?',
        [$status, $fatigueRating, $notes, $existingLog['id']]
    );
    $logId = $existingLog['id'];
    $message = 'Workout log updated';
} else {
    // Create new log
    $logId = Database::insert(
        'INSERT INTO workout_logs (plan_day_id, user_id, status, fatigue_rating, notes)
         VALUES (?, ?, ?, ?, ?)',
        [$planDayId, $userId, $status, $fatigueRating, $notes]
    );
    $message = 'Workout log saved';
}

jsonResponse([
    'success' => true,
    'message' => $message,
    'log' => [
        'id' => $logId,
        'plan_day_id' => $planDayId,
        'status' => $status,
        'fatigue_rating' => $fatigueRating,
        'notes' => $notes
    ]
]);
