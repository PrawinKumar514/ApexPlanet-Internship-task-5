<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';
requireRole('admin');

$db = Database::getInstance()->getConnection();

$message = '';
$message_type = '';

// CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'add') {
            $course_id = (int)$_POST['course_id'];
            $faculty_id = (int)$_POST['faculty_id'];
            $room = sanitizeInput($_POST['room']);
            $day_of_week = sanitizeInput($_POST['day_of_week']);
            $start_time = sanitizeInput($_POST['start_time']);
            $end_time = sanitizeInput($_POST['end_time']);

            $validator = new Validator();
            $validator->required('course_id', $course_id)->numeric('course_id', $course_id)
                      ->required('faculty_id', $faculty_id)->numeric('faculty_id', $faculty_id)
                      ->required('room', $room)->maxLength('room', $room, 50)
                      ->required('day_of_week', $day_of_week)
                      ->required('start_time', $start_time)
                      ->required('end_time', $end_time);

            if ($validator->hasErrors()) {
    $message = implode('<br>', $validator->getErrors());
    $message_type = 'danger';
} elseif ($start_time >= $end_time) {
    $message = 'End time must be later than start time.';
    $message_type = 'danger';
} else {
                // Check conflict: same room, same day, overlapping time
                $stmt = $db->prepare("
    SELECT id
    FROM timetables
    WHERE room = :room
    AND day_of_week = :day_of_week
    AND start_time < :end_time
    AND end_time > :start_time
");

$stmt->execute([
    'room' => $room,
    'day_of_week' => $day_of_week,
    'start_time' => $start_time,
    'end_time' => $end_time
]);
                if ($stmt->fetch()) {
    $message = 'Room is already booked at this time.';
    $message_type = 'danger';
} else {

    $facultyCheck = $db->prepare("
        SELECT id
        FROM timetables
        WHERE faculty_id = :faculty_id
        AND day_of_week = :day_of_week
        AND start_time < :end_time
        AND end_time > :start_time
    ");

    $facultyCheck->execute([
        'faculty_id' => $faculty_id,
        'day_of_week' => $day_of_week,
        'start_time' => $start_time,
        'end_time' => $end_time
    ]);

    if ($facultyCheck->fetch()) {
        $message = 'Faculty is already assigned to another class during this time.';
        $message_type = 'danger';
    } else {

        $stmt = $db->prepare("
            INSERT INTO timetables
            (course_id, faculty_id, room, day_of_week, start_time, end_time)
            VALUES (:course, :faculty, :room, :day_of_week, :start, :end)
        ");

        if ($stmt->execute([
            'course' => $course_id,
            'faculty' => $faculty_id,
            'room' => $room,
            'day_of_week' => $day_of_week,
            'start' => $start_time,
            'end' => $end_time
        ])) {
            $message = 'Timetable entry added successfully.';
            $message_type = 'success';
        } else {
            $message = 'Error adding timetable entry.';
            $message_type = 'danger';
        }
    }
}
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $course_id = (int)$_POST['course_id'];
            $faculty_id = (int)$_POST['faculty_id'];
            $room = sanitizeInput($_POST['room']);
            $day_of_week = sanitizeInput($_POST['day_of_week']);
            $start_time = sanitizeInput($_POST['start_time']);
            $end_time = sanitizeInput($_POST['end_time']);

            $validator = new Validator();
            $validator->required('course_id', $course_id)->numeric('course_id', $course_id)
                      ->required('faculty_id', $faculty_id)->numeric('faculty_id', $faculty_id)
                      ->required('room', $room)->maxLength('room', $room, 50)
                      ->required('day_of_week', $day_of_week)
                      ->required('start_time', $start_time)
                      ->required('end_time', $end_time);

            if ($validator->hasErrors()) {
    $message = implode('<br>', $validator->getErrors());
    $message_type = 'danger';
} elseif ($start_time >= $end_time) {
    $message = 'End time must be later than start time.';
    $message_type = 'danger';
} else {
                // Check conflict excluding current entry
                $stmt = $db->prepare("
    SELECT id
    FROM timetables
    WHERE room = :room
    AND day_of_week = :day_of_week
    AND id != :id
    AND start_time < :end_time
    AND end_time > :start_time
");

$stmt->execute([
    'room' => $room,
    'day_of_week' => $day_of_week,
    'id' => $id,
    'start_time' => $start_time,
    'end_time' => $end_time
]);
                if ($stmt->fetch()) {
    $message = 'Room is already booked at this time.';
    $message_type = 'danger';
} else {

    $facultyCheck = $db->prepare("
        SELECT id
        FROM timetables
        WHERE faculty_id = :faculty_id
        AND day_of_week = :day_of_week
        AND id != :id
        AND start_time < :end_time
        AND end_time > :start_time
    ");

    $facultyCheck->execute([
        'faculty_id' => $faculty_id,
        'day_of_week' => $day_of_week,
        'id' => $id,
        'start_time' => $start_time,
        'end_time' => $end_time
    ]);

    if ($facultyCheck->fetch()) {
        $message = 'Faculty is already assigned to another class during this time.';
        $message_type = 'danger';
    } else {

        $stmt = $db->prepare("
            UPDATE timetables
            SET course_id = :course,
                faculty_id = :faculty,
                room = :room,
                day_of_week = :day_of_week,
                start_time = :start,
                end_time = :end
            WHERE id = :id
        ");

        if ($stmt->execute([
            'course' => $course_id,
            'faculty' => $faculty_id,
            'room' => $room,
            'day_of_week' => $day_of_week,
            'start' => $start_time,
            'end' => $end_time,
            'id' => $id
        ])) {
            $message = 'Timetable entry updated successfully.';
            $message_type = 'success';
        } else {
            $message = 'Error updating timetable entry.';
            $message_type = 'danger';
        }
    }
}
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM timetables WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'timetables entry deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting timetables entry.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch timetables entries with course and faculty names
$entries = $db->query("SELECT t.*, c.name as course_name, CONCAT(f.first_name, ' ', f.last_name) as faculty_name 
                       FROM timetables t
                       LEFT JOIN courses c ON t.course_id = c.id
                       LEFT JOIN faculty f ON t.faculty_id = f.id
                       ORDER BY FIELD(t.day_of_week,'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), t.start_time")->fetchAll();

$courses = $db->query("SELECT id, name FROM courses ORDER BY name")->fetchAll();
$faculty_list = $db->query("SELECT id, first_name, last_name FROM faculty WHERE status = 'Active' ORDER BY first_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetables Management - Admin</title>
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
            <a href="students.php" class="nav-link"><i class="fas fa-users"></i> Students</a>
            <a href="faculty.php" class="nav-link"><i class="fas fa-chalkboard-teacher"></i> Faculty</a>
            <a href="departments.php" class="nav-link"><i class="fas fa-building"></i> Departments</a>
            <a href="courses.php" class="nav-link"><i class="fas fa-book"></i> Courses</a>
            <a href="timetable.php" class="nav-link active">
    <i class="fas fa-calendar-alt"></i> Timetables
</a>
            <a href="announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="feedback.php" class="nav-link"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-history"></i> Activity Logs</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Timetable Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addtimetablesModal"><i class="fas fa-plus"></i> Add Entry</button>
            </div>
        </header>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-timetables" class="form-control" placeholder="Search by course or room..." style="max-width: 300px;">
                <div id="timetables-results"></div>
            </div>

            <!-- timetables Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Course</th>
                                    <th>Faculty</th>
                                    <th>Room</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $e): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($e['day_of_week']) ?></td>
                                        <td><?= date('h:i A', strtotime($e['start_time'])) ?> - <?= date('h:i A', strtotime($e['end_time'])) ?></td>
                                        <td><?= htmlspecialchars($e['course_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($e['faculty_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($e['room']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-timetables" 
                                                    data-id="<?= $e['id'] ?>"
                                                    data-course_id="<?= $e['course_id'] ?>"
                                                    data-faculty_id="<?= $e['faculty_id'] ?>"
                                                    data-room="<?= htmlspecialchars($e['room']) ?>"
                                                    data-day="<?= $e['day_of_week'] ?>"
                                                    data-start_time="<?= $e['start_time'] ?>"
                                                    data-end_time="<?= $e['end_time'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-timetables" data-id="<?= $e['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($entries) === 0): ?>
                                    <tr><td colspan="6" class="text-center">No timetables entries found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add timetables Modal -->
    <div class="modal fade" id="addtimetablesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Timetables Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
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
                            <label for="faculty_id" class="form-label">Faculty <span class="text-danger">*</span></label>
                            <select class="form-select" name="faculty_id" required>
                                <option value="">Select Faculty</option>
                                <?php foreach ($faculty_list as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="room" class="form-label">Room No. <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="room" placeholder="e.g., A-101" required>
                        </div>
                        <div class="mb-3">
                            <label for="day" class="form-label">Day <span class="text-danger">*</span></label>
                            <select class="form-select" name="day_of_week" required>
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit timetables Modal -->
    <div class="modal fade" id="edittimetablesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Timetables Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" id="edit-course_id" required>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit-faculty_id" class="form-label">Faculty <span class="text-danger">*</span></label>
                            <select class="form-select" name="faculty_id" id="edit-faculty_id" required>
                                <?php foreach ($faculty_list as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit-room" class="form-label">Room No. <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="room" id="edit-room" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-day" class="form-label">Day <span class="text-danger">*</span></label>
                            <select class="form-select" name="day_of_week" id="edit-day" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" id="edit-start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" id="edit-end_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete timetables Modal -->
    <div class="modal fade" id="deletetimetablesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete timetables Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this timetables entry?</p>
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
            $('.edit-timetables').on('click', function() {
                const data = $(this).data();
                $('#edit-id').val(data.id);
                $('#edit-course_id').val(data.course_id);
                $('#edit-faculty_id').val(data.faculty_id);
                $('#edit-room').val(data.room);
                $('#edit-day').val(data.day);
                $('#edit-start_time').val(data.start_time);
                $('#edit-end_time').val(data.end_time);
                $('#edittimetablesModal').modal('show');
            });

            $('.delete-timetables').on('click', function() {
                $('#delete-id').val($(this).data('id'));
                $('#deletetimetablesModal').modal('show');
            });
        });
    </script>

    <script>
document.getElementById('search-timetables').addEventListener('keyup', function () {

    let value = this.value.toLowerCase();

    document.querySelectorAll('table tbody tr').forEach(function(row) {

        let text = row.innerText.toLowerCase();

        if (text.includes(value)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }

    });

});
</script>

</body>
</html>