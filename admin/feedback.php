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

// CRUD operations - only edit (reply/status) and delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'edit') {
            $id = (int)$_POST['id'];
            $reply = sanitizeInput($_POST['reply'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'Read');

if (!empty($reply)) {
    $status = 'Replied';
}

            $validator = new Validator();
            $validator->maxLength('reply', $reply, 500);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                // Update feedback with reply and status
                $stmt = $db->prepare("UPDATE feedback SET reply = :reply, status = :status WHERE id = :id");
                if ($stmt->execute(['reply' => $reply, 'status' => $status, 'id' => $id])) {
                    $message = 'Feedback updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating feedback.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM feedback WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'Feedback deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting feedback.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch feedback with student details
$feedback = $db->query("SELECT f.*, 
                        CONCAT(s.first_name, ' ', s.last_name) as student_name,
                        s.student_id as student_id_number
                        FROM feedback f
                        LEFT JOIN students s ON f.student_id = s.id
                        ORDER BY f.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Admin</title>
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
            <a href="timetable.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Timetables</a>
            <a href="announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="feedback.php" class="nav-link active"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-history"></i> Activity Logs</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Feedback Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">Total: <?= count($feedback) ?></span>
            </div>
        </header>

        <div class="content">
            <?php
$newCount = 0;
$readCount = 0;
$repliedCount = 0;

foreach($feedback as $fb){
    if($fb['status'] == 'New'){
        $newCount++;
    } elseif($fb['status'] == 'Read'){
        $readCount++;
    } elseif($fb['status'] == 'Replied'){
        $repliedCount++;
    }
}
?>

<div class="row mb-3">

    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h5><?= $newCount ?></h5>
                <small>New Feedback</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h5><?= $readCount ?></h5>
                <small>Read Feedback</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <h5><?= $repliedCount ?></h5>
                <small>Replied Feedback</small>
            </div>
        </div>
    </div>

</div>
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-feedback" class="form-control" placeholder="Search by student name, subject, or message..." style="max-width: 300px;">
                <div id="feedback-results"></div>
            </div>

            <!-- Feedback Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="feedback-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Reply</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedback as $f): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($f['student_name'] ?? 'Unknown') ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($f['student_id_number'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($f['subject']) ?></td>
                                        <td><?= htmlspecialchars(substr($f['message'], 0, 40)) . (strlen($f['message']) > 40 ? '...' : '') ?></td>
                                        <td><?= htmlspecialchars(substr($f['reply'] ?? '', 0, 30)) . (strlen($f['reply'] ?? '') > 30 ? '...' : '') ?></td>
                                        <td>

<?php if($f['status'] == 'New'): ?>

    <span class="badge bg-danger">
        <i class="fas fa-envelope"></i> New
    </span>

<?php elseif($f['status'] == 'Read'): ?>

    <span class="badge bg-warning text-dark">
        <i class="fas fa-eye"></i> Read
    </span>

<?php else: ?>

    <span class="badge bg-success">
        <i class="fas fa-reply"></i> Replied
    </span>

<?php endif; ?>

</td>
                                        <td><?= date('d M Y, h:i A', strtotime($f['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-feedback" 
                                                    data-id="<?= $f['id'] ?>"
                                                    data-student="<?= htmlspecialchars($f['student_name'] ?? 'Unknown') ?>"
                                                    data-subject="<?= htmlspecialchars($f['subject']) ?>"
                                                    data-message="<?= htmlspecialchars($f['message']) ?>"
                                                    data-reply="<?= htmlspecialchars($f['reply'] ?? '') ?>"
                                                    data-status="<?= htmlspecialchars($f['status']) ?>">
                                                <i class="fas fa-reply"></i> Reply
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-feedback" data-id="<?= $f['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($feedback) === 0): ?>
                                    <tr><td colspan="7" class="text-center">No feedback found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Feedback / Reply Modal -->
    <div class="modal fade" id="editFeedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-reply"></i> Reply to Feedback</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Student</label>
                                <p class="form-control-static" id="edit-student">-</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <p class="form-control-static" id="edit-subject">-</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <div class="card bg-light">
                                <div class="card-body" id="edit-message"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="reply" class="form-label">Reply</label>
                            <textarea class="form-control" name="reply" id="edit-reply" rows="4" placeholder="Type your reply here..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit-status">
                                <option value="New">New</option>
                                <option value="Read">Read</option>
                                <option value="Replied">Replied</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Feedback Modal -->
    <div class="modal fade" id="deleteFeedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Feedback</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this feedback? This action cannot be undone.</p>
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
            // Edit/Reply button click - populate modal
            $('.edit-feedback').on('click', function() {
                const data = $(this).data();
                $('#edit-id').val(data.id);
                $('#edit-student').text(data.student);
                $('#edit-subject').text(data.subject);
                $('#edit-message').text(data.message);
                $('#edit-reply').val(data.reply);
                $('#edit-status').val(data.status);
                $('#editFeedbackModal').modal('show');
            });

            // Delete button click
            $('.delete-feedback').on('click', function() {
                const id = $(this).data('id');
                $('#delete-id').val(id);
                $('#deleteFeedbackModal').modal('show');
            });

            // Live search filter
            $('#search-feedback').on('keyup', function() {
                const searchText = $(this).val().toLowerCase();
                $('#feedback-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(searchText) > -1);
                });
            });
        });
    </script>
</body>
</html>