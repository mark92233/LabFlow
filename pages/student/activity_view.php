<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$activity_id = $_GET['activity_id'] ?? null;
$student_id = $_SESSION['user_id'];

// 2. Fetch Activity Details
$activity = $db->getActivityDetails($activity_id); 
if (!$activity) { header("Location: lab_list.php?error=not_found"); exit(); }

// 2.5 GROUP LOGIC
$myGroup = null;
$classmates = [];
$groupMembers = [];
$isGroupActivity = ($activity['type'] === 'Group');
$masterID = $db->getMasterID($student_id);

if ($isGroupActivity) {
    $myGroup = $db->getStudentGroupStatus($activity_id, $masterID);
    
    if (!$myGroup) {
        $classID = $db->getStudentClassID($student_id);
        $classmates = $db->getAvailableClassmates($activity_id, $classID, $masterID);
        $groupLimit = $activity['group_limit'] ?? 4;
    } else {
        $groupMembers = $db->getGroupMembers($myGroup['GroupID']);
    }
}

// 3. FETCH RECORDS & STATUS
$slip = $db->getSessionForActivity($student_id, $activity_id);

// 🔥 USE NEW ENGINE: This fetches grade, feedback, and lock status correctly for everyone
$statusData = $db->getStudentActivityStatus($activity_id, $student_id);
$submission = $statusData['submission']; // Use the smart submission object
$myGrade = $statusData['grade'];         // The correct individual OR group grade
$myFeedback = $statusData['feedback'];   // The feedback text
$accessStatus = $statusData;             // Contains 'is_locked', etc.

// 4. FILTER SLIP
if ($slip && is_array($slip)) {
    if ((int)$slip['ActivityID'] !== (int)$activity_id) { $slip = null; }
}

// 5. VIEW STATE & LOGISTICS INTERCEPTOR
$isBuilderMode = ($activity['submission_mode'] === 'Builder');
$primary_view = 'LOADING';
$showLeaderCart = isset($_GET['mode']) && $_GET['mode'] === 'cart'; // Allow leader toggle

// A. Grading Check
if ($submission && isset($submission['Status']) && $submission['Status'] === 'Graded') {
    $primary_view = 'GRADED';
}
// B. Logistics Interceptor
elseif ($isGroupActivity && $myGroup && (!$slip || !is_array($slip)) && (!$submission || $submission['Status'] !== 'Submitted')) {
    
    // 1. Leader Logic (HIGHEST PRIORITY)
    if ($myGroup['Is_Leader'] && !$showLeaderCart) {
        // Always keep Leader in Distributor Mode unless they explicitly clicked "Go to Cart"
        $logisticsData = $db->getLogisticsOverview($activity_id, $myGroup['GroupID']);
        $distStats = $db->getDistributionStats($activity_id, $myGroup['GroupID']); // Check safety
        $primary_view = 'DISTRIBUTOR';
    }
    // 2. Member Logic (or Leader viewing Cart)
    else {
        $mySecondaryList = $db->getMyAssignedItems($activity_id, $myGroup['GroupID'], $masterID);
        
        if (!empty($mySecondaryList)) {
            $suggested = $mySecondaryList; 
            $primary_view = 'REQUISITION';
        } else {
            // Member with no items -> Waiting Room
            $primary_view = 'WAITING';
        }
    }
}
// C. Active Slip Check (If I have requested items, show Receipt)
elseif ($slip && is_array($slip) && $slip['Status'] !== 'Returned') {
    $primary_view = 'RECEIPT';
}

// D. Submission / Workspace (Work Phase)
// Only show this if we passed the Logistics check (meaning logistics are done or not needed)
elseif ($isBuilderMode || ($submission && $submission['Status'] === 'Submitted')) {
    $primary_view = $isBuilderMode ? 'BUILDER' : 'SUBMISSION';
}

// E. Fallback (Individual Requisition)
else {
    $primary_view = 'REQUISITION';
}
// 6. FETCH ITEMS (For Receipt View)
$borrowed_items = [];
if ($slip && is_array($slip)) {
    $sid = $slip['SessionID'];
    $iQuery = "SELECT bi.Quantity, i.Item_Name FROM borrowed_items bi 
               JOIN inventory i ON bi.ItemID = i.ItemID WHERE bi.SessionID = :sid";
    $iStmt = $db->db->prepare($iQuery);
    $iStmt->execute(['sid' => $sid]);
    $borrowed_items = $iStmt->fetchAll(PDO::FETCH_ASSOC);
}

// 7. FETCH SHOP
// 🟢 FIX: Only fetch default if $suggested wasn't set by the Interceptor
if (!isset($suggested)) {
    $suggested = $db->getActivityRequirements($activity_id); 
}
$all_inventory = $db->getInventoryShop(); 
$categories = $db->getCategories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($activity['Title']) ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 10px; }
        .thermal-font { font-family: 'Space Mono', monospace; }
        .receipt-paper { background-color: #fff; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .receipt-tear-top { position: absolute; top: -5px; left: 0; width: 100%; height: 10px; background: linear-gradient(135deg, transparent 33%, #fff 33%, #fff 66%, transparent 66%) 0 0; background-size: 20px 10px; }
        .receipt-tear-bottom { position: absolute; bottom: -10px; left: 0; width: 100%; height: 10px; background: linear-gradient(45deg, transparent 33%, #fff 33%, #fff 66%, transparent 66%) 0 0; background-size: 20px 10px; transform: rotate(180deg); }
        .blended-pdf { mix-blend-mode: multiply; border: none; }
        .shop-modal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; align-items: stretch; }
        .item-card { display: flex; flex-direction: column; height: 100%; width: 100%; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 flex flex-col lg:flex-row gap-8 animate-reveal">
                <div class="flex-1">
                    <header class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div class="flex items-center gap-6">
                            <a href="lab_list.php?class_id=<?= $activity['ClassID'] ?>" class="p-3 bg-white border border-slate-100 rounded-2xl shadow-sm text-slate-400 hover:text-blue-600 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </a>
                            <div>
                                <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter">Lab <span class="text-blue-600">Manual.</span></h2>
                                
                                <?php if ($isGroupActivity): ?>
                                    <button onclick="openLobbyModal()" class="mt-2 group flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-full hover:border-indigo-500 transition-all shadow-sm">
                                        <?php if ($myGroup): ?>
                                            <span class="text-[10px] font-black uppercase text-indigo-600">Team: <?= htmlspecialchars($myGroup['GroupName']) ?></span>
                                            <?php if($myGroup['Is_Leader']): ?>
                                                <span class="text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-black uppercase">👑 Leader</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                                            <span class="text-[10px] font-black uppercase text-slate-500 group-hover:text-indigo-600">No Team Created</span>
                                        <?php endif; ?>
                                        <svg class="w-3 h-3 text-slate-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </header>

                    <section class="glass-card p-10 border-t-8 border-blue-600 shadow-xl mb-8">
                        <div class="flex justify-between items-start mb-8">
                            <h3 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter"><?= htmlspecialchars($activity['Title']) ?></h3>
                            <span class="bg-red-50 text-red-500 text-[10px] font-black px-4 py-2 rounded-xl uppercase italic">Deadline: <?= date('M d, H:i', strtotime($activity['Deadline'])) ?></span>
                        </div>

                        <?php if(!empty($activity['Manual_URL'])): ?>
                        <div class="mb-10" x-data="{ showPreview: false }">
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between mb-4">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-red-100 text-red-600 rounded-xl">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-black text-slate-800 uppercase leading-none mb-1">Experiment Manual</p>
                                        <p class="text-[9px] text-slate-400 font-bold uppercase italic tracking-widest">PDF Document</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="togglePDF()" id="pdfToggleBtn" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 text-[9px] font-black uppercase tracking-widest rounded-xl hover:text-blue-600 hover:border-blue-200 transition-all">Preview</button>
                                    <a href="../../<?= htmlspecialchars($activity['Manual_URL']) ?>" target="_blank" class="px-5 py-2.5 bg-[#0f172a] text-white text-[9px] font-black uppercase tracking-widest rounded-xl hover:bg-blue-600 transition-all">Download</a>
                                </div>
                            </div>
                            <div id="pdf-viewer" class="hidden w-full h-[600px] bg-slate-100 rounded-3xl overflow-hidden border border-slate-200 relative">
                                <object data="../../<?= htmlspecialchars($activity['Manual_URL']) ?>" type="application/pdf" class="w-full h-full blended-pdf relative z-10">
                                    <p class="p-10 text-center text-sm text-slate-500">Your browser cannot display this PDF inline.</p>
                                </object>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="prose prose-slate text-sm leading-relaxed text-slate-600">
                            <h4 class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-4 italic">Description</h4>
                            <?= nl2br(htmlspecialchars($activity['Description'])) ?>
                        </div>
                    </section>
                </div>

                <aside class="w-full lg:w-96 sticky top-8 h-fit flex flex-col gap-6">

                    <?php if ($primary_view === 'GRADED'): ?>
        <div class="glass-card p-8 border-t-8 border-emerald-500 bg-emerald-50/20 shadow-2xl animate-reveal active">
            <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mb-4 italic">Assessment Result</p>
            <div class="flex items-baseline gap-2 mb-6">
                <span class="text-6xl font-black text-slate-900"><?= $myGrade ?? '0' ?></span>
                <span class="text-slate-400 font-bold uppercase text-xs">/ 100</span>
            </div>
            <div class="p-5 bg-white rounded-2xl border border-emerald-100">
                <p class="text-[8px] font-black text-slate-400 uppercase mb-2">Teacher Feedback</p>
                <p class="text-xs font-medium text-slate-700 italic">"<?= htmlspecialchars($myFeedback ?? 'No feedback provided.') ?>"</p>
            </div>
        </div>
                        <?php elseif ($primary_view === 'BUILDER'): ?>
    <div class="glass-card p-8 border-t-8 border-indigo-600 shadow-2xl animate-reveal active text-center">
        <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </div>
        
        <?php if ($accessStatus['is_submitted'] ?? false): ?>
            <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tighter mb-2">Report <span class="text-emerald-600">Submitted.</span></h3>
            <a href="workspace.php?activity_id=<?= $activity_id ?>" class="block w-full bg-[#0f172a] text-white py-5 rounded-[2.5rem] font-black uppercase text-[10px] tracking-widest hover:bg-slate-700 transition-all shadow-xl italic">View Archive</a>
        
        <?php elseif ($accessStatus['is_locked'] ?? false): ?>
            <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tighter mb-2">Workspace <span class="text-red-500">Locked.</span></h3>
            <a href="workspace.php?activity_id=<?= $activity_id ?>" class="block w-full bg-slate-200 text-slate-500 py-5 rounded-[2.5rem] font-black uppercase text-[10px] tracking-widest transition-all italic">View Read-Only</a>
        
        <?php else: ?>
            <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tighter mb-2">Collaborative <span class="text-indigo-600">Workspace.</span></h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-8 leading-relaxed">
                Draft and edit your report with your team in real-time.
            </p>
            <a href="workspace.php?activity_id=<?= $activity_id ?>" class="block w-full bg-[#0f172a] text-white py-5 rounded-[2.5rem] font-black uppercase text-[10px] tracking-[0.2em] hover:bg-indigo-600 transition-all shadow-xl italic">
                Open Terminal
            </a>
        <?php endif; ?>
    </div>
 <?php elseif ($primary_view === 'DISTRIBUTOR'): ?>
    <div class="glass-card p-8 border-t-8 border-indigo-600 shadow-2xl animate-reveal" x-data="logisticsManager()">
        <h3 class="font-black text-slate-800 italic uppercase text-xs tracking-widest mb-4">Logistics Command</h3>
        
        <div class="mb-6 p-4 rounded-xl text-[10px] font-bold border 
            <?= $distStats['is_complete'] ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-amber-50 border-amber-200 text-amber-700' ?>">
            
            <?php if($distStats['is_complete']): ?>
                <div class="flex items-center gap-2">
                    <span class="text-xl">✅</span>
                    <div>
                        <p class="uppercase">Distribution Complete</p>
                        <p class="font-normal opacity-80">All items assigned. Every member covered.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-2 uppercase tracking-widest opacity-70">Action Required:</div>
                <ul class="list-disc list-inside space-y-1">
                    <?php if($distStats['remaining_items_count'] > 0): ?>
                        <li>Distribute <strong><?= $distStats['remaining_items_count'] ?></strong> more items.</li>
                    <?php endif; ?>
                    <?php if(!empty($distStats['freeloaders'])): ?>
                        <li>Assign items to: 
                            <?php foreach($distStats['freeloaders'] as $f) echo htmlspecialchars($f['Full_Name']) . ', '; ?>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="space-y-4 mb-6 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
            <?php foreach ($logisticsData as $item): ?>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div class="flex justify-between mb-2">
                        <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($item['Item_Name']) ?></span>
                        <span class="text-[9px] font-black uppercase" :class="'<?= $item['Remaining'] ?>' > 0 ? 'text-indigo-600' : 'text-emerald-500'">
                            <?= $item['Remaining'] > 0 ? "Need {$item['Remaining']}" : "Done" ?> / <?= $item['Required_Qty'] ?>
                        </span>
                    </div>
                    <?php if($item['Remaining'] > 0): ?>
                        <div class="flex gap-2">
                            <select id="target_<?= $item['ItemID'] ?>" class="flex-1 bg-white text-[10px] font-bold p-2 rounded-lg border border-slate-200 outline-none">
                                <?php foreach($groupMembers as $m): ?>
                                    <option value="<?= $m['MasterID'] ?>">
                                        <?= $m['Is_Leader'] ? 'Me (Leader)' : htmlspecialchars($m['Full_Name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" id="qty_<?= $item['ItemID'] ?>" value="<?= $item['Remaining'] ?>" max="<?= $item['Remaining'] ?>" min="1" class="w-12 text-center text-[10px] font-bold p-2 rounded-lg border border-slate-200 outline-none">
                            <button @click="assign('<?= $item['ItemID'] ?>')" class="bg-indigo-600 text-white px-3 rounded-lg text-[10px] font-bold hover:bg-indigo-700">Assign</button>
                        </div>
                    <?php else: ?>
                        <div class="bg-emerald-100 text-emerald-600 text-[10px] font-bold text-center py-1 rounded-lg">✅ Fully Distributed</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="flex gap-2">
            <button onclick="window.location.reload()" class="flex-1 bg-slate-100 text-slate-500 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-slate-200">
                Refresh
            </button>
            
            <?php if($distStats['is_complete']): ?>
                <a href="?activity_id=<?= $activity_id ?>&mode=cart" class="flex-[2] bg-emerald-500 text-white py-3 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-emerald-600 text-center flex items-center justify-center shadow-lg shadow-emerald-200">
                    Finalize & Go to Cart →
                </a>
            <?php else: ?>
                <button disabled class="flex-[2] bg-slate-800 text-slate-500 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest cursor-not-allowed opacity-50">
                    Distribution Incomplete
                </button>
            <?php endif; ?>
        </div>
    </div>
        <button onclick="window.location.reload()" class="w-full bg-slate-800 text-white py-3 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-slate-700">
            Refresh Status
        </button>
    </div>

<?php elseif ($primary_view === 'WAITING'): ?>
    <div class="glass-card p-10 border-t-8 border-slate-300 shadow-xl text-center animate-reveal">
        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce">
            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-lg font-black text-slate-700 uppercase italic">Logistics Pending</h3>
        <p class="text-xs text-slate-400 mt-2 mb-6 max-w-[200px] mx-auto">
            Your Team Leader is currently assigning apparatus responsibilities. 
        </p>
        <button onclick="window.location.reload()" class="text-[10px] font-bold text-blue-500 uppercase hover:underline">Check for Updates</button>
    </div>
                    <?php elseif ($primary_view === 'SUBMISSION'): ?>
                        <div class="glass-card p-8 border-t-8 border-blue-600 shadow-2xl animate-reveal active">
                            <h3 class="font-black text-slate-800 italic uppercase text-xs tracking-widest mb-6">Report Submission</h3>
                            <?php if ($submission): ?>
                                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-emerald-100 text-emerald-600 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                                        <a href="../../<?= htmlspecialchars($submission['Report_URL']) ?>" target="_blank" class="text-[8px] text-blue-600 font-bold uppercase underline">View Work</a>
                                    </div>
                                    <p class="text-[8px] font-black text-slate-400 uppercase italic">Awaiting Grade</p>
                                </div>
                            <?php endif; ?>
                            <form action="../../dbRelated/submit_report.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="activity_id" value="<?= $activity_id ?>">
                                <div id="dropzone" class="border-2 border-dashed border-slate-200 rounded-3xl p-10 text-center relative group hover:border-blue-500 transition-colors">
                                    <input type="file" name="lab_report" id="lab_report" required class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFileName(this)">
                                    <div id="upload-prompt">
                                        <svg class="w-10 h-10 text-slate-300 mx-auto mb-4 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4-4m4 4v12"/></svg>
                                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest italic"><?= $submission ? 'Replace File' : 'Upload Lab Report' ?></p>
                                    </div>
                                    <div id="file-selected" class="hidden">
                                        <div class="w-12 h-12 bg-blue-600 text-white rounded-2xl flex items-center justify-center mx-auto mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                                        <p class="text-[10px] font-black text-blue-600 uppercase italic truncate px-4" id="chosen-filename"></p>
                                    </div>
                                </div>
                                <button type="submit" class="w-full bg-[#0f172a] text-white py-5 rounded-[2.5rem] font-black uppercase text-[10px] tracking-widest hover:bg-blue-600 transition-all shadow-xl">Confirm Submission</button>
                            </form>
                        </div>

                    <?php elseif ($primary_view === 'REQUISITION'): ?>
                        <form method="POST" action="../../dbRelated/submit_requisition.php?activity_id=<?= $activity_id ?>">
                            <div class="glass-card p-8 border-t-8 border-slate-900 shadow-2xl">
                                <div class="flex justify-between items-center mb-8">
                                    <h3 class="font-black text-slate-800 italic uppercase text-xs tracking-widest leading-none">Requisition Bag</h3>
                                    <button type="button" onclick="openShopModal()" class="p-2 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 shadow-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M12 4v16m8-8H4"/></svg></button>
                                </div>
                                <div id="cart-container" class="space-y-3 mb-10 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar border-y border-dashed border-slate-100 py-6">
                                    <?php if(empty($suggested)): ?>
                                        <p class="text-center text-[10px] text-slate-300 py-10 uppercase font-black italic empty-msg">Bag is Empty</p>
                                    <?php else: ?>
                                        <?php foreach ($suggested as $item): ?>
                                        <div class="flex items-center gap-3 p-4 bg-slate-100 border border-slate-200 rounded-2xl opacity-80">
                                            <input type="hidden" name="items[]" value="<?= $item['ItemID'] ?>">
                                            <div class="flex-1 truncate"><p class="text-[10px] font-black text-slate-800 uppercase italic truncate"><?= htmlspecialchars($item['Item_Name']) ?></p><span class="text-[7px] bg-slate-800 text-white px-2 py-0.5 rounded-md uppercase font-black">Required</span></div>
                                            <input type="number" name="qtys[]" value="<?= $item['Required_Qty'] ?>" readonly class="w-12 bg-transparent text-center font-black text-[10px] outline-none cursor-not-allowed">
                                            <div class="w-8 h-8 flex items-center justify-center"><svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg></div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="w-full bg-[#0f172a] text-white py-6 rounded-[2.5rem] font-black uppercase text-[10px] tracking-[0.3em] hover:bg-blue-600 shadow-2xl italic">Send Request</button>
                            </div>
                        </form>
                    <?php endif; ?>


                    <?php if ($primary_view === 'RECEIPT' || ($slip && !empty($borrowed_items))): ?>
                        
                        <?php if ($primary_view !== 'RECEIPT'): ?>
                            <div class="flex items-center gap-4 mt-4 opacity-50">
                                <div class="h-px bg-slate-300 flex-1"></div>
                                <span class="text-[9px] font-black uppercase text-slate-400">Inventory Record</span>
                                <div class="h-px bg-slate-300 flex-1"></div>
                            </div>
                        <?php endif; ?>

                        <div id="receipt-capture" class="px-2">
                            <div class="receipt-paper p-6 text-slate-800 thermal-font w-full mx-auto relative group">
                                <div class="receipt-tear-top"></div>
                                
                                <div class="text-center mb-6 border-b-2 border-black/10 pb-4">
                                    <h4 class="text-xl font-bold uppercase tracking-widest">SNHS LAB</h4>
                                    <p class="text-[9px] uppercase mt-1 text-slate-500">Official Borrowing Slip</p>
                                    <p class="text-[9px] mt-1 font-bold"><?= date('Y-m-d H:i') ?></p>
                                </div>

                                <div class="space-y-2 mb-6 text-[10px] uppercase font-bold">
                                    <div class="flex justify-between"><span class="text-slate-500">Student:</span><span><?= $_SESSION['user_name'] ?></span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">Slip ID:</span><span>#<?= $slip['SessionID'] ?></span></div>
                                </div>

                                <div class="mb-6">
                                    <div class="flex justify-between text-[9px] font-bold border-b border-black mb-2 pb-1"><span>ITEM</span><span>QTY</span></div>
                                    <?php foreach ($borrowed_items as $item): ?>
                                    <div class="flex justify-between text-[10px] font-bold py-1 border-b border-dashed border-black/10">
                                        <span><?= htmlspecialchars($item['Item_Name']) ?></span><span><?= $item['Quantity'] ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="flex flex-col items-center justify-center pt-2">
                                    <?php if($slip['Status'] !== 'Returned' && $slip['Status'] !== 'Cancelled'): ?>
                                        <div id="qrcode" class="mb-4 mix-blend-multiply opacity-90"></div>
                                        <p class="text-[8px] font-bold text-center uppercase">Present this slip<br>to the custodian.</p>
                                    <?php else: ?>
                                        <div class="p-2 border-2 border-slate-300 border-dashed rounded mb-2"><span class="text-xl">✅</span></div>
                                        <p class="text-[8px] font-bold text-center uppercase text-slate-400">Items Returned</p>
                                    <?php endif; ?>
                                    
                                    <p class="text-[12px] font-black mt-2 tracking-widest text-slate-900 border-2 border-slate-900 px-2 py-0.5 rounded uppercase"><?= $slip['Status'] ?></p>
                                </div>

                                <div class="receipt-tear-bottom"></div>
                            </div>
                        </div>
                        <button onclick="saveSlipImage()" class="mt-4 w-full bg-slate-100 text-slate-600 py-3 rounded-xl font-black uppercase text-[9px] tracking-widest hover:bg-slate-200 transition-all italic">Download Copy</button>
                    <?php endif; ?>

                </aside>
            </main>
        </div>
    </div>

    <?php if ($isGroupActivity): ?>
    <div id="lobbyModal" class="hidden fixed inset-0 z-[100] bg-[#0f172a]/95 backdrop-blur-xl flex items-center justify-center p-6">
        <div class="max-w-2xl w-full bg-white rounded-[2.5rem] shadow-2xl overflow-hidden border border-slate-100 animate-reveal"
             x-data="{ 
                selected: [], 
                limit: <?= ($groupLimit ?? 4) - 1 ?>, 
                createTeam() {
                    if (this.selected.length > this.limit) { alert('Too many members selected!'); return; }
                    const formData = new FormData(document.getElementById('lobbyForm'));
                    fetch('group_actions.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(d => {
                            if(d.status === 'success') window.location.reload();
                            else alert(d.message);
                        });
                }
             }">

            <div class="bg-indigo-900 p-10 text-white relative overflow-hidden flex justify-between items-start">
                <div class="relative z-10">
                    <h1 class="text-3xl font-black italic tracking-tighter">Team <span class="text-indigo-400">Lobby.</span></h1>
                    <p class="text-xs font-bold uppercase text-indigo-300 tracking-widest mt-2">Manage your squad</p>
                </div>
                <button onclick="closeLobbyModal()" class="relative z-20 text-indigo-300 hover:text-white text-2xl transition-colors">&times;</button>
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500 rounded-full blur-3xl opacity-20"></div>
            </div>

            <div class="p-10">
                <?php if ($myGroup): ?>
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 text-green-600 rounded-full mb-6">
                            <span class="text-2xl">🛡️</span>
                        </div>
                        <h2 class="text-2xl font-black text-slate-800 uppercase italic mb-2"><?= htmlspecialchars($myGroup['GroupName']) ?></h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-8">Team Roster</p>

                        <div class="bg-slate-50 rounded-2xl p-6 text-left max-w-sm mx-auto border border-slate-200 mb-8">
                            <ul class="space-y-3">
                                <?php foreach ($groupMembers as $m): ?>
                                    <li class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shadow-sm
                                            <?= $m['Is_Leader'] ? 'bg-yellow-100 text-yellow-700 ring-2 ring-yellow-200' : 'bg-white text-slate-400 border border-slate-100' ?>">
                                            <?= $m['Is_Leader'] ? '👑' : substr($m['Full_Name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <p class="text-xs font-black text-slate-700"><?= htmlspecialchars($m['Full_Name']) ?></p>
                                            <p class="text-[8px] font-bold uppercase text-slate-400"><?= $m['Is_Leader'] ? 'Team Leader' : 'Member' ?></p>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <button onclick="closeLobbyModal()" class="w-full bg-slate-100 text-slate-600 py-4 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-slate-200 transition-all">Close Lobby</button>
                    </div>

                <?php else: ?>
                    <form id="lobbyForm" @submit.prevent="createTeam">
                        <input type="hidden" name="activity_id" value="<?= $activity_id ?>">
                        <div class="mb-6">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest">Team Name</label>
                            <input type="text" name="group_name" required placeholder="e.g. The Avengers" 
                                   class="w-full bg-slate-50 border border-slate-200 p-4 rounded-xl font-bold text-slate-800 focus:outline-none focus:border-indigo-500 transition-all">
                        </div>
                        <div class="mb-8">
                            <div class="flex justify-between items-center mb-3">
                                <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest">Select Members</label>
                                <span class="text-[10px] font-black bg-indigo-100 text-indigo-600 px-2 py-1 rounded" x-text="selected.length + '/' + limit"></span>
                            </div>
                            <div class="h-48 overflow-y-auto bg-slate-50 rounded-xl p-2 border border-slate-200 custom-scrollbar">
                                <?php if (empty($classmates)): ?>
                                    <div class="h-full flex flex-col items-center justify-center text-slate-400"><p class="text-xs font-bold">No available classmates.</p></div>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 gap-2">
                                        <?php foreach ($classmates as $cm): ?>
                                            <label class="flex items-center gap-3 p-3 bg-white border border-slate-100 rounded-lg cursor-pointer hover:border-indigo-400 transition-all select-none group">
                                                <input type="checkbox" name="members[]" value="<?= $cm['MasterID'] ?>" x-model="selected" :disabled="selected.length >= limit && !selected.includes('<?= $cm['MasterID'] ?>')" class="w-4 h-4 text-indigo-600 rounded focus:ring-0 cursor-pointer">
                                                <span class="text-xs font-bold text-slate-600 group-hover:text-indigo-900"><?= htmlspecialchars($cm['Full_Name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="text-[9px] text-slate-400 mt-3 font-bold uppercase">* You are the Leader.</p>
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 text-white py-5 rounded-2xl font-black uppercase text-xs tracking-[0.2em] shadow-lg hover:bg-indigo-700 hover:-translate-y-1 transition-all">Create Team</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="shopModal" class="hidden fixed inset-0 z-[100] bg-[#0f172a]/95 backdrop-blur-xl p-6 md:p-16 overflow-y-auto">
        <div class="max-w-7xl mx-auto animate-reveal active">
            <header class="flex justify-between items-center mb-12"><div><h3 class="text-4xl font-black text-white italic uppercase tracking-tighter">Inventory <span class="text-blue-500">Shop.</span></h3><p class="text-white/40 text-[10px] font-black uppercase tracking-widest mt-1">Select items for activity requirements</p></div><button onclick="closeShopModal()" class="w-16 h-16 flex items-center justify-center rounded-full bg-white/5 text-white hover:bg-red-500 text-2xl transition-all">&times;</button></header>
            <div class="flex flex-col md:flex-row gap-6 mb-12"><input type="text" id="modalSearch" onkeyup="runModalFilters()" placeholder="Search apparatus..." class="flex-[3] bg-white/5 border border-white/10 p-6 rounded-[2.5rem] outline-none text-white font-bold text-lg"><select id="modalCategory" onchange="runModalFilters()" class="flex-1 bg-white/5 border border-white/10 p-6 rounded-[2.5rem] text-white font-bold outline-none cursor-pointer"><option value="all">All Categories</option><?php foreach($categories as $cat): ?> <option value="<?= htmlspecialchars($cat['Category_Name']) ?>"><?= htmlspecialchars($cat['Category_Name']) ?></option> <?php endforeach; ?></select></div>
            <div id="modalGrid" class="shop-modal-grid">
                <?php foreach($all_inventory as $item): ?>
                    <div class="glass-card p-8 border-white/5 hover:border-blue-500 cursor-pointer transition-all duration-500 group item-card bg-white/5" data-item-name="<?= strtolower(htmlspecialchars($item['Item_Name'])) ?>" data-item-category="<?= htmlspecialchars($item['Category_Name'] ?? 'General') ?>" onclick="selectItemFromShop('<?= $item['ItemID'] ?>', '<?= addslashes(htmlspecialchars($item['Item_Name'])) ?>')"><div class="flex justify-between items-start mb-6"><span class="text-[10px] font-black text-white/20 uppercase tracking-widest">#<?= $item['ItemID'] ?></span><span class="text-[10px] font-black text-blue-400 uppercase text-right leading-tight"><?= $item['Available_Qty'] ?> <br> STOCK</span></div><div class="h-40 flex items-center justify-center mb-8"><img src="../../assets/img/items/<?= $item['ItemID'] ?>.png" class="max-h-full object-contain group-hover:scale-110 transition-transform duration-500" onerror="this.src='../../assets/img/placeholder.png'"></div><div class="mt-auto"><h4 class="text-lg font-black text-white uppercase italic leading-tight mb-2 group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($item['Item_Name']) ?></h4></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    

    <script>

        function logisticsManager() {
            return {
                assign(itemId) {
                    const target = document.getElementById('target_' + itemId).value;
                    const qty = document.getElementById('qty_' + itemId).value;

                    console.log("Attempting to assign Item:", itemId, "to MasterID:", target);
                    
                    const fd = new FormData();
                    fd.append('action', 'assign_logistics'); 
                    fd.append('activity_id', '<?= $activity_id ?>');
                    fd.append('group_id', '<?= $myGroup['GroupID'] ?? '' ?>');
                    fd.append('item_id', itemId);
                    fd.append('target_id', target);
                    fd.append('qty', qty);

                    // Note: Ensure group_actions.php exists and handles 'assign_logistics'
                    fetch('group_actions.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if(d.status === 'success') window.location.reload();
                            else alert(d.message);
                        });
                }
            }
        }

        // Modal Toggles
        function openShopModal() { document.getElementById('shopModal').classList.remove('hidden'); }
        function closeShopModal() { document.getElementById('shopModal').classList.add('hidden'); }
        function openLobbyModal() { document.getElementById('lobbyModal').classList.remove('hidden'); }
        function closeLobbyModal() { document.getElementById('lobbyModal').classList.add('hidden'); }
        
        function togglePDF() { const viewer = document.getElementById('pdf-viewer'); const btn = document.getElementById('pdfToggleBtn'); if(viewer.classList.contains('hidden')) { viewer.classList.remove('hidden'); btn.textContent = 'Hide Preview'; } else { viewer.classList.add('hidden'); btn.textContent = 'Preview'; } }
        function runModalFilters() { const query = document.getElementById('modalSearch').value.toLowerCase(); const cat = document.getElementById('modalCategory').value; document.querySelectorAll('.item-card').forEach(card => { const name = card.getAttribute('data-item-name'); const category = card.getAttribute('data-item-category'); card.style.display = (name.includes(query) && (cat === 'all' || category === cat)) ? 'flex' : 'none'; }); }
        function selectItemFromShop(id, name) { const container = document.getElementById('cart-container'); if (container.querySelector('.empty-msg')) container.querySelector('.empty-msg').remove(); const div = document.createElement('div'); div.className = "flex items-center gap-3 p-4 bg-white border border-slate-100 rounded-2xl shadow-sm active"; div.innerHTML = `<input type="hidden" name="items[]" value="${id}"><div class="flex-1 truncate"><p class="text-[10px] font-black text-slate-800 uppercase italic truncate">${name}</p><span class="text-[7px] text-blue-500 font-black uppercase">Extra</span></div><input type="number" name="qtys[]" value="1" min="1" class="w-12 bg-slate-50 border-none text-center font-black p-2 rounded-xl text-[10px] outline-none"><button type="button" onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></button>`; container.appendChild(div); closeShopModal(); }
        function saveSlipImage() { html2canvas(document.getElementById('receipt-capture'), { scale: 3, backgroundColor: null }).then(canvas => { const link = document.createElement('a'); link.download = 'SNHS-Borrow-Slip.png'; link.href = canvas.toDataURL(); link.click(); }); }
        function previewFileName(input) { if (input.files && input.files[0]) { document.getElementById('upload-prompt').classList.add('hidden'); document.getElementById('file-selected').classList.remove('hidden'); document.getElementById('chosen-filename').textContent = input.files[0].name; } }
        window.onload = function() { const qrData = "<?= $slip['QR_Code_Data'] ?? '' ?>"; const status = "<?= $slip['Status'] ?? '' ?>"; if(qrData && document.getElementById("qrcode") && (status === 'Pending' || status === 'Approved' || status === 'Issued')) { document.getElementById("qrcode").innerHTML = ""; new QRCode(document.getElementById("qrcode"), { text: qrData, width: 100, height: 100, colorDark : "#000000", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H }); } };
    </script>
</body>
</html>