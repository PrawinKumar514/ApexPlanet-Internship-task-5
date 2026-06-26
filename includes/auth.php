<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function login($email, $password, $remember = false) {
        $stmt = $this->db->prepare("SELECT id, email, password, role, is_active FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_active'] == 0) {
                return ['status' => 'error', 'message' => 'Account not activated. Please verify your email.'];
            }

            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $stmt = $this->db->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
                $stmt->execute(['token' => $token, 'id' => $user['id']]);
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
            }

            // Log activity
            $this->logActivity($user['id'], 'Login', 'User logged in');

            return ['status' => 'success', 'role' => $user['role']];
        }
        return ['status' => 'error', 'message' => 'Invalid email or password.'];
    }

    public function register($email, $password, $role, $extraData) {
        // Check if email exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            return ['status' => 'error', 'message' => 'Email already registered.'];
        }

        // Hash password
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO users (email, password, role, is_active) VALUES (:email, :password, :role, 0)");
            $stmt->execute(['email' => $email, 'password' => $hashed, 'role' => $role]);
            $userId = $this->db->lastInsertId();

            // Insert role-specific data
            if ($role === 'student') {
                $stmt = $this->db->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, department_id, enrollment_year, semester) 
                                            VALUES (:user_id, :student_id, :first_name, :last_name, :department_id, :enrollment_year, :semester)");
                $stmt->execute([
                    'user_id' => $userId,
                    'student_id' => $extraData['student_id'],
                    'first_name' => $extraData['first_name'],
                    'last_name' => $extraData['last_name'],
                    'department_id' => $extraData['department_id'],
                    'enrollment_year' => $extraData['enrollment_year'],
                    'semester' => $extraData['semester']
                ]);
            } elseif ($role === 'faculty') {
                $stmt = $this->db->prepare("INSERT INTO faculty (user_id, faculty_id, first_name, last_name, department_id, designation) 
                                            VALUES (:user_id, :faculty_id, :first_name, :last_name, :department_id, :designation)");
                $stmt->execute([
                    'user_id' => $userId,
                    'faculty_id' => $extraData['faculty_id'],
                    'first_name' => $extraData['first_name'],
                    'last_name' => $extraData['last_name'],
                    'department_id' => $extraData['department_id'],
                    'designation' => $extraData['designation']
                ]);
            }

            // Generate OTP and send email
            $otp = $this->generateOTP();
            $this->saveOTP($email, $otp);
            $this->sendOTPEmail($email, $otp, $role);

            $this->db->commit();
            return ['status' => 'success', 'message' => 'Registration successful. Please verify your email with OTP.'];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    public function verifyOTP($email, $otp) {
        $stmt = $this->db->prepare("SELECT id FROM otp_verifications WHERE email = :email AND otp = :otp AND is_verified = 0 AND expires_at > NOW()");
        $stmt->execute(['email' => $email, 'otp' => $otp]);
        $record = $stmt->fetch();

        if ($record) {
            // Mark OTP as verified
            $stmt = $this->db->prepare("UPDATE otp_verifications SET is_verified = 1 WHERE id = :id");
            $stmt->execute(['id' => $record['id']]);

            // Activate user
            $stmt = $this->db->prepare("UPDATE users SET is_active = 1 WHERE email = :email");
            $stmt->execute(['email' => $email]);

            return ['status' => 'success', 'message' => 'Email verified. You can now login.'];
        }
        return ['status' => 'error', 'message' => 'Invalid or expired OTP.'];
    }

    public function forgotPassword($email) {
        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['status' => 'error', 'message' => 'Email not found.'];
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires) 
                                    ON DUPLICATE KEY UPDATE token = :token, expires_at = :expires");
        $stmt->execute(['email' => $email, 'token' => $token, 'expires' => $expires]);

        // Send reset email
        $this->sendResetEmail($email, $token);

        return ['status' => 'success', 'message' => 'Password reset link sent to your email.'];
    }

    public function resetPassword($token, $newPassword) {
        $stmt = $this->db->prepare("SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW()");
        $stmt->execute(['token' => $token]);
        $record = $stmt->fetch();
        if (!$record) {
            return ['status' => 'error', 'message' => 'Invalid or expired token.'];
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE email = :email");
        $stmt->execute(['password' => $hashed, 'email' => $record['email']]);

        // Delete token
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = :token");
        $stmt->execute(['token' => $token]);

        return ['status' => 'success', 'message' => 'Password reset successfully.'];
    }

    public function logout() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    setcookie('remember_token', '', time() - 3600, '/');

    return ['status' => 'success'];
}

    public function checkSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        $stmt = $this->db->prepare("SELECT id, email, role FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        return $stmt->fetch();
    }

    // Helper methods
    private function generateOTP() {
        return sprintf("%06d", mt_rand(1, 999999));
    }

    private function saveOTP($email, $otp) {
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $stmt = $this->db->prepare("INSERT INTO otp_verifications (email, otp, expires_at) VALUES (:email, :otp, :expires)");
        $stmt->execute(['email' => $email, 'otp' => $otp, 'expires' => $expires]);
    }

    private function sendOTPEmail($email, $otp, $role) {
        require_once __DIR__ . '/mailer.php';
        $mailer = new Mailer();
        $subject = "Verify Your Email - Smart Campus";
        $body = "Hello,<br><br>Your OTP for email verification is: <strong>$otp</strong><br>This OTP expires in 15 minutes.";
        $mailer->send($email, $subject, $body);
    }

    private function sendResetEmail($email, $token) {
        require_once __DIR__ . '/mailer.php';
        $mailer = new Mailer();
        $resetLink = APP_URL . "/auth/reset-password.php?token=$token";
        $subject = "Password Reset - Smart Campus";
        $body = "Hello,<br><br>Click the link below to reset your password:<br><a href='$resetLink'>$resetLink</a><br>This link expires in 1 hour.";
        $mailer->send($email, $subject, $body);
    }

    private function logActivity($userId, $action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $this->db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (:user_id, :action, :description, :ip)");
        $stmt->execute(['user_id' => $userId, 'action' => $action, 'description' => $description, 'ip' => $ip]);
    }
}
?>