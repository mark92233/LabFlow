<?php
session_start();
require_once __DIR__ . '/operation.php';

header('Content-Type: application/json');

$db = new DataManager();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$masterID = $db->getMasterID($_SESSION['user_id']);

// Support both POST and GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$sectionID = $_POST['section_id'] ?? $_GET['section_id'] ?? null;
$content = $_POST['content'] ?? '';
/**
 * This file is deprecated.
 * The "Builder" submission mode and its related functionalities (locking, editing, commenting)
 * have been removed from the application as of March 2026.
 */
echo json_encode(['status' => 'error', 'message' => 'This feature is no longer available.']);
exit();