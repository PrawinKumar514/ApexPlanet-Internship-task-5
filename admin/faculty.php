<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';
requireRole('admin');

$db = Database::getInstance()->getConnection();

// Handle Add/Edit/Delete
$message = '';
$message_type = '';

// Add Faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'add') {
            $faculty_id = sanitizeInput($_POST['faculty_id']);
            $first_name = sanitizeInput($_POST['first_name']);
            $last_name = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email']);
            $department_id = (int)$_POST['department_id'];
            $designation = sanitizeInput($_POST['designation']);
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $gender = sanitizeInput($_POST['gender'] ?? '');
            $qualification = sanitizeInput($_POST['qualification'] ?? '');
            $joining_date = sanitizeInput($_POST['joining_date'] ?? '');

            $validator = new Validator();
            $validator->required('faculty_id', $faculty_id)
                      ->maxLength('faculty_id', $faculty_id, 20)
                      ->required('first_name', $first_name)
                      ->maxLength('first_name', $first_name, 50)
                      ->required('last_name', $last_name)
                      ->maxLength('last_name', $last_name, 50)
                      ->required('email', $email)
                      ->email('email', $email)
                      ->required('department_id', $department_id)
                      ->numeric('department_id', $department_id)
                      ->required('designation', $designation)
                      ->maxLength('designation', $designation, 50)
                      ->maxLength('phone', $phone, 15)
                      ->maxLength('qualification', $qualification, 100);

            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Check if faculty_id already exists
                $stmt = $db->prepare("SELECT id FROM faculty WHERE faculty_id = :fid");
                $stmt->execute(['fid' => $faculty_id]);
                if ($stmt->fetch()) {
                    $message = 'Faculty ID already exists.';
                    $message_type = 'danger';
                } else {
                    // Check if email already exists
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                    $stmt->execute(['email' => $email]);
                    if ($stmt->fetch()) {
                        $message = 'Email already registered.';
                        $message_type = 'danger';
                    } else {
                        // Create user account
                        $password = bin2hex(random_bytes(6)); // 12-character random password
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $db->beginTransaction();
                        try {
                            $stmt = $db->prepare("INSERT INTO users (email, password, role, is_active) VALUES (:email, :pass, 'faculty', 1)");
                            $stmt->execute(['email' => $email, 'pass' => $hashed]);
                            $user_id = $db->lastInsertId();

                            $stmt = $db->prepare("INSERT INTO faculty (user_id, faculty_id, first_name, last_name, department_id, designation, phone, address, gender, qualification, joining_date) 
                                                  VALUES (:user_id, :fid, :fn, :ln, :dept, :des, :phone, :addr, :gender, :qual, :join_date)");
                            $stmt->execute([
                                'user_id' => $user_id,
                                'fid' => $faculty_id,
                                'fn' => $first_name,
                                'ln' => $last_name,
                                'dept' => $department_id,
                                'des' => $designation,
                                'phone' => $phone,
                                'addr' => $address,
                                'gender' => $gender,
                                'qual' => $qualification,
                                'join_date' => $joining_date ?: date('Y-m-d')
                            ]);
                            $db->commit();
                            $message = "Faculty added successfully. Temporary password: <strong>$password</strong> (send to faculty)";
                            $message_type = 'success';
                        } catch (Exception $e) {
                            $db->rollBack();
                            $message = 'Error: ' . $e->getMessage();
                            $message_type = 'danger';
                        }
                    }
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $faculty_id = sanitizeInput($_POST['faculty_id']);
            $first_name = sanitizeInput($_POST['first_name']);
            $last_name = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email']);
            $department_id = (int)$_POST['department_id'];
            $designation = sanitizeInput($_POST['designation']);
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $gender = sanitizeInput($_POST['gender'] ?? '');
            $qualification = sanitizeInput($_POST['qualification'] ?? '');
            $joining_date = sanitizeInput($_POST['joining_date'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'Active');

            $validator = new Validator();
            $validator->required('faculty_id', $faculty_id)
                      ->maxLength('faculty_id', $faculty_id, 20)
                      ->required('first_name', $first_name)
                      ->maxLength('first_name', $first_name, 50)
                      ->required('last_name', $last_name)
                      ->maxLength('last_name', $last_name, 50)
                      ->required('email', $email)
                      ->email('email', $email)
                      ->required('department_id', $department_id)
                      ->numeric('department_id', $department_id)
                      ->required('designation', $designation)
                      ->maxLength('designation', $designation, 50)
                      ->maxLength('phone', $phone, 15)
                      ->maxLength('qualification', $qualification, 100);

            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                $db->beginTransaction();
                try {
                    // Update user email
                    $stmt = $db->prepare("SELECT user_id FROM faculty WHERE id = :id");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

$stmt = $db->prepare("UPDATE users SET email = :email WHERE id = :uid");
$stmt->execute([
    'email' => $email,
    'uid' => $user['user_id']
]);
                    // Update faculty
                    $stmt = $db->prepare("UPDATE faculty SET faculty_id = :fid, first_name = :fn, last_name = :ln, department_id = :dept,
                                          designation = :des, phone = :phone, address = :addr, gender = :gender,
                                          qualification = :qual, joining_date = :join_date, status = :status WHERE id = :id");
                    $stmt->execute([
                        'fid' => $faculty_id,
                        'fn' => $first_name,
                        'ln' => $last_name,
                        'dept' => $department_id,
                        'des' => $designation,
                        'phone' => $phone,
                        'addr' => $address,
                        'gender' => $gender,
                        'qual' => $qualification,
                        'join_date' => $joining_date ?: date('Y-m-d'),
                        'status' => $status,
                        'id' => $id
                    ]);
                    $db->commit();
                    $message = 'Faculty updated successfully.';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            // Soft delete or hard delete? We'll hard delete but first ensure no dependencies.
            // For simplicity, we'll delete associated user as well.
            $db->beginTransaction();
            try {
                // Get user_id
                $stmt = $db->prepare("SELECT user_id FROM faculty WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $user = $stmt->fetch();
                if ($user) {
                    $stmt = $db->prepare("DELETE FROM faculty WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $stmt = $db->prepare("DELETE FROM users WHERE id = :user_id");
                    $stmt->execute(['user_id' => $user['user_id']]);
                    $db->commit();
                    $message = 'Faculty deleted successfully.';
                    $message_type = 'success';
                } else {
                    throw new Exception('Faculty not found.');
                }
            } catch (Exception $e) {
                $db->rollBack();
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Fetch faculty with department names
$faculty_list = $db->query("
    SELECT f.*, u.email, d.name AS department_name
    FROM faculty f
    LEFT JOIN users u ON f.user_id = u.id
    LEFT JOIN departments d ON f.department_id = d.id
    ORDER BY f.created_at DESC
")->fetchAll();

$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Management - Admin</title>
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
            <a href="faculty.php" class="nav-link active"><i class="fas fa-chalkboard-teacher"></i> Faculty</a>
            <a href="departments.php" class="nav-link"><i class="fas fa-building"></i> Departments</a>
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
            <h3>Faculty Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFacultyModal"><i class="fas fa-plus"></i> Add Faculty</button>
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
                <input type="text" id="search-faculty" class="form-control" placeholder="Search faculty by name or ID..." style="max-width: 300px;">
                <div id="faculty-results"></div>
            </div>

            <!-- Faculty Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Designation</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faculty_list as $f): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($f['faculty_id']) ?></td>
                                        <td><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></td>
                                        <td><?= htmlspecialchars($f['email'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($f['department_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($f['designation']) ?></td>
                                        <td><span class="badge <?= $f['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= $f['status'] ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-faculty" 
                                                    data-id="<?= $f['id'] ?>"
                                                    data-faculty_id="<?= htmlspecialchars($f['faculty_id']) ?>"
                                                    data-first_name="<?= htmlspecialchars($f['first_name']) ?>"
                                                    data-last_name="<?= htmlspecialchars($f['last_name']) ?>"
                                                    data-email="<?= htmlspecialchars($f['email']) ?>"
                                                    data-department_id="<?= $f['department_id'] ?>"
                                                    data-designation="<?= htmlspecialchars($f['designation']) ?>"
                                                    data-phone="<?= htmlspecialchars($f['phone']) ?>"
                                                    data-address="<?= htmlspecialchars($f['address']) ?>"
                                                    data-gender="<?= htmlspecialchars($f['gender']) ?>"
                                                    data-qualification="<?= htmlspecialchars($f['qualification']) ?>"
                                                    data-joining_date="<?= htmlspecialchars($f['joining_date']) ?>"
                                                    data-status="<?= $f['status'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-faculty" data-id="<?= $f['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($faculty_list) === 0): ?>
                                    <tr><td colspan="7" class="text-center">No faculty found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Faculty Modal -->
    <div class="modal fade" id="addFacultyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add Faculty</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="faculty_id" class="form-label">Faculty ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="faculty_id" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" required>
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
                                <label for="designation" class="form-label">Designation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="designation" placeholder="e.g., Professor, Assistant Professor" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" name="qualification" placeholder="e.g., Ph.D., M.Sc.">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="joining_date" class="form-label">Joining Date</label>
                                <input type="date" class="form-control" name="joining_date">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> A random password will be generated automatically. Please share it with the faculty member.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Faculty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Faculty Modal -->
    <div class="modal fade" id="editFacultyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit Faculty</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-faculty_id" class="form-label">Faculty ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="faculty_id" id="edit-faculty_id" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" id="edit-email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" id="edit-first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" id="edit-last_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" name="department_id" id="edit-department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-designation" class="form-label">Designation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="designation" id="edit-designation" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-gender" class="form-label">Gender</label>
                                <select class="form-select" name="gender" id="edit-gender">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="edit-phone">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" name="qualification" id="edit-qualification">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-joining_date" class="form-label">Joining Date</label>
                                <input type="date" class="form-control" name="joining_date" id="edit-joining_date">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit-address" class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit-address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit-status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit-status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Faculty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Faculty Modal -->
    <div class="modal fade" id="deleteFacultyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Faculty</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p class="text-danger">Are you sure you want to delete this faculty member? This action cannot be undone.</p>
                        <p><strong>This will also remove their user account and all associated data.</strong></p>
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
    <script src="../assets/js/ajax.js"></script>
    <script>
        $(document).ready(function() {
            // Edit button click - populate modal
            $('.edit-faculty').on('click', function() {
                const data = $(this).data();
                $('#edit-id').val(data.id);
                $('#edit-faculty_id').val(data.faculty_id);
                $('#edit-email').val(data.email);
                $('#edit-first_name').val(data.first_name);
                $('#edit-last_name').val(data.last_name);
                $('#edit-department_id').val(data.department_id);
                $('#edit-designation').val(data.designation);
                $('#edit-phone').val(data.phone);
                $('#edit-address').val(data.address);
                $('#edit-gender').val(data.gender);
                $('#edit-qualification').val(data.qualification);
                $('#edit-joining_date').val(data.joining_date);
                $('#edit-status').val(data.status);
                new bootstrap.Modal(document.getElementById('editFacultyModal')).show();
            });

            // Delete button click
            $('.delete-faculty').on('click', function() {
                const id = $(this).data('id');
                $('#delete-id').val(id);
                new bootstrap.Modal(document.getElementById('deleteFacultyModal')).show();
            });
        });
    </script>
</body>

<script>
document.getElementById('search-faculty').addEventListener('keyup', function () {

    let value = this.value.toLowerCase();

    document.querySelectorAll('table tbody tr').forEach(function(row) {

        let text = row.innerText.toLowerCase();

        if (text.indexOf(value) > -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }

    });

});
</script>
</html>