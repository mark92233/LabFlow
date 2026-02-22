<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Teacher') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$view = $_GET['view'] ?? 'pending'; // Default to pending
$cases = $db->getSettlementCases($view);

// 2. Handle Actions
$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_id'])) {
        if ($db->resolveDamage($_POST['approve_id'])) {
            $msg = "Case Resolved. Student liability cleared.";
            $msg_type = "success";
            // Refresh list
            $cases = $db->getSettlementCases($view);
        }
    }
    elseif (isset($_POST['reject_id'])) {
        if ($db->rejectDamage($_POST['reject_id'])) {
            $msg = "Proof Rejected. Student notified.";
            $msg_type = "error";
            $cases = $db->getSettlementCases($view);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settlement Reviews | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
                    <header class="mb-6 flex-shrink-0 flex justify-between items-end">
                        <div>
                            <h2 class="text-4xl font-extrabold text-[#0f172a] tracking-tighter mb-2">Settlement <span class="text-blue-600">Center.</span></h2>
                            <p class="text-slate-400 font-medium text-xs">Review damaged return settlements.</p>
                        </div>
                        
                        <div class="bg-slate-200 p-1 rounded-xl flex text-[10px] font-black uppercase tracking-widest">
                            <a href="?view=pending" class="px-4 py-2 rounded-lg transition-all <?= $view === 'pending' ? 'bg-white shadow-sm text-blue-600' : 'text-slate-500 hover:text-slate-700' ?>">Pending</a>
                            <a href="?view=history" class="px-4 py-2 rounded-lg transition-all <?= $view === 'history' ? 'bg-white shadow-sm text-blue-600' : 'text-slate-500 hover:text-slate-700' ?>">History</a>
                        </div>
                    </header>

                    <?php if ($msg): ?>
                        <div class="mb-4 p-4 rounded-xl text-xs font-bold uppercase tracking-widest flex-shrink-0 <?= $msg_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?>">
                            <?= $msg ?>
                        </div>
                    <?php endif; ?>

                    <div class="glass-card flex-1 overflow-hidden flex flex-col p-0 border border-slate-200">
                        <div class="overflow-y-auto custom-scrollbar flex-1">
                            <?php if(empty($cases)): ?>
                                <div class="p-10 text-center text-slate-400 italic">No records found for this view.</div>
                            <?php else: ?>
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-slate-50 sticky top-0 z-10 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-100">
                                        <tr>
                                            <th class="px-6 py-4 bg-slate-50">Student / ID</th>
                                            <th class="px-6 py-4 bg-slate-50">Issue</th>
                                            <th class="px-6 py-4 bg-slate-50">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <?php foreach ($cases as $index => $case): 
                                            $jsonData = htmlspecialchars(json_encode($case), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr onclick="selectCase(this, <?= $jsonData ?>)" 
                                                class="cursor-pointer transition-all hover:bg-blue-50/50 group slip-row"
                                                id="row-<?= $case['damage_id'] ?>">
                                                
                                                <td class="px-6 py-4">
                                                    <p class="font-black text-slate-700 text-sm"><?= htmlspecialchars($case['Full_Name']) ?></p>
                                                    <p class="text-[9px] text-slate-400 font-mono"><?= htmlspecialchars($case['ID_Number']) ?></p>
                                                </td>
                                                
                                                <td class="px-6 py-4">
                                                    <p class="text-xs font-bold text-slate-600"><?= htmlspecialchars($case['Item_Name']) ?></p>
                                                    <p class="text-[9px] text-red-400 font-bold uppercase italic"><?= $case['damage_type'] ?> (x<?= $case['qty_damaged'] ?>)</p>
                                                </td>
                                                
                                                <td class="px-6 py-4">
                                                    <?php if($case['status'] === 'Under Review'): ?>
                                                        <span class="bg-blue-100 text-blue-600 px-2 py-0.5 rounded text-[8px] font-black uppercase animate-pulse">Review Now</span>
                                                    <?php elseif($case['status'] === 'Resolved'): ?>
                                                        <span class="bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded text-[8px] font-black uppercase">Cleared</span>
                                                    <?php else: ?>
                                                        <span class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded text-[8px] font-black uppercase">Waiting</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <aside class="w-full lg:w-[450px] h-full overflow-y-auto custom-scrollbar flex flex-col gap-6 pb-10 flex-shrink-0">
                    
                    <div id="empty-state" class="glass-card h-full flex flex-col items-center justify-center text-center p-10 border-2 border-dashed border-slate-200">
                        <span class="text-4xl mb-4">👈</span>
                        <h3 class="text-slate-400 font-bold">Select a Case</h3>
                        <p class="text-xs text-slate-300 mt-2">Click a student on the left to review.</p>
                    </div>

                    <div id="detail-content" class="hidden flex flex-col gap-6">
                        
                        <div class="glass-card p-0 border-t-8 border-blue-600 shadow-2xl animate-reveal bg-white overflow-hidden">
                            <div class="p-6 border-b border-slate-50">
                                <h3 class="font-black text-slate-800 uppercase italic text-sm mb-1">Evidence & Proof</h3>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest" id="proof-status-text">--</p>
                            </div>
                            
                            <div class="grid grid-cols-2 h-48 bg-slate-100 divide-x divide-white">
                                
                                <div class="relative group overflow-hidden">
                                    <img id="evidence-img" src="" class="w-full h-full object-cover hidden">
                                    <div id="no-evidence-msg" class="flex items-center justify-center h-full text-[9px] text-slate-400 uppercase font-bold hidden">No Evidence Photo</div>
                                    <a id="evidence-link" href="#" target="_blank" class="hidden absolute inset-0 bg-black/60 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-white text-[8px] font-black uppercase mb-1">Teacher's Report</span>
                                        <span class="bg-white text-black px-3 py-1 rounded-full text-[8px] font-bold uppercase">View</span>
                                    </a>
                                    <span class="absolute top-2 left-2 bg-black/50 text-white text-[7px] font-black px-2 py-0.5 rounded uppercase pointer-events-none">Damage</span>
                                </div>

                                <div class="relative group overflow-hidden">
                                    <img id="proof-img" src="" class="w-full h-full object-cover hidden">
                                    <div id="no-proof-msg" class="flex items-center justify-center h-full text-[9px] text-slate-400 uppercase font-bold hidden">Pending Upload</div>
                                    <a id="proof-link" href="#" target="_blank" class="hidden absolute inset-0 bg-black/60 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="text-white text-[8px] font-black uppercase mb-1">Student's Proof</span>
                                        <span class="bg-white text-black px-3 py-1 rounded-full text-[8px] font-bold uppercase">View</span>
                                    </a>
                                    <span class="absolute top-2 left-2 bg-blue-600 text-white text-[7px] font-black px-2 py-0.5 rounded uppercase pointer-events-none">Settlement</span>
                                </div>
                            </div>

                            <div class="p-6 bg-slate-50 border-t border-slate-100">
                                <p class="text-xs font-bold text-slate-700 uppercase mb-1" id="damage-detail-text">--</p>
                                <p class="text-[10px] text-slate-500 italic mb-4" id="damage-note-text">--</p>

                                <?php if($view === 'pending'): ?>
                                <div class="grid grid-cols-2 gap-3" id="action-buttons">
                                    <form method="POST" onsubmit="return confirm('Reject this proof?');">
                                        <input type="hidden" name="reject_id" id="reject_input">
                                        <button type="submit" class="w-full py-3 rounded-xl border border-red-200 text-red-500 hover:bg-red-50 font-black uppercase text-[10px] tracking-widest transition-colors">
                                            Reject
                                        </button>
                                    </form>

                                    <form method="POST" onsubmit="return confirm('Mark as Resolved?');">
                                        <input type="hidden" name="approve_id" id="approve_input">
                                        <button type="submit" class="w-full py-3 rounded-xl bg-emerald-500 text-white hover:bg-emerald-600 font-black uppercase text-[10px] tracking-widest transition-colors shadow-lg">
                                            Approve
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <div class="text-center p-2 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-bold uppercase border border-emerald-100">
                                        Transaction Settled
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="px-2 opacity-75 hover:opacity-100 transition-opacity">
                            <div class="flex items-center gap-4 mb-4 opacity-50 px-2">
                                <div class="h-px bg-slate-300 flex-1"></div>
                                <span class="text-[9px] font-black uppercase text-slate-400">Original Transaction</span>
                                <div class="h-px bg-slate-300 flex-1"></div>
                            </div>

                            <div class="receipt-container thermal-font p-6 text-slate-800 w-full relative">
                                <div class="receipt-tear-top"></div>
                                
                                <div class="text-center mb-6 border-b-2 border-black/10 pb-4">
                                    <h4 class="text-xl font-bold uppercase tracking-widest">SNHS LAB</h4>
                                    <p class="text-[9px] uppercase mt-1 text-slate-500">Reference Slip</p>
                                    <p class="text-[9px] mt-1 font-bold" id="receipt-date">--</p>
                                </div>

                                <div class="space-y-1 mb-6 text-[10px] uppercase font-bold">
                                    <div class="flex justify-between"><span class="text-slate-500">Student:</span><span id="receipt-student">--</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">ID:</span><span id="receipt-id">--</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">Activity:</span><span class="text-right w-1/2 truncate" id="receipt-title">--</span></div>
                                </div>

                                <div class="mb-6">
                                    <div class="flex justify-between text-[9px] font-bold border-b border-black mb-2 pb-1"><span>ITEM</span><span>QTY</span></div>
                                    <div id="receipt-items"></div>
                                </div>

                                <div class="receipt-tear-bottom"></div>
                            </div>
                        </div>

                    </div>
                </aside>
            </main>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const firstRow = document.querySelector('.slip-row');
            if(firstRow) firstRow.click();
        });

        function selectCase(row, data) {
            // 1. UI Toggles
            document.getElementById('empty-state').classList.add('hidden');
            document.getElementById('detail-content').classList.remove('hidden');

            document.querySelectorAll('.slip-row').forEach(r => {
                r.classList.remove('active-row', 'bg-blue-50');
            });
            row.classList.add('active-row', 'bg-blue-50');

            // 2. Populate Action Card Text
            document.getElementById('proof-status-text').innerText = data.status;
            document.getElementById('damage-detail-text').innerText = data.Item_Name + " (Qty: " + data.qty_damaged + ")";
            document.getElementById('damage-note-text').innerText = '"' + data.notes + '"';

            // --- 3. IMAGE LOGIC (Dual View) ---
            
            // A. Teacher's Evidence (Left)
            const evImg = document.getElementById('evidence-img');
            const evLink = document.getElementById('evidence-link');
            const evMsg = document.getElementById('no-evidence-msg');

            if (data.evidence_image) {
                evImg.src = "../../uploads/evidence/" + data.evidence_image;
                evLink.href = "../../uploads/evidence/" + data.evidence_image;
                evImg.classList.remove('hidden');
                evLink.classList.remove('hidden'); evLink.classList.add('flex');
                evMsg.classList.add('hidden');
            } else {
                evImg.classList.add('hidden');
                evLink.classList.add('hidden'); evLink.classList.remove('flex');
                evMsg.classList.remove('hidden');
            }

            // B. Student's Proof (Right)
            const prImg = document.getElementById('proof-img');
            const prLink = document.getElementById('proof-link');
            const prMsg = document.getElementById('no-proof-msg');

            if (data.proof_image) {
                prImg.src = "../../uploads/settlements/" + data.proof_image;
                prLink.href = "../../uploads/settlements/" + data.proof_image;
                prImg.classList.remove('hidden');
                prLink.classList.remove('hidden'); prLink.classList.add('flex');
                prMsg.classList.add('hidden');
            } else {
                prImg.classList.add('hidden');
                prLink.classList.add('hidden'); prLink.classList.remove('flex');
                prMsg.classList.remove('hidden');
            }

            // 4. Populate Forms
            const approveInput = document.getElementById('approve_input');
            const rejectInput = document.getElementById('reject_input');
            if(approveInput) approveInput.value = data.damage_id;
            if(rejectInput) rejectInput.value = data.damage_id;

            // 5. Populate Receipt Context
            document.getElementById('receipt-date').innerText = new Date(data.SlipDate).toISOString().split('T')[0];
            document.getElementById('receipt-student').innerText = data.Full_Name;
            document.getElementById('receipt-id').innerText = '#' + data.session_id;
            document.getElementById('receipt-title').innerText = data.ActivityTitle || 'General Use';

            // 6. Populate Items List
            const itemsContainer = document.getElementById('receipt-items');
            itemsContainer.innerHTML = '';
            if(data.slip_items && data.slip_items.length > 0) {
                data.slip_items.forEach(item => {
                    const isDamagedItem = item.Item_Name === data.Item_Name;
                    const style = isDamagedItem ? 'text-red-500' : '';
                    
                    itemsContainer.innerHTML += `
                        <div class="flex justify-between text-[10px] font-bold py-1 border-b border-dashed border-black/10 ${style}">
                            <span>${item.Item_Name} ${isDamagedItem ? '(Issue)' : ''}</span><span>${item.Quantity}</span>
                        </div>`;
                });
            }
        }
    </script>
</body>
</html>