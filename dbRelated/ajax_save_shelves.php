<?php
session_start();
require_once __DIR__ . '/operation.php';
header('Content-Type: application/json');

// Access Control: Only Admins can save layouts
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['Admin', 'Teacher'])) {
    http_response_code(403);
    echo json_encode(['message' => 'Unauthorized']);
    exit();
}

// Get data from Frontend
$input = json_decode(file_get_contents('php://input'), true);

// Validate data
if (!isset($input['layout']) || !is_array($input['layout'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid or empty data received.']);
    exit();
}

$shelves = $input['layout'];
$force = isset($input['force']) && $input['force'] === true;

try {
    $db = new DataManager();
    $savedCount = $db->saveShelves($shelves, $force);

    if ($savedCount !== false) {
        http_response_code(200);
        echo json_encode(['message' => "Successfully saved {$savedCount} shelves to the database."]);
    } else {
        throw new Exception($db->getLastError() ?: 'Failed to save shelf layout.');
    }
} catch (ConfirmationRequiredException $e) {
    http_response_code(409); // 409 Conflict is appropriate for this situation
    echo json_encode(['status' => 'confirmation_required', 'data' => $e->getData()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'An error occurred during save: ' . $e->getMessage()]);
}