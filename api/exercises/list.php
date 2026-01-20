<?php
/**
 * List Exercises Endpoint
 * 
 * GET /api/exercises
 * 
 * Query params:
 * - equipment: none|home|gym (optional)
 * - muscle_group: chest|back|shoulders|etc (optional)
 * - difficulty: beginner|intermediate|advanced (optional)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

startSecureSession();
requireAuth();

// Build query with filters
$where = [];
$params = [];

if (isset($_GET['equipment'])) {
    $where[] = 'equipment = ?';
    $params[] = $_GET['equipment'];
}

if (isset($_GET['muscle_group'])) {
    $where[] = 'muscle_group = ?';
    $params[] = $_GET['muscle_group'];
}

if (isset($_GET['difficulty'])) {
    $where[] = 'difficulty = ?';
    $params[] = $_GET['difficulty'];
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$exercises = Database::fetchAll(
    "SELECT id, name, muscle_group, equipment, difficulty, description, instructions
     FROM exercises
     $whereClause
     ORDER BY muscle_group, difficulty, name",
    $params
);

// Group by muscle group
$grouped = [];
foreach ($exercises as $ex) {
    $group = $ex['muscle_group'];
    if (!isset($grouped[$group])) {
        $grouped[$group] = [];
    }
    $grouped[$group][] = [
        'id' => $ex['id'],
        'name' => $ex['name'],
        'equipment' => $ex['equipment'],
        'difficulty' => $ex['difficulty'],
        'description' => $ex['description'],
        'instructions' => $ex['instructions']
    ];
}

jsonResponse([
    'success' => true,
    'total' => count($exercises),
    'exercises' => $exercises,
    'grouped' => $grouped
]);
