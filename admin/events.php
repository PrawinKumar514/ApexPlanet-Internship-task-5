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
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $event_date = sanitizeInput($_POST['event_date']);
            $venue = sanitizeInput($_POST['venue']);
            $created_by = $_SESSION['user_id'];

            $validator = new Validator();
            $validator->required('title', $title)->maxLength('title', $title, 100)
                      ->maxLength('description', $description, 500)
                      ->required('event_date', $event_date)
                      ->maxLength('venue', $venue, 100);
            if ($validator->hasErrors()) {
    $message = implode('<br>', $validator->getErrors());
    $message_type = 'danger';
} elseif (strtotime($event_date) < time()) {
    $message = 'Event date cannot be in the past.';
    $message_type = 'danger';
} else {
                $stmt = $db->prepare("INSERT INTO events (title, description, event_date, venue, created_by) 
                                      VALUES (:title, :desc, :date, :venue, :created_by)");
                if ($stmt->execute(['title' => $title, 'desc' => $description, 'date' => $event_date, 
                                   'venue' => $venue, 'created_by' => $created_by])) {
                    $message = 'Event added successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error adding event.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $event_date = sanitizeInput($_POST['event_date']);
            $venue = sanitizeInput($_POST['venue']);

            $validator = new Validator();
            $validator->required('title', $title)->maxLength('title', $title, 100)
                      ->maxLength('description', $description, 500)
                      ->required('event_date', $event_date)
                      ->maxLength('venue', $venue, 100);
            if ($validator->hasErrors()) {
    $message = implode('<br>', $validator->getErrors());
    $message_type = 'danger';
} elseif (strtotime($event_date) < time()) {
    $message = 'Event date cannot be in the past.';
    $message_type = 'danger';
} else {
                $stmt = $db->prepare("UPDATE events SET title = :title, description = :desc, event_date = :date, venue = :venue WHERE id = :id");
                if ($stmt->execute(['title' => $title, 'desc' => $description, 'date' => $event_date, 'venue' => $venue, 'id' => $id])) {
                    $message = 'Event updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating event.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM events WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'Event deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting event.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch events with creator email
$events = $db->query("SELECT e.*, u.email as creator_email 
                      FROM events e 
                      LEFT JOIN users u ON e.created_by = u.id 
                      ORDER BY e.event_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Admin</title>
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
            <a href="events.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="feedback.php" class="nav-link"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-history"></i> Activity Logs</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Events Management</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEventModal"><i class="fas fa-plus"></i> Add Event</button>
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
                <input type="text" id="search-event" class="form-control" placeholder="Search events by title or venue..." style="max-width: 300px;">
                <div id="event-results"></div>
            </div>

            <!-- Events Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="events-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
<th>Description</th>
<th>Date & Time</th>
<th>Status</th>
<th>Venue</th>
<th>Created By</th>
<th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $e): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($e['title']) ?></strong></td>
                                        <td><?= htmlspecialchars(substr($e['description'], 0, 50)) . (strlen($e['description']) > 50 ? '...' : '') ?></td>
                                        <td><?= date('d M Y, h:i A', strtotime($e['event_date'])) ?></td>

<td>
<?php if (strtotime($e['event_date']) >= time()): ?>
    <span class="badge bg-success">
    <i class="fas fa-calendar-check"></i> Upcoming
</span>
<?php else: ?>
    <span class="badge bg-danger">
    <i class="fas fa-check-circle"></i> Completed
</span>
<?php endif; ?>
</td>

<td><?= htmlspecialchars($e['venue']) ?></td>
                                        <td><?= htmlspecialchars($e['creator_email'] ?? 'System') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-event" 
                                                    data-id="<?= $e['id'] ?>"
                                                    data-title="<?= htmlspecialchars($e['title']) ?>"
                                                    data-description="<?= htmlspecialchars($e['description']) ?>"
                                                    data-event_date="<?= $e['event_date'] ?>"
                                                    data-venue="<?= htmlspecialchars($e['venue']) ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-event" data-id="<?= $e['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($events) === 0): ?>
                                    <tr><td colspan="7" class="text-center">No events found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="title" class="form-label">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_date" class="form-label">Event Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" name="event_date" min="<?= date('Y-m-d\TH:i') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="venue" class="form-label">Venue</label>
                                <input type="text" class="form-control" name="venue" placeholder="e.g., Auditorium, Room 101">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div class="modal fade" id="editEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-title" class="form-label">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="edit-title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit-description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit-description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-event_date" class="form-label">Event Date & Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" name="event_date" id="edit-event_date" min="<?= date('Y-m-d\TH:i') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-venue" class="form-label">Venue</label>
                                <input type="text" class="form-control" name="venue" id="edit-venue">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Event Modal -->
    <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this event? This action cannot be undone.</p>
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
            // Edit button click - populate modal
            $('.edit-event').on('click', function() {
                const data = $(this).data();
                $('#edit-id').val(data.id);
                $('#edit-title').val(data.title);
                $('#edit-description').val(data.description);
                $('#edit-event_date').val(data.event_date);
                $('#edit-venue').val(data.venue);
                $('#editEventModal').modal('show');
            });

            // Delete button click
            $('.delete-event').on('click', function() {
                const id = $(this).data('id');
                $('#delete-id').val(id);
                $('#deleteEventModal').modal('show');
            });

            // Live search filter
            $('#search-event').on('keyup', function() {
                const searchText = $(this).val().toLowerCase();
                $('#events-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(searchText) > -1);
                });
            });
        });
    </script>
</body>
</html>