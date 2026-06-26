<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
requireRole('faculty');

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Get faculty ID
$stmt = $db->prepare("SELECT id FROM faculty WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$faculty = $stmt->fetch();
if (!$faculty) {
    redirect('../auth/logout.php');
}
$faculty_id = $faculty['id'];

// Get filter values
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;

// Build query with filters
$sql = "SELECT DISTINCT s.id, s.student_id, s.first_name, s.last_name,
               u.email,
               s.semester, s.phone, s.address, s.profile_image,
               d.name as department_name
        FROM students s
        JOIN course_enrollments ce ON s.id = ce.student_id
JOIN courses c ON ce.course_id = c.id
LEFT JOIN departments d ON s.department_id = d.id
LEFT JOIN users u ON s.user_id = u.id
        WHERE c.faculty_id = :faculty_id";

$params = ['faculty_id' => $faculty_id];

if (!empty($search)) {
    $sql .= " AND (
        s.student_id LIKE :search1 OR
        s.first_name LIKE :search2 OR
        s.last_name LIKE :search3
    )";

    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
}

if ($semester > 0) {
    $sql .= " AND s.semester = :semester";
    $params['semester'] = $semester;
}

$sql .= " ORDER BY s.last_name, s.first_name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get total count
$totalStudents = count($students);

// Get semesters for filter (distinct semesters from students in faculty's courses)
$semestersSql = "SELECT DISTINCT s.semester FROM students s
                 JOIN course_enrollments ce ON s.id = ce.student_id
                 JOIN courses c ON ce.course_id = c.id
                 WHERE c.faculty_id = :faculty_id AND s.semester IS NOT NULL
                 ORDER BY s.semester";
$stmt = $db->prepare($semestersSql);
$stmt->execute(['faculty_id' => $faculty_id]);
$semesters = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Faculty</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand"><i class="fas fa-chalkboard-teacher"></i> Smart Campus</div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="attendance.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="marks.php" class="nav-link"><i class="fas fa-chart-bar"></i> Marks</a>
            <a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> Assignments</a>
            <a href="students.php" class="nav-link active"><i class="fas fa-users"></i> Students</a>
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
            <h3>My Students</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">Total: <?= $totalStudents ?></span>
            </div>
        </header>

        <div class="content">
            <!-- Statistics Card -->
            <div class="card stat-card gradient-1 mb-4">
                <div class="card-body">
                    <h5>Total Students</h5>
                    <h2><?= $totalStudents ?></h2>
                    <small>Enrolled in your courses</small>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <form method="GET" action="" class="d-flex">
                        <input type="text" name="search" class="form-control me-2" placeholder="Search by name or ID..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        <?php if (!empty($search) || $semester > 0): ?>
                            <a href="students.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-3">
                    <form method="GET" action="" id="semester-form">
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        <select name="semester" class="form-select" onchange="document.getElementById('semester-form').submit();">
                            <option value="0">All Semesters</option>
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?= $sem ?>" <?= ($semester == $sem) ? 'selected' : '' ?>>Semester <?= $sem ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-3 text-end">
                    <span class="text-muted">Showing <?= count($students) ?> students</span>
                </div>
            </div>

            <!-- Students Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-users me-1"></i> Student List
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="students-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Semester</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $s): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($s['student_id']) ?></strong></td>
                                            <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                            <td><?= htmlspecialchars($s['email']) ?></td>
                                            <td><?= htmlspecialchars($s['department_name'] ?? 'N/A') ?></td>
                                            <td><?= $s['semester'] ?? 'N/A' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info view-student" 
        data-id="<?= $s['id'] ?>"
        data-student_id="<?= htmlspecialchars($s['student_id']) ?>"
        data-name="<?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>"
        data-email="<?= htmlspecialchars($s['email']) ?>"
        data-department="<?= htmlspecialchars($s['department_name'] ?? 'N/A') ?>"
        data-semester="<?= $s['semester'] ?? 'N/A' ?>"
        data-phone="<?= htmlspecialchars($s['phone'] ?? 'N/A') ?>"
        data-address="<?= htmlspecialchars($s['address'] ?? 'N/A') ?>"
        data-image="<?= $s['profile_image'] ? '../uploads/profiles/' . $s['profile_image'] : '' ?>"
        data-bs-toggle="modal" data-bs-target="#studentModal">
    <i class="fas fa-eye"></i>
</button>

<a href="student-performance.php?id=<?= $s['id'] ?>"
   class="btn btn-sm btn-success">
    <i class="fas fa-chart-line"></i>
</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No students found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Profile Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <img id="student-profile-image" src="" alt="Profile" class="img-fluid rounded-circle mb-3" style="max-width: 150px; max-height: 150px;">
                            <h5 id="student-name"></h5>
                            <p class="text-muted" id="student-id"></p>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 150px;">Student ID:</th>
                                    <td id="modal-student-id"></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td id="modal-email"></td>
                                </tr>
                                <tr>
                                    <th>Department:</th>
                                    <td id="modal-department"></td>
                                </tr>
                                <tr>
                                    <th>Semester:</th>
                                    <td id="modal-semester"></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td id="modal-phone"></td>
                                </tr>
                                <tr>
                                    <th>Address:</th>
                                    <td id="modal-address"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        $(document).ready(function() {
            $('.view-student').on('click', function() {
                const data = $(this).data();
                // Set profile image
                if (data.image && data.image !== '') {
    $('#student-profile-image')
        .attr('src', data.image)
        .show();
} else {
    $('#student-profile-image')
        .attr('src', '../assets/images/default-user.png')
        .show();
}
                $('#student-name').text(data.name);
                $('#student-id').text(data.student_id);
                $('#modal-student-id').text(data.student_id);
                $('#modal-email').text(data.email);
                $('#modal-department').text(data.department);
                $('#modal-semester').text(data.semester);
                $('#modal-phone').text(data.phone);
                $('#modal-address').text(data.address);
            });
        });
    </script>
</body>
</html>