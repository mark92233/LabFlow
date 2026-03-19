<?php
session_start();
require_once '../dbRelated/operation.php';

// 1. Access Control & Validation
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['cart_data'])) {
    header("Location: ../pages/common/inventory_hub.php?error=invalid_request");
    exit();
}

$db = new DataManager();
$student_id = $_SESSION['user_id'];
$cart_items = json_decode($_POST['cart_data'], true);
$reason = isset($_POST['reason']) && !empty(trim($_POST['reason'])) ? trim($_POST['reason']) : null;

// 2. Data Integrity Check
if (json_last_error() !== JSON_ERROR_NONE || !is_array($cart_items) || empty($cart_items)) {
    header("Location: ../pages/common/cart_page.php?error=cart_empty");
    exit();
}

// 3. Process Requisition
$session_id = $db->submitRequisition($student_id, null, $cart_items, $reason);

if ($session_id) {
    // 4. Redirect to the active slips page on success.
    // We'll add JS to that page to clear the cart from localStorage.
    header("Location: ../pages/student/active_slips.php?success=requisition_submitted&sid=" . $session_id);
    exit();
} else {
    // Handle submission failure
    $error_message = urlencode($db->getLastError() ?: 'Failed to submit requisition. Please try again.');
    header("Location: ../pages/common/cart_page.php?error=" . $error_message);
    exit();
}