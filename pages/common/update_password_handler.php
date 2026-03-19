<?php
session_start();
require_once '../../dbRelated/operation.php';
header('Content-Type: application/json');

// 1. Security Checks
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid request.']);
    exit();
}

// 2. Get POST data
$userId = $_SESSION['user_id'];
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// 3. Validation
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit();
}

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit();
}

try {
    $db = new DataManager();

    // 4. Verify current password
    $user = $db->checkExistingAccount($_SESSION['master_id']);
    if (!$user || !password_verify($currentPassword, $user['Password_Hash'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
        exit();
    }

    // 5. Update to new password
    if ($db->updatePasswordByMasterId($_SESSION['master_id'], $newPassword)) {
        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    }

} catch (Exception $e) {
    error_log("Password Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}