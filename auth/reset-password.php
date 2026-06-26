<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validator.php';

$auth = new Auth();
if ($auth->isLoggedIn()) {
    redirect(getRoleBasedRedirect($_SESSION['role']));
}

$token = $_GET['token'] ?? '';
if (empty($token)) {
    redirect('forgot-password.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $new_password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $validator = new Validator();
        $validator->required('password', $new_password)->minLength('password', $new_password, 8);
        $validator->required('confirm_password', $confirm)->match('confirm_password', $confirm, $new_password);
        if ($validator->hasErrors()) {
            $error = implode('<br>', $validator->getErrors());
        } else {
            $result = $auth->resetPassword($token, $new_password);
            if ($result['status'] === 'success') {
                $success = $result['message'];
                header('Refresh: 3; url=login.php');
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
    <title>Reset Password - Smart Campus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card glass-card shadow-lg p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-lock fa-3x text-success"></i>
                        <h3 class="mt-3">Reset Password</h3>
                        <p class="text-muted">Enter your new password</p>
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Reset Password</button>
                    </form>
                    <div class="text-center mt-3">
                        <p><a href="login.php">Back to Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>