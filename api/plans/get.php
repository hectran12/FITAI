<?php
/**
 * Get Workout Plan Endpoint
 * 
 * GET /api/plans
 * GET /api/plans/current
 * 
 * Query params:
 * - week_start: YYYY-MM-DD (optional, defaults to current week)
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

// Get week start from query or calculate current week's Monday
$weekStart = $_GET['week_start'] ?? getWeekStart();

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
    errorResponse('Invalid week_start format. Use YYYY-MM-DD');
}

// Fetch plan for the week
$plan = Database::fetchOne(
    'SELECT id, week_start, principles, notes, is_adjusted, created_at
     FROM plans 
     WHERE user_id = ? AND week_start = ?',
    [$userId, $weekStart]
);

if (!$plan) {
    jsonResponse([
        'success' => true,
        'has_plan' => false,
        'week_start' => $weekStart,
        'plan' => null
    ]);
}

// Fetch plan days
$days = Database::fetchAll(
    'SELECT id, date, title, estimated_minutes, day_order
     FROM plan_days 
     WHERE plan_id = ?
     ORDER BY day_order ASC',
    [$plan['id']]
);

// Fetch items for all days and workout logs
$planDays = [];
foreach ($days as $day) {
    $items = Database::fetchAll(
        'SELECT id, exercise_name, sets, reps, rest_sec, notes, order_index
         FROM plan_items 
         WHERE plan_day_id = ?
         ORDER BY order_index ASC',
        [$day['id']]
    );

    // Get workout log for this day
    $log = Database::fetchOne(
        'SELECT status, fatigue_rating, notes, logged_at
         FROM workout_logs 
         WHERE plan_day_id = ? AND user_id = ?',
        [$day['id'], $userId]
    );

    $planDays[] = [
        'id' => $day['id'],
        'date' => $day['date'],
        'title' => $day['title'],
        'estimated_minutes' => (int) $day['estimated_minutes'],
        'sessions' => array_map(function ($item) {
            return [
                'id' => $item['id'],
                'exercise' => $item['exercise_name'],
                'sets' => (int) $item['sets'],
                'reps' => $item['reps'],
                'rest_sec' => (int) $item['rest_sec'],
                'notes' => $item['notes']
            ];
        }, $items),
        'log' => $log ? [
            'status' => $log['status'],
            'fatigue_rating' => $log['fatigue_rating'] ? (int) $log['fatigue_rating'] : null,
            'notes' => $log['notes'],
            'logged_at' => $log['logged_at']
        ] : null
    ];
}

jsonResponse([
    'success' => true,
    'has_plan' => true,
    'plan' => [
        'id' => $plan['id'],
        'week_start' => $plan['week_start'],
        'days' => $planDays,
        'principles' => $plan['principles'] ? json_decode($plan['principles'], true) : [],
        'notes' => $plan['notes'] ? json_decode($plan['notes'], true) : [],
        'is_adjusted' => (bool) $plan['is_adjusted'],
        'created_at' => $plan['created_at']
    ]
]);
