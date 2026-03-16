<?php
session_start();
// Wrap the entire script in a try-catch block to ensure any fatal error is caught and reported as JSON.
try {
    require_once __DIR__ . '/../../dbRelated/operation.php';
    header('Content-Type: application/json');

    // Security check
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Teacher', 'Admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    $db = new DataManager();

    $options = [
        'page' => $_GET['page'] ?? 1,
        'limit' => 12, // Items per page
        'search' => $_GET['search'] ?? '',
        'category' => $_GET['category'] ?? 'all',
    ];

    $data = $db->getPaginatedInventory($options);

    echo json_encode($data);

} catch (Throwable $e) { // Catching Throwable handles Errors as well as Exceptions
    http_response_code(500);
    // Ensure the header is set to JSON even in case of an error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'A critical server error occurred.', 'detail' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}
?>