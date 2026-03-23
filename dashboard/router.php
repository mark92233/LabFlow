<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$role = $_SESSION['user_role'] ?? 'Guest';

switch ($role) {
    case 'Admin':
        header("Location: admin_dash.php");
        break;
    case 'Teacher':
        header("Location: teacher_dash.php");
        break;
    case 'LabTech':
        header("Location: ../includes/labtech_dash.php");
        break;
    case 'Student':
        header("Location: student_dash.php");
        break;
    default:
        header("Location: ../logout.php");
        break;
}
exit();
?>