<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$db = Database::getInstance()->getConnection();
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';

$stmt = $db->prepare("SELECT s.*, d.name as department_name 
                       FROM students s 
                       LEFT JOIN departments d ON s.department_id = d.id 
                       WHERE s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_id LIKE :search
                       LIMIT 20");
$stmt->execute(['search' => $search]);
$students = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($students);
?>