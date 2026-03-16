<?php
session_start();
require_once __DIR__ . '/operation.php';
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Teacher', 'Admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['class_ids']) || empty($_POST['limit'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request parameters.']);
    exit();
}

try {
    $db = new DataManager();
    // Sanitize inputs
    $classIds = explode(',', $_POST['class_ids']);
    $classIds = array_map('intval', $classIds); // Ensure they are integers
    $limit = (int)$_POST['limit'];

    $preview = $db->previewSmartGroups($classIds, $limit);

    echo json_encode(['status' => 'success', 'data' => $preview]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}