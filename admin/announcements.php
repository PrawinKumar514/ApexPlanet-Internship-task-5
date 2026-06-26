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
            $content = sanitizeInput($_POST['content']);
            $target_role = sanitizeInput($_POST['target_role'] ?? 'all');
            $created_by = $_SESSION['user_id'];

            $validator = new Validator();
            $validator->required('title', $title)->maxLength('title', $title, 100)
                      ->required('content', $content);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("INSERT INTO announcements (title, content, target_role, created_by) 
                                      VALUES (:title, :content, :target, :created_by)");
                if ($stmt->execute(['title' => $title, 'content' => $content, 'target' => $target_role, 'created_by' => $created_by])) {
                    $message = 'Announcement added successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error adding announcement.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $title = sanitizeInput($_POST['title']);
            $content = sanitizeInput($_POST['content']);
            $target_role = sanitizeInput($_POST['target_role'] ?? 'all');

            $validator = new Validator();
            $validator->required('title', $title)->maxLength('title', $title, 100)
                      ->required('content', $content);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("UPDATE announcements SET title = :title, content = :content, target_role = :target WHERE id = :id");
                if ($stmt->execute(['title' => $title, 'content' => $content, 'target' => $target_role, 'id' => $id])) {
                    $message = 'Announcement updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating announcement.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM announcements WHERE id = :id");
            if ($stmt->execute(['id' => $id])) {
                $message = 'Announcement deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting announcement.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch announcements with creator email
$announcements = $db->query("SELECT a.*, u.email as creator_email 
                             FROM announcements a 
                             LEFT JOIN users u ON a.created_by = u.id 
                             ORDER BY a.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Admin</title>
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
            <a href="announcements.php" class="nav-link active"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="feedback.php" class="nav-link"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-history"></i> Activity Logs</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>Announcements</h3>
            <div class="ms-auto">
                <button class="btn btn-sm btn-dark-mode"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal"><i class="fas fa-plus"></i> Add Announcement</button>
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
                <input type="text" id="search-announcement" class="form-control" placeholder="Search announcements by title or content..." style="max-width: 300px;">
                <div id="announcement-results"></div>
            </div>

            <!-- Announcements Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="announcements-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Content</th>
                                    <th>Target</th>
                                    <th>Created By</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($announcements as $a): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($a['title']) ?></strong></td>
                                        <td><?= htmlspecialchars(substr($a['content'], 0, 60)) . (strlen($a['content']) > 60 ? '...' : '') ?></td>
                                        <td><span class="badge <?= $a['target_role'] == 'all' ? 'bg-primary' : 'bg-info' ?>"><?= ucfirst($a['target_role']) ?></span></td>
                                        <td><?= htmlspecialchars($a['creator_email'] ?? 'System') ?></td>
                                        <td><?= date('d M Y, h:i A', strtotime($a['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-announcement" 
                                                    data-id="<?= $a['id'] ?>"
                                                    data-title="<?= htmlspecialchars($a['title']) ?>"
                                                    data-content="<?= htmlspecialchars($a['content']) ?>"
                                                    data-target="<?= $a['target_role'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-announcement" data-id="<?= $a['id'] ?>"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($announcements) === 0): ?>
                                    <tr><td colspan="6" class="text-center">No announcements found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Announcement Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Announcement</h5>
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
                            <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="content" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="target_role" class="form-label">Target Audience</label>
                            <select class="form-select" name="target_role">
                                <option value="all">All (Everyone)</option>
                                <option value="admin">Admin Only</option>
                                <option value="faculty">Faculty Only</option>
                                <option value="student">Students Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Announcement</h5>
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
                            <label for="edit-content" class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="content" id="edit-content" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit-target" class="form-label">Target Audience</label>
                            <select class="form-select" name="target_role" id="edit-target">
                                <option value="all">All (Everyone)</option>
                                <option value="admin">Admin Only</option>
                                <option value="faculty">Faculty Only</option>
                                <option value="student">Students Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Announcement Modal -->
    <div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash text-danger"></i> Delete Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p>Are you sure you want to delete this announcement? This action cannot be undone.</p>
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
            $('.edit-announcement').on('click', function() {
                const data = $(this).data();
                $('#edit-id').val(data.id);
                $('#edit-title').val(data.title);
                $('#edit-content').val(data.content);
                $('#edit-target').val(data.target);
                $('#editAnnouncementModal').modal('show');
            });

            // Delete button click
            $('.delete-announcement').on('click', function() {
                const id = $(this).data('id');
                $('#delete-id').val(id);
                $('#deleteAnnouncementModal').modal('show');
            });

            // Live search filter
            $('#search-announcement').on('keyup', function() {
                const searchText = $(this).val().toLowerCase();
                $('#announcements-table tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(searchText) > -1);
                });
            });
        });
    </script>
</body>
</html>