<?php
/**
 * Dashboard Stats Endpoint
 * 
 * GET /api/dashboard/stats
 * 
 * Returns:
 * - Today's session
 * - Weekly completion %
 * - Current streak
 * - Next session
 * - Recent activity
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

$today = date('Y-m-d');
$weekStart = getWeekStart();

// Get current week's plan
$plan = Database::fetchOne(
    'SELECT id, week_start FROM plans WHERE user_id = ? AND week_start = ?',
    [$userId, $weekStart]
);

$todaySession = null;
$nextSession = null;
$weeklyCompletion = 0;
$completedDays = 0;
$totalDays = 0;

if ($plan) {
    // Get today's session
    $todayDay = Database::fetchOne(
        'SELECT pd.id, pd.date, pd.title, pd.estimated_minutes,
                wl.status, wl.fatigue_rating
         FROM plan_days pd
         LEFT JOIN workout_logs wl ON wl.plan_day_id = pd.id AND wl.user_id = ?
         WHERE pd.plan_id = ? AND pd.date = ?',
        [$userId, $plan['id'], $today]
    );

    if ($todayDay) {
        // Get exercises for today
        $exercises = Database::fetchAll(
            'SELECT exercise_name, sets, reps, rest_sec, notes
             FROM plan_items WHERE plan_day_id = ? ORDER BY order_index',
            [$todayDay['id']]
        );

        $todaySession = [
            'id' => $todayDay['id'],
            'date' => $todayDay['date'],
            'title' => $todayDay['title'],
            'estimated_minutes' => (int) $todayDay['estimated_minutes'],
            'status' => $todayDay['status'] ?? 'pending',
            'exercises' => $exercises
        ];
    }

    // Get next upcoming session (after today)
    $nextDay = Database::fetchOne(
        'SELECT pd.id, pd.date, pd.title, pd.estimated_minutes
         FROM plan_days pd
         LEFT JOIN workout_logs wl ON wl.plan_day_id = pd.id AND wl.user_id = ?
         WHERE pd.plan_id = ? AND pd.date > ? AND wl.id IS NULL
         ORDER BY pd.date ASC
         LIMIT 1',
        [$userId, $plan['id'], $today]
    );

    if ($nextDay) {
        $nextSession = [
            'id' => $nextDay['id'],
            'date' => $nextDay['date'],
            'title' => $nextDay['title'],
            'estimated_minutes' => (int) $nextDay['estimated_minutes']
        ];
    }

    // Calculate weekly completion
    $weekStats = Database::fetchOne(
        'SELECT 
            COUNT(pd.id) as total_days,
            SUM(CASE WHEN wl.status = "done" THEN 1 ELSE 0 END) as completed_days
         FROM plan_days pd
         LEFT JOIN workout_logs wl ON wl.plan_day_id = pd.id AND wl.user_id = ?
         WHERE pd.plan_id = ?',
        [$userId, $plan['id']]
    );

    $totalDays = (int) $weekStats['total_days'];
    $completedDays = (int) $weekStats['completed_days'];
    $weeklyCompletion = $totalDays > 0 ? round($completedDays / $totalDays * 100) : 0;
}

// Calculate streak (consecutive days with workout logged as done)
$streak = 0;
$streakLogs = Database::fetchAll(
    'SELECT DISTINCT pd.date, wl.status
     FROM workout_logs wl
     JOIN plan_days pd ON pd.id = wl.plan_day_id
     JOIN plans p ON p.id = pd.plan_id
     WHERE wl.user_id = ? AND wl.status = "done"
     ORDER BY pd.date DESC',
    [$userId]
);

if (!empty($streakLogs)) {
    $currentDate = new DateTime($today);
    $lastWorkoutDate = new DateTime($streakLogs[0]['date']);

    // Check if streak is still active (last workout was today or yesterday)
    $diff = $currentDate->diff($lastWorkoutDate)->days;

    if ($diff <= 1) {
        // Count consecutive days
        $expectedDate = $lastWorkoutDate;
        foreach ($streakLogs as $log) {
            $logDate = new DateTime($log['date']);
            $dayDiff = $expectedDate->diff($logDate)->days;

            if ($dayDiff <= 1) {
                $streak++;
                $expectedDate = $logDate;
                $expectedDate->modify('-1 day');
            } else {
                break;
            }
        }
    }
}

// Get recent activity (last 5 logged workouts)
$recentActivity = Database::fetchAll(
    'SELECT pd.date, pd.title, wl.status, wl.fatigue_rating, wl.logged_at
     FROM workout_logs wl
     JOIN plan_days pd ON pd.id = wl.plan_day_id
     WHERE wl.user_id = ?
     ORDER BY wl.logged_at DESC
     LIMIT 5',
    [$userId]
);

// Get total workouts completed all time
$totalCompleted = Database::fetchOne(
    'SELECT COUNT(*) as count FROM workout_logs WHERE user_id = ? AND status = "done"',
    [$userId]
);

jsonResponse([
    'success' => true,
    'stats' => [
        'today_session' => $todaySession,
        'next_session' => $nextSession,
        'has_plan' => $plan !== null,
        'week_start' => $weekStart,
        'weekly_completion' => $weeklyCompletion,
        'completed_this_week' => $completedDays,
        'total_this_week' => $totalDays,
        'current_streak' => $streak,
        'total_completed' => (int) $totalCompleted['count'],
        'recent_activity' => array_map(function ($a) {
            return [
                'date' => $a['date'],
                'title' => $a['title'],
                'status' => $a['status'],
                'fatigue_rating' => $a['fatigue_rating'] ? (int) $a['fatigue_rating'] : null,
                'logged_at' => $a['logged_at']
            ];
        }, $recentActivity)
    ]
]);
