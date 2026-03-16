<?php
session_start();
require_once 'operation.php';

// Check if user is an Admin and the form was submitted
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    
    $itemId = $_POST['ItemID'] ?? null;

    if ($itemId) {
        try {
            $dataMgr = new DataManager();
            $success = $dataMgr->deleteInventoryItem($itemId);

            if ($success) {
                $_SESSION['toast_message'] = ['text' => 'Item deleted successfully!', 'type' => 'success'];
            } else {
                $errorMessage = $dataMgr->getLastError() ?: 'Failed to delete item. It might be in use or part of an activity.';
                $_SESSION['toast_message'] = ['text' => $errorMessage, 'type' => 'error'];
            }
        } catch (Exception $e) {
            $_SESSION['toast_message'] = ['text' => 'A critical error occurred: ' . $e->getMessage(), 'type' => 'error'];
        }
    } else {
        $_SESSION['toast_message'] = ['text' => 'Invalid item ID provided.', 'type' => 'error'];
    }

} else {
    $_SESSION['toast_message'] = ['text' => 'Unauthorized access or invalid request.', 'type' => 'error'];
}

// Redirect back to the inventory hub, which will now be missing the deleted item
header("Location: ../pages/common/inventory_hub.php");
exit();
?>