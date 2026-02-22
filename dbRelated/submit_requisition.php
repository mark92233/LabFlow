<?php
session_start();
require_once 'operation.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new DataManager();
    $studentId = $_SESSION['user_id'];
    $activityId = $_POST['activity_id'] ?? $_GET['activity_id'] ?? null;
    
    $cartItems = [];

    // 1. Updated Data Parsing for Internal Handover
    if (!empty($_POST['final_items'])) {
        // Source: Leader's Tagging Modal
        foreach ($_POST['final_items'] as $index => $itemId) {
            $cartItems[] = [
                'id' => $itemId,
                'qty' => 1, // Individual items are tagged 1-by-1 in this workflow
                'possessor_id' => $_POST['possessors'][$index] // Captured from modal dropdown
            ];
        }
    } elseif (!empty($_POST['cart_data'])) {
        // Source: Inventory Hub (JSON)
        $cartItems = json_decode($_POST['cart_data'], true);
    } elseif (!empty($_POST['items'])) {
        // Source: Legacy Activity View Sidebar
        foreach ($_POST['items'] as $index => $itemId) {
            $cartItems[] = [
                'id' => $itemId,
                'qty' => intval($_POST['qtys'][$index]),
                'possessor_id' => $db->getMasterID($studentId) // Default to requester
            ];
        }
    }

    if (empty($cartItems)) {
        die("Error: Requisition bag is empty.");
    }

    try {
        // 2. Submit Request with Possessor Assignments
        $sessionId = $db->submitRequisition($studentId, $activityId, $cartItems);

        if ($sessionId) {
            // Redirect back to activity view to see the new individual slips
            header("Location: ../pages/student/activity_view.php?activity_id=" . $activityId . "&status=submitted");
            exit();
        } else {
            throw new Exception("Database failed to record the session.");
        }
    } catch (Exception $e) {
        error_log("Requisition Error: " . $e->getMessage());
        die("Fatal Error: " . $e->getMessage());
    }
}