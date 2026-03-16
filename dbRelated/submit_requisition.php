<?php
session_start();
require_once __DIR__ . '/operation.php';

// 1. Access Control & Validation
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['toast_message'] = ['text' => 'Invalid request or session expired.', 'type' => 'error'];
    header("Location: ../pages/common/inventory_hub.php");
    exit();
}

$db = new DataManager();
$studentId = $_SESSION['user_id'];
$activityId = $_POST['activity_id'] ?? $_GET['activity_id'] ?? null;
$reason = isset($_POST['reason']) && !empty(trim($_POST['reason'])) ? trim($_POST['reason']) : null;

$cartItems = [];

// 2. Data Parsing
if (!empty($_POST['cart_data'])) {
    // Source: Inventory Hub / General Cart (JSON)
    $cartItems = json_decode($_POST['cart_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($cartItems)) {
        $cartItems = [];
    }
} elseif (!empty($_POST['items'])) {
    // Source: Activity View Sidebar (form fields)
    foreach ($_POST['items'] as $index => $itemId) {
        $cartItems[] = [
            'itemId' => $itemId,
            'variantId' => null, // Variants are not selectable from activity view yet
            'qty' => intval($_POST['qtys'][$index])
        ];
    }
}

// 3. Data Integrity Check
if (empty($cartItems)) {
    $cartPage = $activityId ? "../pages/student/activity_view.php?activity_id=$activityId" : "../pages/common/cart_page.php";
    $_SESSION['toast_message'] = ['text' => 'Your requisition cart is empty.', 'type' => 'error'];
    header("Location: $cartPage");
    exit();
}

// 4. Process Requisition
try {
    $sessionId = $db->submitRequisition($studentId, $activityId, $cartItems, $reason);

    if ($sessionId) {
        // 5. Success Redirect
        $_SESSION['toast_message'] = ['text' => 'Requisition submitted successfully!', 'type' => 'success'];
        $redirect_url = $activityId
            ? "../pages/student/activity_view.php?activity_id=" . $activityId . "&status=submitted"
            : "../pages/student/active_slips.php?success=requisition_submitted"; // Flag to clear cart on the client-side
        header("Location: " . $redirect_url);
        exit();
    } else {
        // 6. Handle submission failure from DataManager
        $error_message = $db->getLastError() ?: 'Failed to submit requisition. Please try again.';
        $_SESSION['toast_message'] = ['text' => $error_message, 'type' => 'error'];
        $cartPage = $activityId ? "../pages/student/activity_view.php?activity_id=$activityId" : "../pages/common/cart_page.php";
        header("Location: $cartPage");
        exit();
    }
} catch (Exception $e) {
    // 7. Handle unexpected exceptions
    error_log("Requisition Error: " . $e->getMessage());
    $_SESSION['toast_message'] = ['text' => 'A critical error occurred. Please contact support.', 'type' => 'error'];
    $cartPage = $activityId ? "../pages/student/activity_view.php?activity_id=$activityId" : "../pages/common/cart_page.php";
    header("Location: $cartPage");
    exit();
}