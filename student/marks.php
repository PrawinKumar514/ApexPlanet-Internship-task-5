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

// Helper: Grade to Grade Point mapping (common Indian system)
function getGradePoint($grade) {
    $map = [
        'O' => 10,
        'A+' => 9,
        'A' => 8,
        'B+' => 7,
        'B' => 6,
        'C' => 5,
        'D' => 4,
        'F' => 0
    ];
    return isset($map[$grade]) ? $map[$grade] : 0;
}

// Fetch all marks with course details
$marks = $db->query("SELECT 
    m.id,
    m.exam_type,
    m.marks_obtained,
    m.total_marks,
    m.grade,
    c.id as course_id,
    c.name as course_name,
    c.course_code,
    c.credits
    FROM marks m
    JOIN courses c ON m.course_id = c.id
    WHERE m.student_id = $student_id
    ORDER BY c.name, FIELD(m.exam_type, 'Midterm', 'Quiz', 'Assignment', 'Final')")->fetchAll();

// Group by course
$coursesMarks = [];
$totalCredits = 0;
$totalGradePoints = 0;
$coursesWithGrade = 0;

foreach ($marks as $m) {
    $cid = $m['course_id'];
    if (!isset($coursesMarks[$cid])) {
        $coursesMarks[$cid] = [
            'name' => $m['course_name'],
            'code' => $m['course_code'],
            'credits' => $m['credits'],
            'exams' => [],
            'total_obtained' => 0,
            'total_possible' => 0
        ];
    }
    $coursesMarks[$cid]['exams'][] = [
        'type' => $m['exam_type'],
        'obtained' => $m['marks_obtained'],
        'total' => $m['total_marks'],
        'grade' => $m['grade']
    ];
    // Accumulate for course total (if we want to compute overall percentage)
    if ($m['marks_obtained'] !== null && $m['total_marks'] !== null) {
        $coursesMarks[$cid]['total_obtained'] += $m['marks_obtained'];
        $coursesMarks[$cid]['total_possible'] += $m['total_marks'];
    }
    // For GPA: use final grade? Usually each course has one overall grade, but here multiple exam types.
    // We'll compute average grade point per course based on all exams? 
    // Better: use the grade from a final exam or overall grade stored? Our schema doesn't have overall course grade.
    // For simplicity, we will compute a weighted average of grade points based on marks percentage.
    // But we can also assume the grade is already overall for each course (maybe from marks table with exam_type = 'Final').
    // However, we can compute course percentage and assign a grade based on that.
    // Let's compute course percentage from total_obtained/total_possible.
}

// Now compute course-wise percentage and grade, and GPA
$courseSummary = [];
foreach ($coursesMarks as $cid => $course) {
    $totalObtained = $course['total_obtained'];
    $totalPossible = $course['total_possible'];
    $percentage = ($totalPossible > 0) ? ($totalObtained / $totalPossible) * 100 : 0;
    // Assign grade based on percentage (common scale)
    if ($percentage >= 90) $grade = 'O';
    elseif ($percentage >= 80) $grade = 'A+';
    elseif ($percentage >= 70) $grade = 'A';
    elseif ($percentage >= 60) $grade = 'B+';
    elseif ($percentage >= 50) $grade = 'B';
    elseif ($percentage >= 40) $grade = 'C';
    elseif ($percentage >= 33) $grade = 'D';
    else $grade = 'F';
    $gradePoint = getGradePoint($grade);
    $credits = $course['credits'];
    $totalCredits += $credits;
    $totalGradePoints += $gradePoint * $credits;
    $coursesWithGrade++;
    $courseSummary[$cid] = [
        'name' => $course['name'],
        'code' => $course['code'],
        'credits' => $credits,
        'percentage' => round($percentage, 2),
        'grade' => $grade,
        'grade_point' => $gradePoint,
        'exams' => $course['exams']
    ];
}

$gpa = ($totalCredits > 0) ? round($totalGradePoints / $totalCredits, 2) : 0;

// Fetch total enrolled courses count for display
$totalCourses = $db->query("SELECT COUNT(*) FROM course_enrollments WHERE student_id = $student_id AND status = 'Enrolled'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks & Results - Student</title>
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
            <a href="attendance.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> Assignments</a>
            <a href="marks.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Marks</a>
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
            <h3>Marks & Results</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">GPA: <?= $gpa ?></span>
            </div>
        </header>

        <div class="content">
            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Overall GPA</h5>
                            <h2><?= $gpa ?></h2>
                            <small>out of 10</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Total Credits</h5>
                            <h2><?= $totalCredits ?></h2>
                            <small>Earned</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Courses</h5>
                            <h2><?= $totalCourses ?></h2>
                            <small>Enrolled</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Grade Point</h5>
                            <h2><?= round($totalGradePoints, 2) ?></h2>
                            <small>Total weighted</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course-wise Marks -->
            <?php if (count($courseSummary) > 0): ?>
                <?php foreach ($courseSummary as $cid => $course): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong><?= htmlspecialchars($course['code']) ?> - <?= htmlspecialchars($course['name']) ?></strong>
                            <span>
                                <span class="badge bg-info">Credits: <?= $course['credits'] ?></span>
                                <span class="badge <?= $course['grade'] == 'F' ? 'bg-danger' : 'bg-success' ?>">Grade: <?= $course['grade'] ?></span>
                                <span class="badge bg-secondary"><?= $course['percentage'] ?>%</span>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Exam Type</th>
                                            <th>Marks Obtained</th>
                                            <th>Total Marks</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($course['exams'] as $exam): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($exam['type']) ?></td>
                                                <td><?= $exam['obtained'] ?? '-' ?></td>
                                                <td><?= $exam['total'] ?? '-' ?></td>
                                                <td><?= htmlspecialchars($exam['grade'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-active">
                                            <td><strong>Total</strong></td>
                                            <td><strong><?= $course['percentage'] ?>%</strong></td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i> No marks available yet.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>