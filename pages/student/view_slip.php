<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control: Ensure session and SID exist
if (!isset($_GET['sid']) || !isset($_SESSION['user_id'])) {
    header("Location: active_slips.php");
    exit();
}

$db = new DataManager();
$sid = $_GET['sid'];

/** * FETCH SLIP DETAILS
 * Adjusted to match snhs_inventory.sql schema
 */
$query = "SELECT bs.*, COALESCE(la.Title, 'General Laboratory Use') as Title 
          FROM borrowing_sessions bs
          LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
          WHERE bs.SessionID = :sid AND bs.StudentID = :uid";

$stmt = $db->db->prepare($query);
$stmt->execute(['sid' => $sid, 'uid' => $_SESSION['user_id']]);
$slip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slip) {
    echo "Unauthorized access or Slip ID not found.";
    exit();
}

/** * FETCH ITEMS 
 */
$iQuery = "SELECT bi.Quantity, i.Item_Name 
           FROM borrowed_items AS bi 
           JOIN inventory AS i ON bi.ItemID = i.ItemID 
           WHERE bi.SessionID = :sid";

$iStmt = $db->db->prepare($iQuery);
$iStmt->execute(['sid' => $sid]); 
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);
$page_title = "Digital Receipt";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Slip #<?= $sid ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* Modern Thermal Receipt Styles */
        .thermal-font {
            font-family: 'Space Mono', monospace;
        }

        .receipt-container {
            background-color: #fff;
            width: 100%;
            max-width: 380px; /* Authentic receipt width */
            margin: 0 auto;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            color: #1e293b;
        }

        /* Zig-Zag Tear Lines */
        .receipt-tear-top {
            position: absolute; top: -6px; left: 0; width: 100%; height: 12px;
            background: linear-gradient(135deg, transparent 33%, #fff 33%, #fff 66%, transparent 66%) 0 0;
            background-size: 24px 12px;
        }

        .receipt-tear-bottom {
            position: absolute; bottom: -12px; left: 0; width: 100%; height: 12px;
            background: linear-gradient(45deg, transparent 33%, #fff 33%, #fff 66%, transparent 66%) 0 0;
            background-size: 24px 12px;
            transform: rotate(180deg);
        }

        /* Screen-only adjustments */
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .receipt-container { box-shadow: none; }
        }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 flex flex-col items-center animate-reveal">
                
                <div class="w-full max-w-[380px] mb-8 flex justify-between items-center no-print">
                    <a href="active_slips.php" class="text-[10px] font-black uppercase text-slate-400 hover:text-blue-600 transition-all flex items-center gap-2 tracking-widest">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Back
                    </a>
                    <button onclick="downloadReceipt()" class="bg-[#0f172a] text-white px-6 py-3 rounded-full text-[9px] font-black uppercase tracking-widest hover:bg-blue-600 transition-all shadow-xl">
                        Save Image
                    </button>
                </div>

                <div id="receipt-capture" class="relative my-4 p-4"> <div class="receipt-container thermal-font p-8 pb-10">
                        <div class="receipt-tear-top"></div>

                        <div class="text-center mb-8 border-b-2 border-black/10 pb-6">
                            <h3 class="text-2xl font-bold uppercase tracking-widest text-slate-900">SNHS LAB</h3>
                            <p class="text-[9px] text-slate-500 uppercase mt-1">Official Borrowing Slip</p>
                        </div>

                        <div class="space-y-2 mb-8 text-[10px] uppercase font-bold text-slate-800">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Date:</span>
                                <span><?= date('Y-m-d H:i', strtotime($slip['CreatedAt'])) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Slip ID:</span>
                                <span>#<?= $sid ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Borrower:</span>
                                <span><?= $_SESSION['user_name'] ?></span>
                            </div>
                            <div class="flex justify-between items-start">
                                <span class="text-slate-400 whitespace-nowrap mr-2">Activity:</span>
                                <span class="text-right leading-tight"><?= htmlspecialchars($slip['Title']) ?></span>
                            </div>
                        </div>

                        <div class="mb-8">
                            <div class="flex justify-between text-[9px] font-bold border-b-2 border-slate-900 mb-2 pb-1 text-slate-900">
                                <span>ITEM DESCRIPTION</span>
                                <span>QTY</span>
                            </div>
                            
                            <?php foreach ($items as $item): ?>
                            <div class="flex justify-between text-[11px] font-bold py-2 border-b border-dashed border-slate-200">
                                <span class="uppercase"><?= htmlspecialchars($item['Item_Name']) ?></span>
                                <span><?= $item['Quantity'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex flex-col items-center pt-4">
                            <?php 
                            $qrEligible = ['Pending', 'Approved', 'Issued'];
                            if (!empty($slip['QR_Code_Data']) && in_array($slip['Status'], $qrEligible)): ?>
                                <div id="qrcode" class="mb-4 mix-blend-multiply opacity-90"></div>
                                <p class="text-[8px] font-bold text-center uppercase text-slate-400">Scan for Release/Return</p>
                            <?php else: ?>
                                <div class="w-24 h-24 border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center mb-4">
                                    <span class="text-2xl">🔒</span>
                                </div>
                                <p class="text-[8px] font-bold text-center uppercase text-slate-400">Transaction Closed</p>
                            <?php endif; ?>

                            <div class="mt-6 border-2 border-slate-900 px-4 py-1 rounded text-[10px] font-black uppercase tracking-widest">
                                <?= $slip['Status'] ?>
                            </div>
                        </div>

                        <div class="receipt-tear-bottom"></div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        window.addEventListener('load', function() {
            const qrContainer = document.getElementById("qrcode");
            const qrData = "<?= $slip['QR_Code_Data'] ?? '' ?>";
            
            if(qrContainer && qrData !== "") {
                qrContainer.innerHTML = "";
                new QRCode(qrContainer, {
                    text: qrData,
                    width: 120, // Slightly smaller for thermal look
                    height: 120,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });

        function downloadReceipt() {
            const element = document.getElementById('receipt-capture');
            // Using html2canvas with transparent background option
            html2canvas(element, {
                scale: 3,
                backgroundColor: null 
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'SNHS-Receipt-<?= $sid ?>.png';
                link.href = canvas.toDataURL();
                link.click();
            });
        }
    </script>
</body>
</html>