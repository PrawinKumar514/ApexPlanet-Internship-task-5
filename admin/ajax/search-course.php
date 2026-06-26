<?php
/**
 * AJAX endpoint for searching courses
 * Used by admin/courses.php for live search functionality
 * Returns JSON array of matching courses
 */

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

// Only admin can access
requireRole('admin');

// Get search term
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (strlen($search) < 2) {
    // Return empty result if search is too short
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$db = Database::getInstance()->getConnection();

// Prepare search pattern
$pattern = '%' . $search . '%';

// Query: search by course_code or name
$stmt = $db->prepare("
    SELECT c.id, c.course_code, c.name, c.credits, c.semester, c.academic_year,
           d.name as department_name,
           CONCAT(f.first_name, ' ', f.last_name) as faculty_name
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN faculty f ON c.faculty_id = f.id
    WHERE c.course_code LIKE :pattern OR c.name LIKE :pattern
    ORDER BY c.course_code
    LIMIT 20
");
$stmt->execute(['pattern' => $pattern]);
$results = $stmt->fetchAll();

// Return JSON
header('Content-Type: application/json');
echo json_encode($results);
exit;
?>