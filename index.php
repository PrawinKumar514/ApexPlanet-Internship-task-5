<?php

require_once __DIR__ . '/includes/session.php';

if (isset($_SESSION['user_id'])) {

    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/index.php");
    }

    elseif ($_SESSION['role'] == 'faculty') {
        header("Location: faculty/index.php");
    }

    elseif ($_SESSION['role'] == 'student') {
        header("Location: student/index.php");
    }

} else {

    header("Location: auth/login.php");

}

exit;