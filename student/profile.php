<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';
requireRole('student');

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Fetch student data with user email
$stmt = $db->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$student = $stmt->fetch();

if (!$student) {
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
                $stmt = $db->prepare("UPDATE students SET phone = :phone, address = :address WHERE user_id = :user_id");
                if ($stmt->execute(['phone' => $phone, 'address' => $address, 'user_id' => $user_id])) {
                    $message = 'Profile updated successfully.';
                    $message_type = 'success';
                    // Refresh student data
                    $stmt = $db->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = :user_id");
                    $stmt->execute(['user_id' => $user_id]);
                    $student = $stmt->fetch();
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
                    $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                    $uploadPath = '../uploads/profiles/' . $filename;
                    // Ensure directory exists
                    if (!is_dir('../uploads/profiles/')) {
                        mkdir('../uploads/profiles/', 0777, true);
                    }
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                        // Remove old photo if exists
                        if ($student['profile_image'] && file_exists('../uploads/profiles/' . $student['profile_image'])) {
                            unlink('../uploads/profiles/' . $student['profile_image']);
                        }
                        // Update database
                        $stmt = $db->prepare("UPDATE students SET profile_image = :img WHERE user_id = :user_id");
                        if ($stmt->execute(['img' => $filename, 'user_id' => $user_id])) {
                            $message = 'Profile photo updated.';
                            $message_type = 'success';
                            // Refresh student data
                            $stmt = $db->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = :user_id");
                            $stmt->execute(['user_id' => $user_id]);
                            $student = $stmt->fetch();
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
    <title>My Profile - Student</title>
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
            <a href="profile.php" class="nav-link active"><i class="fas fa-user"></i> Profile</a>
            <a href="courses.php" class="nav-link"><i class="fas fa-book"></i> My Courses</a>
            <a href="timetable.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Timetables</a>
            <a href="attendance.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="assignments.php" class="nav-link"><i class="fas fa-tasks"></i> Assignments</a>
            <a href="marks.php" class="nav-link"><i class="fas fa-chart-bar"></i> Marks</a>
            <a href="announcements.php" class="nav-link"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="events.php" class="nav-link"><i class="fas fa-calendar-check"></i> Events</a>
            <a href="feedback.php" class="nav-link"><i class="fas fa-star"></i> Feedback</a>
            <a href="leave-request.php" class="nav-link"><i class="fas fa-file-signature"></i> Leave Request</a>
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
                <!-- Profile Info -->
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <?php if ($student['profile_image']): ?>
                                <img src="../uploads/profiles/<?= htmlspecialchars($student['profile_image']) ?>" class="rounded-circle img-thumbnail" width="150" height="150" alt="Profile">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-8x text-muted"></i>
                            <?php endif; ?>
                            <h5 class="mt-2"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h5>
                            <p class="text-muted"><?= htmlspecialchars($student['student_id']) ?></p>
                            <p><?= htmlspecialchars($student['email']) ?></p>
                            <hr>
                            <?php
$deptName = 'N/A';

if (!empty($student['department_id'])) {
    $stmtDept = $db->prepare("SELECT name FROM departments WHERE id = ?");
    $stmtDept->execute([$student['department_id']]);
    $deptName = $stmtDept->fetchColumn() ?: 'N/A';
}
?>

<p><strong>Department:</strong> <?= htmlspecialchars($deptName) ?></p>
                            <p><strong>Semester:</strong> <?= $student['semester'] ?? 'N/A' ?></p>
                            <p><strong>Enrollment Year:</strong> <?= $student['enrollment_year'] ?? 'N/A' ?></p>
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

                <!-- Edit Profile -->
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
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($student['first_name']) ?>" disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Last Name (read-only)</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($student['last_name']) ?>" disabled>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label>Email (read-only)</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label>Student ID (read-only)</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['student_id']) ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label for="phone">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="address">Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
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