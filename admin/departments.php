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
            $name = sanitizeInput($_POST['name']);
            $code = sanitizeInput($_POST['code']);
            $description = sanitizeInput($_POST['description']);
            $head_id = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;

            $validator = new Validator();
            $validator->required('name', $name)->maxLength('name', $name, 100)
                      ->required('code', $code)->maxLength('code', $code, 10)
                      ->maxLength('description', $description, 500);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Check if code already exists
                $stmt = $db->prepare("SELECT id FROM departments WHERE code = :code");
                $stmt->execute(['code' => $code]);
                if ($stmt->fetch()) {
                    $message = 'Department code already exists.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare("INSERT INTO departments (name, code, description, head_id) VALUES (:name, :code, :desc, :head)");
                    if ($stmt->execute(['name' => $name, 'code' => $code, 'desc' => $description, 'head' => $head_id])) {
                        $message = 'Department added successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error adding department.';
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitizeInput($_POST['name']);
            $code = sanitizeInput($_POST['code']);
            $description = sanitizeInput($_POST['description']);
            $head_id = !empty($_POST['head_id']) ? (int)$_POST['head_id'] : null;

            $validator = new Validator();
            $validator->required('name', $name)->maxLength('name', $name, 100)
                      ->required('code', $code)->maxLength('code', $code, 10)
                      ->maxLength('description', $description, 500);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Check if code conflicts with another department
                $stmt = $db->prepare("SELECT id FROM departments WHERE code = :code AND id != :id");
                $stmt->execute(['code' => $code, 'id' => $id]);
                if ($stmt->fetch()) {
                    $message = 'Department code already used by another department.';
                    $message_type = 'danger';
                } else {
                    $stmt = $db->prepare("UPDATE departments SET name = :name, code = :code, description = :desc, head_id = :head WHERE id = :id");
                    if ($stmt->execute(['name' => $name, 'code' => $code, 'desc' => $description, 'head' => $head_id, 'id' => $id])) {
                        $message = 'Department updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating department.';
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            // Check if there are any students, faculty, or courses in this department
            $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE department_id = :id");
            $stmt->execute(['id' => $id]);
            $studentCount = $stmt->fetchColumn();
            $stmt = $db->prepare("SELECT COUNT(*) FROM faculty WHERE department_id = :id");
            $stmt->execute(['id' => $id]);
            $facultyCount = $stmt->fetchColumn();
            $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE department_id = :id");
            $stmt->execute(['id' => $id]);
            $courseCount = $stmt->fetchColumn();

            if ($studentCount > 0 || $facultyCount > 0 || $courseCount > 0) {
                $message = "Cannot delete department because it has associated records. (Students: $studentCount, Faculty: $facultyCount, Courses: $courseCount)";
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("DELETE FROM departments WHERE id = :id");
                if ($stmt->execute(['id' => $id])) {
                    $message = 'Department deleted successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error deleting department.';
                    $message_type = 'danger';
                }
            }
        }
    }
}

// Fetch all departments with head names
$departments = $db->query("SELECT d.*, CONCAT(f.first_name, ' ', f.last_name) as head_name 
                           FROM departments d 
                           LEFT JOIN faculty f ON d.head_id = f.id 
                           ORDER BY d.name")->fetchAll();

// Fetch faculty for dropdown (head selection)
$faculty_members = $db->query("SELECT id, first_name, last_name FROM faculty WHERE status = 'Active' ORDER BY first_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Admin</title>
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
            <a href="departments.php" class="nav-link active"><i class="fas fa-building"></i> Departments</a>
            <a href="courses.php" class="nav-link"><i class="fas fa-book"></i> Courses</a>
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
            <h3>Department Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal"><i class="fas fa-plus"></i> Add Department</button>
            </div>
        </header>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Head</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $d): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($d['code']) ?></strong></td>
                                        <td><?= htmlspecialchars($d['name']) ?></td>
                                        <td><?= htmlspecialchars($d['description']) ?></td>
                                        <td><?= htmlspecialchars($d['head_name'] ?? 'Not assigned') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-dept" 
                                                    data-id="<?= $d['id'] ?>"
                                                    data-name="<?= htmlspecialchars($d['name']) ?>"
                                                    data-code="<?= htmlspecialchars($d['code']) ?>"
                                                    data-description="<?= htmlspecialchars($d['description']) ?>"
                                                    data-head_id="<?= $d['head_id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-dept" data-id="<?= $d['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($departments) === 0): ?>
                                    <tr><td colspan="5" class="text-center">No departments found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-building"></i> Add Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="name" class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="code" class="form-label">Department Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" placeholder="e.g., CS, MATH" required>
                            <small class="text-muted">Short unique code (max 10 characters).</small>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="head_id" class="form-label">Department Head</label>
                            <select class="form-select" name="head_id">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculty_members as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-name" class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit-name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-code" class="form-label">Department Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" id="edit-code" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit-description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit-head_id" class="form-label">Department Head</label>
                            <select class="form-select" name="head_id" id="edit-head_id">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculty_members as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Department Modal -->
    <div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p class="text-danger">Are you sure you want to delete this department?</p>
                        <p><strong>This action cannot be undone.</strong></p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Departments with associated students, faculty, or courses cannot be deleted.
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
            $('.edit-dept').on('click', function() {
                const data = $(this).data();
                $('#edit-id').val(data.id);
                $('#edit-name').val(data.name);
                $('#edit-code').val(data.code);
                $('#edit-description').val(data.description);
                $('#edit-head_id').val(data.head_id);
                $('#editDepartmentModal').modal('show');
            });

            // Delete button click
            $('.delete-dept').on('click', function() {
                const id = $(this).data('id');
                $('#delete-id').val(id);
                $('#deleteDepartmentModal').modal('show');
            });
        });
    </script>
</body>
</html>