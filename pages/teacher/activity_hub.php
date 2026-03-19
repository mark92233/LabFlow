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
$activityRequirements = $db->getActivityRequirements($activity_id);
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

// This is a common trick to recursively lowercase all keys.
// It ensures that nested keys like 'Name' and 'Role' inside 'members' become 'name' and 'role',
// which is what the Alpine.js template expects.
$listItems = json_decode(strtolower(json_encode($listItems)), true) ?? [];
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
                <!-- Left Column: Activity Details -->
                <div class="lg:col-span-2">
                    
                    <!-- Activity Header -->
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
                    
                    <!-- Activity Description -->
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

                <!-- Right Column: Roster/Group List -->
                <aside class="w-full lg:col-span-1">
                    <div class="sticky top-24 space-y-8">
                        <div x-data="activityHub(<?= htmlspecialchars(json_encode($listItems), ENT_QUOTES, 'UTF-8') ?>)" class="bg-white p-6 rounded-3xl border border-slate-200/50 shadow-lg">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest">
                                    <?= $isGroupActivity ? 'Teams' : 'Enrollment' ?>
                                </h3>
                                <span class="bg-slate-100 text-slate-500 text-[9px] font-black px-2 py-1 rounded-md">
                                    <?= count($listItems) ?> <?= $isGroupActivity ? 'Groups' : 'Students' ?>
                                </span>
                            </div>
                            
                            <?php if ($isGroupActivity): ?>
                            <!-- Search Box -->
                            <div class="mb-6">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <input type="search" 
                                           x-model.debounce.300ms="searchQuery" 
                                           @keydown.enter.prevent="findStudent()"
                                           placeholder="Find student in a group..." 
                                           class="w-full pl-12 pr-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none focus:border-orange-500/50 focus:ring-4 focus:ring-orange-500/10 hover:border-slate-200 transition-all duration-300 font-medium text-sm text-slate-800 placeholder:text-slate-400 shadow-sm">
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="space-y-3 max-h-[65vh] overflow-y-auto custom-scrollbar pr-2">
                                <template x-for="item in paginatedItems" :key="item.groupid || item.masterid" x-cloak>
                                    <div class="bg-white border border-slate-100 rounded-2xl transition-all duration-300 p-4" :class="{ 'ring-2 ring-orange-500 shadow-lg': highlightedGroupId === item.groupid }" :data-groupid="item.groupid">
                                        <?php if ($isGroupActivity): ?>
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
                                            
                                            <!-- Group Members (always visible) -->
                                            <div class="p-4 border-t border-slate-100 bg-slate-50/50 rounded-b-2xl -mx-4 -mb-4 mt-4" x-cloak>
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
                                        <?php else: ?>
                                            <!-- Individual Student Card -->
                                            <div class="w-full text-left rounded-2xl border transition-all flex items-center justify-between"
                                                :class="item.status && item.status.toLowerCase() === 'graded' ? 'bg-orange-50 border-orange-100' : (item.status && item.status.toLowerCase() === 'submitted' ? 'bg-emerald-50 border-emerald-100' : 'bg-white border-slate-100 opacity-60')">
                                                
                                                <div class="truncate mr-4 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <p class="text-[10px] font-black text-slate-800 uppercase italic truncate" x-text="item.full_name"></p>
                                                        <template x-if="item.islate === true">
                                                            <span class="text-[8px] font-black text-red-500 bg-red-100 px-1.5 py-0.5 rounded">LATE</span>
                                                        </template>
                                                    </div>
                                                    <p class="text-[8px] font-bold uppercase" :class="(item.status && (item.status.toLowerCase() === 'submitted' || item.status.toLowerCase() === 'graded')) ? 'text-orange-500' : 'text-slate-300'" x-text="item.status || 'Unsubmitted'"></p>
                                                </div>

                                                <div class="flex items-center gap-2 flex-shrink-0">
                                                    <template x-if="item.status && item.status.toLowerCase() === 'graded'"><span class="text-[10px] font-black bg-orange-600 text-white px-3 py-1 rounded-lg" x-text="item.grade"></span></template>
                                                    <template x-if="item.status && item.status.toLowerCase() === 'submitted'"><div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div></template>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </template>
                            </div>

                            <!-- Pagination Controls -->
                            <div x-show="totalPages > 1" class="mt-8 flex justify-between items-center bg-white p-4 rounded-2xl border border-gray-200 shadow-sm" x-cloak>
                                <div class="flex items-center gap-2">
                                    <?php if (!$isGroupActivity): ?>
                                        <label for="itemsPerPage" class="text-xs font-bold text-gray-500">Show:</label>
                                        <select x-model.number="itemsPerPage" id="itemsPerPage" class="bg-gray-50 border-gray-200 rounded-md text-xs font-bold p-1 focus:ring-orange-500 focus:border-orange-500">
                                            <option value="10">10</option>
                                            <option value="15">15</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Previous
                                    </button>
                                    <div class="flex items-center gap-1">
                                        <template x-for="(page, index) in pages" :key="index">
                                            <div>
                                                <span x-show="page === '...'" class="px-1 py-2 text-xs font-bold text-gray-400">…</span>
                                                <button x-show="page !== '...'" @click="currentPage = page" x-text="page" class="px-2 py-2 rounded-lg text-xs font-bold transition-colors" :class="{
                                                            'bg-orange-500 text-white shadow-md': currentPage === page,
                                                            'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50': currentPage !== page
                                                        }"></button>
                                            </div>
                                        </template>
                                    </div>
                                    <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="px-3 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Required Apparatus & Materials -->
                        <div class="bg-white p-6 rounded-3xl border border-slate-200/50 shadow-lg">
                            <h4 class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-4 italic">Required Apparatus & Materials</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php if (empty($activityRequirements)): ?>
                                    <span class="text-xs text-slate-400 italic">No items specified for this activity.</span>
                                <?php else: ?>
                                    <?php foreach ($activityRequirements as $req): ?>
                                        <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-xs font-bold">
                                            <?= htmlspecialchars($req['Required_Qty']) ?>x <?= htmlspecialchars($req['Item_Name']) ?>
                                            <?php if (!empty($req['Size_Value'])): ?>
                                                (<?= htmlspecialchars($req['Size_Value']) ?><?= htmlspecialchars($req['Unit'] ?? '') ?>)
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
                paginatedItems: [],
                currentPage: 1,
                itemsPerPage: 10,
                searchQuery: '',
                highlightedGroupId: null,

                init() {
                    if (<?php echo json_encode($isGroupActivity); ?>) {
                        const hasLargeGroup = this.allItems.some(item => item.members && item.members.length > 3);
                        this.itemsPerPage = hasLargeGroup ? 1 : 2;
                    }

                    this.updatePaginatedItems();
                    this.$watch('currentPage', () => this.updatePaginatedItems());
                    this.$watch('itemsPerPage', () => {
                        // If itemsPerPage changes, reset to page 1
                        if (this.currentPage !== 1) {
                            this.currentPage = 1;
                        } else {
                            // If already on page 1, manually trigger update
                            this.updatePaginatedItems();
                        }
                    });
                    this.$watch('searchQuery', (value) => {
                        if (value.trim() === '') {
                            this.highlightedGroupId = null;
                        }
                    });
                },

                updatePaginatedItems() {
                    const start = (this.currentPage - 1) * this.itemsPerPage;
                    const end = start + this.itemsPerPage;
                    this.paginatedItems = this.allItems.slice(start, end);
                },

                findStudent() {
                    this.highlightedGroupId = null;
                    const query = this.searchQuery.trim().toLowerCase();
                    if (query === '') {
                        return;
                    }

                    let found = false;
                    for (let i = 0; i < this.allItems.length; i++) {
                        const group = this.allItems[i];
                        if (group.members && Array.isArray(group.members)) {
                            const memberFound = group.members.some(member => 
                                member.name && member.name.toLowerCase().includes(query)
                            );

                            if (memberFound) {
                                const page = Math.floor(i / this.itemsPerPage) + 1;
                                this.currentPage = page;
                                
                                setTimeout(() => {
                                    this.highlightedGroupId = group.groupid;
                                    const el = this.$root.querySelector(`[data-groupid='${group.groupid}']`);
                                    if (el) {
                                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    }
                                }, 150);

                                found = true;
                                break; // Stop at the first match
                            }
                        }
                    }

                    if (!found) {
                        showToast(`Student "${this.searchQuery}" not found in any group.`, 'error');
                    }
                },

                get totalPages() {
                    return Math.ceil(this.allItems.length / this.itemsPerPage);
                },
                get pages() {
                    const pages = [];
                    const pagesToShow = 7; // Max number of page buttons to show
                    const half = Math.floor(pagesToShow / 2);
    
                    if (this.totalPages <= pagesToShow) {
                        for (let i = 1; i <= this.totalPages; i++) {
                            pages.push(i);
                        }
                    } else if (this.currentPage <= half + 1) {
                        for (let i = 1; i < pagesToShow; i++) { pages.push(i); }
                        pages.push('...');
                        pages.push(this.totalPages);
                    } else if (this.currentPage >= this.totalPages - half) {
                        pages.push(1);
                        pages.push('...');
                        for (let i = this.totalPages - (pagesToShow - 2); i <= this.totalPages; i++) { pages.push(i); }
                    } else {
                        pages.push(1); pages.push('...'); for (let i = this.currentPage - (half - 2); i <= this.currentPage + (half - 2); i++) { pages.push(i); } pages.push('...'); pages.push(this.totalPages);
                    }
                    return pages;
                }
            }
        }
    </script>
    <script>
        // Fallback showToast function in case it's not in the layout footer
        if (typeof showToast === 'undefined') {
            function showToast(message, type = 'success') {
                const toastContainer = document.getElementById('toast-container');
                if (!toastContainer) return;
                const iconContainer = toastContainer.querySelector('#toast-icon-container');
                const messageEl = toastContainer.querySelector('#toast-message');
                
                toastContainer.className = 'fixed bottom-10 right-10 z-[200] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal';
                iconContainer.innerHTML = '';

                toastContainer.classList.add(type === 'success' ? 'bg-emerald-600' : 'bg-red-600');
                iconContainer.classList.add(type === 'success' ? 'bg-emerald-100' : 'bg-red-100');
                iconContainer.innerHTML = type === 'success' 
                    ? `<svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>`
                    : `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`;
                messageEl.textContent = message;

                toastContainer.classList.remove('hidden');
                setTimeout(() => { toastContainer.classList.add('hidden'); }, 4000);
            }
        }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
    
</body>
</html>