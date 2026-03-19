<?php
session_start();
require_once __DIR__ . '/operation.php';

// Access Control: Only Admins can manipulate stock directly.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new DataManager();
    $itemId = $_POST['item_id'] ?? null;
    $newTotal = $_POST['new_total'] ?? null;

    if ($itemId && $newTotal !== null) {
        try {
            // 1. Get current item data to calculate the difference
            $item = $db->getItemDetails($itemId);
            $currentTotal = $item['Total_Qty'];
            $currentAvailable = $item['Available_Qty'];

            // 2. Calculate new availability
            // Logic: If I add 5 to total, I must add 5 to available.
            $difference = $newTotal - $currentTotal;
            $newAvailable = $currentAvailable + $difference;

            // Safety check: Available cannot be negative
            if ($newAvailable < 0) {
                echo json_encode(['success' => false, 'error' => 'New total is lower than current items in use.']);
                exit();
            }

            // 3. Update the Database [cite: 2025-12-06]
            $query = "UPDATE inventory SET Total_Qty = :tq, Available_Qty = :aq WHERE ItemID = :id";
            $stmt = $db->getConnection()->prepare($query);
            $success = $stmt->execute([
                'tq' => $newTotal,
                'aq' => $newAvailable,
                'id' => $itemId
            ]);

            if ($success) {
                echo json_encode([
                    'success' => true, 
                    'new_available' => $newAvailable
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database update failed.']);
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data provided.']);
    }
}