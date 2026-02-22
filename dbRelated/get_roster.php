<?php
// dbRelated/get_roster.php

// 1. SILENCE & BUFFER
// This stops any random warnings from breaking the JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ob_start();

header('Content-Type: application/json');

// 2. LOAD DEPENDENCIES
require_once 'operation.php'; 

try {
    if (!isset($_POST['classes'])) {
        throw new Exception("No classes selected.");
    }

    // 3. INITIALIZE DB
    $db = new DataManager();
    
    // 4. PREPARE INPUT
    $classIdsRaw = explode(',', $_POST['classes']);
    
    // 5. CALL THE NEW FUNCTION (The one we validated in the debug report)
    // This is the critical line. It avoids the "Private Property" crash.
    $roster = $db->getStudentsByClassList($classIdsRaw);

    // 6. OUTPUT JSON
    ob_clean(); // Clean the buffer
    echo json_encode($roster);

} catch (Exception $e) {
    // 7. HANDLE CRASHES GRACEFULLY
    ob_clean(); 
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>