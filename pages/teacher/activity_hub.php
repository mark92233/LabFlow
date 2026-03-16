<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$activity_id = $_GET['activity_id'] ?? null;
$url_class_id = $_GET['class_id'] ?? null;

// 2. Fetch Basic Activity Data
$activity = $db->getActivityDetails($activity_id, $url_class_id);
if (!$activity) { die("Activity not found."); }

// 3. Context & Type Logic
$current_class_id = $url_class_id ? $url_class_id : $activity['ClassID'];

// ROBUST CHECK: Check 'Type' (alias) OR 'type' (raw column), and handle case sensitivity
$typeVal = $activity['Type'] ?? $activity['type'] ?? 'Individual';
$isGroupActivity = (strcasecmp($typeVal, 'Group') === 0); 

// 4. Fetch the correct list (The Switch)
if ($isGroupActivity) {
    // Fetches Groups (Alpha Team, Beta Team)
    $listItems = $db->getGroupsWithSubmissions($activity_id, $current_class_id); 
} else {
    // Fetches Individual Students (Jomar Jun, Kim Solis)
    $listItems = $db->getEnrollmentWithSubmissions($activity_id, $current_class_id);
}

// Helper for date comparison
$deadline = $activity['Deadline'];
$deadlineTime = strtotime($deadline);

// Post-process to add isLate flag
foreach ($listItems as &$item) {
    $isLate = false;
    if (!empty($item['SubmissionDate'])) {
        $submitTime = strtotime($item['SubmissionDate']);
        if ($submitTime > $deadlineTime) {
            $isLate = true;
        }
    }
    $item['isLate'] = $isLate;
}
unset($item); // Unset reference
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review: <?= $activity['Title'] ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 flex flex-col lg:grid lg:grid-cols-3 gap-8 animate-reveal">
                <div class="lg:col-span-2">
                    <header class="mb-8">
                        <div class="flex justify-between items-start">
                            <h2 class="text-4xl font-black text-slate-900 uppercase italic tracking-tighter">
                                <span class="block text-xs font-medium text-orange-500 not-italic tracking-normal mb-1">
                                    <?= $isGroupActivity ? 'Group Activity' : 'Individual Activity' ?>
                                </span>
                                <?= htmlspecialchars($activity['Title']) ?>
                            </h2>
                            <div class="text-right">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Deadline</p>
                                <p class="text-xs font-bold text-slate-700"><?= date("M d, Y h:i A", strtotime($deadline)) ?></p>
                            </div>
                        </div>
                    </header>
                    
                    <div class="bg-white p-10 rounded-3xl border border-slate-200/50 shadow-lg">
                        <h4 class="text-[10px] font-black text-orange-500 uppercase tracking-widest mb-4 italic">Lab Description & Instructions</h4>
                        <p class="text-slate-600 text-sm leading-relaxed mb-8"><?= nl2br(htmlspecialchars($activity['Description'])) ?></p>
                        
                        <?php if($activity['Manual_URL']): ?>
                            <div class="flex items-center gap-3">
                                <button onclick="toggleManualPreview()" class="inline-flex items-center bg-slate-200 text-slate-700 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-300 transition-all">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Preview
                                </button>
                                <a href="../../<?= $activity['Manual_URL'] ?>" target="_blank" class="inline-flex items-center bg-slate-100 text-slate-700 px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-orange-600 hover:text-white transition-all shadow-sm">
                                    Download Reference
                                </a>
                            </div>
                            <div id="manual_preview_container" class="hidden mt-6 border-t border-slate-200 pt-6 animate-reveal">
                                <iframe src="../../<?= $activity['Manual_URL'] ?>" class="w-full h-[600px] rounded-2xl border border-slate-200 bg-white"></iframe>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="w-full lg:col-span-1">
                    <div x-data="activityHub(<?= htmlspecialchars(json_encode(array_map('array_change_key_case', $listItems)), ENT_QUOTES, 'UTF-8') ?>)" class="bg-white p-6 rounded-3xl border border-slate-200/50 shadow-lg sticky top-24">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest">
                                <?= $isGroupActivity ? 'Teams' : 'Enrollment' ?>
                            </h3>
                            <span class="bg-slate-100 text-slate-500 text-[9px] font-black px-2 py-1 rounded-md">
                                <?= count($listItems) ?> <?= $isGroupActivity ? 'Groups' : 'Students' ?>
                            </span>
                        </div>

                        <div class="space-y-3 max-h-[65vh] overflow-y-auto custom-scrollbar pr-2">
                            <template x-for="item in paginatedItems" :key="item.groupid || item.masterid" x-cloak>
                                <div>
                                <?php if ($isGroupActivity): ?>
                                    <div class="bg-white border border-slate-100 rounded-2xl transition-all duration-300" :class="{'ring-2 ring-orange-400 shadow-md': expandedGroupId === item.groupid }">
                                        <button @click="expandedGroupId = (expandedGroupId === item.groupid ? null : item.groupid)" class="w-full text-left p-4 focus:outline-none">
                                            <div class="flex justify-between items-start mb-3">
                                                <div>
                                                    <h4 class="text-sm font-black text-slate-800 uppercase italic" x-text="item.groupname"></h4>
                                                    <p class="text-[9px] font-bold uppercase text-slate-400 mt-1"><span x-text="item.members ? item.members.length : 0"></span> Members</p>
                                                </div>
                                                <template x-if="item.status && item.status.toLowerCase() === 'graded'">
                                                    <span class="text-[10px] font-black bg-orange-600 text-white px-2 py-1 rounded" x-text="'Avg: ' + item.grade"></span>
                                                </template>
                                                <template x-if="item.status && item.status.toLowerCase() === 'submitted'">
                                                    <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                                                </template>
                                            </div>

                                            <div class="flex gap-2 mt-2">
                                                <span class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 py-2 rounded-lg text-[9px] font-black uppercase tracking-wider transition-colors text-center">
                                                    Roster
                                                </span>
                                            </div>
                                        </button>
                                        <!-- Roster Accordion -->
                                        <div x-show="expandedGroupId === item.groupid" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 -translate-y-2" class="p-4 border-t border-slate-100 bg-slate-50/50 rounded-b-2xl" x-cloak>
                                            <h5 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Team Roster</h5>
                                            <div class="space-y-2">
                                                <template x-for="member in item.members" :key="member.name">
                                                    <div class="flex items-center gap-3 p-2 rounded-lg" :class="member.role == 1 ? 'bg-amber-100/50' : ''">
                                                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-sm" :class="member.role == 1 ? 'bg-amber-400 text-white' : 'bg-slate-200 text-slate-500'">
                                                            <span x-text="member.role == 1 ? '👑' : '👤'"></span>
                                                        </div>
                                                        <p class="text-xs font-bold text-slate-700" x-text="member.name"></p>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                <?php else: ?>
                                    <div class="w-full text-left p-4 rounded-2xl border transition-all flex items-center justify-between"
                                            :class="item.status && item.status.toLowerCase() === 'graded' ? 'bg-orange-50 border-orange-100' : (item.status && item.status.toLowerCase() === 'submitted' ? 'bg-emerald-50 border-emerald-100' : 'bg-white border-slate-100 opacity-60')">
                                            
                                        <div class="truncate mr-4 flex-1">
                                            <div class="flex items-center gap-2">
                                                <p class="text-[10px] font-black text-slate-800 uppercase italic truncate" x-text="item.full_name"></p>
                                                <template x-if="item.islate === true">
                                                    <span class="text-[8px] font-black text-red-500 bg-red-100 px-1.5 py-0.5 rounded">LATE</span>
                                                </template>
                                            </div>
                                            <p class="text-[8px] font-bold uppercase" :class="(item.status && (item.status.toLowerCase() === 'submitted' || item.status.toLowerCase() === 'graded')) ? 'text-orange-500' : 'text-slate-300'" x-text="item.status || 'Unsubmitted'">
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <template x-if="item.status && item.status.toLowerCase() === 'graded'">
                                                <span class="text-[10px] font-black bg-orange-600 text-white px-3 py-1 rounded-lg" x-text="item.grade">
                                                </span>
                                            </template>
                                            <template x-if="item.status && item.status.toLowerCase() === 'submitted'">
                                                <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                                            </template>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </template>
                        </div>

                        <!-- Pagination Controls -->
                        <div x-show="totalPages > 1" class="mt-6 pt-4 border-t border-slate-100 flex justify-between items-center" x-cloak>
                            <p class="text-xs font-bold text-gray-500">
                                Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span>
                            </p>
                            <div class="flex gap-2">
                                <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">&larr; Prev</button>
                                <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Next &rarr;</button>
                            </div>
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>

<script>
        // Existing Toggles
        function toggleManualPreview() { document.getElementById('manual_preview_container').classList.toggle('hidden'); }
        function toggleStudentPreview() { document.getElementById('student_preview_container').classList.toggle('hidden'); }

        function activityHub(items) {
            return {
                allItems: items,
                currentPage: 1,
                itemsPerPage: 5,
                expandedGroupId: null,
                get totalPages() {
                    return Math.ceil(this.allItems.length / this.itemsPerPage);
                },
                get paginatedItems() {
                    const start = (this.currentPage - 1) * this.itemsPerPage;
                    const end = start + this.itemsPerPage;
                    return this.allItems.slice(start, end);
                }
            }
        }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>