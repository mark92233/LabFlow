 
<?php
// adminhome.php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/dbRelated/operation.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$dataMgr = new DataManager();
$message = "";

if (isset($_GET['action']) && $_GET['action'] == 'upload') {
    $file = $_FILES['masterlist_file']['tmp_name'];
    
    try {
        $spreadsheet = IOFactory::load($file);
        $data = $spreadsheet->getActiveSheet()->toArray();

        // Skip the first row (header)
        for ($i = 1; $i < count($data); $i++) {
            $id    = $data[$i][0]; // Column A: ID Number
            $name  = $data[$i][1]; // Column B: Full Name
            $email = $data[$i][2]; // Column C: Official Email
            $role  = $data[$i][3]; // Column D: Teacher/Student

            if (!empty($id) && !empty($name)) {
                $dataMgr->importFromExcel($id, $name, $email, $role);
            }
        }
        $message = "✅ Masterlist seeded successfully!";
    } catch (Exception $e) {
        $message = "❌ Error processing file: " . $e->getMessage();
    }
}

// Logic to include the page
$page = $_GET['p'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - E-LIMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <nav class="bg-blue-800 p-4 text-white shadow-lg">
        <div class="container mx-auto flex justify-between">
            <span class="font-bold">E-LIMS Admin Panel</span>
            <span>Welcome, Administrator</span>
        </div>
    </nav>

    <main class="container mx-auto mt-10 p-4">
        <?php if($message): ?>
            <div class="mb-4 p-4 bg-blue-100 text-blue-700 rounded border border-blue-200"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php include "pages/admin/$page.php"; ?>
    </main>
</body>
</html>