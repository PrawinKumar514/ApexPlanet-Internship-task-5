<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
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

// Submit feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $subject = sanitizeInput($_POST['subject']);
        $messageText = sanitizeInput($_POST['message']);
        $validator = new Validator();
        $validator->required('subject', $subject)->maxLength('subject', $subject, 100)
                  ->required('message', $messageText)->maxLength('message', $messageText, 500);
        if ($validator->hasErrors()) {
            $message = implode('<br>', $validator->getErrors());
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("INSERT INTO feedback (student_id, subject, message, status) VALUES (:sid, :sub, :msg, 'New')");
            if ($stmt->execute(['sid' => $student_id, 'sub' => $subject, 'msg' => $messageText])) {
                $message = 'Feedback submitted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error submitting feedback.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch user feedback history
$feedbacks = $db->query("SELECT * FROM feedback WHERE student_id = $student_id ORDER BY created_at DESC")->fetchAll();
$totalFeedback = count($feedbacks);

$newFeedback = 0;
$resolvedFeedback = 0;

foreach ($feedbacks as $f) {
    if ($f['status'] == 'New') {
        $newFeedback++;
    }

    if ($f['status'] == 'Resolved') {
        $resolvedFeedback++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Student</title>
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
            <a href="feedback.php" class="nav-link active"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-request.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Request</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Feedback</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#feedbackModal"><i class="fas fa-plus"></i> New Feedback</button>
            </div>
        </header>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card gradient-1">
            <div class="card-body">
                <h5>Total Feedback</h5>
                <h2><?= $totalFeedback ?></h2>
                <small>Submitted feedback</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card stat-card gradient-2">
            <div class="card-body">
                <h5>Pending</h5>
                <h2><?= $newFeedback ?></h2>
                <small>Awaiting response</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card stat-card gradient-4">
            <div class="card-body">
                <h5>Resolved</h5>
                <h2><?= $resolvedFeedback ?></h2>
                <small>Completed feedback</small>
            </div>
        </div>
    </div>
</div>

            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-feedback" class="form-control" placeholder="Search by subject or message..." style="max-width: 300px;">
            </div>

            <!-- Feedback Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="feedback-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Reply</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($feedbacks) > 0): ?>
                                    <?php foreach ($feedbacks as $f): ?>
                                        <?php 
                                            $statusClass = $f['status'] == 'New' ? 'danger' : ($f['status'] == 'Read' ? 'warning' : 'success');
                                            $statusText = $f['status'];
                                            $hasReply = !empty($f['reply']);
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($f['subject']) ?></strong></td>
                                            <td><?= htmlspecialchars(substr($f['message'], 0, 40)) . (strlen($f['message']) > 40 ? '...' : '') ?></td>
                                            <td><?= $hasReply ? htmlspecialchars(substr($f['reply'], 0, 30)) . (strlen($f['reply']) > 30 ? '...' : '') : '<span class="text-muted">No reply yet</span>' ?></td>
                                            <td><span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span></td>
                                            <td><?= date('d M Y, h:i A', strtotime($f['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info view-feedback" 
                                                        data-bs-toggle="modal" data-bs-target="#viewFeedbackModal"
                                                        data-subject="<?= htmlspecialchars($f['subject']) ?>"
                                                        data-message="<?= htmlspecialchars($f['message']) ?>"
                                                        data-reply="<?= htmlspecialchars($f['reply'] ?? 'No reply yet.') ?>"
                                                        data-status="<?= $f['status'] ?>"
                                                        data-date="<?= date('d M Y, h:i A', strtotime($f['created_at'])) ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No feedback submitted yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-pen"></i> Submit Feedback</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="subject" placeholder="e.g., Course Content Feedback" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="message" rows="4" placeholder="Write your feedback here..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Feedback Modal -->
    <div class="modal fade" id="viewFeedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Subject:</strong> <span id="view-subject"></span></p>
                    <p><strong>Message:</strong> <span id="view-message"></span></p>
                    <hr>
                    <p><strong>Reply:</strong> <span id="view-reply"></span></p>
                    <hr>
                    <p><strong>Status:</strong> <span id="view-status"></span></p>
                    <p><strong>Submitted On:</strong> <span id="view-date"></span></p>
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
            // Search filter
            $('#search-feedback').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#feedback-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });

            // Populate view modal
            $('.view-feedback').on('click', function() {
                $('#view-subject').text($(this).data('subject'));
                $('#view-message').text($(this).data('message'));
                $('#view-reply').text($(this).data('reply'));
                $('#view-status').text($(this).data('status'));
                $('#view-date').text($(this).data('date'));
            });
        });
    </script>
</body>
</html>