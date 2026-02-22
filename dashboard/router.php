<?php
session_start();

// Security: If not logged in, kick back to landing page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Redirect based on the Role stored during login/registration
if ($_SESSION['user_role'] === 'Teacher' || $_SESSION['user_role'] === 'Admin') {
    header("Location: admin_dash.php");
} else {
    header("Location: student_dash.php");
}
exit();