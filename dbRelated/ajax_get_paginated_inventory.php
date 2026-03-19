<?php
session_start();
require_once __DIR__ . '/operation.php';
header('Content-Type: application/json');

// Access Control: Ensure user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = new DataManager();
    
    $options = [
        'page' => $_GET['page'] ?? 1,
        'limit' => $_GET['limit'] ?? 15,
        'search' => $_GET['search'] ?? '',
        'category' => $_GET['category'] ?? 'all',
        'asset_type' => $_GET['asset_type'] ?? 'all'
    ];

    $result = $db->getPaginatedInventory($options);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}