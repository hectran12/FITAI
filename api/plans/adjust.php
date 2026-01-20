<?php
/**
 * Adjust Next Week Plan Endpoint
 * 
 * POST /api/plans/adjust
 * 
 * Calls Python AI service to generate adjusted plan based on workout logs
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

$currentWeekStart = getWeekStart();
$nextWeekStart = date('Y-m-d', strtotime($currentWeekStart . ' +7 days'));

// Get current week's plan with logs
$currentPlan = Database::fetchOne(
    'SELECT id, week_start, principles, notes
     FROM plans WHERE user_id = ? AND week_start = ?',
    [$userId, $currentWeekStart]
);

if (!$currentPlan) {
    errorResponse('No current week plan found. Generate a plan first.', 400);
}

// Get plan days with logs
$days = Database::fetchAll(
    'SELECT pd.id, pd.date, pd.title, pd.estimated_minutes,
            wl.status, wl.fatigue_rating, wl.notes as log_notes
     FROM plan_days pd
     LEFT JOIN workout_logs wl ON wl.plan_day_id = pd.id AND wl.user_id = ?
     WHERE pd.plan_id = ?
     ORDER BY pd.day_order ASC',
    [$userId, $currentPlan['id']]
);

// Build logs summary
$completedDays = 0;
$totalFatigue = 0;
$fatigueCount = 0;
$logsSummary = [];

foreach ($days as $day) {
    if ($day['status'] === 'done') {
        $completedDays++;
    }
    if ($day['fatigue_rating']) {
        $totalFatigue += $day['fatigue_rating'];
        $fatigueCount++;
    }
    $logsSummary[] = [
        'date' => $day['date'],
        'title' => $day['title'],
        'status' => $day['status'] ?? 'pending',
        'fatigue_rating' => $day['fatigue_rating'],
        'notes' => $day['log_notes']
    ];
}

$avgFatigue = $fatigueCount > 0 ? round($totalFatigue / $fatigueCount, 1) : null;

// Get user profile
$profile = Database::fetchOne(
    'SELECT goal, level, days_per_week, session_minutes, equipment, constraints_text, availability
     FROM user_profiles WHERE user_id = ?',
    [$userId]
);

// Get available exercises
$exercises = Database::fetchAll(
    'SELECT name, muscle_group, equipment, difficulty, description
     FROM exercises
     WHERE equipment IN ("none", ?)
     ORDER BY muscle_group, difficulty',
    [$profile['equipment']]
);

// Prepare request for AI service
$aiRequest = [
    'user_id' => $userId,
    'week_start' => $nextWeekStart,
    'profile' => [
        'goal' => $profile['goal'],
        'level' => $profile['level'],
        'days_per_week' => (int) $profile['days_per_week'],
        'session_minutes' => (int) $profile['session_minutes'],
        'equipment' => $profile['equipment'],
        'constraints' => $profile['constraints_text'],
        'availability' => $profile['availability'] ? json_decode($profile['availability'], true) : null
    ],
    'exercises' => $exercises,
    'previous_plan' => [
        'week_start' => $currentPlan['week_start'],
        'principles' => json_decode($currentPlan['principles'], true),
        'days' => $logsSummary
    ],
    'logs_summary' => [
        'completed_days' => $completedDays,
        'total_days' => count($days),
        'completion_rate' => count($days) > 0 ? round($completedDays / count($days) * 100) : 0,
        'average_fatigue' => $avgFatigue
    ]
];

// Call AI service
$aiUrl = AI_SERVICE_URL . '/adjust_plan';
$ch = curl_init($aiUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($aiRequest),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => AI_SERVICE_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('AI service curl error: ' . $curlError);
    errorResponse('AI service unavailable. Please try again later.', 503);
}

if ($httpCode !== 200) {
    error_log('AI service returned HTTP ' . $httpCode . ': ' . $response);
    errorResponse('AI service error. Please try again later.', 502);
}

$planData = json_decode($response, true);

if (!$planData || !isset($planData['days'])) {
    error_log('Invalid AI response: ' . $response);
    errorResponse('Invalid response from AI service', 500);
}

// Save adjusted plan to database
try {
    Database::beginTransaction();

    // Delete existing plan for next week if exists
    $existingPlan = Database::fetchOne(
        'SELECT id FROM plans WHERE user_id = ? AND week_start = ?',
        [$userId, $nextWeekStart]
    );

    if ($existingPlan) {
        Database::delete('DELETE FROM plans WHERE id = ?', [$existingPlan['id']]);
    }

    // Insert adjusted plan
    $planId = Database::insert(
        'INSERT INTO plans (user_id, week_start, principles, notes, is_adjusted) VALUES (?, ?, ?, ?, TRUE)',
        [
            $userId,
            $nextWeekStart,
            json_encode($planData['principles'] ?? []),
            json_encode($planData['notes'] ?? [])
        ]
    );

    // Insert days and items
    foreach ($planData['days'] as $dayOrder => $day) {
        $dayId = Database::insert(
            'INSERT INTO plan_days (plan_id, date, title, estimated_minutes, day_order) VALUES (?, ?, ?, ?, ?)',
            [
                $planId,
                $day['date'],
                $day['title'],
                $day['estimated_minutes'] ?? 45,
                $dayOrder
            ]
        );

        foreach ($day['sessions'] as $itemOrder => $session) {
            Database::insert(
                'INSERT INTO plan_items (plan_day_id, exercise_name, sets, reps, rest_sec, notes, order_index) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $dayId,
                    $session['exercise'],
                    $session['sets'],
                    $session['reps'],
                    $session['rest_sec'] ?? 60,
                    $session['notes'] ?? null,
                    $itemOrder
                ]
            );
        }
    }

    Database::commit();

    jsonResponse([
        'success' => true,
        'message' => 'Adjusted plan generated for next week',
        'plan' => [
            'id' => $planId,
            'week_start' => $nextWeekStart,
            'days' => $planData['days'],
            'principles' => $planData['principles'] ?? [],
            'notes' => $planData['notes'] ?? [],
            'is_adjusted' => true
        ],
        'adjustment_based_on' => [
            'completion_rate' => $aiRequest['logs_summary']['completion_rate'],
            'average_fatigue' => $avgFatigue
        ]
    ], 201);

} catch (Exception $e) {
    Database::rollback();
    error_log('Error saving adjusted plan: ' . $e->getMessage());
    errorResponse('Failed to save adjusted plan', 500);
}
