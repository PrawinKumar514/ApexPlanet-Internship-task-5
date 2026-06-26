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

// Fetch all attendance records grouped by course
$attendanceData = $db->query("SELECT 
    c.id as course_id,
    c.name as course_name,
    c.course_code,
    COUNT(a.id) as total_classes,
    SUM(CASE WHEN a.status IN ('Present','Late','Excused') THEN 1 ELSE 0 END) as attended,
    SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
    SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
    SUM(CASE WHEN a.status = 'Excused' THEN 1 ELSE 0 END) as excused
    FROM attendance a
    JOIN courses c ON a.course_id = c.id
    WHERE a.student_id = $student_id
    GROUP BY c.id, c.name, c.course_code
    ORDER BY c.name")->fetchAll();

// Calculate overall statistics
$totalAll = 0;
$presentAll = 0;
$absentAll = 0;
$lateAll = 0;
$excusedAll = 0;

foreach ($attendanceData as $row) {
    $totalAll += $row['total_classes'];
    $presentAll += $row['attended'];
    $absentAll += $row['absent'];
    $lateAll += $row['late'];
    $excusedAll += $row['excused'];
}
$overallPercent = ($totalAll > 0) ? round(($presentAll / $totalAll) * 100, 1) : 0;

// Get recent attendance records (last 10)
$recent = $db->query("SELECT a.*, c.name as course_name, c.course_code 
                      FROM attendance a
                      JOIN courses c ON a.course_id = c.id
                      WHERE a.student_id = $student_id
                      ORDER BY a.date DESC, a.created_at DESC
                      LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Student</title>
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
            <a href="timetable.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Timetables</a>
            <a href="attendance.php" class="nav-link active"><i class="fas fa-clipboard-check"></i> Attendance</a>
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
            <h3>Attendance</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">Overall: <?= $overallPercent ?>%</span>
            </div>
        </header>

        <div class="content">


    <!-- Overall Summary Cards -->
    <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Overall Attendance</h5>
                            <h2><?= $overallPercent ?>%</h2>
                            <small><?= $presentAll ?> / <?= $totalAll ?> classes</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Present</h5>
                            <h2><?= $presentAll ?></h2>
                            <small>Classes attended</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Absent</h5>
                            <h2><?= $absentAll ?></h2>
                            <small>Classes missed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Late / Excused</h5>
                            <h2><?= $lateAll + $excusedAll ?></h2>
                            <small>Late: <?= $lateAll ?>, Excused: <?= $excusedAll ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course-wise Attendance -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i> Course-wise Attendance
                </div>
                <div class="card-body">
                    <?php if (count($attendanceData) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Attended</th>
                                        <th>Total</th>
                                        <th>Percentage</th>
                                        <th>Requirement</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceData as $row): ?>
                                        <?php 
                                            $percent = ($row['total_classes'] > 0) ? round(($row['attended'] / $row['total_classes']) * 100, 1) : 0;
                                            $statusClass = $percent >= 75 ? 'success' : 'danger';
                                            $statusText = $percent >= 75 ? 'Eligible' : 'Shortage';
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($row['course_code']) ?></strong></td>
                                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                                            <td><?= $row['attended'] ?></td>
                                            <td><?= $row['total_classes'] ?></td>
                                            <td>
    <div class="progress" style="height:25px;">
        <div class="progress-bar bg-<?= $statusClass ?>"
             role="progressbar"
             style="width: <?= $percent ?>%;"
             aria-valuenow="<?= $percent ?>"
             aria-valuemin="0"
             aria-valuemax="100">
            <?= $percent ?>%
        </div>
    </div>
</td>

<td>
    <?php if($percent >= 75): ?>
        <span class="badge bg-success">75% Met</span>
    <?php else: ?>
        <span class="badge bg-danger">Below 75%</span>
    <?php endif; ?>
</td>

<td>
    <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> No attendance records found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clock"></i> Recent Attendance
                </div>
                <div class="card-body">
                    <?php if (count($recent) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $rec): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($rec['date'])) ?></td>
                                            <td><?= htmlspecialchars($rec['course_code']) ?> - <?= htmlspecialchars($rec['course_name']) ?></td>
                                            <td>
                                                <span class="badge <?= $rec['status'] == 'Present' ? 'bg-success' : ($rec['status'] == 'Absent' ? 'bg-danger' : ($rec['status'] == 'Late' ? 'bg-warning' : 'bg-info')) ?>">
                                                    <?= $rec['status'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent attendance records.</p>
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
            // Optional: search filter for course-wise table
            $('#attendance-table').DataTable ? $('#attendance-table').DataTable() : null;
        });
    </script>
</body>
</html>