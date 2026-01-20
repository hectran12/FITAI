<?php
/**
 * Generate Workout Plan Endpoint
 * 
 * POST /api/plans/generate
 * 
 * Calls Python AI service to generate a new 7-day plan
 * If plan exists for current week, returns cached plan unless force=true
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

$weekStart = getWeekStart();
$force = isset($input['force']) && $input['force'] === true;

// Check if plan already exists for this week
if (!$force) {
    $existingPlan = Database::fetchOne(
        'SELECT id FROM plans WHERE user_id = ? AND week_start = ?',
        [$userId, $weekStart]
    );

    if ($existingPlan) {
        // Return cached plan
        header('Location: get.php?week_start=' . $weekStart);
        exit;
    }
}

// Get user profile
$profile = Database::fetchOne(
    'SELECT goal, level, days_per_week, session_minutes, equipment, constraints_text, availability
     FROM user_profiles WHERE user_id = ?',
    [$userId]
);

if (!$profile || !$profile['goal'] || !$profile['level']) {
    errorResponse('Please complete your profile first', 400);
}

// Get available exercises from database
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
    'week_start' => $weekStart,
    'profile' => [
        'goal' => $profile['goal'],
        'level' => $profile['level'],
        'days_per_week' => (int) $profile['days_per_week'],
        'session_minutes' => (int) $profile['session_minutes'],
        'equipment' => $profile['equipment'],
        'constraints' => $profile['constraints_text'],
        'availability' => $profile['availability'] ? json_decode($profile['availability'], true) : null
    ],
    'exercises' => $exercises
];

// Call AI service
$aiUrl = AI_SERVICE_URL . '/generate_plan';
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

// Save plan to database
try {
    Database::beginTransaction();

    // Delete existing plan for this week if force regenerate
    if ($force) {
        $existingPlan = Database::fetchOne(
            'SELECT id FROM plans WHERE user_id = ? AND week_start = ?',
            [$userId, $weekStart]
        );

        if ($existingPlan) {
            Database::delete('DELETE FROM plans WHERE id = ?', [$existingPlan['id']]);
        }
    }

    // Insert plan
    $planId = Database::insert(
        'INSERT INTO plans (user_id, week_start, principles, notes) VALUES (?, ?, ?, ?)',
        [
            $userId,
            $weekStart,
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

    // Return the saved plan
    jsonResponse([
        'success' => true,
        'message' => 'Plan generated successfully',
        'plan' => [
            'id' => $planId,
            'week_start' => $weekStart,
            'days' => $planData['days'],
            'principles' => $planData['principles'] ?? [],
            'notes' => $planData['notes'] ?? [],
            'is_adjusted' => false
        ]
    ], 201);

} catch (Exception $e) {
    Database::rollback();
    error_log('Error saving plan: ' . $e->getMessage());
    errorResponse('Failed to save plan', 500);
}
