<?php
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function getRoleBasedRedirect($role) {
    switch ($role) {
        case 'admin': return APP_URL . '/admin/';
        case 'faculty': return APP_URL . '/faculty/';
        case 'student': return APP_URL . '/student/';
        default: return APP_URL . '/auth/login.php';
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isFaculty() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'faculty';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect(APP_URL . '/auth/login.php');
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        redirect(APP_URL . '/auth/login.php');
    }
}
?>