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

// Check student ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<div class="alert alert-danger">Student ID missing.</div>');
}
$student_id = (int)$_GET['id'];

// Verify student exists and is enrolled in at least one course of this faculty
$checkSql = "SELECT COUNT(*) FROM students s
             JOIN course_enrollments ce ON s.id = ce.student_id
             JOIN courses c ON ce.course_id = c.id
             WHERE s.id = :sid AND c.faculty_id = :fid";
$stmt = $db->prepare($checkSql);
$stmt->execute(['sid' => $student_id, 'fid' => $faculty_id]);
if ($stmt->fetchColumn() == 0) {
    die('<div class="alert alert-danger">Student not found or not enrolled in your courses.</div>');
}

// Fetch student details
$stmt = $db->prepare("SELECT s.id,
                             s.student_id,
                             s.first_name,
                             s.last_name,
                             u.email,
                             s.phone,
                             s.semester,
                             s.profile_image,
                             d.name as department_name
                      FROM students s
                      LEFT JOIN users u ON s.user_id = u.id
                      LEFT JOIN departments d ON s.department_id = d.id
                      WHERE s.id = :sid");
$stmt->execute(['sid' => $student_id]);
$student = $stmt->fetch();
if (!$student) {
    die('<div class="alert alert-danger">Student not found.</div>');
}

// 1. Attendance Analytics
$attSql = "SELECT COUNT(*) as total, 
                  SUM(CASE WHEN status IN ('Present','Late','Excused') THEN 1 ELSE 0 END) as present
           FROM attendance
           WHERE student_id = :sid";
$stmt = $db->prepare($attSql);
$stmt->execute(['sid' => $student_id]);
$att = $stmt->fetch();
$totalClasses = (int)$att['total'];
$presentClasses = (int)$att['present'];
$absentClasses = $totalClasses - $presentClasses;
$attPercentage = ($totalClasses > 0) ? round(($presentClasses / $totalClasses) * 100, 1) : 0;

// 2. Marks Analytics
$marksSql = "SELECT marks_obtained FROM marks WHERE student_id = :sid";
$stmt = $db->prepare($marksSql);
$stmt->execute(['sid' => $student_id]);
$marksData = $stmt->fetchAll(PDO::FETCH_COLUMN);
$marksCount = count($marksData);
$avgMarks = ($marksCount > 0) ? round(array_sum($marksData) / $marksCount, 2) : 0;
$highestMark = ($marksCount > 0) ? max($marksData) : 0;
$lowestMark = ($marksCount > 0) ? min($marksData) : 0;

// 3. Assignment Analytics (from assignment_submissions)
$assignSql = "SELECT marks_obtained FROM assignment_submissions WHERE student_id = :sid";
$stmt = $db->prepare($assignSql);
$stmt->execute(['sid' => $student_id]);
$assignData = $stmt->fetchAll(PDO::FETCH_COLUMN);
$assignCount = count($assignData);
$avgAssign = ($assignCount > 0) ? round(array_sum($assignData) / $assignCount, 2) : 0;
$highestAssign = ($assignCount > 0) ? max($assignData) : 0;
$lowestAssign = ($assignCount > 0) ? min($assignData) : 0;

// 4. Recent Marks
$recentMarksSql = "SELECT c.name as course_name, m.exam_type, m.marks_obtained, m.created_at
                   FROM marks m
                   JOIN courses c ON m.course_id = c.id
                   WHERE m.student_id = :sid
                   ORDER BY m.created_at DESC
                   LIMIT 5";
$stmt = $db->prepare($recentMarksSql);
$stmt->execute(['sid' => $student_id]);
$recentMarks = $stmt->fetchAll();

// 5. Recent Assignments
$recentAssignSql = "SELECT a.title as assignment_title, a_s.submitted_at, a_s.marks_obtained, a_s.status
                    FROM assignment_submissions a_s
                    JOIN assignments a ON a_s.assignment_id = a.id
                    WHERE a_s.student_id = :sid
                    ORDER BY a_s.submitted_at DESC
                    LIMIT 5";
$stmt = $db->prepare($recentAssignSql);
$stmt->execute(['sid' => $student_id]);
$recentAssigns = $stmt->fetchAll();

// 6. Performance Summary - overall score (average of attendance %, avg marks, avg assignment)
// We'll compute overall average as a weighted average of the three (each 100% scale)
$attScore = $attPercentage;
$marksScore = ($marksCount > 0) ? min(100, ($avgMarks / 100) * 100) : 0; // assuming marks out of 100
$assignScore = ($assignCount > 0) ? min(100, ($avgAssign / 100) * 100) : 0;
$overall = round(($attScore + $marksScore + $assignScore) / 3, 1);

if ($overall >= 85) $status = 'Excellent';
elseif ($overall >= 70) $status = 'Good';
elseif ($overall >= 50) $status = 'Average';
else $status = 'Needs Improvement';

$statusClass = ($overall >= 85) ? 'success' : (($overall >= 70) ? 'info' : (($overall >= 50) ? 'warning' : 'danger'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Performance - Faculty</title>
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
            <h3>Student Performance</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <a href="students.php" class="btn btn-sm btn-secondary ms-2"><i class="fas fa-arrow-left"></i> Back to Students</a>
            </div>
        </header>

        <div class="content">
            <!-- Student Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-graduate me-1"></i> Student Information
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <?php if ($student['profile_image']): ?>
                                <img src="../uploads/profiles/<?= htmlspecialchars($student['profile_image']) ?>" class="rounded-circle img-thumbnail" width="150" height="150" alt="Profile">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-7x text-muted"></i>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Phone:</strong> <?= htmlspecialchars($student['phone'] ?? 'N/A') ?></p>
                                    <p><strong>Department:</strong> <?= htmlspecialchars($student['department_name'] ?? 'N/A') ?></p>
                                    <p><strong>Semester:</strong> <?= $student['semester'] ?? 'N/A' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Analytics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Total Classes</h5>
                            <h2><?= $totalClasses ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Present</h5>
                            <h2><?= $presentClasses ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Absent</h5>
                            <h2><?= $absentClasses ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Attendance %</h5>
                            <h2><?= $attPercentage ?>%</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Marks Analytics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Total Subjects</h5>
                            <h2><?= $marksCount ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Average Marks</h5>
                            <h2><?= $avgMarks ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Highest Mark</h5>
                            <h2><?= $highestMark ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Lowest Mark</h5>
                            <h2><?= $lowestMark ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment Analytics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Assignments</h5>
                            <h2><?= $assignCount ?></h2>
                            <small>Submitted</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Avg Assignment</h5>
                            <h2><?= $avgAssign ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Highest</h5>
                            <h2><?= $highestAssign ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Lowest</h5>
                            <h2><?= $lowestAssign ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Summary -->
            <div class="card mb-4 border-<?= $statusClass ?>">
                <div class="card-header bg-<?= $statusClass ?> text-white">
                    <i class="fas fa-chart-line me-1"></i> Overall Performance
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h6>Attendance</h6>
                            <h3><?= $attPercentage ?>%</h3>
                        </div>
                        <div class="col-md-4">
                            <h6>Average Marks</h6>
                            <h3><?= $avgMarks ?></h3>
                        </div>
                        <div class="col-md-4">
                            <h6>Assignment Avg</h6>
                            <h3><?= $avgAssign ?></h3>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h5>Overall Score: <span class="text-<?= $statusClass ?>"><?= $overall ?>%</span></h5>
                        <h4><span class="badge bg-<?= $statusClass ?>"><?= $status ?></span></h4>
                    </div>
                </div>
            </div>

            <!-- Recent Marks Table -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-table me-1"></i> Recent Marks
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Assessment Type</th>
                                    <th>Marks Obtained</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentMarks) > 0): ?>
                                    <?php foreach ($recentMarks as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                                            <td><?= htmlspecialchars($row['exam_type']) ?></td>
                                            <td><?= $row['marks_obtained'] ?></td>
                                            <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center">No marks records.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Assignments Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table me-1"></i> Recent Assignments
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Submitted</th>
                                    <th>Marks</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recentAssigns) > 0): ?>
                                    <?php foreach ($recentAssigns as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['assignment_title']) ?></td>
                                            <td><?= date('d M Y', strtotime($row['submitted_at'])) ?></td>
                                            <td><?= $row['marks_obtained'] ?? '-' ?></td>
                                            <td><span class="badge <?= $row['status'] == 'Graded' ? 'bg-success' : 'bg-warning' ?>"><?= $row['status'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center">No assignments submitted.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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