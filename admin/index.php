<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$db = Database::getInstance()->getConnection();

// Get counts
$studentCount = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$facultyCount = $db->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
$courseCount = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$deptCount = $db->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Recent activities
$activities = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Campus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <i class="fas fa-graduation-cap"></i> Smart Campus
        </div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="students.php" class="nav-link"><i class="fas fa-users"></i> Students</a>
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

    <!-- Main Content -->
    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Admin Dashboard</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-danger"><?= $db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$_SESSION['user_id']} AND is_read = 0")->fetchColumn() ?></span>
            </div>
        </header>

        <div class="content">
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Students</h5>
                            <h2><?= $studentCount ?></h2>
                            <small><i class="fas fa-arrow-up"></i> 12% increase</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Faculty</h5>
                            <h2><?= $facultyCount ?></h2>
                            <small><i class="fas fa-arrow-up"></i> 5% increase</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Courses</h5>
                            <h2><?= $courseCount ?></h2>
                            <small><i class="fas fa-arrow-up"></i> 8% increase</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Departments</h5>
                            <h2><?= $deptCount ?></h2>
                            <small><i class="fas fa-arrow-up"></i> 2 new</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Student Growth</div>
                        <div class="card-body">
                            <canvas id="studentGrowthChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Attendance Trends</div>
                        <div class="card-body">
                            <canvas id="attendanceTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">Recent Activities</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($activities as $activity): ?>
                            <li class="list-group-item">
                                <strong><?= htmlspecialchars($activity['action']) ?></strong>
                                <span class="text-muted"><?= htmlspecialchars($activity['description']) ?></span>
                                <small class="float-end"><?= htmlspecialchars($activity['created_at']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Chart.js configuration
        const ctx1 = document.getElementById('studentGrowthChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Students',
                    data: [120, 150, 180, 200, 230, 260],
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true
                }]
            }
        });

        const ctx2 = document.getElementById('attendanceTrendChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Present',
                    data: [85, 90, 78, 92, 88],
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }, {
                    label: 'Absent',
                    data: [15, 10, 22, 8, 12],
                    backgroundColor: 'rgba(255, 99, 132, 0.6)'
                }]
            }
        });
    </script>
</body>
</html>