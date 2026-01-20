<?php
/**
 * Admin Exercises Management API
 * 
 * GET /api/admin/exercises.php - List exercises
 * POST /api/admin/exercises.php - Add/Update exercise
 * DELETE /api/admin/exercises.php - Delete exercise
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/middleware.php';

handleCorsPreflightRequest();

startSecureSession();
requireAuth();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetExercises();
            break;
        case 'POST':
            handleSaveExercise();
            break;
        case 'DELETE':
            handleDeleteExercise();
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Admin exercises error: ' . $e->getMessage());
    errorResponse('Lỗi xử lý yêu cầu', 500);
}

/**
 * Get exercises list
 */
function handleGetExercises()
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $muscleGroup = $_GET['muscle_group'] ?? '';

    $whereClause = '';
    $params = [];

    if ($muscleGroup) {
        $whereClause = 'WHERE muscle_group = ?';
        $params = [$muscleGroup];
    }

    $sql = "SELECT * FROM exercises $whereClause ORDER BY muscle_group, name LIMIT ? OFFSET ?";
    $queryParams = array_merge($params, [$limit, $offset]);
    $exercises = Database::fetchAll($sql, $queryParams);

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM exercises $whereClause";
    $total = Database::fetchOne($countSql, $params);

    // Get muscle groups for filter
    $muscleGroups = Database::fetchAll(
        'SELECT DISTINCT muscle_group FROM exercises ORDER BY muscle_group'
    );

    jsonResponse([
        'success' => true,
        'exercises' => $exercises,
        'muscle_groups' => array_column($muscleGroups, 'muscle_group'),
        'total' => (int) $total['total'],
        'page' => $page,
        'total_pages' => ceil($total['total'] / $limit)
    ]);
}

/**
 * Add or update exercise
 */
function handleSaveExercise()
{
    $data = json_decode(file_get_contents('php://input'), true);

    $id = (int) ($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $muscleGroup = trim($data['muscle_group'] ?? '');
    $equipment = trim($data['equipment'] ?? 'none');
    $difficulty = trim($data['difficulty'] ?? 'beginner');
    $description = trim($data['description'] ?? '');

    if (!$name || !$muscleGroup) {
        errorResponse('Tên và nhóm cơ là bắt buộc', 400);
    }

    if ($id) {
        // Update existing
        Database::update(
            'UPDATE exercises SET name = ?, muscle_group = ?, equipment = ?, difficulty = ?, description = ? WHERE id = ?',
            [$name, $muscleGroup, $equipment, $difficulty, $description, $id]
        );
        $message = 'Đã cập nhật bài tập';
    } else {
        // Insert new
        $id = Database::insert(
            'INSERT INTO exercises (name, muscle_group, equipment, difficulty, description) VALUES (?, ?, ?, ?, ?)',
            [$name, $muscleGroup, $equipment, $difficulty, $description]
        );
        $message = 'Đã thêm bài tập mới';
    }

    jsonResponse([
        'success' => true,
        'message' => $message,
        'id' => $id
    ]);
}

/**
 * Delete exercise
 */
function handleDeleteExercise()
{
    $data = json_decode(file_get_contents('php://input'), true);
    $exerciseId = (int) ($data['exercise_id'] ?? 0);

    if (!$exerciseId) {
        errorResponse('Exercise ID required', 400);
    }

    Database::delete('DELETE FROM exercises WHERE id = ?', [$exerciseId]);

    jsonResponse([
        'success' => true,
        'message' => 'Đã xóa bài tập'
    ]);
}
