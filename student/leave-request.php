<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';
requireRole('student');

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

$message = '';
$message_type = '';

// Apply for leave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $type = sanitizeInput($_POST['leave_type']);
        $start_date = sanitizeInput($_POST['start_date']);
        $end_date = sanitizeInput($_POST['end_date']);
        $reason = sanitizeInput($_POST['reason']);
        $validator = new Validator();
        $validator->required('type', $type)
                  ->required('start_date', $start_date)
                  ->required('end_date', $end_date)
                  ->required('reason', $reason)
                  ->maxLength('reason', $reason, 500);
        if ($validator->hasErrors()) {
            $message = implode('<br>', $validator->getErrors());
            $message_type = 'danger';
        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $message = 'End date must be after start date.';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("INSERT INTO leave_requests (user_id, type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            if ($stmt->execute([$user_id, $type, $start_date, $end_date, $reason])) {
                $message = 'Leave request submitted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error submitting request.';
                $message_type = 'danger';
            }
        }
    }
}

// Cancel Leave Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'cancel') {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {

        $message = 'Invalid security token.';
        $message_type = 'danger';

    } else {

        $leaveId = (int)$_POST['id'];

        $stmt = $db->prepare("
            DELETE FROM leave_requests
            WHERE id = ?
            AND user_id = ?
            AND status = 'Pending'
        ");

        if ($stmt->execute([$leaveId, $user_id])) {

            $message = 'Leave request cancelled successfully.';
            $message_type = 'success';

        } else {

            $message = 'Unable to cancel request.';
            $message_type = 'danger';

        }
    }
}

// Fetch leave history for this student
$stmt = $db->prepare("
    SELECT *
    FROM leave_requests
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$user_id]);

$leaves = $stmt->fetchAll();

$totalLeaves = count($leaves);

$pendingLeaves = 0;
$approvedLeaves = 0;
$rejectedLeaves = 0;

foreach ($leaves as $leave) {

    if ($leave['status'] == 'Pending') {
        $pendingLeaves++;
    }

    if ($leave['status'] == 'Approved') {
        $approvedLeaves++;
    }

    if ($leave['status'] == 'Rejected') {
        $rejectedLeaves++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request - Student</title>
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
            <a href="leave-request.php" class="nav-link active"><i class="fas fa-file-signature"></i> Leave Request</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Leave Requests</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#leaveModal"><i class="fas fa-plus"></i> Apply Leave</button>
            </div>
        </header>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">

    <div class="col-md-3">
        <div class="card stat-card gradient-4">
            <div class="card-body">
                <h5>Total Requests</h5>
                <h2><?= $totalLeaves ?></h2>
                <small>Submitted leaves</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card gradient-2">
            <div class="card-body">
                <h5>Pending</h5>
                <h2><?= $pendingLeaves ?></h2>
                <small>Awaiting approval</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card gradient-1">
            <div class="card-body">
                <h5>Approved</h5>
                <h2><?= $approvedLeaves ?></h2>
                <small>Accepted requests</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card gradient-3">
            <div class="card-body">
                <h5>Rejected</h5>
                <h2><?= $rejectedLeaves ?></h2>
                <small>Declined requests</small>
            </div>
        </div>
    </div>

</div>

            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-leave" class="form-control" placeholder="Search by type or reason..." style="max-width: 300px;">
            </div>

            <!-- Leave Requests Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="leave-table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($leaves) > 0): ?>
                                    <?php foreach ($leaves as $l): ?>
                                        <?php 
                                            $statusClass = $l['status'] == 'Pending' ? 'warning' : ($l['status'] == 'Approved' ? 'success' : 'danger');
                                            $statusText = $l['status'];
                                            $isPending = $l['status'] == 'Pending';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($l['type']) ?></td>
                                            <td><?= date('d M Y', strtotime($l['start_date'])) ?></td>
                                            <td><?= date('d M Y', strtotime($l['end_date'])) ?></td>
                                            <td>
<?php
$days = (strtotime($l['end_date']) - strtotime($l['start_date'])) / 86400 + 1;
echo $days;
?>
</td>
                                            <td title="<?= htmlspecialchars($l['reason']) ?>">
    <?= htmlspecialchars(substr($l['reason'], 0, 30)) . (strlen($l['reason']) > 30 ? '...' : '') ?>
</td>
                                            <td><span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span></td>
                                            <td><?= date('d M Y, h:i A', strtotime($l['created_at'])) ?></td>
                                            <td>
                                                <?php if ($isPending): ?>
                                                    <button class="btn btn-sm btn-danger cancel-leave" data-id="<?= $l['id'] ?>" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center">No leave requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Apply Leave Modal -->
    <div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-pen"></i> Apply for Leave</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="leave_type" class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="leave_type" required>
                                <option value="">Select</option>
                                <option value="Sick">Sick</option>
                                <option value="Casual">Casual</option>
                                <option value="Annual">Annual</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reason" rows="3" required></textarea>
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

    <!-- Cancel Leave Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger"></i> Cancel Leave Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id" id="cancel-id">
                        <p>Are you sure you want to cancel this leave request? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Cancel Request</button>
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
            // Search filter
            $('#search-leave').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#leave-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });

            // Set delete id for cancellation
            $('.cancel-leave').on('click', function() {
                $('#cancel-id').val($(this).data('id'));
            });
        });
    </script>
</body>
</html>