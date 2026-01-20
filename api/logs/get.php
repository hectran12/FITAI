<?php
/**
 * Get Workout Logs Endpoint
 * 
 * GET /api/logs
 * 
 * Query params:
 * - week_start: YYYY-MM-DD (optional, defaults to current week)
 * - limit: number of logs to return (optional, default 50)
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

$weekStart = $_GET['week_start'] ?? null;
$limit = isset($_GET['limit']) ? min((int) $_GET['limit'], 100) : 50;

// Build query
$params = [$userId];
$whereClause = 'WHERE wl.user_id = ?';

if ($weekStart) {
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
        errorResponse('Invalid week_start format. Use YYYY-MM-DD');
    }
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
    $whereClause .= ' AND pd.date BETWEEN ? AND ?';
    $params[] = $weekStart;
    $params[] = $weekEnd;
}

$params[] = $limit;

$logs = Database::fetchAll(
    "SELECT wl.id, wl.plan_day_id, wl.status, wl.fatigue_rating, wl.notes, wl.logged_at,
            pd.date, pd.title, p.week_start
     FROM workout_logs wl
     JOIN plan_days pd ON pd.id = wl.plan_day_id
     JOIN plans p ON p.id = pd.plan_id
     $whereClause
     ORDER BY wl.logged_at DESC
     LIMIT ?",
    $params
);

// Calculate statistics
$stats = [
    'total_logged' => count($logs),
    'completed' => 0,
    'skipped' => 0,
    'partial' => 0,
    'average_fatigue' => null
];

$totalFatigue = 0;
$fatigueCount = 0;

foreach ($logs as $log) {
    if ($log['status'] === 'done')
        $stats['completed']++;
    if ($log['status'] === 'skipped')
        $stats['skipped']++;
    if ($log['status'] === 'partial')
        $stats['partial']++;
    if ($log['fatigue_rating']) {
        $totalFatigue += $log['fatigue_rating'];
        $fatigueCount++;
    }
}

if ($fatigueCount > 0) {
    $stats['average_fatigue'] = round($totalFatigue / $fatigueCount, 1);
}

jsonResponse([
    'success' => true,
    'logs' => array_map(function ($log) {
        return [
            'id' => $log['id'],
            'plan_day_id' => $log['plan_day_id'],
            'date' => $log['date'],
            'title' => $log['title'],
            'week_start' => $log['week_start'],
            'status' => $log['status'],
            'fatigue_rating' => $log['fatigue_rating'] ? (int) $log['fatigue_rating'] : null,
            'notes' => $log['notes'],
            'logged_at' => $log['logged_at']
        ];
    }, $logs),
    'stats' => $stats
]);
