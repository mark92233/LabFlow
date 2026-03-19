<?php
session_start();
require_once __DIR__ . '/operation.php';
header('Content-Type: application/json');

// Access Control
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['Admin', 'Teacher'])) {
    http_response_code(403);
    echo json_encode(['message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$shelfNames = $input['shelf_names'] ?? [];

if (empty($shelfNames)) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'No shelves to check.']);
    exit();
}

try {
    $db = new DataManager();
    $itemsOnShelves = $db->getItemsOnShelves($shelfNames);

    if (!empty($itemsOnShelves)) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'confirmation_required', 'data' => $itemsOnShelves]);
    } else {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'An error occurred: ' . $e->getMessage()]);
}