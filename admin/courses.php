<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';
requireRole('admin');

$db = Database::getInstance()->getConnection();

$message = '';
$message_type = '';

// CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'add') {
            $course_code = sanitizeInput($_POST['course_code']);
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $credits = (int)$_POST['credits'];
            $department_id = (int)$_POST['department_id'];
            $faculty_id = (int)$_POST['faculty_id'];
            $semester = (int)$_POST['semester'];
            $academic_year = (int)$_POST['academic_year'];

            $validator = new Validator();
            $validator->required('course_code', $course_code)->maxLength('course_code', $course_code, 20)
                      ->required('name', $name)->maxLength('name', $name, 100)
                      ->maxLength('description', $description, 500)
                      ->required('credits', $credits)->numeric('credits', $credits)
                      ->required('department_id', $department_id)->numeric('department_id', $department_id)
                      ->required('faculty_id', $faculty_id)->numeric('faculty_id', $faculty_id)
                      ->required('semester', $semester)->numeric('semester', $semester)
                      ->required('academic_year', $academic_year)->numeric('academic_year', $academic_year);

            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Check if course_code already exists
                $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = :code");
                $stmt->execute(['code' => $course_code]);
                if ($stmt->fetch()) {
                    $message = 'Course code already exists.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare("INSERT INTO courses (course_code, name, description, credits, department_id, faculty_id, semester, academic_year) 
                                          VALUES (:code, :name, :desc, :credits, :dept, :fac, :sem, :ay)");
                    if ($stmt->execute(['code' => $course_code, 'name' => $name, 'desc' => $description, 'credits' => $credits, 
                                        'dept' => $department_id, 'fac' => $faculty_id, 'sem' => $semester, 'ay' => $academic_year])) {
                        $message = 'Course added successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error adding course.';
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $course_code = sanitizeInput($_POST['course_code']);
            $name = sanitizeInput($_POST['name']);
            $description = sanitizeInput($_POST['description']);
            $credits = (int)$_POST['credits'];
            $department_id = (int)$_POST['department_id'];
            $faculty_id = (int)$_POST['faculty_id'];
            $semester = (int)$_POST['semester'];
            $academic_year = (int)$_POST['academic_year'];

            $validator = new Validator();
            $validator->required('course_code', $course_code)->maxLength('course_code', $course_code, 20)
                      ->required('name', $name)->maxLength('name', $name, 100)
                      ->maxLength('description', $description, 500)
                      ->required('credits', $credits)->numeric('credits', $credits)
                      ->required('department_id', $department_id)->numeric('department_id', $department_id)
                      ->required('faculty_id', $faculty_id)->numeric('faculty_id', $faculty_id)
                      ->required('semester', $semester)->numeric('semester', $semester)
                      ->required('academic_year', $academic_year)->numeric('academic_year', $academic_year);

            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Check if course_code conflicts with another course
                $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = :code AND id != :id");
                $stmt->execute(['code' => $course_code, 'id' => $id]);
                if ($stmt->fetch()) {
                    $message = 'Course code already used by another course.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare("UPDATE courses SET course_code = :code, name = :name, description = :desc, credits = :credits, 
                                          department_id = :dept, faculty_id = :fac, semester = :sem, academic_year = :ay WHERE id = :id");
                    if ($stmt->execute(['code' => $course_code, 'name' => $name, 'desc' => $description, 'credits' => $credits, 
                                        'dept' => $department_id, 'fac' => $faculty_id, 'sem' => $semester, 'ay' => $academic_year, 'id' => $id])) {
                        $message = 'Course updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating course.';
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            // Check if there are enrollments or assignments linked to this course
            $stmt = $db->prepare("SELECT COUNT(*) FROM course_enrollments WHERE course_id = :id");
            $stmt->execute(['id' => $id]);
            $enrollCount = $stmt->fetchColumn();
            $stmt = $db->prepare("SELECT COUNT(*) FROM assignments WHERE course_id = :id");
            $stmt->execute(['id' => $id]);
            $assignCount = $stmt->fetchColumn();
            $stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE course_id = :id");
            $stmt->execute(['id' => $id]);
            $attendanceCount = $stmt->fetchColumn();
            $stmt = $db->prepare("SELECT COUNT(*) FROM marks WHERE course_id = :id");
            $stmt->execute(['id' => $id]);
            $marksCount = $stmt->fetchColumn();

            if ($enrollCount > 0 || $assignCount > 0 || $attendanceCount > 0 || $marksCount > 0) {
                $message = "Cannot delete course because it has associated records. (Enrollments: $enrollCount, Assignments: $assignCount, Attendance: $attendanceCount, Marks: $marksCount)";
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("DELETE FROM courses WHERE id = :id");
                if ($stmt->execute(['id' => $id])) {
                    $message = 'Course deleted successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error deleting course.';
                    $message_type = 'danger';
                }
            }
        }
    }
}

// Fetch courses with department and faculty names
$courses = $db->query("SELECT c.*, d.name as dept_name, CONCAT(f.first_name, ' ', f.last_name) as faculty_name 
                       FROM courses c 
                       LEFT JOIN departments d ON c.department_id = d.id 
                       LEFT JOIN faculty f ON c.faculty_id = f.id 
                       ORDER BY c.created_at DESC")->fetchAll();

$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$faculty_list = $db->query("SELECT id, first_name, last_name FROM faculty WHERE status = 'Active' ORDER BY first_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand"><i class="fas fa-graduation-cap"></i> Smart Campus</div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students.php" class="nav-link"><i class="fas fa-users"></i> Students</a>
            <a href="faculty.php" class="nav-link"><i class="fas fa-chalkboard-teacher"></i> Faculty</a>
            <a href="departments.php" class="nav-link"><i class="fas fa-building"></i> Departments</a>
            <a href="courses.php" class="nav-link active"><i class="fas fa-book"></i> Courses</a>
            <a href="timetable.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Timetables</a>
            <a href="announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="feedback.php" class="nav-link"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-history"></i> Activity Logs</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Course Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal"><i class="fas fa-plus"></i> Add Course</button>
            </div>
        </header>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-course" class="form-control" placeholder="Search courses by name or code..." style="max-width: 300px;">
                <div id="course-results"></div>
            </div>

            <!-- Course Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Faculty</th>
                                    <th>Credits</th>
                                    <th>Semester</th>
                                    <th>Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $c): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($c['name']) ?></td>
                                        <td><?= htmlspecialchars($c['dept_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($c['faculty_name'] ?? 'Not Assigned') ?></td>
                                        <td><?= $c['credits'] ?></td>
                                        <td><?= $c['semester'] ?></td>
                                        <td><?= $c['academic_year'] ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-course" 
                                                    data-id="<?= $c['id'] ?>"
                                                    data-course_code="<?= htmlspecialchars($c['course_code']) ?>"
                                                    data-name="<?= htmlspecialchars($c['name']) ?>"
                                                    data-description="<?= htmlspecialchars($c['description']) ?>"
                                                    data-credits="<?= $c['credits'] ?>"
                                                    data-department_id="<?= $c['department_id'] ?>"
                                                    data-faculty_id="<?= $c['faculty_id'] ?>"
                                                    data-semester="<?= $c['semester'] ?>"
                                                    data-academic_year="<?= $c['academic_year'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-course" data-id="<?= $c['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($courses) === 0): ?>
                                    <tr><td colspan="8" class="text-center">No courses found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Course</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="course_code" class="form-label">Course Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="course_code" placeholder="e.g., CS101" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Course Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" placeholder="e.g., Introduction to Programming" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="credits" class="form-label">Credits <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="credits" min="1" max="6" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                                <select class="form-select" name="semester" required>
                                    <option value="">Select</option>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?= $i ?>">Semester <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="academic_year" value="<?= date('Y') ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="faculty_id" class="form-label">Assigned Faculty <span class="text-danger">*</span></label>
                                <select class="form-select" name="faculty_id" required>
                                    <option value="">Select Faculty</option>
                                    <?php foreach ($faculty_list as $f): ?>
                                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Course</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-course_code" class="form-label">Course Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="course_code" id="edit-course_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-name" class="form-label">Course Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="edit-name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit-description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit-description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit-credits" class="form-label">Credits <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="credits" id="edit-credits" min="1" max="6" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit-semester" class="form-label">Semester <span class="text-danger">*</span></label>
                                <select class="form-select" name="semester" id="edit-semester" required>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?= $i ?>">Semester <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit-academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="academic_year" id="edit-academic_year" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" name="department_id" id="edit-department_id" required>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-faculty_id" class="form-label">Assigned Faculty <span class="text-danger">*</span></label>
                                <select class="form-select" name="faculty_id" id="edit-faculty_id" required>
                                    <?php foreach ($faculty_list as $f): ?>
                                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Course Modal -->
    <div class="modal fade" id="deleteCourseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Course</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p class="text-danger">Are you sure you want to delete this course?</p>
                        <p><strong>This action cannot be undone.</strong></p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Courses with enrollments, assignments, attendance, or marks cannot be deleted.
                        </div>
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
            // Edit button click - populate modal
            $('.edit-course').on('click', function() {
                const data = $(this).data();
                $('#edit-id').val(data.id);
                $('#edit-course_code').val(data.course_code);
                $('#edit-name').val(data.name);
                $('#edit-description').val(data.description);
                $('#edit-credits').val(data.credits);
                $('#edit-department_id').val(data.department_id);
                $('#edit-faculty_id').val(data.faculty_id);
                $('#edit-semester').val(data.semester);
                $('#edit-academic_year').val(data.academic_year);
                $('#editCourseModal').modal('show');
            });

            // Delete button click
            $('.delete-course').on('click', function() {
                const id = $(this).data('id');
                $('#delete-id').val(id);
                $('#deleteCourseModal').modal('show');
            });
        });
    </script>

    <script>
document.getElementById('search-course').addEventListener('keyup', function () {

    let value = this.value.toLowerCase();

    document.querySelectorAll('table tbody tr').forEach(function(row) {

        let text = row.innerText.toLowerCase();

        if (text.includes(value)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }

    });

});
</script>
</body>
</html>