<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
requireRole('student');

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Get student ID
$stmt = $db->prepare("SELECT id FROM students WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$student = $stmt->fetch();
if (!$student) {
    redirect('../auth/logout.php');
}
$student_id = $student['id'];

// Fetch enrolled courses with faculty and department
$courses = $db->query("SELECT c.*, 
                       CONCAT(f.first_name, ' ', f.last_name) as faculty_name, 
                       d.name as dept_name,
                       ce.enrollment_date,
                       ce.status as enrollment_status
                       FROM course_enrollments ce
                       JOIN courses c ON ce.course_id = c.id
                       LEFT JOIN faculty f ON c.faculty_id = f.id
                       LEFT JOIN departments d ON c.department_id = d.id
                       WHERE ce.student_id = $student_id AND ce.status = 'Enrolled'
                       ORDER BY c.semester, c.name")->fetchAll();

// Get total enrolled count
$totalEnrolled = count($courses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student</title>
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
            <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> Profile</a>
            <a href="courses.php" class="nav-link active"><i class="fas fa-book"></i> My Courses</a>
            <a href="timetable.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Timetables</a>
            <a href="attendance.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> Assignments</a>
            <a href="marks.php" class="nav-link"><i class="fas fa-chart-bar"></i> Marks</a>
            <a href="announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="feedback.php" class="nav-link"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-request.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Request</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>My Courses</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">Total: <?= $totalEnrolled ?></span>
            </div>
        </header>

        <div class="content">
            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-course" class="form-control" placeholder="Search by course name or code..." style="max-width: 300px;">
            </div>

            <!-- Course Cards or Table -->
            <div class="card">
                <div class="card-body">
                    <?php if ($totalEnrolled > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="courses-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Faculty</th>
                                        <th>Credits</th>
                                        <th>Semester</th>
                                        <th>Enrolled On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($courses as $c): ?>
                                        <tr>
                                            <td><?= $counter++ ?></td>
                                            <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                                            <td><?= htmlspecialchars($c['name']) ?></td>
                                            <td><?= htmlspecialchars($c['dept_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($c['faculty_name'] ?? 'Not assigned') ?></td>
                                            <td><?= $c['credits'] ?></td>
                                            <td><?= $c['semester'] ?></td>
                                            <td>
<?= !empty($c['enrollment_date'])
    ? date('d M Y', strtotime($c['enrollment_date']))
    : 'N/A'; ?>
</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> You are not enrolled in any courses yet.
                            <br><small>Please contact your department for enrollment.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        $(document).ready(function() {
            $('#search-course').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#courses-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });
        });
    </script>
</body>
</html>