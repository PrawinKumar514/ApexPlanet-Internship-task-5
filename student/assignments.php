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

$message = '';
$message_type = '';

// Handle assignment submission / update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'submit' || $action === 'update') {
            $assignment_id = (int)$_POST['assignment_id'];
            $submission_text = sanitizeInput($_POST['submission_text'] ?? '');
            
            // Check if assignment exists
            $stmt = $db->prepare("SELECT id, due_date, max_marks FROM assignments WHERE id = :id");
            $stmt->execute(['id' => $assignment_id]);
            $assignment = $stmt->fetch();
            if (!$assignment) {
                $message = 'Assignment not found.';
                $message_type = 'danger';
            } else {
                // Check if already submitted
                $stmt = $db->prepare("SELECT id, submission_file FROM assignment_submissions WHERE assignment_id = :aid AND student_id = :sid");
                $stmt->execute(['aid' => $assignment_id, 'sid' => $student_id]);
                $existing = $stmt->fetch();
                
                // File upload handling
                $submission_file = '';
                $uploadError = false;
                if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $_FILES['submission_file']['tmp_name']);
                        finfo_close($finfo);
                        if (!in_array($mime, $allowed)) {
                            $message = 'Invalid file type. Only PDF, DOC, DOCX allowed.';
                            $message_type = 'danger';
                            $uploadError = true;
                        } elseif ($_FILES['submission_file']['size'] > 10 * 1024 * 1024) {
                            $message = 'File size exceeds 10MB.';
                            $message_type = 'danger';
                            $uploadError = true;
                        } else {
                            $ext = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
                            $filename = 'submission_' . $assignment_id . '_' . $student_id . '_' . time() . '.' . $ext;
                            $uploadPath = '../uploads/assignment_submissions/';
                            if (!is_dir($uploadPath)) {
                                mkdir($uploadPath, 0777, true);
                            }
                            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $uploadPath . $filename)) {
                                $submission_file = $filename;
                            } else {
                                $message = 'Failed to upload file.';
                                $message_type = 'danger';
                                $uploadError = true;
                            }
                        }
                    } else {
                        $message = 'File upload error.';
                        $message_type = 'danger';
                        $uploadError = true;
                    }
                }
                
                if (!$uploadError) {
                    // Determine status: if due date passed, mark as Late, else Submitted
                    $status = (strtotime($assignment['due_date']) < time()) ? 'Late' : 'Submitted';
                    
                    if ($action === 'submit' && !$existing) {
                        // Insert new submission
                        $stmt = $db->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, submission_file, submission_text, submitted_at, status) 
                                              VALUES (:aid, :sid, :file, :text, NOW(), :status)");
                        $result = $stmt->execute([
                            'aid' => $assignment_id,
                            'sid' => $student_id,
                            'file' => $submission_file,
                            'text' => $submission_text,
                            'status' => $status
                        ]);
                        if ($result) {
                            $message = 'Assignment submitted successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error submitting assignment.';
                            $message_type = 'danger';
                        }
                    } elseif ($action === 'update' && $existing) {
                        // Delete old file if new file uploaded
                        if ($submission_file && $existing['submission_file']) {
                            $oldPath = '../uploads/assignment_submissions/' . $existing['submission_file'];
                            if (file_exists($oldPath)) {
                                unlink($oldPath);
                            }
                        }
                        // Update existing submission
                        $stmt = $db->prepare("UPDATE assignment_submissions SET 
                                              submission_file = COALESCE(:file, submission_file),
                                              submission_text = :text,
                                              submitted_at = NOW(),
                                              status = :status
                                              WHERE id = :id");
                        $result = $stmt->execute([
                            'file' => $submission_file ?: null,
                            'text' => $submission_text,
                            'status' => $status,
                            'id' => $existing['id']
                        ]);
                        if ($result) {
                            $message = 'Assignment updated successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error updating assignment.';
                            $message_type = 'danger';
                        }
                    } else {
                        $message = 'Invalid action.';
                        $message_type = 'danger';
                    }
                }
            }
        }
    }
}

// Get all assignments for courses the student is enrolled in (using prepared statement)
$sql = "SELECT a.*, c.name as course_name, c.course_code, 
               CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
               (SELECT id FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid1) as submission_id,
               (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid2) as submission_status,
               (SELECT marks_obtained FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid3) as marks_obtained,
               (SELECT feedback FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid4) as feedback,
               (SELECT submission_file FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid5) as submission_file,
               (SELECT submission_text FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid6) as submission_text,
               (SELECT submitted_at FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid7) as submitted_at
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN course_enrollments ce ON c.id = ce.course_id
        LEFT JOIN faculty f ON a.faculty_id = f.id
        WHERE ce.student_id = :sid8
        ORDER BY a.due_date DESC";

$stmt = $db->prepare($sql);

$stmt->bindValue(':sid1', $student_id, PDO::PARAM_INT);
$stmt->bindValue(':sid2', $student_id, PDO::PARAM_INT);
$stmt->bindValue(':sid3', $student_id, PDO::PARAM_INT);
$stmt->bindValue(':sid4', $student_id, PDO::PARAM_INT);
$stmt->bindValue(':sid5', $student_id, PDO::PARAM_INT);
$stmt->bindValue(':sid6', $student_id, PDO::PARAM_INT);
$stmt->bindValue(':sid7', $student_id, PDO::PARAM_INT);
$stmt->bindValue(':sid8', $student_id, PDO::PARAM_INT);

$stmt->execute();
$assignments = $stmt->fetchAll();

// Compute statistics based on improved status logic
$totalAssignments = count($assignments);
$pendingAssignments = 0;
$submittedAssignments = 0;
$gradedAssignments = 0;

foreach ($assignments as &$ass) {
    // Determine display status
    if ($ass['marks_obtained'] !== null) {
        $ass['display_status'] = 'Graded';
        $gradedAssignments++;
    } elseif ($ass['submission_status'] === 'Late') {
        $ass['display_status'] = 'Late';
        $submittedAssignments++;
    } elseif ($ass['submission_status'] !== null) {
        $ass['display_status'] = 'Submitted';
        $submittedAssignments++;
    } else {
        $ass['display_status'] = 'Pending';
        $pendingAssignments++;
    }
    // Also check overdue for pending assignments
    if ($ass['display_status'] === 'Pending' && strtotime($ass['due_date']) < time()) {
        $ass['overdue'] = true;
    } else {
        $ass['overdue'] = false;
    }
}
unset($ass);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - Student</title>
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
            <h3>Assignments</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
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
                            <h5>Total Assignments</h5>
                            <h2><?= $totalAssignments ?></h2>
                            <small>Assigned to you</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-2">
                        <div class="card-body">
                            <h5>Pending</h5>
                            <h2><?= $pendingAssignments ?></h2>
                            <small>Not yet submitted</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-3">
                        <div class="card-body">
                            <h5>Submitted</h5>
                            <h2><?= $submittedAssignments ?></h2>
                            <small>Submitted</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card gradient-4">
                        <div class="card-body">
                            <h5>Graded</h5>
                            <h2><?= $gradedAssignments ?></h2>
                            <small>Marks received</small>
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
                    <i class="fas fa-list me-1"></i> My Assignments
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="assignment-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Due Date</th>
                                    <th>Max Marks</th>
                                    <th>Faculty File</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($assignments) > 0): ?>
                                    <?php foreach ($assignments as $ass): ?>
                                        <?php 
                                            $status = $ass['display_status'];
                                            $statusClass = $status === 'Graded' ? 'bg-success' : ($status === 'Submitted' ? 'bg-info' : ($status === 'Late' ? 'bg-warning' : 'bg-secondary'));
                                            $overdue = $ass['overdue'] ?? false;
                                        ?>
                                        <tr <?= $overdue ? 'class="table-danger"' : '' ?>>
                                            <td><strong><?= htmlspecialchars($ass['title']) ?></strong></td>
                                            <td><?= htmlspecialchars($ass['course_name']) ?></td>
                                            <td><?= date('d M Y, h:i A', strtotime($ass['due_date'])) ?></td>
                                            <td><?= $ass['max_marks'] ?? '-' ?></td>
                                            <td>
                                                <?php if ($ass['file_path']): ?>
                                                    <a href="../uploads/assignments/<?= htmlspecialchars($ass['file_path']) ?>"
   download
   class="btn btn-sm btn-primary">
   <i class="fas fa-download"></i>
</a>
                                                <?php else: ?>
                                                    <span class="text-muted">No file</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $overdue ? 'bg-danger' : $statusClass ?>">
                                                    <?= $overdue ? 'Overdue' : $status ?>
                                                </span>
                                                <?php if ($status === 'Graded'): ?>
                                                    <br><small class="text-muted">Marks: <?= $ass['marks_obtained'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ass['submitted_at']): ?>
                                                    <?= date('d M Y', strtotime($ass['submitted_at'])) ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info view-assignment" 
                                                        data-id="<?= $ass['id'] ?>"
                                                        data-title="<?= htmlspecialchars($ass['title']) ?>"
                                                        data-description="<?= htmlspecialchars($ass['description']) ?>"
                                                        data-due="<?= date('d M Y, h:i A', strtotime($ass['due_date'])) ?>"
                                                        data-max="<?= $ass['max_marks'] ?? 'N/A' ?>"
                                                        data-file="<?= $ass['file_path'] ? '../uploads/assignments/' . $ass['file_path'] : '' ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($status === 'Pending'): ?>
                                                    <button class="btn btn-sm btn-primary submit-assignment" 
                                                            data-id="<?= $ass['id'] ?>"
                                                            data-title="<?= htmlspecialchars($ass['title']) ?>"
                                                            data-bs-toggle="modal" data-bs-target="#submitModal">
                                                        <i class="fas fa-upload"></i> Submit
                                                    </button>
                                                <?php elseif ($status === 'Submitted' || $status === 'Late' || $status === 'Graded'): ?>
                                                    <button class="btn btn-sm btn-warning submit-assignment" 
                                                            data-id="<?= $ass['id'] ?>"
                                                            data-title="<?= htmlspecialchars($ass['title']) ?>"
                                                            data-submission-id="<?= $ass['submission_id'] ?>"
                                                            data-text="<?= htmlspecialchars($ass['submission_text'] ?? '', ENT_QUOTES) ?>"
                                                            data-file="<?= $ass['submission_file'] ?>"
                                                            data-bs-toggle="modal" data-bs-target="#submitModal">
                                                        <i class="fas fa-edit"></i> Update
                                                    </button>
                                                    <?php if ($status === 'Graded'): ?>
                                                        <button class="btn btn-sm btn-success view-feedback" 
                                                                data-feedback="<?= htmlspecialchars($ass['feedback'] ?? 'No feedback yet') ?>"
                                                                data-marks="<?= $ass['marks_obtained'] ?>"
                                                                data-max="<?= $ass['max_marks'] ?? 'N/A' ?>"
                                                                data-bs-toggle="modal" data-bs-target="#feedbackModal">
                                                            <i class="fas fa-comment"></i> Feedback
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center">No assignments found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Assignment Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="view-title">Assignment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Description:</strong> <span id="view-description"></span></p>
                    <p><strong>Due Date:</strong> <span id="view-due"></span></p>
                    <p><strong>Max Marks:</strong> <span id="view-max"></span></p>
                    <p id="view-file-container">
    <strong>Faculty File:</strong>
    <a id="view-file" href="#" download>Download</a>
</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit / Update Assignment Modal -->
    <div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="submit-title">Submit Assignment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" id="submit-action" value="submit">
                        <input type="hidden" name="assignment_id" id="submit-assignment-id">
                        <input type="hidden" name="submission_id" id="submit-submission-id">
                        <div class="mb-3">
                            <label for="submission_text" class="form-label">Submission Text</label>
                            <textarea class="form-control" name="submission_text" id="submission-text" rows="4"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="submission_file" class="form-label">Upload File (Optional)</label>
                            <input type="file" class="form-control" name="submission_file" accept=".pdf,.doc,.docx">
                            <small class="text-muted">Allowed: PDF, DOC, DOCX. Max 10MB.</small>
                            <div id="current-file" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submit-btn">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback & Marks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Marks Obtained:</strong> <span id="feedback-marks"></span> / <span id="feedback-max"></span></p>
                    <p><strong>Feedback:</strong> <span id="feedback-text"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        $(document).ready(function() {
            // View assignment
            $('.view-assignment').on('click', function() {
                const data = $(this).data();
                $('#view-title').text(data.title);
                $('#view-description').text(data.description || 'No description');
                $('#view-due').text(data.due);
                $('#view-max').text(data.max);
                if (data.file) {
                    $('#view-file').attr('href', data.file);
                    $('#view-file-container').show();
                } else {
                    $('#view-file-container').hide();
                }
            });

            // Submit / Update assignment
            $('.submit-assignment').on('click', function() {
                const data = $(this).data();
                // Fix 2: use undefined check
                const isUpdate = data.submissionId !== undefined;
                $('#submit-title').text(isUpdate ? 'Update Submission' : 'Submit Assignment');
                $('#submit-assignment-id').val(data.id);
                $('#submit-action').val(isUpdate ? 'update' : 'submit');
                if (isUpdate) {
                    $('#submit-submission-id').val(data.submissionId);
                    $('#submission-text').val(data.text || '');
                    if (data.file) {
                        $('#current-file').html('<span class="text-muted">Current file: ' + data.file + '</span>');
                    } else {
                        $('#current-file').html('');
                    }
                    $('#submit-btn').text('Update');
                } else {
                    $('#submit-submission-id').val('');
                    $('#submission-text').val('');
                    $('#current-file').html('');
                    $('#submit-btn').text('Submit');
                }
            });

            // Feedback modal
            $('.view-feedback').on('click', function() {
                const data = $(this).data();
                $('#feedback-marks').text(data.marks);
                $('#feedback-max').text(data.max);
                $('#feedback-text').text(data.feedback || 'No feedback provided.');
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