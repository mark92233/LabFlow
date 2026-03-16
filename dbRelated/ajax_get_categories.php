<?php
session_start();
require_once __DIR__ . '/operation.php';
header('Content-Type: application/json');

// Access Control: Only Admins can access this.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = new DataManager();

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    // The JS sends 'consumable' or 'non-consumable'. The DB expects 1 or 0.
    $isConsumable = ($type === 'consumable') ? 1 : 0;

    try {
        $categories = $db->getCategoriesByType($isConsumable);
        // Defensive check: Ensure we always return a JSON array.
        if (is_array($categories)) {
            echo json_encode($categories);
        } else {
            // If the query failed silently, return an empty array to prevent JS errors.
            echo json_encode([]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        // Provide a more detailed error message for easier debugging in the future.
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Type parameter not specified.']);
}
?>