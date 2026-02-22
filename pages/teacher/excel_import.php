<?php
session_start();
require_once '../../dbRelated/operation.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$db = new DataManager();
$class_id = $_GET['class_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    
    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Skip the header row (index 0)
        for ($i = 1; $i < count($rows); $i++) {
            $id_num = $rows[$i][0];   // Column A
            $name = $rows[$i][1];     // Column B
            $email = $rows[$i][2];    // Column C

            if (!empty($id_num)) {
                // Injects directly into lookup_masterlist
                $db->uploadStudentToMasterlist($id_num, $name, $email);
            }
        }
        header("Location: manage_classes.php?upload=success");
    } catch (Exception $e) {
        die("Error loading file: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Import | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white p-8 rounded-2xl shadow-xl">
        <h2 class="text-2xl font-bold mb-2">Bulk Student Import</h2>
        <p class="text-sm text-gray-500 mb-6">Upload a CSV/Excel file to populate your student list.</p>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="border-2 border-dashed border-gray-200 p-8 text-center rounded-xl hover:border-blue-400 transition">
                <input type="file" name="excel_file" id="file" class="hidden" accept=".csv, .xlsx" required onchange="updateFileName()">
                <label for="file" class="cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <span id="file-name" class="text-gray-600 font-medium">Click to select file</span>
                </label>
            </div>
            <div class="flex gap-3">
                <a href="manage_classes.php" class="flex-1 text-center py-3 bg-gray-100 text-gray-600 rounded-xl font-bold">Cancel</a>
                <button type="submit" class="flex-1 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 shadow-lg shadow-green-100">Upload Now</button>
            </div>
        </form>
    </div>

    <script>
        function updateFileName() {
            const input = document.getElementById('file');
            const label = document.getElementById('file-name');
            label.textContent = input.files[0].name;
        }
    </script>
</body>
</html>