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

// 4. FILTER SLIP
if ($slip && is_array($slip)) {
    if ((int)$slip['ActivityID'] !== (int)$activity_id) { $slip = null; }
}

// 5. VIEW STATE & LOGISTICS INTERCEPTOR
$primary_view = 'LOADING';
$showLeaderCart = isset($_GET['mode']) && $_GET['mode'] === 'cart'; // Allow leader toggle
 
// NEW: Interceptor for group creation if it's a group activity and user has no group
if ($isGroupActivity && !$myGroup) {
    $primary_view = 'CREATE_GROUP';
}
// A. Logistics Interceptor (For Group activities after a group is formed, but before a slip is made)
elseif ($isGroupActivity && $myGroup && (!$slip || !is_array($slip))) {
    
    // 1. Leader Logic (HIGHEST PRIORITY)
    if ($myGroup['Is_Leader'] && !$showLeaderCart) {
        $logisticsData = $db->getLogisticsOverview($activity_id, $myGroup['GroupID']);
        $primary_view = 'DISTRIBUTOR';
    }
    // 2. Member Logic (or Leader viewing Cart)
    else {
        $mySecondaryList = $db->getMyAssignedItems($activity_id, $myGroup['GroupID'], $masterID);
        if (!empty($mySecondaryList)) {
            $suggested = $mySecondaryList; 
            $primary_view = 'REQUISITION';
        } else {
            $primary_view = 'WAITING';
        }
    }
}
// B. Active Slip Check (If I have requested items, show Receipt)
elseif ($slip && is_array($slip) && $slip['Status'] !== 'Returned') {
    $primary_view = 'RECEIPT';
}
// C. Fallback (Default view is to request items)
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
                    <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div class="flex items-center gap-6">
                            <a href="lab_list.php?class_id=<?= $activity['ClassID'] ?>" class="p-3 bg-white border border-slate-100 rounded-2xl shadow-sm text-slate-400 hover:text-orange-600 transition-all group">
                                <svg class="w-6 h-6 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </a>
                            <div>
                                <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter">Lab <span class="text-orange-600">Manual.</span></h2>
                                
                                <?php if ($isGroupActivity): ?>
                                    <div class="mt-2 group flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-full shadow-sm">
                                        <?php if ($myGroup): ?>
                                            <span class="text-[10px] font-black uppercase text-orange-600">Team: <?= htmlspecialchars($myGroup['GroupName']) ?></span>
                                            <?php if($myGroup['Is_Leader']): ?>
                                                <span class="text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-black uppercase">👑 Leader</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                                            <span class="text-[10px] font-black uppercase text-slate-500">No Team Created</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </header>

                    <section class="bg-white p-10 rounded-3xl border border-slate-200/50 shadow-lg mb-8">
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
                                    <button onclick="togglePDF()" id="pdfToggleBtn" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 text-[9px] font-black uppercase tracking-widest rounded-xl hover:text-orange-600 hover:border-orange-200 transition-all">Preview</button>
                                    <a href="../../<?= htmlspecialchars($activity['Manual_URL']) ?>" target="_blank" class="px-5 py-2.5 bg-[#0f172a] text-white text-[9px] font-black uppercase tracking-widest rounded-xl hover:bg-orange-600 transition-all">Download</a>
                                </div>
                            </div>
                            <div id="pdf-viewer" class="hidden w-full h-[600px] bg-slate-100 rounded-3xl overflow-hidden border border-slate-200 relative">
                                <object data="../../<?= htmlspecialchars($activity['Manual_URL']) ?>" type="application/pdf" class="w-full h-full blended-pdf relative z-10">
                                    <p class="p-10 text-center text-sm text-slate-500">Your browser cannot disp lay this PDF inline.</p>
                                </object>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="prose prose-slate text-sm leading-relaxed text-slate-600">
                            <h4 class="text-[10px] font-black text-orange-500 uppercase tracking-widest mb-4 italic">Description</h4>
                            <?= nl2br(htmlspecialchars($activity['Description'])) ?>
                        </div>
                    </section>
                </div>

                <aside class="w-full lg:w-96 sticky top-8 h-fit flex flex-col gap-6">
                    
                    <?php if ($isGroupActivity && $myGroup): ?>
                        <div class="bg-white p-8 rounded-3xl border border-slate-200/50 shadow-lg">
                            <h3 class="font-black text-slate-800 italic uppercase text-xs tracking-widest mb-6">Team Roster</h3>
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
                    <?php endif; ?>

                    <?php if ($primary_view === 'DISTRIBUTOR'): ?>
    <div class="bg-white p-8 rounded-3xl border border-slate-200/50 shadow-lg animate-reveal"
         x-data="logisticsManager(<?= htmlspecialchars(json_encode($logisticsData), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($groupMembers), ENT_QUOTES) ?>)">
        <h3 class="font-black text-slate-800 italic uppercase text-xs tracking-widest mb-6">Logistics Hub</h3>

        <div class="space-y-4 mb-6 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
            <template x-for="item in logisticsData" :key="item.ItemID + '_' + (item.VariantID || 0)">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <!-- Item Header -->
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-200">
                        <span class="text-sm font-black text-slate-800 uppercase italic" x-text="item.Item_Name + (item.Size_Value ? ` (${item.Size_Value}${item.Unit || ''})` : '')"></span>
                        <div class="text-right">
                            <p class="text-xs font-black uppercase"
                              :class="getRemaining(item) > 0 ? 'text-orange-600' : 'text-emerald-500'">
                                <span x-text="getRemaining(item)"></span> Remaining
                            </p>
                            <p class="text-[9px] font-bold text-slate-400">of <span x-text="item.Required_Qty"></span> required</p>
                        </div>
                    </div>
                    <!-- Member assignment inputs -->
                    <div class="space-y-2">
                        <template x-for="(assignment, index) in assignments[`${item.ItemID}_${item.VariantID || 0}`]" :key="assignment.id">
                             <div class="flex items-center gap-2 bg-white p-2 rounded-lg border border-slate-100 shadow-sm">
                                <select x-model="assignment.member_id" class="flex-1 bg-white text-[10px] font-bold p-2 rounded-lg border border-slate-200 outline-none">
                                    <option :value="null" disabled>Select Member...</option>
                                    <template x-for="member in groupMembers" :key="member.MasterID">
                                        <option :value="member.MasterID" x-text="member.Is_Leader == 1 ? 'Me (Leader)' : member.Full_Name"></option>
                                    </template>
                                </select>
                                <input type="number" x-model.number="assignment.qty" min="1" class="w-16 text-center text-sm font-bold p-2 rounded-lg border border-slate-200 outline-none focus:ring-2 focus:ring-orange-500 transition-all" placeholder="0">
                                <button @click="removeAssignmentRow(`${item.ItemID}_${item.VariantID || 0}`, assignment.id)" class="text-red-400 hover:text-red-600 font-black w-8 h-8 rounded-lg hover:bg-red-100 transition-colors" title="Remove Assignment">&times;</button>
                            </div>
                        </template>
                    </div>
                    <button @click="addAssignmentRow(`${item.ItemID}_${item.VariantID || 0}`)" x-show="getRemaining(item) > 0" class="w-full mt-3 text-center bg-slate-100 hover:bg-slate-200 text-slate-500 py-2 rounded-lg text-[9px] font-black uppercase tracking-wider transition-colors">
                        + Add Person
                    </button>
                </div>
            </template>
        </div>

        <div class="flex gap-2">
            <button @click="confirmAssignments()" :disabled="!isComplete()"
                    class="w-full bg-emerald-500 text-white py-5 rounded-2xl font-black uppercase text-[10px] tracking-widest text-center flex items-center justify-center shadow-lg shadow-emerald-200 disabled:bg-slate-800 disabled:text-slate-500 disabled:cursor-not-allowed disabled:shadow-none transition-all">
                Confirm & Finalize Distribution
            </button>
        </div>
    </div>
        <button onclick="window.location.reload()" class="w-full bg-slate-800 text-white py-3 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-slate-700">
            Refresh Status
        </button>
    </div>

<?php elseif ($primary_view === 'WAITING'): ?>
    <div class="bg-white p-10 rounded-3xl border border-slate-200/50 shadow-lg text-center animate-reveal">
        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 animate-bounce">
            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-lg font-black text-slate-700 uppercase italic">Logistics Pending</h3>
        <p class="text-xs text-slate-400 mt-2 mb-6 max-w-[200px] mx-auto">
            Your Team Leader is currently assigning apparatus responsibilities. 
        </p>
        <button onclick="window.location.reload()" class="text-[10px] font-bold text-orange-500 uppercase hover:underline">Check for Updates</button>
    </div>

                    <?php elseif ($primary_view === 'REQUISITION'): ?>
                        <form method="POST" action="../../dbRelated/submit_requisition.php?activity_id=<?= $activity_id ?>">
                            <div class="bg-white p-8 rounded-3xl border border-slate-200/50 shadow-lg">
                                <div class="flex justify-between items-center mb-8">
                                    <h3 class="font-black text-slate-800 italic uppercase text-xs tracking-widest leading-none">Requisition Bag</h3>
                                    <button type="button" onclick="openShopModal()" class="p-2 bg-orange-50 text-orange-600 rounded-xl hover:bg-orange-600 shadow-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M12 4v16m8-8H4"/></svg></button>
                                </div>
                                <div id="cart-container" class="space-y-3 mb-10 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar border-y border-dashed border-slate-100 py-6">
                                    <?php if(empty($suggested)): ?>
                                        <p class="text-center text-[10px] text-slate-300 py-10 uppercase font-black italic empty-msg">Bag is Empty</p>
                                    <?php else: ?>
                                        <?php foreach ($suggested as $item): ?>
                                        <div class="flex items-center gap-3 p-4 bg-slate-100 border border-slate-200 rounded-2xl opacity-80">
                                            <input type="hidden" name="items[]" value="<?= $item['ItemID'] ?>">
                                            <div class="flex-1 truncate">
                                                <p class="text-[10px] font-black text-slate-800 uppercase italic truncate">
                                                    <?= htmlspecialchars($item['Item_Name']) ?>
                                                    <?php if (!empty($item['Size_Value'])): ?>
                                                        <span class="text-xs font-normal normal-case text-slate-500">(<?= htmlspecialchars($item['Size_Value']) . htmlspecialchars($item['Unit']) ?>)</span>
                                                    <?php endif; ?>
                                                </p>
                                                <span class="text-[7px] bg-slate-800 text-white px-2 py-0.5 rounded-md uppercase font-black">Required</span>
                                            </div>
                                            <input type="number" name="qtys[]" value="<?= $item['Required_Qty'] ?>" readonly class="w-12 bg-transparent text-center font-black text-[10px] outline-none cursor-not-allowed">
                                            <div class="w-8 h-8 flex items-center justify-center"><svg class="w-3 h-3 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg></div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="w-full bg-[#0f172a] text-white py-6 rounded-[2.5rem] font-black uppercase text-[10px] tracking-[0.3em] hover:bg-orange-600 shadow-2xl italic">Send Request</button>
                            </div>
                        </form>
                    <?php elseif ($primary_view === 'CREATE_GROUP'): ?>
                        <div class="bg-white p-8 rounded-3xl border border-slate-200/50 shadow-lg"
                             x-data="{ 
                                selected: [], 
                                limit: <?= ($groupLimit ?? 4) - 1 ?>, 
                                createTeam() {
                                    if (this.selected.length > this.limit) { alert('Too many members selected!'); return; }
                                    const formData = new FormData(document.getElementById('createGroupForm'));
                                    fetch('group_actions.php', { method: 'POST', body: formData })
                                        .then(r => r.json())
                                        .then(d => {
                                            if(d.status === 'success') window.location.reload();
                                            else alert(d.message);
                                        });
                                }
                             }">
                            <h3 class="font-black text-slate-800 italic uppercase text-xs tracking-widest mb-6">Create a Team</h3>
                            <form id="createGroupForm" @submit.prevent="createTeam">
                                <input type="hidden" name="action" value="create_group">
                                <input type="hidden" name="activity_id" value="<?= $activity_id ?>">
                                <div class="mb-4">
                                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest">Team Name</label>
                                    <input type="text" name="group_name" required placeholder="e.g. The Avengers" 
                                           class="w-full bg-slate-50 border border-slate-200 p-3 rounded-xl font-bold text-slate-800 text-sm focus:outline-none focus:border-orange-500 transition-all">
                                </div>
                                <div class="mb-6">
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest">Select Members</label>
                                        <span class="text-[10px] font-black bg-orange-100 text-orange-600 px-2 py-1 rounded" x-text="selected.length + '/' + limit"></span>
                                    </div>
                                    <div class="h-48 overflow-y-auto bg-slate-50 rounded-xl p-2 border border-slate-200 custom-scrollbar">
                                        <?php if (empty($classmates)): ?>
                                            <div class="h-full flex flex-col items-center justify-center text-slate-400"><p class="text-xs font-bold">No available classmates.</p></div>
                                        <?php else: ?>
                                            <div class="grid grid-cols-1 gap-2">
                                                <?php foreach ($classmates as $cm): ?>
                                                    <label class="flex items-center gap-3 p-3 bg-white border border-slate-100 rounded-lg cursor-pointer hover:border-orange-400 transition-all select-none group has-[:checked]:bg-orange-50 has-[:checked]:border-orange-300">
                                                        <input type="checkbox" name="members[]" value="<?= $cm['MasterID'] ?>" x-model="selected" :disabled="selected.length >= limit && !selected.includes('<?= $cm['MasterID'] ?>')" class="w-4 h-4 text-orange-600 rounded focus:ring-0 cursor-pointer">
                                                        <span class="text-xs font-bold text-slate-600 group-hover:text-orange-900"><?= htmlspecialchars($cm['Full_Name']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-[9px] text-slate-400 mt-2 font-bold uppercase">* You are the Leader.</p>
                                </div>
                                <button type="submit" class="w-full bg-orange-600 text-white py-4 rounded-xl font-black uppercase text-xs tracking-[0.2em] shadow-lg shadow-orange-500/20 hover:bg-orange-700 hover:-translate-y-1 transition-all">Create Team</button>
                            </form>
                        </div>
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
                            <div class="receipt-paper p-6 text-slate-800 thermal-font w-full mx-auto relative group border border-slate-100 rounded-2xl">
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
                        <button onclick="saveSlipImage()" class="mt-4 w-full bg-slate-800 text-white py-3 rounded-xl font-black uppercase text-[9px] tracking-widest hover:bg-orange-600 transition-all italic">Download Copy</button>
                    <?php endif; ?>

                </aside>
            </main>
        </div>
    </div>

    <div id="shopModal" class="hidden fixed inset-0 z-[100] bg-[#0f172a]/95 backdrop-blur-xl p-6 md:p-12 overflow-y-auto">
        <div class="max-w-7xl mx-auto animate-reveal active">
            <header class="flex justify-between items-center mb-8"><div><h3 class="text-4xl font-black text-white italic uppercase tracking-tighter">Inventory <span class="text-orange-500">Shop.</span></h3><p class="text-white/40 text-[10px] font-black uppercase tracking-widest mt-1">Select items for activity requirements</p></div><button onclick="closeShopModal()" class="w-16 h-16 flex items-center justify-center rounded-full bg-white/5 text-white hover:bg-red-500 text-2xl transition-all">&times;</button></header>
            <div class="flex flex-col md:flex-row gap-6 mb-8"><input type="text" id="modalSearch" onkeyup="runModalFilters()" placeholder="Search apparatus..." class="flex-[3] bg-white/5 border border-white/10 p-5 rounded-2xl outline-none text-white font-bold text-base"><select id="modalCategory" onchange="runModalFilters()" class="flex-1 bg-white/5 border border-white/10 p-5 rounded-2xl text-white font-bold outline-none cursor-pointer"><option value="all">All Categories</option><?php foreach($categories as $cat): ?> <option value="<?= htmlspecialchars($cat['Category_Name']) ?>"><?= htmlspecialchars($cat['Category_Name']) ?></option> <?php endforeach; ?></select></div>
            <div id="modalGrid" class="shop-modal-grid">
                <?php foreach($all_inventory as $item): ?>
                    <div class="p-8 border border-white/10 hover:border-orange-500 cursor-pointer transition-all duration-500 group item-card bg-white/5 rounded-3xl" data-item-name="<?= strtolower(htmlspecialchars($item['Item_Name'])) ?>" data-item-category="<?= htmlspecialchars($item['Category_Name'] ?? 'General') ?>" onclick="selectItemFromShop('<?= $item['ItemID'] ?>', '<?= addslashes(htmlspecialchars($item['Item_Name'])) ?>')"><div class="flex justify-between items-start mb-6"><span class="text-[10px] font-black text-white/20 uppercase tracking-widest">#<?= $item['ItemID'] ?></span><span class="text-[10px] font-black text-orange-400 uppercase text-right leading-tight"><?= $item['Available_Qty'] ?> <br> STOCK</span></div><div class="h-40 flex items-center justify-center mb-8"><img src="../../assets/img/items/<?= $item['ItemID'] ?>.png" class="max-h-full object-contain group-hover:scale-110 transition-transform duration-500" onerror="this.src='../../assets/img/placeholder.png'"></div><div class="mt-auto"><h4 class="text-lg font-black text-white uppercase italic leading-tight mb-2 group-hover:text-orange-400 transition-colors"><?= htmlspecialchars($item['Item_Name']) ?></h4></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    

    <script>

        function logisticsManager(logisticsData, groupMembers) {
            return {
                logisticsData: logisticsData,
                groupMembers: groupMembers,
                assignments: {},

                init() {
                    this.logisticsData.forEach(item => {
                        const key = `${item.ItemID}_${item.VariantID || 0}`;
                        this.assignments[key] = [];
                        if (item.Assignments && item.Assignments.length > 0) {
                            item.Assignments.forEach(ass => {
                                this.assignments[key].push({
                                    id: ass.LogisticsID, // Use existing ID
                                    member_id: ass.AssignedToMasterID,
                                    qty: parseInt(ass.Quantity)
                                });
                            });
                        } else {
                            // If no assignments, add one empty row to start
                            this.addAssignmentRow(key);
                        }
                    });
                },

                addAssignmentRow(key) {
                    this.assignments[key].push({
                        id: 'new_' + Date.now() + Math.random(), // Temporary unique ID
                        member_id: null,
                        qty: 1
                    });
                },

                removeAssignmentRow(key, assignmentId) {
                    this.assignments[key] = this.assignments[key].filter(a => a.id !== assignmentId);
                },

                getRemaining(item) {
                    const key = `${item.ItemID}_${item.VariantID || 0}`;
                    const assignedQty = (this.assignments[key] || []).reduce((sum, ass) => sum + (parseInt(ass.qty) || 0), 0);
                    return item.Required_Qty - assignedQty;
                },

                isComplete() {
                    return this.logisticsData.every(item => this.getRemaining(item) === 0);
                },

                confirmAssignments() {
                    if (!this.isComplete()) { alert('Distribution is not complete. Please ensure all required items are fully assigned.'); return; }

                    const flatAssignments = [];
                    this.logisticsData.forEach(item => {
                        const key = `${item.ItemID}_${item.VariantID || 0}`;
                        (this.assignments[key] || []).forEach(ass => {
                            if (ass.member_id && ass.qty > 0) {
                                flatAssignments.push({ item_id: item.ItemID, variant_id: item.VariantID, target_id: ass.member_id, qty: ass.qty });
                            }
                        });
                    });

                    const fd = new FormData();
                    fd.append('action', 'bulk_assign_logistics');
                    fd.append('activity_id', '<?= $activity_id ?>');
                    fd.append('group_id', '<?= $myGroup['GroupID'] ?? '' ?>');
                    fd.append('assignments', JSON.stringify(flatAssignments));

                    fetch('group_actions.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.status === 'success') {
                                window.location.href = '?activity_id=<?= $activity_id ?>&mode=cart';
                            } else {
                                alert(d.message || 'An error occurred while saving assignments.');
                            }
                        });
                }
            }
        }

        // Modal Toggles
        function openShopModal() { document.getElementById('shopModal').classList.remove('hidden'); }
        function closeShopModal() { document.getElementById('shopModal').classList.add('hidden'); }
        
        function togglePDF() { const viewer = document.getElementById('pdf-viewer'); const btn = document.getElementById('pdfToggleBtn'); if(viewer.classList.contains('hidden')) { viewer.classList.remove('hidden'); btn.textContent = 'Hide'; } else { viewer.classList.add('hidden'); btn.textContent = 'Preview'; } }
        function runModalFilters() { const query = document.getElementById('modalSearch').value.toLowerCase(); const cat = document.getElementById('modalCategory').value; document.querySelectorAll('.item-card').forEach(card => { const name = card.getAttribute('data-item-name'); const category = card.getAttribute('data-item-category'); card.style.display = (name.includes(query) && (cat === 'all' || category === cat)) ? 'flex' : 'none'; }); }
        function selectItemFromShop(id, name) { const container = document.getElementById('cart-container'); if (container.querySelector('.empty-msg')) container.querySelector('.empty-msg').remove(); const div = document.createElement('div'); div.className = "flex items-center gap-3 p-4 bg-white border border-slate-100 rounded-2xl shadow-sm active"; div.innerHTML = `<input type="hidden" name="items[]" value="${id}"><div class="flex-1 truncate"><p class="text-[10px] font-black text-slate-800 uppercase italic truncate">${name}</p><span class="text-[7px] text-blue-500 font-black uppercase">Extra</span></div><input type="number" name="qtys[]" value="1" min="1" class="w-12 bg-slate-50 border-none text-center font-black p-2 rounded-xl text-[10px] outline-none"><button type="button" onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg></button>`; container.appendChild(div); closeShopModal(); }
        function saveSlipImage() { html2canvas(document.getElementById('receipt-capture'), { scale: 3, backgroundColor: null }).then(canvas => { const link = document.createElement('a'); link.download = 'SNHS-Borrow-Slip.png'; link.href = canvas.toDataURL(); link.click(); }); }
        function previewFileName(input) { if (input.files && input.files[0]) { document.getElementById('upload-prompt').classList.add('hidden'); document.getElementById('file-selected').classList.remove('hidden'); document.getElementById('chosen-filename').textContent = input.files[0].name; } }
        window.onload = function() { const qrData = "<?= $slip['QR_Code_Data'] ?? '' ?>"; const status = "<?= $slip['Status'] ?? '' ?>"; if(qrData && document.getElementById("qrcode") && (status === 'Pending' || status === 'Approved' || status === 'Issued')) { document.getElementById("qrcode").innerHTML = ""; new QRCode(document.getElementById("qrcode"), { text: qrData, width: 100, height: 100, colorDark : "#000000", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H }); } };
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>