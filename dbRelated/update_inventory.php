<?php
session_start();
require_once 'operation.php';

// Check if user is an Admin and the form was submitted
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    
    $itemId = $_POST['ItemID'] ?? null;
    $itemName = trim($_POST['Item_Name'] ?? '');
    $description = trim($_POST['Description'] ?? '');
    $variantsData = $_POST['variants'] ?? null;
    $success = false;
    $errorMessage = 'An unknown error occurred.';

    try {
        $dataMgr = new DataManager();

        // Check if we are updating a scalable item (variants will be posted)
        if ($variantsData !== null) {
            if ($itemId && $itemName) {
                $success = $dataMgr->updateScalableInventoryItem($itemId, $itemName, $description, $variantsData);
                if (!$success) {
                    $errorMessage = $dataMgr->getLastError() ?: 'Failed to update variants. One or more sizes may have items currently borrowed.';
                }
            } else {
                $errorMessage = 'Invalid data submitted for scalable item.';
            }
        } else { // This is a non-scalable item
            $location = trim($_POST['Location'] ?? '');
            $totalQty = $_POST['Total_Qty'] ?? null;

            if ($itemId && $itemName && $totalQty !== null) {
            $success = $dataMgr->updateInventoryItem($itemId, $itemName, $description, $location, (int)$totalQty);
                if (!$success) {
                    $errorMessage = $dataMgr->getLastError() ?: 'Update failed. No changes were made or the item does not exist.';
                }
            } else {
                $errorMessage = 'Invalid data submitted. Item Name and Total Quantity are required for non-scalable items.';
            }
        }

        $_SESSION['toast_message'] = ['text' => $success ? 'Item updated successfully!' : $errorMessage, 'type' => $success ? 'success' : 'error'];
    } catch (Exception $e) {
        $_SESSION['toast_message'] = ['text' => 'A critical error occurred: ' . $e->getMessage(), 'type' => 'error'];
    }

} else {
    // If not admin or not a valid post request, set an error message
    $_SESSION['toast_message'] = ['text' => 'Unauthorized access or invalid request.', 'type' => 'error'];
}

// Redirect back to the inventory hub
header("Location: ../pages/common/inventory_hub.php");
exit();
?>