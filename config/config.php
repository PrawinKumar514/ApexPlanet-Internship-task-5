<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_campus');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'Smart Campus Management System');
define('APP_URL', 'http://localhost/smart-campus');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');

// Email configuration (PHPMailer)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);

define('SMTP_USER', 'prawinkumar514@gmail.com');
define('SMTP_PASS', 'szpossxznasyhfyz');

define('SMTP_FROM', 'prawinkumar514@gmail.com');
define('SMTP_FROM_NAME', 'Smart Campus');

// Security
define('SALT', 'your-secure-salt-string');
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>