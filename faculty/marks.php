<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';
requireRole('faculty');

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Get faculty ID
$stmt = $db->prepare("SELECT id, first_name, last_name FROM faculty WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$faculty = $stmt->fetch();
if (!$faculty) {
    redirect('../auth/logout.php');
}
$faculty_id = $faculty['id'];

$message = '';
$message_type = '';

// Handle marks actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $student_id = (int)$_POST['student_id'];
            $course_id = (int)$_POST['course_id'];
            $exam_type = trim($_POST['exam_type'] ?? '');
            $allowedExamTypes = ['Midterm','Final','Quiz','Assignment'];

if (!in_array($exam_type, $allowedExamTypes)) {
    $message = 'Invalid exam type selected.';
    $message_type = 'danger';
} else {
            $marks_obtained = !empty($_POST['marks_obtained']) ? (float)$_POST['marks_obtained'] : null;
            $total_marks = !empty($_POST['total_marks']) ? (float)$_POST['total_marks'] : null;
            $grade = sanitizeInput($_POST['grade'] ?? '');
            
            // Validate
            $validator = new Validator();
            $validator->required('student_id', $student_id)->numeric('student_id', $student_id)
                      ->required('course_id', $course_id)->numeric('course_id', $course_id)
                      ->required('exam_type', $exam_type);
            if ($marks_obtained !== null) {
                $validator->numeric('marks_obtained', $marks_obtained);
            }
            if ($total_marks !== null) {
                $validator->numeric('total_marks', $total_marks);
            }
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Check if marks already exist for this student, course, exam_type
                $stmt = $db->prepare("
    SELECT id
    FROM marks
    WHERE student_id = :sid
    AND course_id = :cid
    AND exam_type = :type
");

$stmt->execute([
    'sid' => $student_id,
    'cid' => $course_id,
    'type' => $exam_type
]);

$existingMark = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existingMark) {
                    $message = 'Marks already exist for this student, course, and exam type. Use Edit to update.';
                    $message_type = 'danger';
                } else {

    try {

        $stmt = $db->prepare("
            INSERT INTO marks (
                student_id,
                course_id,
                exam_type,
                marks_obtained,
                total_marks,
                grade
            )
            VALUES (
                :sid,
                :cid,
                :type,
                :marks,
                :total,
                :grade
            )
        ");

        $stmt->execute([
            'sid'   => $student_id,
            'cid'   => $course_id,
            'type'  => $exam_type,
            'marks' => $marks_obtained,
            'total' => $total_marks,
            'grade' => $grade
        ]);

        $message = 'Marks added successfully.';
        $message_type = 'success';

    } catch(PDOException $e) {

        $message = $e->getMessage();
        $message_type = 'danger';

    }

}
}
}
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $marks_obtained = !empty($_POST['marks_obtained']) ? (float)$_POST['marks_obtained'] : null;
            $total_marks = !empty($_POST['total_marks']) ? (float)$_POST['total_marks'] : null;
            $grade = sanitizeInput($_POST['grade'] ?? '');
            
            $validator = new Validator();
            if ($marks_obtained !== null) {
                $validator->numeric('marks_obtained', $marks_obtained);
            }
            if ($total_marks !== null) {
                $validator->numeric('total_marks', $total_marks);
            }
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("UPDATE marks SET marks_obtained = :marks, total_marks = :total, grade = :grade WHERE id = :id");
                if ($stmt->execute(['marks' => $marks_obtained, 'total' => $total_marks, 'grade' => $grade, 'id' => $id])) {
                    $message = 'Marks updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating marks.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM marks WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'Marks deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting marks.';
                $message_type = 'danger';
            }
        }
    }
}

// Get faculty's courses
$stmt = $db->prepare("
SELECT id, name
FROM courses
WHERE faculty_id = ?
ORDER BY name
");

$stmt->execute([$faculty_id]);

$courses = $stmt->fetchAll();

// Get all marks for faculty's courses with student details
$marksList = $db->query("SELECT m.*, 
                         s.student_id as student_id_number,
                         CONCAT(s.first_name, ' ', s.last_name) as student_name,
                         c.name as course_name,
                         c.course_code
                         FROM marks m
                         JOIN students s ON m.student_id = s.id
                         JOIN courses c ON m.course_id = c.id
                         WHERE c.faculty_id = $faculty_id
                         ORDER BY c.name, s.last_name, m.exam_type")->fetchAll();

// Statistics
$totalStudents = $db->query("SELECT COUNT(DISTINCT ce.student_id) 
                             FROM course_enrollments ce 
                             JOIN courses c ON ce.course_id = c.id 
                             WHERE c.faculty_id = $faculty_id")->fetchColumn();

$totalCourses = count($courses);

$totalMarksEntries = count($marksList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Management - Faculty</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
    <!-- Sidebar (same as attendance) -->
    <div class="sidebar">
        <div class="brand"><i class="fas fa-chalkboard-teacher"></i> Smart Campus</div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="attendance.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="marks.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Marks</a>
            <a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> Assignments</a>
            <a href="students.php" class="nav-link"><i class="fas fa-users"></i> Students</a>
            <a href="announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> Profile</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Marks Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#marksModal"><i class="fas fa-plus"></i> Add Marks</button>
            </div>
        </header>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Total Students</h5>
                            <h2><?= $totalStudents ?></h2>
                            <small>In your courses</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Subjects Handled</h5>
                            <h2><?= $totalCourses ?></h2>
                            <small>Courses assigned</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Marks Entered</h5>
                            <h2><?= $totalMarksEntries ?></h2>
                            <small>Total entries</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <input type="text" id="search-marks" class="form-control" placeholder="Search by student or course...">
                </div>
                <div class="col-md-4">
                    <select id="filter-course" class="form-select">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select id="filter-exam" class="form-select">
                        <option value="">All Exam Types</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Quiz">Quiz</option>
                        <option value="Midterm">Midterm</option>
                        <option value="Final">Final</option>
                    </select>
                </div>
            </div>

            <!-- Marks Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table me-1"></i> Marks Records
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="marks-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Course</th>
                                    <th>Exam Type</th>
                                    <th>Marks Obtained</th>
                                    <th>Total Marks</th>
                                    <th>Grade</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($marksList) > 0): ?>
                                    <?php foreach ($marksList as $row): ?>
    <tr data-course-id="<?= $row['course_id'] ?>">
                                            <td><?= htmlspecialchars($row['student_id_number']) ?></td>
                                            <td><?= htmlspecialchars($row['student_name']) ?></td>
                                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                                            <td><?= htmlspecialchars($row['exam_type']) ?></td>
                                            <td><?= $row['marks_obtained'] !== null ? $row['marks_obtained'] : '-' ?></td>
                                            <td><?= $row['total_marks'] !== null ? $row['total_marks'] : '-' ?></td>
                                            <td><?= htmlspecialchars($row['grade'] ?? '-') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning edit-marks" 
                                                        data-id="<?= $row['id'] ?>"
                                                        data-marks_obtained="<?= $row['marks_obtained'] ?>"
                                                        data-total_marks="<?= $row['total_marks'] ?>"
                                                        data-grade="<?= htmlspecialchars($row['grade'] ?? '') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-marks" data-id="<?= $row['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center">No marks records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Marks Modal -->
    <div class="modal fade" id="marksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Marks</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Student <span class="text-danger">*</span></label>
                            <select class="form-select" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php
                                $students = $db->query("SELECT s.id, s.student_id, CONCAT(s.first_name, ' ', s.last_name) as name 
                                                        FROM students s 
                                                        JOIN course_enrollments ce ON s.id = ce.student_id 
                                                        JOIN courses c ON ce.course_id = c.id 
                                                        WHERE c.faculty_id = $faculty_id 
                                                        GROUP BY s.id 
                                                        ORDER BY name")->fetchAll();
                                foreach ($students as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['student_id']) ?> - <?= htmlspecialchars($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="exam_type" class="form-label">Exam Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="exam_type" required>
                                <option value="">Select</option>
                                <option value="Assignment">Assignment</option>
                                <option value="Quiz">Quiz</option>
                                <option value="Midterm">Midterm</option>
                                <option value="Final">Final</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="marks_obtained">Marks Obtained</label>
                                <input type="number" class="form-control" name="marks_obtained" step="0.01" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="total_marks">Total Marks</label>
                                <input type="number" class="form-control" name="total_marks" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="grade">Grade</label>
                            <select class="form-select" name="grade">
                                <option value="">Select</option>
                                <option value="O">O</option>
                                <option value="A+">A+</option>
                                <option value="A">A</option>
                                <option value="B+">B+</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Marks</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Marks Modal -->
    <div class="modal fade" id="editMarksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Marks</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-marks_obtained">Marks Obtained</label>
                                <input type="number" class="form-control" name="marks_obtained" id="edit-marks_obtained" step="0.01" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-total_marks">Total Marks</label>
                                <input type="number" class="form-control" name="total_marks" id="edit-total_marks" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit-grade">Grade</label>
                            <select class="form-select" name="grade" id="edit-grade">
                                <option value="">Select</option>
                                <option value="O">O</option>
                                <option value="A+">A+</option>
                                <option value="A">A</option>
                                <option value="B+">B+</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Marks</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Marks Modal -->
    <div class="modal fade" id="deleteMarksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Marks Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this marks record? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        $(document).ready(function() {
            // Edit button - populate modal
            $('.edit-marks').on('click', function() {
                $('#edit-id').val($(this).data('id'));
                $('#edit-marks_obtained').val($(this).data('marks_obtained') || '');
                $('#edit-total_marks').val($(this).data('total_marks') || '');
                $('#edit-grade').val($(this).data('grade') || '');
                $('#editMarksModal').modal('show');
            });

            // Delete button
            $('.delete-marks').on('click', function() {
                $('#delete-id').val($(this).data('id'));
                $('#deleteMarksModal').modal('show');
            });

            // Search filter
            $('#search-marks').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#marks-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });

            // Course filter
$('#filter-course').on('change', function() {

    const selectedCourse = $(this).val();

    $('#marks-table tbody tr').each(function() {

        const rowCourseId = $(this).data('course-id');

        if (selectedCourse === '' || rowCourseId == selectedCourse) {
            $(this).show();
        } else {
            $(this).hide();
        }

    });

});

            // Exam type filter
            $('#filter-exam').on('change', function() {
                const exam = $(this).val();
                $('#marks-table tbody tr').each(function() {
                    const examType = $(this).find('td:eq(3)').text();
                    if (exam === '' || examType === exam) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        });
    </script>
</body>
</html>