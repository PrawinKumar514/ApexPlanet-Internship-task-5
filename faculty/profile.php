<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';
requireRole('faculty');

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Fetch faculty data with user email and department
$stmt = $db->prepare("SELECT f.*, u.email, d.name as department_name 
                       FROM faculty f 
                       JOIN users u ON f.user_id = u.id 
                       LEFT JOIN departments d ON f.department_id = d.id 
                       WHERE f.user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$faculty = $stmt->fetch();

if (!$faculty) {
    redirect('../auth/logout.php');
}

$message = '';
$message_type = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'];
        if ($action === 'update_profile') {
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $address = sanitizeInput($_POST['address'] ?? '');
            $validator = new Validator();
            $validator->maxLength('phone', $phone, 15)
                      ->maxLength('address', $address, 255);
            if ($validator->hasErrors()) {
                $message = implode('<br>', $validator->getErrors());
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("UPDATE faculty SET phone = :phone, address = :address WHERE user_id = :user_id");
                if ($stmt->execute(['phone' => $phone, 'address' => $address, 'user_id' => $user_id])) {
                    $message = 'Profile updated successfully.';
                    $message_type = 'success';
                    // Refresh data
                    $stmt = $db->prepare("SELECT f.*, u.email, d.name as department_name 
                                           FROM faculty f 
                                           JOIN users u ON f.user_id = u.id 
                                           LEFT JOIN departments d ON f.department_id = d.id 
                                           WHERE f.user_id = :user_id");
                    $stmt->execute(['user_id' => $user_id]);
                    $faculty = $stmt->fetch();
                } else {
                    $message = 'Error updating profile.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'change_password') {
            $current = $_POST['current_password'];
            $new = $_POST['new_password'];
            $confirm = $_POST['confirm_password'];

            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
            $stmt->execute(['id' => $user_id]);
            $user = $stmt->fetch();
            if (!password_verify($current, $user['password'])) {
                $message = 'Current password is incorrect.';
                $message_type = 'danger';
            } elseif ($new !== $confirm) {
                $message = 'New passwords do not match.';
                $message_type = 'danger';
            } elseif (strlen($new) < 8) {
                $message = 'New password must be at least 8 characters.';
                $message_type = 'danger';
            } else {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = :pass WHERE id = :id");
                if ($stmt->execute(['pass' => $hashed, 'id' => $user_id])) {
                    $message = 'Password changed successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Error changing password.';
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'upload_photo') {
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['profile_image']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowed)) {
                    $message = 'Invalid image format. Only JPG, PNG, GIF allowed.';
                    $message_type = 'danger';
                } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
                    $message = 'Image size exceeds 2MB.';
                    $message_type = 'danger';
                } else {
                    $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $filename = 'faculty_' . $user_id . '_' . time() . '.' . $ext;
                    $uploadPath = '../uploads/profiles/';
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }
                    $fullPath = $uploadPath . $filename;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $fullPath)) {
                        // Remove old photo
                        if ($faculty['profile_image'] && file_exists($uploadPath . $faculty['profile_image'])) {
                            unlink($uploadPath . $faculty['profile_image']);
                        }
                        // Update DB
                        $stmt = $db->prepare("UPDATE faculty SET profile_image = :img WHERE user_id = :user_id");
                        if ($stmt->execute(['img' => $filename, 'user_id' => $user_id])) {
                            $message = 'Profile photo updated.';
                            $message_type = 'success';
                            // Refresh data
                            $stmt = $db->prepare("SELECT f.*, u.email, d.name as department_name 
                                                   FROM faculty f 
                                                   JOIN users u ON f.user_id = u.id 
                                                   LEFT JOIN departments d ON f.department_id = d.id 
                                                   WHERE f.user_id = :user_id");
                            $stmt->execute(['user_id' => $user_id]);
                            $faculty = $stmt->fetch();
                        } else {
                            $message = 'Database error.';
                            $message_type = 'danger';
                        }
                    } else {
                        $message = 'Failed to upload image.';
                        $message_type = 'danger';
                    }
                }
            } else {
                $message = 'Please select a file.';
                $message_type = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Faculty</title>
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
            <a href="announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="leave-requests.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Requests</a>
            <a href="profile.php" class="nav-link active"><i class="fas fa-user"></i> Profile</a>
            <a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <h3>My Profile</h3>
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

            <div class="row g-4">
                <!-- Profile Info (Left) -->
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <?php if ($faculty['profile_image']): ?>
                                <img src="../uploads/profiles/<?= htmlspecialchars($faculty['profile_image']) ?>" class="rounded-circle img-thumbnail" width="150" height="150" alt="Profile">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-8x text-muted"></i>
                            <?php endif; ?>
                            <h5 class="mt-2"><?= htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) ?></h5>
                            <p class="text-muted"><?= htmlspecialchars($faculty['faculty_id']) ?></p>
                            <p><?= htmlspecialchars($faculty['email']) ?></p>
                            <hr>
                            <p><strong>Department:</strong> <?= htmlspecialchars($faculty['department_name'] ?? 'N/A') ?></p>
                            <p><strong>Designation:</strong> <?= htmlspecialchars($faculty['designation']) ?></p>
                            <p><strong>Qualification:</strong> <?= htmlspecialchars($faculty['qualification'] ?? 'N/A') ?></p>
                            <p><strong>Joining Date:</strong>
    <?= !empty($faculty['joining_date'])
        ? date('d M Y', strtotime($faculty['joining_date']))
        : 'N/A'; ?>
</p>
                        </div>
                    </div>

                    <!-- Upload Photo -->
                    <div class="card mt-3">
                        <div class="card-header">Update Photo</div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="upload_photo">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="profile_image" accept="image/*" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Upload</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile (Right) -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">Edit Profile</div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>First Name (read-only)</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($faculty['first_name']) ?>" disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Last Name (read-only)</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($faculty['last_name']) ?>" disabled>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label>Email (read-only)</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($faculty['email']) ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label>Faculty ID (read-only)</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($faculty['faculty_id']) ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label for="phone">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($faculty['phone'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="address">Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($faculty['address'] ?? '') ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="card mt-3">
                        <div class="card-header">Change Password</div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="change_password">
                                <div class="mb-3">
                                    <label>Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label>New Password (min 8 characters)</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label>Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-warning">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>