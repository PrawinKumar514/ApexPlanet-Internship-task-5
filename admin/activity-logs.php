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

// Handle delete operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM activity_logs WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'Log entry deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting log entry.';
                $message_type = 'danger';
            }
        } elseif ($action === 'clear_all') {
            // Optional: ask for confirmation via JS; we'll still handle it
            $stmt = $db->prepare("DELETE FROM activity_logs");
            if ($stmt->execute()) {
                $message = 'All logs cleared successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error clearing logs.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch logs with user email
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$params = [];

if (!empty($search)) {

    $searchCondition = "
        WHERE u.email LIKE :search1
        OR l.action LIKE :search2
        OR l.description LIKE :search3
        OR l.ip_address LIKE :search4
    ";

    $searchValue = '%' . $search . '%';

    $params = [
        'search1' => $searchValue,
        'search2' => $searchValue,
        'search3' => $searchValue,
        'search4' => $searchValue
    ];
}

$sql = "SELECT l.*, u.email 
        FROM activity_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        $searchCondition
        ORDER BY l.created_at DESC";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->execute($params);
}
else {
    $stmt->execute();
}
$logs = $stmt->fetchAll();

$totalLogs = $db->query(
    "SELECT COUNT(*) FROM activity_logs"
)->fetchColumn();

$loginLogs = $db->query(
    "SELECT COUNT(*) FROM activity_logs
     WHERE action LIKE '%login%'"
)->fetchColumn();

$deleteLogs = $db->query(
    "SELECT COUNT(*) FROM activity_logs
     WHERE action LIKE '%delete%'"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin</title>
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
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="activity-logs.php" class="nav-link active"><i class="fas fa-history"></i> Activity Logs</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Activity Logs</h3>
            <div class="ms-auto">

    <button class="btn btn-sm btn-dark-mode">
        <i class="fas fa-moon"></i>
    </button>

    <span class="badge bg-primary ms-2">
        Total: <?= $totalLogs ?>
    </span>
                <button class="btn btn-sm btn-danger" id="clear-all-logs" data-bs-toggle="modal" data-bs-target="#clearAllModal">
                    <i class="fas fa-trash-alt"></i> Clear All
                </button>
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
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h2><?= $totalLogs ?></h2>
                <p>Total Logs</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h2><?= $loginLogs ?></h2>
                <p>Login Activities</p>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h2><?= $deleteLogs ?></h2>
                <p>Delete Activities</p>
            </div>
        </div>
    </div>
</div>

            <!-- Search -->
            <div class="mb-3">
                <form method="GET" action="" class="d-flex" style="max-width: 400px;">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search by user, action, description..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="activity-logs.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="logs-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Timestamp</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?= $counter++ ?></td>
                                            <td>
    <i class="fas fa-user text-primary"></i>
    <?= htmlspecialchars($log['email'] ?? 'System') ?>
</td>
                                            <td>

<?php
$badge = 'bg-secondary';

if (stripos($log['action'],'login') !== false)
    $badge = 'bg-success';

elseif (stripos($log['action'],'delete') !== false)
    $badge = 'bg-danger';

elseif (stripos($log['action'],'update') !== false)
    $badge = 'bg-warning text-dark';

elseif (stripos($log['action'],'create') !== false)
    $badge = 'bg-primary';
?>

<span class="badge <?= $badge ?>">
    <?= htmlspecialchars($log['action']) ?>
</span>

</td>
                                            <td><?= htmlspecialchars($log['description']) ?></td>
                                            <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                            <td>
<span class="text-primary">
<?= date('d M Y', strtotime($log['created_at'])) ?>
</span>
<br>
<small class="text-muted">
<?= date('h:i:s A', strtotime($log['created_at'])) ?>
</small>
</td>
                                            <td>
                                                <button class="btn btn-sm btn-danger delete-log" data-id="<?= $log['id'] ?>"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center"><?php if(!empty($search)): ?>
    No logs match your search.
<?php else: ?>
    No activity logs found.
<?php endif; ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Single Log Modal -->
    <div class="modal fade" id="deleteLogModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Log Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-log-id">
                        <p>Are you sure you want to delete this log entry? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Clear All Logs Modal -->
    <div class="modal fade" id="clearAllModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger"></i> Clear All Logs</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="clear_all">
                        <p class="text-danger"><strong>Warning:</strong> This will permanently delete ALL activity logs. This action cannot be undone.</p>
                        <p>Are you sure you want to continue?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Yes, Delete All</button>
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
            // Delete single log
            $('.delete-log').on('click', function() {
                const id = $(this).data('id');
                $('#delete-log-id').val(id);
                $('#deleteLogModal').modal('show');
            });

            // Clear all logs with confirmation already in modal
            // No extra JS needed
        });
    </script>
</body>
</html>