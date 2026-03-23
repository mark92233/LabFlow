<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control: User must be a logged-in student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: /LabFlow/index.php");
    exit();
}

$db = new DataManager();
$userId = $_SESSION['user_id'];

// 2. Fetch all necessary data
$userProfile = $db->getUserProfileData($userId);
$liabilities = $db->checkLiability($userId); // This function returns ['has_liability' => bool, 'items' => array]

// Fetch borrowing history summary
$historyStmt = $db->db->prepare("SELECT COUNT(*) as total_sessions, MAX(CreatedAt) as last_borrow_date FROM borrowing_sessions WHERE StudentID = ?");
$historyStmt->execute([$userId]);
$historySummary = $historyStmt->fetch(PDO::FETCH_ASSOC);

if (!$userProfile) {
    die("Error: Could not retrieve user profile.");
}

// 3. Prepare data for the view
$studentName = $userProfile['Full_Name'];
$studentIdNumber = $userProfile['ID_Number'];
$hasLiabilities = $liabilities['has_liability'];
$liabilityItems = $liabilities['items'];

// 4. Prepare QR Code Data
// This URL will take the admin to the settlement review page, pre-filtered with the student's ID number.
$qrCodeData = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/LabFlow/pages/teacher/settlement_reviews.php?search=' . urlencode($studentIdNumber);

$page_title = "Student Clearance Slip";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .thermal-font { font-family: 'Courier Prime', 'Courier New', Courier, monospace; }
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; }
            .printable-area {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body class="bg-slate-100 p-8">

    <div class="max-w-md mx-auto">
        <!-- Action Buttons (No Print) -->
        <div class="no-print mb-6 flex justify-between items-center">
            <a href="../common/profile.php" class="text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">&larr; Back to Profile</a>
            <button onclick="window.print()" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-orange-600 transition-all shadow-lg">
                Print Slip
            </button>
        </div>

        <!-- Printable Slip -->
        <div class="printable-area bg-white rounded-2xl shadow-2xl p-8 thermal-font text-slate-800">
            <header class="text-center mb-8 pb-6 border-b-2 border-dashed border-slate-200">
                <h1 class="text-2xl font-bold uppercase tracking-widest">CSM Laboratory</h1>
                <p class="text-xs uppercase mt-1 text-slate-400 font-bold">Student Clearance Summary</p>
                <p class="text-[10px] mt-2 font-bold"><?= date('F j, Y, g:i a') ?></p>
            </header>

            <section class="mb-8">
                <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Student Identity</h2>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="font-bold">Name:</span>
                        <span class="font-bold text-right"><?= htmlspecialchars($studentName) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold">ID Number:</span>
                        <span class="font-bold"><?= htmlspecialchars($studentIdNumber) ?></span>
                    </div>
                </div>
            </section>

            <section class="mb-8">
                <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Borrowing History</h2>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="font-bold">Total Slips:</span>
                        <span class="font-bold"><?= $historySummary['total_sessions'] ?? 0 ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold">Last Activity:</span>
                        <span class="font-bold">
                            <?= $historySummary['last_borrow_date'] ? date('M d, Y', strtotime($historySummary['last_borrow_date'])) : 'N/A' ?>
                        </span>
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Accountability Status</h2>
                
                <?php if ($hasLiabilities): ?>
                    <div class="bg-red-50 border-2 border-dashed border-red-200 rounded-xl p-6">
                        <div class="text-center mb-4">
                            <p class="text-lg font-black text-red-600 uppercase">LIABILITY PENDING</p>
                            <p class="text-xs font-bold text-red-500">Student has unresolved damages.</p>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between font-bold border-b border-dashed border-red-200 pb-1 mb-1">
                                <span>ITEM</span>
                                <span>QTY</span>
                            </div>
                            <?php foreach ($liabilityItems as $item): ?>
                                <div class="flex justify-between">
                                    <span class="font-bold"><?= htmlspecialchars($item['Item_Name']) ?></span>
                                    <span class="font-bold"><?= htmlspecialchars($item['qty_damaged']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-emerald-50 border-2 border-dashed border-emerald-300 rounded-xl p-8 text-center">
                        <p class="text-2xl font-black text-emerald-600 uppercase tracking-wider">CLEARED</p>
                        <p class="text-xs font-bold text-emerald-500 mt-1">No outstanding liabilities found.</p>
                    </div>
                <?php endif; ?>
            </section>

            <footer class="mt-8 pt-8 border-t-2 border-dashed border-slate-200 flex flex-col items-center">
                <div id="qrcode-container" class="p-2 bg-white border-4 border-slate-800 rounded-lg shadow-md mb-2"></div>
                <p class="text-[9px] font-bold text-center uppercase text-slate-500">
                    Admin: Scan to verify liabilities
                </p>
            </footer>

            <div class="mt-8 text-center">
                <p class="text-[10px] text-slate-400">_________________________</p>
                <p class="text-[10px] font-bold text-slate-500 mt-1">Signature over Printed Name</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const qrContainer = document.getElementById("qrcode-container");
            const qrData = "<?= $qrCodeData ?>";
            
            if(qrContainer && qrData) {
                qrContainer.innerHTML = ""; // Clear previous QR code if any
                new QRCode(qrContainer, {
                    text: qrData,
                    width: 128,
                    height: 128,
                    colorDark : "#0f172a", // slate-900
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });
    </script>

</body>
</html>