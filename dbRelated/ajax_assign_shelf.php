<?php
session_start();
require_once __DIR__ . '/operation.php';
header('Content-Type: application/json');

// Prevent PHP notices and warnings from breaking the JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Initialize a default response and status code
$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$statusCode = 500;

// Access Control: Only Teacher and Admin can do this
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['Admin', 'Teacher'])) {
    $statusCode = 403;
    $response['message'] = 'Unauthorized';
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $itemId = $input['itemId'] ?? null;
    $shelfName = $input['shelfName'] ?? null;
    error_log("Backend (ajax_assign_shelf.php): Received itemId=" . $itemId . ", shelfName=" . $shelfName);

    if (!$itemId || !$shelfName) {
        $statusCode = 400;
        $response['message'] = 'Invalid data provided.';
    } else {
        try {
            $db = new DataManager();
            if ($db->assignItemToShelf($itemId, $shelfName)) {
                $statusCode = 200;
                $response['success'] = true;
                $response['message'] = 'Item assigned to shelf successfully.';
            } else {
                $statusCode = 500;
                $response['message'] = $db->getLastError() ?: 'Failed to assign item to shelf.';
            }
        } catch (Exception $e) {
            $statusCode = 500;
            $response['message'] = $e->getMessage();
        }
    }
}

// Send the response
http_response_code($statusCode);
echo json_encode($response);
exit();