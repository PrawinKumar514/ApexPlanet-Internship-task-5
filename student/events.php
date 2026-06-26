<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
requireRole('student');

$db = Database::getInstance()->getConnection();

// Fetch all events (upcoming and past)
$events = $db->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Student</title>
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
            <a href="events.php" class="nav-link active"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="feedback.php" class="nav-link"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-request.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Request</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Events</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">Total: <?= count($events) ?></span>
            </div>
        </header>

        <div class="content">

    <!-- Search -->
    <div class="mb-3">
        <input type="text" id="search-event" class="form-control" placeholder="Search events by title or venue..." style="max-width: 300px;">
    </div>

    <!-- Total Events Card -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card gradient-2">
                <div class="card-body">
                    <h5>Total Events</h5>
                    <h2><?= count($events) ?></h2>
                    <small>Campus Events</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Events Table -->
    <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="events-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Date & Time</th>
                                    <th>Venue</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($events) > 0): ?>
                                    <?php foreach ($events as $e): ?>

<?php
$isUpcoming = strtotime($e['event_date']) >= time();
?>

<tr>
    <td>
        <strong><?= htmlspecialchars($e['title']) ?></strong>

        <?php if($isUpcoming): ?>
            <span class="badge bg-success ms-2">Upcoming</span>
        <?php else: ?>
            <span class="badge bg-secondary ms-2">Completed</span>
        <?php endif; ?>
    </td>
                                            <td><?= date('d M Y, h:i A', strtotime($e['event_date'])) ?></td>
                                            <td><?= htmlspecialchars($e['venue'] ?: 'Not specified') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info view-event" 
                                                        data-bs-toggle="modal" data-bs-target="#eventModal"
                                                        data-title="<?= htmlspecialchars($e['title']) ?>"
                                                        data-description="<?= htmlspecialchars($e['description'] ?? 'No description provided.') ?>"
                                                        data-date="<?= date('d M Y, h:i A', strtotime($e['event_date'])) ?>"
                                                        data-venue="<?= htmlspecialchars($e['venue'] ?: 'Not specified') ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center">No events found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Detail Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="event-title">Title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="event-description"></p>
                    <hr>
                    <p><strong>Date & Time:</strong> <span id="event-date"></span></p>
                    <p><strong>Venue:</strong> <span id="event-venue"></span></p>
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
            $('#search-event').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#events-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });

            // Populate modal with event details
            $('.view-event').on('click', function() {
                $('#event-title').text($(this).data('title'));
                $('#event-description').text($(this).data('description'));
                $('#event-date').text($(this).data('date'));
                $('#event-venue').text($(this).data('venue'));
            });
        });
    </script>
</body>
</html>