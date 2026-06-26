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

// Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'add') {
            $student_id = sanitizeInput($_POST['student_id']);
            $first_name = sanitizeInput($_POST['first_name']);
            $last_name = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email']);
            $department_id = (int)$_POST['department_id'];
            $enrollment_year = (int)$_POST['enrollment_year'];
            $semester = (int)$_POST['semester'];
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $gender = sanitizeInput($_POST['gender'] ?? '');
           

            $validator = new Validator();
            $validator->required('student_id', $student_id)
                      ->required('first_name', $first_name)
                      ->required('last_name', $last_name)
                      ->required('email', $email)->email('email', $email)
                      ->required('department_id', $department_id)->numeric('department_id', $department_id)
                      ->required('enrollment_year', $enrollment_year)->numeric('enrollment_year', $enrollment_year)
                      ->required('semester', $semester)->numeric('semester', $semester);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Check if student_id already exists
                $stmt = $db->prepare("SELECT id FROM students WHERE student_id = :sid");
                $stmt->execute(['sid' => $student_id]);
                if ($stmt->fetch()) {
                    $message = 'Student ID already exists.';
                    $message_type = 'danger';
                } else {
                    // Create user account
                    $password = bin2hex(random_bytes(8)); // generate random password
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $db->beginTransaction();
                    try {
                        $stmt = $db->prepare("INSERT INTO users (email, password, role, is_active) VALUES (:email, :pass, 'student', 1)");
                        $stmt->execute(['email' => $email, 'pass' => $hashed]);
                        $user_id = $db->lastInsertId();

                        $stmt = $db->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, department_id, enrollment_year, semester, phone, address, gender) 
                                              VALUES (:user_id, :sid, :fn, :ln, :dept, :ey, :sem, :phone, :addr, :gender)");
                        $stmt->execute([
                            'user_id' => $user_id,
                            'sid' => $student_id,
                            'fn' => $first_name,
                            'ln' => $last_name,
                            'dept' => $department_id,
                            'ey' => $enrollment_year,
                            'sem' => $semester,
                            'phone' => $phone,
                            'addr' => $address,
                            'gender' => $gender
                        ]);
                        $db->commit();
                        $message = "Student added successfully. Temporary password: <strong>$password</strong> (send to student)";
                        $message_type = 'success';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $message = 'Error: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $student_id = sanitizeInput($_POST['student_id']);
            $first_name = sanitizeInput($_POST['first_name']);
            $last_name = sanitizeInput($_POST['last_name']);
            $email = sanitizeInput($_POST['email'] ?? '');
            $department_id = (int)($_POST['department_id'] ?? 0);
            $enrollment_year = (int)($_POST['enrollment_year'] ?? 0);
            $semester = (int)$_POST['semester'];
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $gender = sanitizeInput($_POST['gender'] ?? '');

            $validator = new Validator();
$validator->required('student_id', $student_id)
          ->required('first_name', $first_name)
          ->required('last_name', $last_name)
          ->required('semester', $semester)
          ->numeric('semester', $semester);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                $db->beginTransaction();
                try {
                    // Update user email
                    // Update email in users table
$stmt = $db->prepare("
SELECT user_id
FROM students
WHERE id = :id
");

$stmt->execute([
    'id' => $id
]);

$user = $stmt->fetch();

$stmt = $db->prepare("
UPDATE users
SET email = :email
WHERE id = :uid
");

$stmt->execute([
    'email' => $email,
    'uid'   => $user['user_id']
]);

// Update student
$stmt = $db->prepare("
UPDATE students
SET student_id   = :sid,
    first_name   = :fn,
    last_name    = :ln,
    department_id= :dept,
    semester     = :sem,
    phone        = :phone,
    address      = :addr,
    gender       = :gender
WHERE id = :id
");

$stmt->execute([
    'sid'   => $student_id,
    'fn'    => $first_name,
    'ln'    => $last_name,
    'dept'  => $department_id,
    'sem'   => $semester,
    'phone' => $phone,
    'addr'  => $address,
    'gender'=> $gender,
    'id'    => $id
]);
                    $db->commit();
                    $message = 'Student updated successfully.';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];

$db->beginTransaction();

try {

    $stmt = $db->prepare("
    SELECT user_id
    FROM students
    WHERE id = :id
    ");

    $stmt->execute([
        'id' => $id
    ]);

    $user = $stmt->fetch();

    if ($user) {

        $stmt = $db->prepare("
        DELETE FROM students
        WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $stmt = $db->prepare("
        DELETE FROM users
        WHERE id = :uid
        ");

        $stmt->execute([
            'uid' => $user['user_id']
        ]);
    }

    $db->commit();

    $message = 'Student deleted successfully.';
    $message_type = 'success';

} catch (Exception $e) {

    $db->rollBack();

    $message = 'Error: ' . $e->getMessage();
    $message_type = 'danger';
}
                
        }
    }
}

// Fetch students with department names
$students = $db->query("
    SELECT s.*, u.email, d.name as department_name
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY s.created_at DESC
")->fetchAll();

$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
    <!-- Sidebar (same as admin dashboard) -->
    <div class="sidebar">
        <div class="brand"><i class="fas fa-graduation-cap"></i> Smart Campus</div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students.php" class="nav-link active"><i class="fas fa-users"></i> Students</a>
            <a href="faculty.php" class="nav-link"><i class="fas fa-chalkboard-teacher"></i> Faculty</a>
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
            <h3>Student Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="fas fa-plus"></i> Add Student</button>
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
                <input type="text" id="search-student" class="form-control" placeholder="Search students by name or ID..." style="max-width: 300px;">
                <div id="student-results"></div>
            </div>

            <!-- Students Table -->
            <div class="card">
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Semester</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['student_id']) ?></td>
                                    <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                    <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($s['department_name'] ?? '') ?></td>
                                    <td><?= $s['semester'] ?></td>
                                    <td>
                                        <button
class="btn btn-sm btn-warning edit-student"

data-id="<?= $s['id'] ?>"
data-studentid="<?= $s['student_id'] ?>"
data-firstname="<?= htmlspecialchars($s['first_name']) ?>"
data-lastname="<?= htmlspecialchars($s['last_name']) ?>"
data-email="<?= htmlspecialchars($s['email']) ?>"
data-department="<?= $s['department_id'] ?>"
data-semester="<?= $s['semester'] ?>"
data-phone="<?= htmlspecialchars($s['phone']) ?>"
data-gender="<?= htmlspecialchars($s['gender']) ?>"
data-address="<?= htmlspecialchars($s['address']) ?>"

>
<i class="fas fa-edit"></i>
</button>
                                        <form method="POST" style="display:inline;">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= $s['id'] ?>">

    <button type="submit"
            class="btn btn-sm btn-danger"
            onclick="return confirm('Delete this student?')">
        <i class="fas fa-trash"></i>
    </button>
</form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Student</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" class="form-control" name="student_id" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label>Department</label>
                            <select class="form-select" name="department_id" required>
                                <option value="">Select</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Enrollment Year</label>
                                <input type="number" class="form-control" name="enrollment_year" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Semester</label>
                                <select class="form-select" name="semester" required>
                                    <?php for ($i=1; $i<=8; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="mb-3">
                            <label>Address</label>
                            <textarea class="form-control" name="address"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit/Delete modals similar (omitted for brevity) -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/ajax.js"></script>

    <script>
document.querySelectorAll('.edit-student').forEach(btn => {

    btn.addEventListener('click', function() {

        document.getElementById('edit_id').value =
            this.dataset.id;

        document.getElementById('edit_student_id').value =
            this.dataset.studentid;

        document.getElementById('edit_first_name').value =
            this.dataset.firstname;

        document.getElementById('edit_last_name').value =
            this.dataset.lastname;

        document.getElementById('edit_email').value =
            this.dataset.email;

        document.getElementById('edit_department').value =
            this.dataset.department;

        document.getElementById('edit_semester').value =
            this.dataset.semester;

        document.getElementById('edit_phone').value =
            this.dataset.phone;

        document.getElementById('edit_gender').value =
            this.dataset.gender;

        document.getElementById('edit_address').value =
            this.dataset.address;

        new bootstrap.Modal(
            document.getElementById('editStudentModal')
        ).show();

    });

});
</script>

<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

        <form method="POST">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit"></i> Edit Student
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= generateCSRFToken() ?>">

                <input type="hidden"
                       name="action"
                       value="edit">

                <input type="hidden"
                       name="id"
                       id="edit_id">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Student ID</label>
                        <input type="text"
                               class="form-control"
                               name="student_id"
                               id="edit_student_id">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Email</label>
                        <input type="email"
                               class="form-control"
                               name="email"
                               id="edit_email">
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>First Name</label>
                        <input type="text"
                               class="form-control"
                               name="first_name"
                               id="edit_first_name">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Last Name</label>
                        <input type="text"
                               class="form-control"
                               name="last_name"
                               id="edit_last_name">
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Department</label>

                        <select class="form-select"
                                name="department_id"
                                id="edit_department">

                            <?php foreach($departments as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Semester</label>

                        <select class="form-select"
                                name="semester"
                                id="edit_semester">

                            <?php for($i=1;$i<=8;$i++): ?>
                                <option value="<?= $i ?>">
                                    Semester <?= $i ?>
                                </option>
                            <?php endfor; ?>

                        </select>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Phone</label>
                        <input type="text"
                               class="form-control"
                               name="phone"
                               id="edit_phone">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Gender</label>

                        <select class="form-select"
                                name="gender"
                                id="edit_gender">

                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>

                        </select>
                    </div>

                </div>

                <div class="mb-3">
                    <label>Address</label>

                    <textarea class="form-control"
                              name="address"
                              id="edit_address"
                              rows="3"></textarea>
                </div>

            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="submit"
                        class="btn btn-warning">
                    Update Student
                </button>

            </div>

                </form>

        </div> <!-- modal-content -->
    </div> <!-- modal-dialog -->
</div> <!-- modal -->


<script>
document.getElementById('search-student').addEventListener('keyup', function() {

    let value = this.value.toLowerCase();

    document.querySelectorAll('table tbody tr').forEach(function(row) {

        let text = row.innerText.toLowerCase();

        if(text.includes(value)){
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }

    });

});
</script>

</body>
</html>