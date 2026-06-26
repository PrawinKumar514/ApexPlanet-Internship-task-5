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

// Handle status update and delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'update_status') {
            $id = (int)$_POST['id'];
            $status = sanitizeInput($_POST['status']);
            $approved_by = $_SESSION['user_id'];

            // Validate status
            if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
                $message = 'Invalid status.';
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("UPDATE leave_requests SET status = :status, approved_by = :approved_by WHERE id = :id");
                if ($stmt->execute(['status' => $status, 'approved_by' => $approved_by, 'id' => $id])) {
                    $message = 'Leave request status updated successfully.';
                    $message_type = 'success';
                    
                    // Get user email to send notification (optional)
                    // Here we would send notification via email if needed
                } else {
                    $message = 'Error updating status.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM leave_requests WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'Leave request deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting leave request.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch leave requests with user details (email and role)
$requests = $db->query("SELECT l.*, u.email, 
                        CASE 
                            WHEN u.role = 'student' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE user_id = u.id)
                            WHEN u.role = 'faculty' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM faculty WHERE user_id = u.id)
                            ELSE u.email
                        END as user_name,
                        admin.email as admin_name
                        FROM leave_requests l
                        LEFT JOIN users u ON l.user_id = u.id
                        LEFT JOIN users admin ON l.approved_by = admin.id
                        ORDER BY l.created_at DESC")->fetchAll();

                        $pendingCount = count(array_filter($requests, fn($r) => $r['status'] == 'Pending'));

$approvedCount = count(array_filter($requests, fn($r) => $r['status'] == 'Approved'));

$rejectedCount = count(array_filter($requests, fn($r) => $r['status'] == 'Rejected'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - Admin</title>
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
            <a href="feedback.php" class="nav-link"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-requests.php" class="nav-link active"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-history"></i> Activity Logs</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Leave Requests</h3>
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

            <div class="row mb-4">

    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= $pendingCount ?></h3>
                <p>Pending Requests</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= $approvedCount ?></h3>
                <p>Approved Requests</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3><?= $rejectedCount ?></h3>
                <p>Rejected Requests</p>
            </div>
        </div>
    </div>

</div>

            <!-- Search and Filter -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <input type="text" id="search-leave" class="form-control" placeholder="Search by user, type, or reason...">
                </div>
                <div class="col-md-3">
                    <select id="filter-status" class="form-select">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <span class="text-muted">Total: <?= count($requests) ?></span>
                </div>
            </div>

            <!-- Leave Requests Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="leave-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Approved By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($r['user_name'] ?? $r['email']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($r['email']) ?></small>
                                        </td>
                                        <?php
$typeClass = match($r['type']) {
    'Sick' => 'bg-danger',
    'Casual' => 'bg-primary',
    'Annual' => 'bg-success',
    default => 'bg-secondary'
};
?>

<td>
<span class="badge <?= $typeClass ?>">
<?= htmlspecialchars($r['type']) ?>
</span>
</td>
                                        <td><?= date('d M Y', strtotime($r['start_date'])) ?></td>
                                        <td><?= date('d M Y', strtotime($r['end_date'])) ?></td>

                                        <td>
<?= ((strtotime($r['end_date']) - strtotime($r['start_date'])) / 86400) + 1 ?>
</td>
                                        <td>
    <?= htmlspecialchars(substr($r['reason'], 0, 25)) ?>
    <?php if(strlen($r['reason']) > 50): ?>
        ...
    <?php endif; ?>
</td>
                                        <td>
    <span class="badge <?= $r['status'] == 'Pending' ? 'bg-warning text-dark' : ($r['status'] == 'Approved' ? 'bg-success' : 'bg-danger') ?>">

        <?php if($r['status'] == 'Pending'): ?>
            <i class="fas fa-clock"></i> Pending

        <?php elseif($r['status'] == 'Approved'): ?>
            <i class="fas fa-check-circle"></i> Approved

        <?php else: ?>
            <i class="fas fa-times-circle"></i> Rejected

        <?php endif; ?>

    </span>
</td>
<td>
<?= !empty($r['approved_by']) ? 'Admin' : '-' ?>
</td>
                                        <td>
                                            <button
class="btn btn-sm btn-primary view-reason"
data-reason="<?= htmlspecialchars($r['reason']) ?>">
<i class="fas fa-eye"></i>
</button>

<?php if ($r['status'] == 'Pending'): ?>
    <button class="btn btn-sm btn-success approve-leave" data-id="<?= $r['id'] ?>">
        <i class="fas fa-check"></i>
    </button>

    <button class="btn btn-sm btn-danger reject-leave" data-id="<?= $r['id'] ?>">
        <i class="fas fa-times"></i>
    </button>

<?php else: ?>

    <button class="btn btn-sm btn-info change-status"
            data-id="<?= $r['id'] ?>"
            data-status="<?= $r['status'] ?>">
        <i class="fas fa-edit"></i>
    </button>

<?php endif; ?>

<button class="btn btn-sm btn-danger delete-leave" data-id="<?= $r['id'] ?>">
    <i class="fas fa-trash"></i>
</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($requests) === 0): ?>
                                    <tr><td colspan="9" class="text-center">No leave requests found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Status Modal (for editing status) -->
    <div class="modal fade" id="changeStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Update Leave Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" id="status-id">
                        <div class="mb-3">
                            <label for="status-select" class="form-label">Status</label>
                            <select class="form-select" name="status" id="status-select">
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reasonModal" tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt"></i>
                    Leave Reason
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body">

                <p id="fullReason"></p>

            </div>

        </div>

    </div>

</div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteLeaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Leave Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this leave request? This action cannot be undone.</p>
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
            $('.view-reason').on('click', function() {

    let reason = $(this).data('reason');

    $('#fullReason').text(reason);

    $('#reasonModal').modal('show');

});
            // Approve button (direct action via POST)
            $('.approve-leave').on('click', function() {
                const id = $(this).data('id');
                if (confirm('Approve this leave request?')) {
                    // Submit form with status Approved
                    const form = $('<form method="POST" action=""></form>');
                    form.append('<input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">');
                    form.append('<input type="hidden" name="action" value="update_status">');
                    form.append('<input type="hidden" name="id" value="' + id + '">');
                    form.append('<input type="hidden" name="status" value="Approved">');
                    $('body').append(form);
                    form.submit();
                }
            });

            // Reject button
            $('.reject-leave').on('click', function() {
                const id = $(this).data('id');
                if (confirm('Reject this leave request?')) {
                    const form = $('<form method="POST" action=""></form>');
                    form.append('<input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">');
                    form.append('<input type="hidden" name="action" value="update_status">');
                    form.append('<input type="hidden" name="id" value="' + id + '">');
                    form.append('<input type="hidden" name="status" value="Rejected">');
                    $('body').append(form);
                    form.submit();
                }
            });

            // Change status button (open modal)
            $('.change-status').on('click', function() {
                const id = $(this).data('id');
                const status = $(this).data('status');
                $('#status-id').val(id);
                $('#status-select').val(status);
                $('#changeStatusModal').modal('show');
            });

            // Delete button
            $('.delete-leave').on('click', function() {
                const id = $(this).data('id');
                $('#delete-id').val(id);
                $('#deleteLeaveModal').modal('show');
            });

            // Live search and filter
            $('#search-leave').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                filterTable();
            });
            $('#filter-status').on('change', function() {
                filterTable();
            });

            function filterTable() {
                const search = $('#search-leave').val().toLowerCase();
                const status = $('#filter-status').val();
                $('#leave-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    const rowStatus = $(this)
.find('td:eq(6) .badge')
.text()
.toLowerCase();
                    let show = true;
                    if (search && rowText.indexOf(search) === -1) show = false;
                    if (
    status &&
    !rowStatus.includes(status.toLowerCase())
){
    show = false;
}
                    $(this).toggle(show);
                });
            }
        });
    </script>
</body>
</html>