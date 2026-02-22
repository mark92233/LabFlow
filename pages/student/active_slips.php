<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$student_id = $_SESSION['user_id'];

// 2. Fetch Data
$slips = $db->getStudentHistoryWithDetails($student_id);

// 3. Handle File Upload (Self-Posting Form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof_photo'])) {
    $did = $_POST['damage_id'];
    $file = $_FILES['proof_photo'];
    
    $result = $db->submitDamageProof($did, $file);
    
    // Redirect to clear form submission
    $msg = ($result === true) ? "success" : urlencode($result);
    header("Location: active_slips.php?status=" . $msg);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction History | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .thermal-font { font-family: 'Space Mono', monospace; }
        .receipt-container { background-color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; }
        .receipt-tear-top {
            position: absolute; top: -5px; left: 0; width: 100%; height: 10px;
            background: linear-gradient(135deg, transparent 33%, #fff 33%, #fff 66%, transparent 66%) 0 0;
            background-size: 20px 10px;
        }
        .receipt-tear-bottom {
            position: absolute; bottom: -10px; left: 0; width: 100%; height: 10px;
            background: linear-gradient(45deg, transparent 33%, #fff 33%, #fff 66%, transparent 66%) 0 0;
            background-size: 20px 10px; transform: rotate(180deg);
        }
        /* Highlight active row */
        .active-row { background-color: #eff6ff; border-left: 4px solid #2563eb; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 flex flex-col lg:flex-row gap-8 animate-reveal h-[calc(100vh-100px)] overflow-hidden">
                
                <div class="flex-1 flex flex-col h-full min-w-0">
                    <header class="mb-6 flex-shrink-0">
                        <h2 class="text-4xl font-extrabold text-[#0f172a] tracking-tighter mb-2">My <span class="text-blue-600">History.</span></h2>
                        <p class="text-slate-400 font-medium text-xs">Select a transaction to view details.</p>
                    </header>

                    <?php if (isset($_GET['status'])): ?>
                        <div class="mb-4 p-4 rounded-xl text-xs font-bold uppercase tracking-widest flex-shrink-0 <?= $_GET['status'] == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $_GET['status'] == 'success' ? "Proof Submitted Successfully!" : htmlspecialchars($_GET['status']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="glass-card flex-1 overflow-hidden flex flex-col p-0 border border-slate-200">
                        <div class="overflow-y-auto custom-scrollbar flex-1">
                            <?php if(empty($slips)): ?>
                                <div class="p-10 text-center text-slate-400 italic">No transactions found.</div>
                            <?php else: ?>
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-slate-50 sticky top-0 z-10 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-100">
                                        <tr>
                                            <th class="px-6 py-4 bg-slate-50">Date</th>
                                            <th class="px-6 py-4 bg-slate-50">Activity</th>
                                            <th class="px-6 py-4 bg-slate-50">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <?php foreach ($slips as $index => $slip): 
                                            // Prepare JSON for JS
                                            $jsonData = htmlspecialchars(json_encode($slip), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr onclick="selectSlip(this, <?= $jsonData ?>)" 
                                                class="cursor-pointer transition-all hover:bg-blue-50/50 group slip-row"
                                                id="row-<?= $slip['SessionID'] ?>">
                                                
                                                <td class="px-6 py-4 text-xs font-bold text-slate-500 whitespace-nowrap">
                                                    <?= date('M d', strtotime($slip['CreatedAt'])) ?>
                                                    <div class="text-[9px] text-slate-300 font-mono"><?= date('H:i', strtotime($slip['CreatedAt'])) ?></div>
                                                </td>
                                                
                                                <td class="px-6 py-4">
                                                    <p class="font-black text-slate-700 text-sm truncate max-w-[150px]"><?= htmlspecialchars($slip['Title']) ?></p>
                                                    <p class="text-[9px] text-slate-300 font-mono">#<?= $slip['SessionID'] ?></p>
                                                </td>
                                                
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col items-start gap-1">
                                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase <?= $slip['Status'] === 'Returned' ? 'bg-emerald-100 text-emerald-600' : 'bg-blue-100 text-blue-600' ?>">
                                                            <?= $slip['Status'] ?>
                                                        </span>
                                                        <?php if($slip['liability_status'] === 'HasLiability'): ?>
                                                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase bg-red-100 text-red-600 animate-pulse">
                                                                Action Required
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <aside class="w-full lg:w-[400px] h-full overflow-y-auto custom-scrollbar pb-10 flex-shrink-0">
                    
                    <div id="empty-state" class="glass-card h-full flex flex-col items-center justify-center text-center p-10 border-2 border-dashed border-slate-200">
                        <span class="text-4xl mb-4">👈</span>
                        <h3 class="text-slate-400 font-bold">Select a Transaction</h3>
                        <p class="text-xs text-slate-300 mt-2">Click a row on the left to view details.</p>
                    </div>

                    <div id="detail-content" class="hidden flex flex-col gap-6">
                        
                        <div id="settlement-section" class="hidden glass-card p-6 border-t-8 border-red-500 shadow-2xl animate-reveal bg-white">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="p-2 bg-red-100 text-red-600 rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-black text-slate-800 uppercase italic text-sm">Liability Alert</h3>
                                    <p class="text-[10px] text-red-500 font-bold uppercase tracking-widest" id="settle-status-text">Unresolved Damage</p>
                                </div>
                            </div>
                            
                            <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                                Items in this transaction were marked as damaged or lost. Please upload proof of replacement or payment to clear your record.
                            </p>

                            <div id="evidence-container" class="mb-4 hidden">
                                </div>

                            <form method="POST" enctype="multipart/form-data" id="settle-form">
                                <input type="hidden" name="damage_id" id="settle_damage_id">
                                
                                <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:border-red-400 transition-colors relative mb-4 group cursor-pointer">
                                    <input type="file" name="proof_photo" required class="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" onchange="document.getElementById('file-label').innerText = this.files[0].name">
                                    <svg class="w-6 h-6 text-slate-300 mx-auto mb-2 group-hover:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4-4m4 4v12"/></svg>
                                    <p class="text-[9px] font-black text-slate-400 uppercase group-hover:text-red-400" id="file-label">Tap to Upload Receipt</p>
                                </div>
                                
                                <button type="submit" class="w-full bg-red-600 text-white py-3 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-red-700 shadow-lg transition-all">
                                    Submit Proof
                                </button>
                            </form>
                            
                            <div id="settle-review-msg" class="hidden p-4 bg-blue-50 text-blue-600 rounded-xl text-center border border-blue-100">
                                <p class="text-xs font-bold">Proof Submitted</p>
                                <p class="text-[10px] opacity-70">Waiting for teacher approval.</p>
                            </div>
                        </div>

                        <div class="px-2">
                            <div class="flex items-center gap-4 mb-4 opacity-50 px-2">
                                <div class="h-px bg-slate-300 flex-1"></div>
                                <span class="text-[9px] font-black uppercase text-slate-400">Digital Record</span>
                                <div class="h-px bg-slate-300 flex-1"></div>
                            </div>

                            <div id="receipt-capture" class="receipt-container thermal-font p-6 text-slate-800 w-full relative">
                                <div class="receipt-tear-top"></div>
                                
                                <div class="text-center mb-6 border-b-2 border-black/10 pb-4">
                                    <h4 class="text-xl font-bold uppercase tracking-widest">SNHS LAB</h4>
                                    <p class="text-[9px] uppercase mt-1 text-slate-500">Borrowing Record</p>
                                    <p class="text-[9px] mt-1 font-bold" id="receipt-date">--</p>
                                </div>

                                <div class="space-y-1 mb-6 text-[10px] uppercase font-bold">
                                    <div class="flex justify-between"><span class="text-slate-500">Student:</span><span><?= $_SESSION['user_name'] ?></span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">ID:</span><span id="receipt-id">--</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">Activity:</span><span class="text-right w-1/2 truncate" id="receipt-title">--</span></div>
                                </div>

                                <div class="mb-6">
                                    <div class="flex justify-between text-[9px] font-bold border-b border-black mb-2 pb-1"><span>ITEM</span><span>QTY</span></div>
                                    <div id="receipt-items"></div>
                                </div>

                                <div class="flex flex-col items-center justify-center pt-2">
                                    <div id="qrcode" class="mb-4 mix-blend-multiply opacity-90"></div>
                                    <div class="mt-2 border-2 border-slate-900 px-4 py-1 rounded text-[10px] font-black uppercase tracking-widest" id="receipt-status">--</div>
                                </div>

                                <div class="receipt-tear-bottom"></div>
                            </div>

                            <button onclick="downloadReceipt()" class="mt-4 w-full bg-[#0f172a] text-white py-3 rounded-xl font-black uppercase text-[9px] tracking-widest hover:bg-blue-600 transition-all shadow-xl">
                                Save Image
                            </button>
                        </div>
                    </div>

                </aside>
            </main>
        </div>
    </div>

    <script>
        // Auto-select first row
        window.addEventListener('load', () => {
            const firstRow = document.querySelector('.slip-row');
            if(firstRow) firstRow.click();
        });

        function selectSlip(row, data) {
            // 1. UI Toggles
            document.getElementById('empty-state').classList.add('hidden');
            document.getElementById('detail-content').classList.remove('hidden');

            document.querySelectorAll('.slip-row').forEach(r => {
                r.classList.remove('active-row', 'bg-blue-50');
            });
            row.classList.add('active-row', 'bg-blue-50');

            // 2. Fill Receipt
            document.getElementById('receipt-date').innerText = new Date(data.CreatedAt).toISOString().split('T')[0];
            document.getElementById('receipt-id').innerText = '#' + data.SessionID;
            document.getElementById('receipt-title').innerText = data.Title;
            document.getElementById('receipt-status').innerText = data.Status;

            // 3. Fill Items
            const itemsContainer = document.getElementById('receipt-items');
            itemsContainer.innerHTML = '';
            if(data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    itemsContainer.innerHTML += `
                        <div class="flex justify-between text-[10px] font-bold py-1 border-b border-dashed border-black/10">
                            <span>${item.Item_Name}</span><span>${item.Quantity}</span>
                        </div>`;
                });
            } else {
                itemsContainer.innerHTML = '<p class="text-[9px] italic text-center text-slate-400">No items recorded</p>';
            }

            // 4. QR Code
            const qrContainer = document.getElementById("qrcode");
            qrContainer.innerHTML = "";
            if (data.QR_Code_Data && ['Pending', 'Approved', 'Issued'].includes(data.Status)) {
                new QRCode(qrContainer, {
                    text: data.QR_Code_Data,
                    width: 100, height: 100,
                    colorDark : "#000000", colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            } else {
                qrContainer.innerHTML = '<span class="text-2xl opacity-20">🔒</span>';
            }

            // 5. SETTLEMENT LOGIC (The new feature)
            const settlementSection = document.getElementById('settlement-section');
            const settleForm = document.getElementById('settle-form');
            const settleMsg = document.getElementById('settle-review-msg');
            const statusText = document.getElementById('settle-status-text');
            const evidenceBox = document.getElementById('evidence-container');

            if (data.liability_status === 'HasLiability') {
                settlementSection.classList.remove('hidden');
                
                // Show Evidence if available
                if (data.evidence_image) {
                    evidenceBox.innerHTML = `
                        <p class="text-[9px] font-black text-slate-400 uppercase mb-2">Damage Evidence</p>
                        <div class="relative group cursor-pointer overflow-hidden rounded-xl border border-slate-200">
                            <img src="../../uploads/evidence/${data.evidence_image}" class="w-full h-32 object-cover">
                            <a href="../../uploads/evidence/${data.evidence_image}" target="_blank" class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <span class="text-white text-[9px] font-bold uppercase">View Full Image</span>
                            </a>
                        </div>
                    `;
                    evidenceBox.classList.remove('hidden');
                } else {
                    evidenceBox.innerHTML = '';
                    evidenceBox.classList.add('hidden');
                }

                if (data.damage_db_status === 'Under Review') {
                    // Already uploaded, waiting for teacher
                    settleForm.classList.add('hidden');
                    settleMsg.classList.remove('hidden');
                    statusText.innerText = "Under Review";
                    statusText.classList.replace('text-red-500', 'text-blue-500');
                    settlementSection.classList.replace('border-red-500', 'border-blue-500');
                } else {
                    // Needs upload
                    settleForm.classList.remove('hidden');
                    settleMsg.classList.add('hidden');
                    document.getElementById('settle_damage_id').value = data.damage_id;
                    
                    statusText.innerText = "Unresolved Damage";
                    statusText.classList.replace('text-blue-500', 'text-red-500');
                    settlementSection.classList.replace('border-blue-500', 'border-red-500');
                }
            } else {
                settlementSection.classList.add('hidden');
            }
        }

        function downloadReceipt() {
            html2canvas(document.getElementById('receipt-capture'), { scale: 3, backgroundColor: null }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Receipt.png';
                link.href = canvas.toDataURL();
                link.click();
            });
        }
    </script>
</body>
</html>