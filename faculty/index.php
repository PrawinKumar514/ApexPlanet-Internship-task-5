<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
requireRole('faculty');

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Get faculty details
$stmt = $db->prepare("SELECT id, first_name, last_name, department_id FROM faculty WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$faculty = $stmt->fetch();
if (!$faculty) redirect('../auth/logout.php');
$faculty_id = $faculty['id'];

// Statistics
// Total students in faculty's courses
$studentsCount = $db->query("SELECT COUNT(DISTINCT ce.student_id) 
                             FROM course_enrollments ce 
                             JOIN courses c ON ce.course_id = c.id 
                             WHERE c.faculty_id = $faculty_id")->fetchColumn();

// Total courses assigned
$coursesCount = $db->query("SELECT COUNT(*) FROM courses WHERE faculty_id = $faculty_id")->fetchColumn();

// Total assignments created
$assignmentsCount = $db->query("SELECT COUNT(*) FROM assignments WHERE faculty_id = $faculty_id")->fetchColumn();

// Pending leave requests
$pendingLeaves = $db->query("SELECT COUNT(*) FROM leave_requests WHERE user_id = $user_id AND status = 'Pending'")->fetchColumn();

// Recent announcements (latest 3)
$announcements = $db->query("SELECT * FROM announcements WHERE target_role IN ('all','faculty') ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Upcoming events (next 3)
$events = $db->query("SELECT * FROM events WHERE event_date >= NOW() ORDER BY event_date ASC LIMIT 3")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - Smart Campus</title>
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
            <a href="index.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="attendance.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="marks.php" class="nav-link"><i class="fas fa-chart-bar"></i> Marks</a>
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
            <h3>Dashboard</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">Welcome, <?= htmlspecialchars($faculty['first_name']) ?></span>
            </div>
        </header>

        <div class="content">
            <!-- Welcome -->
            <div class="card glass-card mb-4">
                <div class="card-body">
                    <h4>Welcome back, <?= htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) ?>! 📚</h4>
                    <p class="text-muted">Here's your teaching overview.</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Students</h5>
                            <h2><?= $studentsCount ?></h2>
                            <small>Enrolled in your courses</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Courses</h5>
                            <h2><?= $coursesCount ?></h2>
                            <small>Assigned to you</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Assignments</h5>
                            <h2><?= $assignmentsCount ?></h2>
                            <small>Created</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Leave Requests</h5>
                            <h2><?= $pendingLeaves ?></h2>
                            <small>Pending</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Announcements & Events -->
            <div class="row g-4">
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
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>