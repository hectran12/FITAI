<?php
/**
 * Update User Profile Endpoint
 * 
 * POST /api/profile
 * 
 * Request body:
 * {
 *   "goal": "muscle_gain",
 *   "level": "intermediate",
 *   "days_per_week": 4,
 *   "session_minutes": 60,
 *   "equipment": "gym",
 *   "constraints": "back_pain",
 *   "availability": {...}
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

// Allowed values
$allowedGoals = ['fat_loss', 'muscle_gain', 'maintenance'];
$allowedLevels = ['beginner', 'intermediate', 'advanced'];
$allowedEquipment = ['none', 'home', 'gym'];

// Validate goal
if (isset($input['goal']) && !in_array($input['goal'], $allowedGoals)) {
    errorResponse('Invalid goal. Must be one of: ' . implode(', ', $allowedGoals));
}

// Validate level
if (isset($input['level']) && !in_array($input['level'], $allowedLevels)) {
    errorResponse('Invalid level. Must be one of: ' . implode(', ', $allowedLevels));
}

// Validate equipment
if (isset($input['equipment']) && !in_array($input['equipment'], $allowedEquipment)) {
    errorResponse('Invalid equipment. Must be one of: ' . implode(', ', $allowedEquipment));
}

// Validate days_per_week
if (isset($input['days_per_week'])) {
    $days = (int) $input['days_per_week'];
    if ($days < 3 || $days > 6) {
        errorResponse('days_per_week must be between 3 and 6');
    }
}

// Validate session_minutes
if (isset($input['session_minutes'])) {
    $minutes = (int) $input['session_minutes'];
    if ($minutes < 20 || $minutes > 90) {
        errorResponse('session_minutes must be between 20 and 90');
    }
}

// Build update query dynamically
$updates = [];
$params = [];

$fields = [
    'display_name' => 'display_name',
    'bio' => 'bio',
    'goal' => 'goal',
    'level' => 'level',
    'days_per_week' => 'days_per_week',
    'session_minutes' => 'session_minutes',
    'equipment' => 'equipment',
    'constraints' => 'constraints_text'
];

foreach ($fields as $inputKey => $dbColumn) {
    if (isset($input[$inputKey])) {
        $updates[] = "$dbColumn = ?";
        $params[] = $input[$inputKey];
    }
}

// Handle availability separately (JSON)
if (isset($input['availability'])) {
    $updates[] = "availability = ?";
    $params[] = json_encode($input['availability']);
}

// Handle social_links separately (JSON)
if (isset($input['social_links'])) {
    $updates[] = "social_links = ?";
    $params[] = json_encode($input['social_links']);
}

if (empty($updates)) {
    errorResponse('No fields to update');
}

// Add user_id to params
$params[] = $userId;

// Execute update
$sql = "UPDATE user_profiles SET " . implode(', ', $updates) . " WHERE user_id = ?";
$affected = Database::update($sql, $params);

if ($affected === 0) {
    // Check if profile exists
    $profile = Database::fetchOne('SELECT id FROM user_profiles WHERE user_id = ?', [$userId]);
    if (!$profile) {
        errorResponse('Profile not found', 404);
    }
}

// Fetch updated profile
$profile = Database::fetchOne(
    'SELECT display_name, bio, social_links, goal, level, days_per_week, session_minutes, equipment, constraints_text, availability
     FROM user_profiles WHERE user_id = ?',
    [$userId]
);

jsonResponse([
    'success' => true,
    'message' => 'Đã cập nhật hồ sơ thành công',
    'profile' => [
        'display_name' => $profile['display_name'],
        'bio' => $profile['bio'],
        'social_links' => $profile['social_links'] ? json_decode($profile['social_links'], true) : null,
        'goal' => $profile['goal'],
        'level' => $profile['level'],
        'days_per_week' => (int) $profile['days_per_week'],
        'session_minutes' => (int) $profile['session_minutes'],
        'equipment' => $profile['equipment'],
        'constraints' => $profile['constraints_text'],
        'availability' => $profile['availability'] ? json_decode($profile['availability'], true) : null
    ]
]);
