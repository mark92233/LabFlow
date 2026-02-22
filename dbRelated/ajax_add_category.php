<?php
session_start();
require_once __DIR__ . '/operation.php';
$db = new DataManager();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category_name'])) {
    $name = trim($_POST['category_name']);
    $newId = $db->addCategory($name); // Make sure addCategory returns the lastInsertId()

    if ($newId) {
        echo json_encode(['success' => true, 'new_id' => $newId]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Category already exists']);
    }
}