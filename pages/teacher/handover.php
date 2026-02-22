<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$session_data = null;
$borrowedItems = []; 
$error = "";
$success = "";

// 2. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- A. SEARCH HANDLER (HYBRID LOGIC) ---
    if (isset($_POST['find_slip']) || isset($_POST['search_input']) || isset($_POST['exact_session_id'])) {
        
        $query = "SELECT bs.*, u.UserID as StudentID, m.Full_Name, COALESCE(la.Title, 'General Laboratory Use') as Title
                  FROM borrowing_sessions bs
                  JOIN users u ON bs.StudentID = u.UserID
                  JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                  LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID";
        
        $params = [];

        // PATH 1: Exact Match (Clicked from Table)
        if (!empty($_POST['exact_session_id'])) {
            $query .= " WHERE bs.SessionID = :sid";
            $params = ['sid' => $_POST['exact_session_id']];
        } 
        // PATH 2: Fuzzy Search (Scanner or Typing)
        else {
            $searchQuery = $_POST['search_input'] ?? '';
            $query .= " WHERE (bs.QR_Code_Data = :input OR m.Full_Name LIKE :name_input)
                        AND bs.Status IN ('Pending', 'Approved', 'Issued', 'Returned')";
            $params = ['input' => $searchQuery, 'name_input' => "%$searchQuery%"];
        }

        $query .= " LIMIT 1"; 

        $stmt = $db->db->prepare($query);
        $stmt->execute($params);
        $session_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Error Handling
        if (!$session_data) {
            if(!empty($_POST['exact_session_id'])) {
                 $error = "Session #" . htmlspecialchars($_POST['exact_session_id']) . " is no longer valid.";
            } else {
                 $error = "No active record found.";
            }
        } else {
            // Fetch Items
            $iStmt = $db->db->prepare("SELECT bi.ItemID, bi.Quantity, i.Item_Name 
                                       FROM borrowed_items bi 
                                       JOIN inventory i ON bi.ItemID = i.ItemID 
                                       WHERE bi.SessionID = ?");
            $iStmt->execute([$session_data['SessionID']]);
            $borrowedItems = $iStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // --- B. ISSUE HANDLER ---
    if (isset($_POST['action_issue'])) {
        try {
            if ($db->finalizeHandover($_POST['sid'])) {
                $success = "Apparatus successfully issued!";
                $session_data = null; 
            } else {
                $error = "Handover failed: " . ($db->getLastError() ?? "No details.");
            }
        } catch (Exception $e) { $error = "System Error: " . $e->getMessage(); }
    }

    // --- C. RETURN HANDLER ---
    if (isset($_POST['action_return'])) {
        try {
            $sid = $_POST['sid'];
            if (!empty($_POST['return_data'])) {
                // Complex Return (Damaged)
                $returnData = json_decode($_POST['return_data'], true);
                $result = $db->processReturnWithDamage($sid, $returnData);
            } else {
                // Clean Return
                $result = $db->processCleanReturn($sid);
            }

            if ($result) {
                $success = "Return processed & Inventory updated.";
                $session_data = null; 
            } else {
                $error = "Return failed. Check database connection.";
            }
        } catch (Exception $e) { $error = "System Error: " . $e->getMessage(); }
    }
}

// 3. Waiting List Query (For the Table)
$waitingQuery = "SELECT bs.SessionID, m.Full_Name, bs.Status, bs.CreatedAt
                 FROM borrowing_sessions bs
                 JOIN users u ON bs.StudentID = u.UserID
                 JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                 WHERE bs.Status IN ('Approved', 'Issued', 'Pending') 
                 ORDER BY bs.CreatedAt DESC LIMIT 10";
$waitingList = $db->db->query($waitingQuery)->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Handover Terminal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Handover Terminal | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 animate-reveal relative h-[calc(100vh-100px)] overflow-hidden flex flex-col">
                <header class="mb-8 flex-shrink-0">
                    <h2 class="text-4xl font-extrabold text-[#0f172a] tracking-tighter mb-2">Terminal<span class="text-blue-600">.</span></h2>
                    <p class="text-slate-400 font-medium text-xs">Scan QR or select a session to process.</p>
                </header>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 h-full min-h-0">
                    
                    <div class="lg:col-span-2 flex flex-col gap-6 h-full min-h-0">
                        
                        <section class="glass-card p-6 border-blue-500/20 shadow-lg flex-shrink-0">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Identification</h3>
                                <button type="button" onclick="toggleScanner()" id="scanner-btn" class="bg-blue-50 text-blue-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all">
                                    Start Camera
                                </button>
                            </div>

                            <div id="scanner-wrapper" class="hidden mb-4 rounded-2xl overflow-hidden border-4 border-slate-50 bg-black shadow-inner">
                                <div id="reader" style="width: 100%;"></div>
                            </div>

                            <form method="POST" id="searchForm" class="flex gap-3">
                                <input type="hidden" name="find_slip" value="1">
                                <div class="relative flex-1">
                                    <input type="text" name="search_input" id="search_input" 
                                           placeholder="Scan Result or type Name..." 
                                           class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-100 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 transition-all text-sm font-bold text-slate-700">
                                    <div class="absolute left-3 top-3 text-slate-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    </div>
                                </div>
                                <button type="submit" class="bg-[#0f172a] text-white px-6 rounded-xl font-bold hover:bg-blue-600 transition-all shadow-lg text-xs uppercase tracking-wider">Find</button>
                            </form>
                        </section>

                        <section class="glass-card p-0 border border-slate-200 flex-1 overflow-hidden flex flex-col">
                            <div class="p-4 border-b border-slate-50 bg-white/50 backdrop-blur-sm sticky top-0 z-10">
                                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Active Queue</h3>
                            </div>
                            
                            <div class="overflow-y-auto custom-scrollbar flex-1">
                                <table class="w-full text-left border-collapse">
                                    <thead class="bg-slate-50 text-slate-400 text-[9px] font-black uppercase tracking-widest sticky top-0 z-10">
                                        <tr>
                                            <th class="px-6 py-3">Status</th>
                                            <th class="px-6 py-3">Student Name</th>
                                            <th class="px-6 py-3 text-right">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <?php if(empty($waitingList)): ?>
                                            <tr>
                                                <td colspan="3" class="px-6 py-8 text-center text-xs text-slate-400 italic">No active sessions in queue.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($waitingList as $row): 
                                                // Status Color Logic
                                                $statusClass = match($row['Status']) {
                                                    'Approved' => 'bg-blue-100 text-blue-600',
                                                    'Issued' => 'bg-indigo-100 text-indigo-600',
                                                    'Pending' => 'bg-amber-100 text-amber-600',
                                                    default => 'bg-slate-100 text-slate-500'
                                                };
                                            ?>
                                                <tr onclick="selectSession(<?= $row['SessionID'] ?>)" 
                                                    class="cursor-pointer hover:bg-blue-50/50 transition-colors group">
                                                    
                                                    <td class="px-6 py-4">
                                                        <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase <?= $statusClass ?>">
                                                            <?= $row['Status'] ?>
                                                        </span>
                                                    </td>
                                                    
                                                    <td class="px-6 py-4">
                                                        <p class="text-xs font-bold text-slate-700 group-hover:text-blue-700 transition-colors">
                                                            <?= htmlspecialchars($row['Full_Name']) ?>
                                                        </p>
                                                        <p class="text-[9px] text-slate-400 font-mono">#<?= $row['SessionID'] ?></p>
                                                    </td>
                                                    
                                                    <td class="px-6 py-4 text-right">
                                                        <span class="text-[10px] font-mono text-slate-400">
                                                            <?= date('H:i', strtotime($row['CreatedAt'])) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <div class="space-y-6 h-full overflow-y-auto custom-scrollbar pb-10">
                        <?php if ($error || $success): ?>
                            <div class="<?= $error ? 'bg-red-50 text-red-600 border-red-100' : 'bg-green-50 text-green-600 border-green-100' ?> p-4 rounded-2xl text-xs font-bold border italic animate-reveal">
                                <?= $error ?: $success ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($session_data): ?>
                            <div class="w-full bg-white shadow-2xl p-8 rounded-3xl border-t-8 <?= ($session_data['Status'] == 'Issued') ? 'border-indigo-600' : 'border-blue-600' ?> animate-reveal">
                                <div class="flex justify-between items-start mb-6">
                                    <div>
                                        <p class="text-[9px] font-black text-slate-300 uppercase mb-1">Session ID</p>
                                        <h3 class="text-2xl font-black text-[#0f172a] uppercase italic">#<?= $session_data['SessionID'] ?></h3>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[9px] font-black text-slate-300 uppercase mb-1">Borrower</p>
                                        <h4 class="text-sm font-black text-slate-800 uppercase"><?= htmlspecialchars($session_data['Full_Name']) ?></h4>
                                    </div>
                                </div>
                                
                                <div class="bg-slate-50 rounded-xl p-4 mb-6 border border-slate-100">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 border-b border-slate-200 pb-2">Items Requested</p>
                                    <div class="space-y-2">
                                        <?php foreach ($borrowedItems as $item): ?>
                                            <div class="flex justify-between text-xs font-bold text-slate-700">
                                                <span><?= htmlspecialchars($item['Item_Name']) ?></span>
                                                <span class="text-blue-600">x<?= $item['Quantity'] ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <?php if ($session_data['Status'] == 'Approved' || $session_data['Status'] == 'Pending'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="sid" value="<?= $session_data['SessionID'] ?>">
                                        <button type="submit" name="action_issue" class="w-full bg-[#0f172a] text-white py-4 rounded-xl font-black uppercase text-[10px] tracking-[0.2em] hover:bg-blue-600 transition-all shadow-lg">
                                            Release Apparatus
                                        </button>
                                    </form>

                                <?php elseif ($session_data['Status'] == 'Issued'): ?>
                                    <div class="space-y-3">
                                        <form method="POST" id="quickReturnForm">
                                            <input type="hidden" name="sid" value="<?= $session_data['SessionID'] ?>">
                                            <input type="hidden" name="action_return" value="1">
                                            <button type="button" onclick="confirmQuickReturn()" class="w-full bg-indigo-600 text-white py-4 rounded-xl font-black uppercase text-[10px] tracking-[0.2em] hover:bg-[#0f172a] transition-all shadow-lg hover:shadow-xl">
                                                Return All (Clean)
                                            </button>
                                        </form>
                                        
                                        <button onclick="openDamageModal()" class="w-full bg-red-50 text-red-600 py-4 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-red-600 hover:text-white transition-all">
                                            Report Damage / Loss
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="h-64 flex flex-col items-center justify-center text-center text-slate-300 border-2 border-dashed border-slate-200 rounded-3xl">
                                <span class="text-4xl mb-2">👈</span>
                                <p class="text-xs font-bold uppercase">Select a Session</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form id="tableSelectForm" method="POST" class="hidden">
                    <input type="hidden" name="exact_session_id" id="table_session_id">
                    <input type="hidden" name="find_slip" value="1">
                </form>

                <div id="damageModal" class="fixed inset-0 bg-[#0f172a]/95 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
                    <div class="bg-white w-full max-w-4xl rounded-[2.5rem] p-10 relative animate-reveal shadow-2xl overflow-y-auto max-h-[90vh]">
                        <button onclick="closeDamageModal()" class="absolute top-8 right-8 text-slate-300 hover:text-slate-900 text-2xl transition-colors">&times;</button>
                        
                        <h3 class="text-3xl font-black text-red-600 uppercase italic mb-1">Report Issue</h3>
                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-8">Please specify items that are damaged, lost, or dirty.</p>
                        
                        <form method="POST" id="damageForm" enctype="multipart/form-data">
                            <input type="hidden" name="sid" value="<?= $session_data['SessionID'] ?? '' ?>">
                            <input type="hidden" name="action_return" value="1">
                            <input type="hidden" name="return_data" id="return_data_input">

                            <div class="space-y-4 mb-8" id="modal_items_container">
                                </div>

                            <button type="button" onclick="submitDamageReport()" class="w-full bg-red-600 text-white py-5 rounded-2xl font-black uppercase text-xs tracking-[0.2em] hover:bg-red-700 transition-all shadow-xl">
                                Confirm & Log Damage
                            </button>
                        </form>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        // --- 1. TABLE CLICK HANDLER ---
        function selectSession(sessionId) {
            document.getElementById('table_session_id').value = sessionId;
            document.getElementById('tableSelectForm').submit();
        }

        // --- 2. DATA FROM PHP ---
        const borrowedItems = <?= json_encode($borrowedItems) ?>;
        
        // --- 3. SCANNER LOGIC ---
        let html5QrCode;
        function toggleScanner() {
            const wrapper = document.getElementById('scanner-wrapper');
            const btn = document.getElementById('scanner-btn');
            
            if (wrapper.classList.contains('hidden')) {
                wrapper.classList.remove('hidden');
                btn.innerText = "Stop Camera";
                btn.classList.replace('bg-blue-50', 'bg-red-50');
                btn.classList.replace('text-blue-600', 'text-red-600');
                startScanner();
            } else {
                stopScanner();
                wrapper.classList.add('hidden');
                btn.innerText = "Start Camera";
                btn.classList.replace('bg-red-50', 'bg-blue-50');
                btn.classList.replace('text-red-600', 'text-blue-600');
            }
        }
        function startScanner() {
            html5QrCode = new Html5Qrcode("reader");
            html5QrCode.start(
                { facingMode: "environment" }, 
                { fps: 10, qrbox: 250 },
                (decodedText) => {
                    document.getElementById('search_input').value = decodedText;
                    stopScanner();
                    document.getElementById('searchForm').submit();
                }
            ).catch(err => console.error(err));
        }
        function stopScanner() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    document.getElementById('scanner-wrapper').classList.add('hidden');
                }).catch(err => console.warn(err));
            }
        }

        // --- 4. RETURN LOGIC ---
        function confirmQuickReturn() {
            if(confirm("Are you sure all items are returned in good condition?")) {
                document.getElementById('quickReturnForm').submit();
            }
        }

        function openDamageModal() {
            const container = document.getElementById('modal_items_container');
            container.innerHTML = ''; 

            borrowedItems.forEach((item) => {
                const html = `
                    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 flex flex-col gap-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-xs font-black text-slate-800 uppercase italic">${item.Item_Name}</p>
                                <p class="text-[10px] text-blue-600 font-bold">Borrowed: ${item.Quantity}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase mb-1">Qty Damaged</label>
                                <input type="number" id="dmg_${item.ItemID}" min="0" max="${item.Quantity}" value="0" 
                                       class="w-full bg-white border border-slate-200 p-2 rounded-xl font-bold outline-none focus:border-red-500">
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase mb-1">Issue Type</label>
                                <select id="type_${item.ItemID}" class="w-full bg-white border border-slate-200 p-2 rounded-xl text-xs font-bold text-slate-700 outline-none focus:border-red-500">
                                    <option value="Broken">Broken</option>
                                    <option value="Lost">Lost</option>
                                    <option value="Dirty">Dirty</option>
                                    <option value="Malfunction">Malfunction</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase mb-1">Evidence</label>
                                <input type="file" name="evidence_${item.ItemID}" class="w-full text-[9px]">
                            </div>
                        </div>
                        <div>
                            <input type="text" id="note_${item.ItemID}" placeholder="Briefly describe damage..." 
                                   class="w-full bg-white border border-slate-200 p-2 rounded-xl text-xs outline-none focus:border-red-500">
                        </div>
                    </div>`;
                container.insertAdjacentHTML('beforeend', html);
            });
            document.getElementById('damageModal').classList.remove('hidden');
        }

        function closeDamageModal() {
            document.getElementById('damageModal').classList.add('hidden');
        }

        function submitDamageReport() {
            const data = [];
            let hasError = false;

            borrowedItems.forEach(item => {
                const dmgInput = document.getElementById(`dmg_${item.ItemID}`);
                const typeInput = document.getElementById(`type_${item.ItemID}`);
                const noteInput = document.getElementById(`note_${item.ItemID}`);
                const qtyDamaged = parseInt(dmgInput.value) || 0;
                
                if (qtyDamaged > parseInt(item.Quantity)) {
                    alert(`Error: More damages than borrowed for ${item.Item_Name}`);
                    hasError = true;
                }

                if (qtyDamaged > 0) {
                    data.push({
                        item_id: item.ItemID,
                        qty: qtyDamaged,
                        type: typeInput.value,
                        notes: noteInput.value
                    });
                }
            });

            if(hasError) return;
            if (data.length === 0 && !confirm("No damages specified. Proceed as clean return?")) return;

            document.getElementById('return_data_input').value = JSON.stringify(data);
            document.getElementById('damageForm').submit();
        }
    </script>
</body>
</html>