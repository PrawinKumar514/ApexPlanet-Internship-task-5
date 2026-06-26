<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
if ($auth->isLoggedIn()) {
    redirect(getRoleBasedRedirect($_SESSION['role']));
}

$db = Database::getInstance()->getConnection();
$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $faculty_id = sanitizeInput($_POST['faculty_id']);
        $department_id = (int)$_POST['department_id'];
        $designation = sanitizeInput($_POST['designation']);

        $validator = new Validator();
        $validator->required('email', $email)->email('email', $email);
        $validator->required('password', $password)->minLength('password', $password, 8);
        $validator->required('confirm_password', $confirm)->match('confirm_password', $confirm, $password);
        $validator->required('first_name', $first_name)->maxLength('first_name', $first_name, 50);
        $validator->required('last_name', $last_name)->maxLength('last_name', $last_name, 50);
        $validator->required('faculty_id', $faculty_id)->maxLength('faculty_id', $faculty_id, 20);
        $validator->required('department_id', $department_id)->numeric('department_id', $department_id);
        $validator->required('designation', $designation)->maxLength('designation', $designation, 50);

        if ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            $extraData = [
                'faculty_id' => $faculty_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'department_id' => $department_id,
                'designation' => $designation
            ];
            $result = $auth->register($email, $password, 'faculty', $extraData);
            if ($result['status'] === 'success') {
                $_SESSION['verify_email'] = $email;
                redirect('verify-otp.php');
            } else {
                $error = $result['message'];
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
    <title>Faculty Registration - Smart Campus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8 col-lg-6">
                <div class="card glass-card shadow-lg p-4">
                    <div class="text-center mb-4">
                        <img src="../assets/images/logo.png" alt="Logo" height="60">
                        <h3 class="mt-3">Faculty Registration</h3>
                        <p class="text-muted">Create your faculty account</p>
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty ID</label>
                            <input type="text" class="form-control" id="faculty_id" name="faculty_id" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation" placeholder="e.g., Professor, Assistant Professor" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
                    <div class="text-center mt-3">
                        <p>Already have an account? <a href="login.php">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>