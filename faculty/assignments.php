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

// Handle assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $course_id = (int)$_POST['course_id'];
            $due_date = sanitizeInput($_POST['due_date']);
            
            $validator = new Validator();
            $validator->required('title', $title)->maxLength('title', $title, 100)
                      ->required('description', $description)
                      ->required('course_id', $course_id)->numeric('course_id', $course_id)
                      ->required('due_date', $due_date);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Handle file upload
                $file_path = '';
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
                    finfo_close($finfo);
                    if (!in_array($mime, $allowed)) {
                        $message = 'Invalid file type. Only PDF and DOC/DOCX allowed.';
                        $message_type = 'danger';
                    } elseif ($_FILES['file']['size'] > 5 * 1024 * 1024) {
                        $message = 'File size exceeds 5MB.';
                        $message_type = 'danger';
                    } else {
                        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                        $filename = 'assignment_' . time() . '_' . uniqid() . '.' . $ext;
                        $uploadPath = '../uploads/assignments/' . $filename;
                        if (!is_dir('../uploads/assignments/')) {
                            mkdir('../uploads/assignments/', 0777, true);
                        }
                        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
                            $file_path = $filename;
                        } else {
                            $message = 'Failed to upload file.';
                            $message_type = 'danger';
                        }
                    }
                }
                if (empty($message)) {
                    $stmt = $db->prepare("INSERT INTO assignments (course_id, faculty_id, title, description, due_date, file_path) 
                                          VALUES (:course, :faculty, :title, :desc, :due, :file)");
                    if ($stmt->execute(['course' => $course_id, 'faculty' => $faculty_id, 'title' => $title, 
                                        'desc' => $description, 'due' => $due_date, 'file' => $file_path])) {
                        $message = 'Assignment created successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error creating assignment.';
                        $message_type = 'danger';
                    }
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $course_id = (int)$_POST['course_id'];
            $due_date = sanitizeInput($_POST['due_date']);
            
            $validator = new Validator();
            $validator->required('title', $title)->maxLength('title', $title, 100)
                      ->required('description', $description)
                      ->required('course_id', $course_id)->numeric('course_id', $course_id)
                      ->required('due_date', $due_date);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("UPDATE assignments SET title = :title, description = :desc, course_id = :course, due_date = :due WHERE id = :id");
                if ($stmt->execute(['title' => $title, 'desc' => $description, 'course' => $course_id, 'due' => $due_date, 'id' => $id])) {
                    $message = 'Assignment updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating assignment.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            // First, delete any submissions (optional) or we can keep them. For simplicity, delete assignment only.
            $stmt = $db->prepare("DELETE FROM assignments WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'Assignment deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting assignment.';
                $message_type = 'danger';
            }
        } elseif ($action === 'grade_submission') {
            $submission_id = (int)$_POST['submission_id'];
            $marks_obtained = !empty($_POST['marks_obtained']) ? (float)$_POST['marks_obtained'] : null;
            $feedback = sanitizeInput($_POST['feedback'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'Graded');
            
            $stmt = $db->prepare("UPDATE assignment_submissions SET marks_obtained = :marks, feedback = :feedback, status = :status WHERE id = :id");
            if ($stmt->execute(['marks' => $marks_obtained, 'feedback' => $feedback, 'status' => $status, 'id' => $submission_id])) {
                $message = 'Submission graded successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error grading submission.';
                $message_type = 'danger';
            }
        }
    }
}

// Get faculty's courses
$courses = $db->query("SELECT id, name FROM courses WHERE faculty_id = $faculty_id ORDER BY name")->fetchAll();

// Get all assignments with submission count
$assignments = $db->query("SELECT a.*, c.name as course_name,
                           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
                           FROM assignments a
                           JOIN courses c ON a.course_id = c.id
                           WHERE a.faculty_id = $faculty_id
                           ORDER BY a.due_date DESC")->fetchAll();

// Statistics
$totalAssignments = count($assignments);
$activeAssignments = 0;
$submissionsReceived = 0;
$today = date('Y-m-d');
foreach ($assignments as $ass) {
    if ($ass['due_date'] >= $today) $activeAssignments++;
    $submissionsReceived += $ass['submission_count'];
}

// For viewing submissions details
$submissions = [];
if (isset($_GET['view'])) {
    $assignment_id = (int)$_GET['view'];
    $submissions = $db->query("SELECT a_s.*, 
                                s.student_id as student_id_number,
                                CONCAT(s.first_name, ' ', s.last_name) as student_name
                                FROM assignment_submissions a_s
                                JOIN students s ON a_s.student_id = s.id
                                WHERE a_s.assignment_id = $assignment_id
                                ORDER BY a_s.submitted_at DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Faculty</title>
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
            <a href="assignments.php" class="nav-link active"><i class="fas fa-tasks"></i> Assignments</a>
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
            <h3>Assignments</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignmentModal"><i class="fas fa-plus"></i> Create Assignment</button>
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
                <div class="col-md-4">
                    <div class="card stat-card gradient-1">
                        <div class="card-body">
                            <h5>Total Assignments</h5>
                            <h2><?= $totalAssignments ?></h2>
                            <small>Created</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Active Assignments</h5>
                            <h2><?= $activeAssignments ?></h2>
                            <small>Not yet due</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Submissions Received</h5>
                            <h2><?= $submissionsReceived ?></h2>
                            <small>Total uploaded</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-assignment" class="form-control" placeholder="Search by title or course..." style="max-width: 300px;">
            </div>

            <!-- Assignments Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table me-1"></i> Assignments
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="assignment-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Submissions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($assignments) > 0): ?>
                                    <?php foreach ($assignments as $ass): ?>
                                        <?php 
                                            $status = (strtotime($ass['due_date']) >= time()) ? 'Active' : 'Expired';
                                            $statusClass = $status == 'Active' ? 'bg-success' : 'bg-secondary';
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($ass['title']) ?></strong></td>
                                            <td><?= htmlspecialchars($ass['course_name']) ?></td>
                                            <td><?= date('d M Y, h:i A', strtotime($ass['due_date'])) ?></td>
                                            <td><span class="badge <?= $statusClass ?>"><?= $status ?></span></td>
                                            <td><?= $ass['submission_count'] ?></td>
                                            <td>
                                                <a href="assignments.php?view=<?= $ass['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Submissions</a>
                                                <button class="btn btn-sm btn-warning edit-assignment" 
                                                        data-id="<?= $ass['id'] ?>"
                                                        data-title="<?= htmlspecialchars($ass['title']) ?>"
                                                        data-description="<?= htmlspecialchars($ass['description']) ?>"
                                                        data-course_id="<?= $ass['course_id'] ?>"
                                                        data-due_date="<?= $ass['due_date'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-assignment" data-id="<?= $ass['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No assignments found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Submissions Section (if view parameter is set) -->
            <?php if (isset($_GET['view'])): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-upload me-1"></i> Submissions for Assignment
                        <a href="assignments.php" class="btn btn-sm btn-secondary float-end">Back</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>File</th>
                                        <th>Submitted On</th>
                                        <th>Marks</th>
                                        <th>Feedback</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($submissions) > 0): ?>
                                        <?php foreach ($submissions as $sub): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($sub['student_id_number']) ?></td>
                                                <td><?= htmlspecialchars($sub['student_name']) ?></td>
                                                <td>
                                                    <?php if ($sub['submission_file']): ?>
                                                        <a href="../uploads/assignment_submissions/<?= htmlspecialchars($sub['submission_file']) ?>" target="_blank" class="btn btn-sm btn-primary">
    <i class="fas fa-download"></i>
</a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No file</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d M Y, h:i A', strtotime($sub['submitted_at'])) ?></td>
                                                <td><?= $sub['marks_obtained'] ?? '-' ?></td>
                                                <td><?= htmlspecialchars(substr($sub['feedback'] ?? '', 0, 20)) . (strlen($sub['feedback'] ?? '') > 20 ? '...' : '') ?></td>
                                                <td><span class="badge <?= $sub['status'] == 'Graded' ? 'bg-success' : 'bg-warning' ?>"><?= $sub['status'] ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning grade-submission" 
                                                            data-submission-id="<?= $sub['id'] ?>"
                                                            data-marks="<?= $sub['marks_obtained'] ?? '' ?>"
                                                            data-feedback="<?= htmlspecialchars($sub['feedback'] ?? '') ?>"
                                                            data-status="<?= $sub['status'] ?>"
                                                            data-bs-toggle="modal" data-bs-target="#gradeModal">
                                                        <i class="fas fa-graduation-cap"></i> Grade
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="8" class="text-center">No submissions yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Assignment Modal -->
    <div class="modal fade" id="assignmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create Assignment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" rows="4" required></textarea>
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
                            <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="due_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="file" class="form-label">Attachment (Optional)</label>
                            <input type="file" class="form-control" name="file" accept=".pdf,.doc,.docx">
                            <small class="text-muted">Max 5MB, allowed PDF, DOC, DOCX</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Assignment Modal -->
    <div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Assignment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="edit-title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" id="edit-description" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit-course_id" class="form-label">Course <span class="text-danger">*</span></label>
                            <select class="form-select" name="course_id" id="edit-course_id" required>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit-due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="due_date" id="edit-due_date" required>
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

    <!-- Delete Assignment Modal -->
    <div class="modal fade" id="deleteAssignmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Assignment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this assignment? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Grade Submission Modal -->
    <div class="modal fade" id="gradeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-graduation-cap"></i> Grade Submission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="grade_submission">
                        <input type="hidden" name="submission_id" id="grade-submission-id">
                        <div class="mb-3">
                            <label for="marks_obtained" class="form-label">Marks Obtained</label>
                            <input type="number" class="form-control" name="marks_obtained" id="grade-marks" step="0.01" min="0">
                        </div>
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Feedback</label>
                            <textarea class="form-control" name="feedback" id="grade-feedback" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="grade-status">
                                <option value="Submitted">Submitted</option>
                                <option value="Graded">Graded</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Grades</button>
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
            // Edit Assignment - populate modal
            $('.edit-assignment').on('click', function() {
                $('#edit-id').val($(this).data('id'));
                $('#edit-title').val($(this).data('title'));
                $('#edit-description').val($(this).data('description'));
                $('#edit-course_id').val($(this).data('course_id'));
                $('#edit-due_date').val($(this).data('due_date'));
                $('#editAssignmentModal').modal('show');
            });

            // Delete Assignment
            $('.delete-assignment').on('click', function() {
                $('#delete-id').val($(this).data('id'));
                $('#deleteAssignmentModal').modal('show');
            });

            // Grade Submission - populate modal
            $('.grade-submission').on('click', function() {
                $('#grade-submission-id').val($(this).data('submission-id'));
                $('#grade-marks').val($(this).data('marks'));
                $('#grade-feedback').val($(this).data('feedback'));
                $('#grade-status').val($(this).data('status'));
            });

            // Search filter
            $('#search-assignment').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#assignment-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });
        });
    </script>
</body>
</html>