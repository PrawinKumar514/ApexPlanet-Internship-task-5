<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
requireRole('student');

$db = Database::getInstance()->getConnection();

// Get student ID from session
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("
SELECT id, first_name, last_name
FROM students
WHERE user_id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found.");
}
$student_id = $student['id'];

// Stats
// Total enrolled courses
$courseCount = $db->query("
SELECT COUNT(*)
FROM course_enrollments
WHERE student_id = $student_id
")->fetchColumn();

// Attendance percentage (overall)
$attendance = $db->query("SELECT 
    COUNT(CASE WHEN status IN ('Present','Late','Excused') THEN 1 END) as present,
    COUNT(*) as total
    FROM attendance WHERE student_id = $student_id")->fetch();
$attendancePercent = ($attendance['total'] > 0) ? round(($attendance['present'] / $attendance['total']) * 100, 1) : 0;

// Recent announcements (latest 3)
$announcements = $db->query("SELECT * FROM announcements WHERE target_role IN ('all','student') ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Upcoming events (next 3)
$events = $db->query("SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 3")->fetchAll();

// Feedback status counts
$feedbackCount = $db->query("
SELECT COUNT(*)
FROM feedback
WHERE student_id = $student_id
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Smart Campus</title>
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
            <a href="index.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> Profile</a>
            <a href="courses.php" class="nav-link"><i class="fas fa-book"></i> My Courses</a>
            <a href="timetable.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Timetables</a>
            <a href="attendance.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="assignments.php" class="nav-link">
    <i class="fas fa-tasks"></i> Assignments
</a>
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
            <h3>Dashboard</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
            </div>
        </header>

        <div class="content">
            <!-- Welcome -->
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h4>Welcome back, <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>! 🎓</h4>
                    <p class="text-muted">Here's your academic overview.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Courses</h5>
                            <h2><?= $courseCount ?></h2>
                            <small>Enrolled</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Attendance</h5>
                            <h2><?= $attendancePercent ?>%</h2>
                            <small>Overall present</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Feedback</h5>
                            <h2><?= $feedbackCount ?></h2>
                            <small>Submitted</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Events</h5>
                            <h2><?= count($events) ?></h2>
                            <small>Upcoming</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Announcements -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">📢 Latest Announcements</div>
                        <div class="card-body">
                            <?php if ($announcements): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($announcements as $a): ?>
                                        <li class="list-group-item">
                                            <strong><?= htmlspecialchars($a['title']) ?></strong>
                                            <br><small class="text-muted"><?= date('d M Y', strtotime($a['created_at'])) ?></small>
                                            <p><?= htmlspecialchars(substr($a['content'], 0, 80)) . (strlen($a['content']) > 80 ? '...' : '') ?></p>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No recent announcements.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">📅 Upcoming Events</div>
                        <div class="card-body">
                            <?php if ($events): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($events as $e): ?>
                                        <li class="list-group-item">
                                            <strong><?= htmlspecialchars($e['title']) ?></strong>
                                            <br><small class="text-muted"><?= date('d M Y, h:i A', strtotime($e['event_date'])) ?></small>
                                            <br><small><?= htmlspecialchars($e['venue']) ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No upcoming events.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

           <div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                ⭐ Feedback Summary
            </div>
            <div class="card-body">
                <h5>
                    Total Feedback Submitted:
                    <?= $feedbackCount ?>
                </h5>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>