<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';
requireRole('faculty');

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Get faculty ID
$stmt = $db->prepare("SELECT id, first_name, last_name FROM faculty WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$faculty = $stmt->fetch();
if (!$faculty) {
    redirect('../auth/logout.php');
}
$faculty_id = $faculty['id'];

$message = '';
$message_type = '';

// Handle attendance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'mark') {
            $student_id = (int)$_POST['student_id'];
            $course_id = (int)$_POST['course_id'];
            $date = sanitizeInput($_POST['date']);
            $status = sanitizeInput($_POST['status']);
            
            // Validate date
            if (strtotime($date) > time()) {
                $message = 'Cannot mark attendance for future dates.';
                $message_type = 'danger';
            } else {
                // Check if attendance already exists for this student, course, date
                $stmt = $db->prepare("SELECT id FROM attendance WHERE student_id = :sid AND course_id = :cid AND date = :date");
                $stmt->execute(['sid' => $student_id, 'cid' => $course_id, 'date' => $date]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update existing record
                    $stmt = $db->prepare("UPDATE attendance SET status = :status, marked_by = :faculty WHERE id = :id");
                    $result = $stmt->execute(['status' => $status, 'faculty' => $faculty_id, 'id' => $existing['id']]);
                } else {
                    // Insert new record
                    $stmt = $db->prepare("INSERT INTO attendance (student_id, course_id, date, status, marked_by) VALUES (:sid, :cid, :date, :status, :faculty)");
                    $result = $stmt->execute(['sid' => $student_id, 'cid' => $course_id, 'date' => $date, 'status' => $status, 'faculty' => $faculty_id]);
                }
                
                if ($result) {
                    $message = 'Attendance marked successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error marking attendance.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'update') {
            $id = (int)$_POST['id'];
            $status = sanitizeInput($_POST['status']);
            $stmt = $db->prepare("UPDATE attendance SET status = :status WHERE id = :id");
            if ($stmt->execute(['status' => $status, 'id' => $id])) {
                $message = 'Attendance updated.';
                $message_type = 'success';
            } else {
                $message = 'Error updating.';
                $message_type = 'danger';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM attendance WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'Attendance record deleted.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting.';
                $message_type = 'danger';
            }
        }
    }
}

// Get faculty's courses
$courses = $db->query("SELECT id, name FROM courses WHERE faculty_id = $faculty_id ORDER BY name")->fetchAll();

// Get today's date
$today = $_GET['date'] ?? date('Y-m-d');

// Get attendance records for today for faculty's courses
$attendanceToday = $db->query("SELECT a.*,
a.course_id,
s.student_id as student_id_number,
CONCAT(s.first_name,' ',s.last_name) as student_name,
c.name as course_name
                                FROM attendance a
                                JOIN students s ON a.student_id = s.id
                                JOIN courses c ON a.course_id = c.id
                                WHERE a.course_id IN (SELECT id FROM courses WHERE faculty_id = $faculty_id)
                                AND a.date = '$today'
                                ORDER BY a.created_at DESC")->fetchAll();

// Statistics
$totalStudents = $db->query("SELECT COUNT(DISTINCT ce.student_id) 
                             FROM course_enrollments ce 
                             JOIN courses c ON ce.course_id = c.id 
                             WHERE c.faculty_id = $faculty_id")->fetchColumn();

// Count present today
$presentToday = $db->query("SELECT COUNT(DISTINCT student_id) FROM attendance 
                            WHERE date = '$today' 
                            AND course_id IN (SELECT id FROM courses WHERE faculty_id = $faculty_id)
                            AND status = 'Present'")->fetchColumn();

// Count all students who have been marked today (present or absent) - not necessarily all students
$markedToday = $db->query("SELECT COUNT(DISTINCT student_id) FROM attendance 
                           WHERE date = '$today' 
                           AND course_id IN (SELECT id FROM courses WHERE faculty_id = $faculty_id)")->fetchColumn();

// Absent = total students - marked present (only if marked today, else we can't know)
// Better: count students who are not marked present today, but we have no record for them, so we can't count absent.
// We'll count absent as those who were marked absent today.
$absentToday = $db->query("SELECT COUNT(DISTINCT student_id) FROM attendance 
                           WHERE date = '$today' 
                           AND course_id IN (SELECT id FROM courses WHERE faculty_id = $faculty_id)
                           AND status = 'Absent'")->fetchColumn();

// However, if we want to show absent as total - present, we need to know which students have courses with this faculty.
// We can compute: total students in faculty's courses minus those marked present today.
// But that would require that all students have attendance marked each day. We'll just show marked present and marked absent.
// We'll add a card for "Marked Today" (total attendance entries).
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Faculty</title>
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
            <a href="attendance.php" class="nav-link active"><i class="fas fa-clipboard-check"></i> Attendance</a>
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
            <h3>Attendance Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#attendanceModal"><i class="fas fa-plus"></i> Mark Attendance</button>
            </div>
        </header>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Total Students</h5>
                            <h2><?= $totalStudents ?></h2>
                            <small>In your courses</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Present Today</h5>
                            <h2><?= $presentToday ?></h2>
                            <small>Marked present</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Absent Today</h5>
                            <h2><?= $absentToday ?></h2>
                            <small>Marked absent</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Marked Today</h5>
                            <h2><?= count($attendanceToday) ?></h2>
                            <small>Total records</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Filter and Search -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <input type="text" id="search-attendance" class="form-control" placeholder="Search by student or course...">
                </div>
                <div class="col-md-4">
                    <select id="filter-course" class="form-select">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="date" id="filter-date" class="form-control" value="<?= $today ?>">
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table me-1"></i> Attendance Records
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="attendance-table">
                            <thead>
<tr>
    <th style="display:none;">Course ID</th>
    <th>Student ID</th>
    <th>Student Name</th>
    <th>Course</th>
    <th>Date</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>
                            <tbody>
                                <?php if (count($attendanceToday) > 0): ?>
                                    <?php foreach ($attendanceToday as $row): ?>
                                        <tr>
    <td style="display:none;">
        <?= $row['course_id'] ?>
    </td>

    <td><?= htmlspecialchars($row['student_id_number']) ?></td>
                                            <td><?= htmlspecialchars($row['student_name']) ?></td>
                                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                                            <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                                            <td>
                                                <span class="badge <?= $row['status'] == 'Present' ? 'bg-success' : ($row['status'] == 'Absent' ? 'bg-danger' : ($row['status'] == 'Late' ? 'bg-warning' : 'bg-info')) ?>">
                                                    <?= $row['status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning edit-attendance" 
                                                        data-id="<?= $row['id'] ?>"
                                                        data-status="<?= $row['status'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-attendance" data-id="<?= $row['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No attendance records found for today.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Attendance Modal -->
    <div class="modal fade" id="attendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-check-circle"></i> Mark Attendance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="mark">
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Student <span class="text-danger">*</span></label>
                            <select class="form-select" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php
                                $students = $db->query("SELECT s.id, s.student_id, CONCAT(s.first_name, ' ', s.last_name) as name 
                                                        FROM students s 
                                                        JOIN course_enrollments ce ON s.id = ce.student_id 
                                                        JOIN courses c ON ce.course_id = c.id 
                                                        WHERE c.faculty_id = $faculty_id 
                                                        GROUP BY s.id 
                                                        ORDER BY name")->fetchAll();
                                foreach ($students as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['student_id']) ?> - <?= htmlspecialchars($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Late">Late</option>
                                <option value="Excused">Excused</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Update Attendance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="edit-status" required>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Late">Late</option>
                                <option value="Excused">Excused</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Attendance Modal -->
    <div class="modal fade" id="deleteAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Attendance Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this attendance record? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        $(document).ready(function() {
            // Edit button - populate modal
            $('.edit-attendance').on('click', function() {
                $('#edit-id').val($(this).data('id'));
                $('#edit-status').val($(this).data('status'));
                $('#editAttendanceModal').modal('show');
            });

            // Delete button
            $('.delete-attendance').on('click', function() {
                $('#delete-id').val($(this).data('id'));
                $('#deleteAttendanceModal').modal('show');
            });

            // Search filter
            $('#search-attendance').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#attendance-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });

            // Course filter
            $('#filter-course').on('change', function() {

    const selectedCourse = $(this).val();

    $('#attendance-table tbody tr').each(function() {

        const rowCourseId = $(this).find('td:eq(0)').text().trim();

        if (selectedCourse === '' || rowCourseId === selectedCourse) {
            $(this).show();
        } else {
            $(this).hide();
        }

    });

});

            // Date filter - reload page with selected date
            $('#filter-date').on('change', function() {
                const date = $(this).val();
                window.location.href = 'attendance.php?date=' + date;
            });

            // If date parameter in URL, set the date filter
            const urlParams = new URLSearchParams(window.location.search);
            const dateParam = urlParams.get('date');
            if (dateParam) {
                $('#filter-date').val(dateParam);
                // Reload table via AJAX would be better, but we'll keep simple and reload
                // For simplicity, we'll reload the page with the date parameter
            }
        });
    </script>
</body>
</html>