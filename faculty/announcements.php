<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
requireRole('faculty');

$db = Database::getInstance()->getConnection();

// Fetch announcements targeted to faculty or all
$announcements = $db->query("SELECT a.*, u.email as creator_email 
                             FROM announcements a 
                             LEFT JOIN users u ON a.created_by = u.id 
                             WHERE a.target_role IN ('all','faculty')
                             ORDER BY a.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Faculty</title>
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
            <a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> Assignments</a>
            <a href="students.php" class="nav-link"><i class="fas fa-users"></i> Students</a>
            <a href="announcements.php" class="nav-link active"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> Profile</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Announcements</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <span class="badge bg-primary ms-2">Total: <?= count($announcements) ?></span>
            </div>
        </header>

        <div class="content">
            <!-- Search -->
            <div class="mb-3">
                <input type="text" id="search-announce" class="form-control" placeholder="Search announcements by title or content..." style="max-width: 300px;">
            </div>

            <!-- Announcements Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list me-1"></i> Announcements
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="announce-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Content</th>
                                    <th>Posted By</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($announcements) > 0): ?>
                                    <?php foreach ($announcements as $a): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
                                            <td><?= htmlspecialchars(substr($a['content'], 0, 60)) . (strlen($a['content']) > 60 ? '...' : '') ?></td>
                                            <td><?= htmlspecialchars($a['creator_email'] ?? 'System') ?></td>
                                            <td><?= date('d M Y, h:i A', strtotime($a['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info view-announce" 
                                                        data-bs-toggle="modal" data-bs-target="#announceModal"
                                                        data-title="<?= htmlspecialchars($a['title']) ?>"
                                                        data-content="<?= htmlspecialchars($a['content']) ?>"
                                                        data-creator="<?= htmlspecialchars($a['creator_email'] ?? 'System') ?>"
                                                        data-date="<?= date('d M Y, h:i A', strtotime($a['created_at'])) ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">No announcements found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Announcement Modal -->
    <div class="modal fade" id="announceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="announce-title">Title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Content:</strong></p>
                    <p id="announce-content"></p>
                    <hr>
                    <p><strong>Posted By:</strong> <span id="announce-creator"></span></p>
                    <p><strong>Date:</strong> <span id="announce-date"></span></p>
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
            $('#search-announce').on('keyup', function() {
                const search = $(this).val().toLowerCase();
                $('#announce-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(search) > -1);
                });
            });

            // Populate modal with announcement details
            $('.view-announce').on('click', function() {
                const data = $(this).data();
                $('#announce-title').text(data.title);
                $('#announce-content').text(data.content);
                $('#announce-creator').text(data.creator);
                $('#announce-date').text(data.date);
            });
        });
    </script>
</body>
</html>