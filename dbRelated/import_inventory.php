<?php
session_start();
require_once 'operation.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// --- Security Check ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast_message'] = ['text' => 'Unauthorized access.', 'type' => 'error'];
    header("Location: ../pages/common/inventory_hub.php");
    exit();
}

if (isset($_FILES['inventory_csv']) && $_FILES['inventory_csv']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['inventory_csv']['tmp_name'];

    try {
        $dataMgr = new DataManager();
        $reader = IOFactory::createReader('Csv');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($fileTmpPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();

        if (count($data) < 2) {
            throw new Exception("CSV file is empty or contains only a header.");
        }

        $header = array_map('trim', array_shift($data));
        $headerMap = array_flip($header);

        $requiredColumns = ['Item_Name', 'Category_Name', 'is_consumable', 'is_scalable'];
        foreach ($requiredColumns as $col) {
            if (!isset($headerMap[$col])) {
                throw new Exception("Missing required column in CSV: " . $col);
            }
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($data as $rowIndex => $row) {
            $itemName = trim($row[$headerMap['Item_Name']] ?? '');
            $categoryName = trim($row[$headerMap['Category_Name']] ?? '');

            if (empty($itemName) || empty($categoryName)) {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . ": Skipped due to missing Item Name or Category Name.";
                continue;
            }

            $itemData = [
                'name' => $itemName,
                'category' => $categoryName,
                'description' => trim($row[$headerMap['Description']] ?? ''),
                'total_qty' => (int)($row[$headerMap['Total_Qty']] ?? 0),
                'location' => trim($row[$headerMap['Location']] ?? null),
                'is_consumable' => (int)($row[$headerMap['is_consumable']] ?? 0),
                'is_scalable' => (int)($row[$headerMap['is_scalable']] ?? 0),
                'variants' => trim($row[$headerMap['variants']] ?? '')
            ];

            if ($dataMgr->importInventoryItem($itemData)) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Row " . ($rowIndex + 2) . " ('{$itemName}'): " . ($dataMgr->getLastError() ?: 'An unknown error occurred.');
            }
        }

        $message = "Import complete. {$successCount} items processed successfully.";
        if ($errorCount > 0) {
            $message .= " {$errorCount} items failed. Check logs for details.";
            error_log("CSV Import Errors: " . implode(" | ", $errors));
        }
        $_SESSION['toast_message'] = ['text' => $message, 'type' => $errorCount > 0 ? 'error' : 'success'];

    } catch (Exception $e) {
        $_SESSION['toast_message'] = ['text' => 'Error processing file: ' . $e->getMessage(), 'type' => 'error'];
    }
} else {
    $_SESSION['toast_message'] = ['text' => 'File upload failed. Please try again.', 'type' => 'error'];
}

header("Location: ../pages/admin/manage_inventory.php");
exit();
?>