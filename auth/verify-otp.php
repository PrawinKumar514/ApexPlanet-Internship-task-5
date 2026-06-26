<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
if ($auth->isLoggedIn()) {
    redirect(getRoleBasedRedirect($_SESSION['role']));
}

$email = $_SESSION['verify_email'] ?? '';
if (empty($email)) {
    redirect('login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = sanitizeInput($_POST['otp']);
    if (empty($otp) || strlen($otp) !== 6) {
        $error = 'Please enter a valid 6-digit OTP.';
    } else {
        $result = $auth->verifyOTP($email, $otp);
        if ($result['status'] === 'success') {
            $success = $result['message'];
            unset($_SESSION['verify_email']);
            // Redirect to login after 3 seconds
            header('Refresh: 3; url=login.php');
        } else {
            $error = $result['message'];
        }
    }
}

// Resend OTP functionality
if (isset($_GET['resend'])) {
    $otp = $auth->generateOTP();
    $auth->saveOTP($email, $otp);
    $auth->sendOTPEmail($email, $otp, '');
    $success = 'A new OTP has been sent to your email.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Smart Campus</title>
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
                        <i class="fas fa-envelope fa-3x text-primary"></i>
                        <h3 class="mt-3">Verify Your Email</h3>
                        <p class="text-muted">Enter the 6-digit OTP sent to <strong><?= htmlspecialchars($email) ?></strong></p>
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
                            <label for="otp" class="form-label">OTP Code</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp" placeholder="000000" maxlength="6" pattern="\d{6}" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Verify OTP</button>
                    </form>
                    <div class="text-center mt-3">
                        <p>Didn't receive OTP? <a href="?resend=1">Resend OTP</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>