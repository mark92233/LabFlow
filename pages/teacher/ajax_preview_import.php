<?php
session_start();
require_once '../../dbRelated/operation.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// 1. Security & Role Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Teacher', 'Admin'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

// 2. File Upload Check
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'File upload failed or no file selected.']);
    exit();
}

$file = $_FILES['excel_file']['tmp_name'];
$students = [];

try {
    // 3. Read Spreadsheet
    $spreadsheet = IOFactory::load($file);
    $rows = $spreadsheet->getActiveSheet()->toArray();
    
    if (count($rows) < 2) {
        throw new Exception("File is empty or contains only a header row.");
    }

    // 4. Process Rows (skip header)
    for ($i = 1; $i < count($rows); $i++) {
        $idNum = trim($rows[$i][0] ?? '');
        $fullName = trim($rows[$i][1] ?? '');
        $email = trim($rows[$i][2] ?? '');

        if (!empty($idNum)) {
            $students[] = ['id' => $idNum, 'name' => $fullName, 'email' => $email];
        }
    }

    echo json_encode(['data' => $students]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => "Error processing file: " . $e->getMessage()]);
}