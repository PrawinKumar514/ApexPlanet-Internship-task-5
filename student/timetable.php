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

// Get timetable entries for courses the student is enrolled in
$timetable = $db->query("SELECT t.*,
       t.day_of_week AS day,
       t.room AS room_no,
       c.name as course_name,
       c.course_code,
       CONCAT(f.first_name, ' ', f.last_name) as faculty_name
                         FROM timetables t
JOIN course_enrollments ce ON t.course_id = ce.course_id
JOIN courses c ON t.course_id = c.id
LEFT JOIN faculty f ON t.faculty_id = f.id
                         WHERE ce.student_id = $student_id AND ce.status = 'Enrolled'
                         ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), t.start_time")->fetchAll();

// Group by day for display
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$timetableByDay = [];
foreach ($days as $day) {
    $timetableByDay[$day] = [];
}
foreach ($timetable as $entry) {
    $timetableByDay[$entry['day']][] = $entry;
}

// Get total entries count
$totalEntries = count($timetable);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable - Student</title>
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
            <a href="courses.php" class="nav-link"><i class="fas fa-book"></i> My Courses</a>
            <a href="timetable.php" class="nav-link active"><i class="fas fa-calendar-alt"></i> Timetables</a>
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
            <h3>My Timetable</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">Total Classes: <?= $totalEntries ?></span>
            </div>
        </header>

        <div class="content">
            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-timetable" class="form-control" placeholder="Search by course or faculty..." style="max-width: 300px;">
            </div>

            <?php if ($totalEntries > 0): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="timetable-table">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Course</th>
                                        <th>Code</th>
                                        <th>Faculty</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timetable as $entry): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($entry['day']) ?></td>
                                            <td><?= date('h:i A', strtotime($entry['start_time'])) ?></td>
                                            <td><?= date('h:i A', strtotime($entry['end_time'])) ?></td>
                                            <td><strong><?= htmlspecialchars($entry['course_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($entry['course_code']) ?></td>
                                            <td><?= htmlspecialchars($entry['faculty_name'] ?? 'Not assigned') ?></td>
                                            <td><?= htmlspecialchars($entry['room_no']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Weekly View (Card-based) -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-calendar-week"></i> Weekly Schedule
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($days as $day): ?>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card h-100 <?= count($timetableByDay[$day]) > 0 ? 'border-primary' : 'border-light' ?>">
                                        <div class="card-header bg-<?= count($timetableByDay[$day]) > 0 ? 'primary' : 'secondary' ?> text-white">
                                            <strong><?= $day ?></strong>
                                            <span class="badge bg-light text-dark float-end"><?= count($timetableByDay[$day]) ?> classes</span>
                                        </div>
                                        <div class="card-body">
                                            <?php if (count($timetableByDay[$day]) > 0): ?>
                                                <ul class="list-group list-group-flush">
                                                    <?php foreach ($timetableByDay[$day] as $entry): ?>
                                                        <li class="list-group-item">
                                                            <strong><?= htmlspecialchars($entry['course_code']) ?></strong><br>
                                                            <small><?= date('h:i A', strtotime($entry['start_time'])) ?> - <?= date('h:i A', strtotime($entry['end_time'])) ?></small><br>
                                                            <small class="text-muted">Room: <?= htmlspecialchars($entry['room_no']) ?></small>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="text-muted text-center"><i class="fas fa-hourglass"></i> No classes</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i> No timetable entries found for your enrolled courses.
                    <br><small>Please contact your department for timetable updates.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        $(document).ready(function() {
            $('#search-timetable').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#timetable-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });
        });
    </script>
</body>
</html>