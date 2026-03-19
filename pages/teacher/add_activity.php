<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control: Only Teachers and Admins can create activities.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Teacher', 'Admin'])) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$inventory = $db->getInventoryShop() ?? [];
$categories = $db->getCategories() ?? [];
$classes = $db->getTeacherClasses($_SESSION['user_id']);

// Logic for editing an existing activity
$editData = null;
if (isset($_GET['edit_id'])) {
    $activityID = $_GET['edit_id'];
    $activityDetails = $db->getActivityDetails($activityID);
    $activityReqs = $db->getActivityRequirements($activityID);
    $activityAssignments = $db->getAssignedClassesForActivity($activityID);
    
    if ($activityDetails) {
        $editData = [
            'details' => $activityDetails,
            'requirements' => $activityReqs,
            'assignments' => array_column($activityAssignments, 'ClassID')
        ];
    }
}

$page_title = $editData ? "Edit Activity" : "New Activity";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .shop-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen font-sans text-slate-600">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            <main class="p-8" x-data="activityWizard(
                <?= htmlspecialchars(json_encode($inventory), ENT_QUOTES) ?>, 
                <?= htmlspecialchars(json_encode($categories), ENT_QUOTES) ?>,
                <?= htmlspecialchars(json_encode($editData), ENT_QUOTES) ?>,
                <?= htmlspecialchars(json_encode($classes), ENT_QUOTES) ?>
            )">
                <header class="mb-10">
                    <h2 class="text-5xl font-extrabold text-slate-800 tracking-tighter mb-2">
                        <span x-text="editMode ? 'Edit' : 'Create New'"></span> Activity<span class="text-orange-500">.</span>
                    </h2>
                    <p class="text-slate-400 font-medium" x-text="editMode ? 'Update the details of the existing activity.' : 'Build and configure a new lab activity for your classes.'"></p>
                </header>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    <!-- Left Column: Form Wizard -->
                    <section class="lg:col-span-2 bg-white p-8 rounded-3xl border border-slate-200/50 shadow-lg">
                        <form id="wizardForm" @submit.prevent="finalSubmit" class="space-y-8">
                            <input type="hidden" name="activity_id" x-model="activityId" x-if="editMode">
                            <!-- Breadcrumb Stepper -->
                            <nav aria-label="Progress">
                                <ol role="list" class="space-y-4 md:flex md:space-x-8 md:space-y-0">
                                    <li class="md:flex-1">
                                        <a href="#" @click.prevent="step = 1" class="group flex flex-col border-l-4 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4" :class="step >= 1 ? 'border-orange-500' : 'border-gray-200 hover:border-gray-300'">
                                            <span class="text-sm font-medium" :class="step >= 1 ? 'text-orange-600' : 'text-gray-500 group-hover:text-gray-700'">Step 1</span>
                                            <span class="text-sm font-medium">Resources</span>
                                        </a>
                                    </li>
                                    <li class="md:flex-1">
                                        <a href="#" @click.prevent="step >= 2 ? step = 2 : false" class="group flex flex-col border-l-4 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4" :class="step >= 2 ? 'border-orange-500' : 'border-gray-200 hover:border-gray-300'">
                                            <span class="text-sm font-medium" :class="step >= 2 ? 'text-orange-600' : 'text-gray-500 group-hover:text-gray-700'">Step 2</span>
                                            <span class="text-sm font-medium">Logistics</span>
                                        </a>
                                    </li>
                                    <li class="md:flex-1">
                                        <a href="#" @click.prevent="step >= 3 ? step = 3 : false" class="group flex flex-col border-l-4 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4" :class="step >= 3 ? 'border-orange-500' : 'border-gray-200 hover:border-gray-300'">
                                            <span class="text-sm font-medium" :class="step >= 3 ? 'text-orange-600' : 'text-gray-500 group-hover:text-gray-700'">Step 3</span>
                                            <span class="text-sm font-medium">Settings</span>
                                        </a>
                                    </li>
                                    <li class="md:flex-1" x-show="mode === 'Group' && grouping === 'Manual'" x-cloak>
                                        <a href="#" @click.prevent="step >= 4 ? step = 4 : false" class="group flex flex-col border-l-4 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4" :class="step >= 4 ? 'border-orange-500' : 'border-gray-200 hover:border-gray-300'">
                                            <span class="text-sm font-medium" :class="step >= 4 ? 'text-orange-600' : 'text-gray-500 group-hover:text-gray-700'">Step 4</span>
                                            <span class="text-sm font-medium">Grouping</span>
                                        </a>
                                    </li>
                                </ol>
                            </nav>

                            <div class="relative min-h-[550px]">
                                <div x-show="step === 1" x-transition.opacity class="space-y-6 animate-reveal" x-cloak>
                                    <div class="pb-4 border-b border-gray-200/80"><h3 class="text-base font-semibold leading-6 text-gray-900">1. Resources</h3><p class="mt-1 text-sm text-gray-500">Provide the basic details and reference materials for the activity.</p></div>
                                    <input type="text" name="title" required x-model="title" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" placeholder="Activity Title">
                                    <textarea name="description" rows="4" x-model="description" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" placeholder="Instructions..."></textarea>
                                    <div>
                                        <label class="text-xs font-bold text-gray-500 mb-2 block">Activity Deadline</label>
                                        <input type="datetime-local" name="deadline" required x-model="deadline" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500">
                                    </div>
                                    
                                    <div class="bg-blue-50 p-6 rounded-2xl border-2 border-dashed border-blue-200 relative text-center">
                                        <label class="cursor-pointer block">
                                            <span class="text-3xl block mb-2">📄</span>
                                            <span class="font-bold text-blue-700 text-sm">Upload PDF Manual</span>
                                            <input type="file" name="manual" accept="application/pdf" class="hidden" @change="previewPDF">
                                        </label>
                                        <p x-show="fileName" class="text-xs font-bold text-slate-500 mt-2" x-text="'Selected: ' + fileName" x-cloak></p>
                                    </div>
                                </div>

                                <div x-show="step === 2" x-transition.opacity class="space-y-6 animate-reveal" x-cloak>
                                    <div class="pb-4 border-b border-gray-200/80 flex justify-between items-center">
                                        <div>
                                            <h3 class="text-base font-semibold leading-6 text-gray-900">2. Logistics</h3>
                                            <p class="mt-1 text-sm text-gray-500">Select the required apparatus and materials for this activity.</p>
                                        </div>
                                        <button type="button" @click="getAiSuggestions()" :disabled="isAiSuggesting"
                                                class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-500/20 text-xs font-bold disabled:bg-slate-400 disabled:shadow-none">
                                            <svg x-show="isAiSuggesting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <span x-text="isAiSuggesting ? 'Analyzing...' : 'Suggest with AI'"></span>
                                        </button>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="flex items-center gap-4">
                                            <div class="relative w-full flex-1 group">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <svg class="w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                                </div>
                                                <input type="text" x-model="search" placeholder="Search items by name..." class="w-full pl-12 pr-4 py-3.5 bg-white border-2 border-gray-100 rounded-2xl outline-none focus:border-orange-500/50 focus:ring-4 focus:ring-orange-500/10 hover:border-gray-200 transition-all duration-300 font-medium text-sm text-gray-800 placeholder:text-gray-400 shadow-sm">
                                            </div>
                                            <button type="button" @click="showFilters = !showFilters" class="flex-shrink-0 p-3.5 bg-white border-2 border-gray-100 rounded-2xl text-gray-500 hover:bg-orange-50 hover:text-orange-600 hover:border-orange-200 transition-all shadow-sm" title="Toggle Filters">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                            </button>
                                        </div>
                                        <div x-show="showFilters" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0 -translate-y-2" class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm grid md:grid-cols-2 gap-6 origin-top" x-cloak>
                                            <div>
                                                <label class="block text-sm font-bold text-gray-700 mb-2">Asset Type</label>
                                                <div class="flex items-center gap-2 bg-gray-100 p-2 rounded-2xl border border-gray-200 shadow-sm w-full">
                                                    <button type="button" @click="assetType = 'all'" :class="{ 'bg-white text-orange-600 shadow-md': assetType === 'all', 'text-gray-500 hover:bg-gray-50': assetType !== 'all' }" class="flex-1 px-5 py-2 text-xs font-bold rounded-xl transition-all duration-300">All</button>
                                                    <button type="button" @click="assetType = 'consumable'" :class="{ 'bg-white text-orange-600 shadow-md': assetType === 'consumable', 'text-gray-500 hover:bg-gray-50': assetType !== 'consumable' }" class="flex-1 px-5 py-2 text-xs font-bold rounded-xl transition-all duration-300 whitespace-nowrap">Consumable</button>
                                                    <button type="button" @click="assetType = 'non-consumable'" :class="{ 'bg-white text-orange-600 shadow-md': assetType === 'non-consumable', 'text-gray-500 hover:bg-gray-50': assetType !== 'non-consumable' }" class="flex-1 px-5 py-2 text-xs font-bold rounded-xl transition-all duration-300 whitespace-nowrap">Non-Consumable</button>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-bold text-gray-700 mb-2">Category</label>
                                                <div class="relative w-full">
                                                    <select x-model="selectedCategory" class="w-full pl-4 pr-10 py-3 bg-white border border-gray-200 rounded-2xl outline-none focus:ring-2 focus:ring-orange-500 font-medium text-sm text-gray-700 shadow-sm appearance-none cursor-pointer">
                                                        <option value="all">All Categories</option>
                                                        <template x-for="cat in filteredCategories" :key="cat.CategoryID">
                                                            <option :value="cat.CategoryID" x-text="cat.Category_Name"></option>
                                                        </template>
                                                    </select>
                                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="overflow-y-auto h-[450px] pr-2 custom-scrollbar">
                                        <div class="shop-grid content-start relative">
                                            <template x-for="item in filteredInventory" :key="item.ItemID">
                                                <div @click="addItem(item)" class="bg-white p-4 rounded-xl cursor-pointer hover:bg-orange-50 border border-slate-200 hover:border-orange-300 transition-all text-center">
                                                    <img :src="`../../assets/img/items/${item.ItemID}.png`" class="h-16 mx-auto mb-2 opacity-80" onerror="this.src='../../assets/img/placeholder.png'">
                                                    <p class="text-xs font-bold text-slate-700 line-clamp-2" x-text="item.Item_Name"></p>
                                                </div>
                                            </template>
                                            <div x-show="filteredInventory.length === 0" class="col-span-full text-center py-10 text-slate-400" x-cloak>
                                                <p class="font-bold">No items found.</p>
                                                <p class="text-xs">Try adjusting your search or filter.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="step === 3" x-transition.opacity="" x-cloak class="space-y-6 animate-reveal">
                                    <div class="pb-4 border-b border-gray-200/80">
                                        <h3 class="text-base font-semibold leading-6 text-gray-900">3. Settings & Assignments</h3>
                                        <p class="mt-1 text-sm text-gray-500">Define workflow logic and assign to classes.</p>
                                    </div>
                                    <div class="grid md:grid-cols-2 gap-8">
                                        <div class="bg-slate-50 p-6 rounded-2xl">
                                            <h3 class="font-bold text-xs uppercase text-slate-400 mb-4">Assign Classes</h3>
                                            <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
                                                <?php foreach ($classes as $class): ?>
                                                    <div class="flex items-center">
                                                        <input id="class-<?= $class['ClassID'] ?>" name="target_classes[]" type="checkbox" value="<?= $class['ClassID'] ?>" x-model="selectedClasses" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @change="fetchGroupPreview()">
                                                        <label for="class-<?= $class['ClassID'] ?>" class="ml-3 block text-sm font-medium text-gray-700">
                                                            <?= htmlspecialchars($class['Class_Name']) ?> - <?= htmlspecialchars($class['Section']) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="space-y-8">
                                            <div class="bg-indigo-50 p-6 rounded-2xl">
                                                <h3 class="text-indigo-900 font-bold mb-4">Mode</h3>
                                                <div class="flex gap-2 mb-4">
                                                    <button type="button" @click="mode='Individual'" :class="mode=='Individual'?'bg-indigo-600 text-white':'bg-white text-indigo-900'" class="flex-1 py-3 rounded-xl font-bold text-xs uppercase">Individual</button>
                                                    <button type="button" @click="mode='Group'" :class="mode=='Group'?'bg-indigo-600 text-white':'bg-white text-indigo-900'" class="flex-1 py-3 rounded-xl font-bold text-xs uppercase">Group</button>
                                                </div>
                                                <input type="hidden" name="activity_type" x-model="mode">
                                                
                                                <div x-show="mode === 'Group'" x-cloak>
                                                    <label class="block text-xs font-bold uppercase text-indigo-400 mb-2">Strategy</label>
                                                    <select name="grouping_mode" x-model="grouping" class="w-full p-3 rounded-xl text-sm font-bold mb-4 outline-none">
                                                        <option value="Auto">Smart Auto-Assign</option>
                                                        <option value="Manual">Teacher Selects (Manual)</option>
                                                        <option value="Student">Student Choice</option>
                                                    </select>
                                                    
                                                    <label class="block text-xs font-bold uppercase text-indigo-400 mb-2">
                                                        <span x-text="grouping === 'Manual' ? 'Target Members Per Group' : 'Group Limit'"></span>
                                                    </label>
                                                    <input type="number" name="group_limit" x-model="group_limit" value="4" min="1" class="w-full p-3 rounded-xl text-sm font-bold border border-indigo-100" @input.debounce.500ms="fetchGroupPreview()">
                                                    
                                                    <p class="text-[10px] text-indigo-400 mt-2 italic" x-show="grouping === 'Manual'" x-cloak>
                                                        * Configure specific members in the next step.
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Group Preview -->
                                            <div x-show="mode === 'Group' && grouping === 'Auto'" x-transition class="bg-white p-6 rounded-2xl border border-slate-200 mt-8" x-cloak>
                                                <h4 class="text-xs font-bold uppercase text-slate-400 mb-2">Group Preview</h4>
                                                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 max-h-60 overflow-y-auto space-y-4 custom-scrollbar pr-2">
                                                    <div x-show="isPreviewLoading" class="text-center text-slate-400 p-4 animate-pulse" x-cloak>Loading Preview...</div>
                                                    <template x-if="!isPreviewLoading && groupPreview.length === 0">
                                                        <div class="text-center text-slate-400 p-4 text-xs font-bold">Select classes and set a group limit to see a preview.</div>
                                                    </template>
                                                    <template x-for="(group, index) in groupPreview" :key="index">
                                                        <div class="bg-white p-3 rounded-lg border border-slate-200">
                                                            <p class="font-bold text-sm text-slate-800 mb-2" x-text="group.name"></p>
                                                            <ul class="space-y-1">
                                                                <template x-for="member in group.members" :key="member.MasterID">
                                                                    <li class="flex items-center gap-2 text-xs">
                                                                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px]" :class="member.isLeader ? 'bg-yellow-200 text-yellow-800' : 'bg-slate-200 text-slate-600'"><span x-text="member.isLeader ? '👑' : '👤'"></span></span>
                                                                        <span class="font-medium text-slate-600" x-text="member.Full_Name"></span>
                                                                    </li>
                                                                </template>
                                                            </ul>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- NEW STEP 4: Manual Grouping -->
                                <div x-show="step === 4" x-transition.opacity x-cloak class="space-y-6 animate-reveal">
                                    <div class="pb-4 border-b border-gray-200/80">
                                        <h3 class="text-base font-semibold leading-6 text-gray-900">4. Manual Grouping</h3>
                                        <p class="mt-1 text-sm text-gray-500">Assign leaders, then auto-assign the rest or build groups manually.</p>
                                    </div>

                                    <div class="flex h-[60vh] border border-slate-200 rounded-2xl overflow-hidden shadow-inner bg-slate-50">
                                        <!-- Roster Panel -->
                                        <div class="w-1/3 bg-slate-100 border-r border-slate-200 p-4 flex flex-col">
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-slate-600 font-bold text-xs uppercase tracking-widest">Class Roster (<span x-text="roster.length"></span>)</h3>
                                                <button x-show="roster.length > 0" @click="randomlyAssignRemainder()" type="button" title="Randomly assign remaining students to groups with leaders" class="text-orange-500 hover:text-orange-700 p-1 rounded-full hover:bg-orange-100 transition-colors" x-cloak>
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                                                </button>
                                            </div>
                                            <div class="flex-1 overflow-y-auto space-y-2 pr-2 custom-scrollbar">
                                                <template x-for="stu in roster" :key="stu.MasterID">
                                                    <div class="bg-white p-3 rounded-lg border border-slate-200 hover:border-orange-500 cursor-pointer group transition-all" :class="selectedStudent === stu ? 'border-blue-500 bg-blue-50' : ''" @click="selectStudent(stu)">
                                                        <p class="text-xs font-bold text-slate-700 group-hover:text-slate-900" x-text="stu.Full_Name"></p>
                                                        <p class="text-[9px] text-slate-400 font-bold uppercase" x-text="stu.Class_Name"></p>
                                                    </div>
                                                </template>
                                                <div x-show="roster.length === 0" class="text-slate-400 text-xs font-bold text-center mt-10" x-cloak>All students assigned!</div>
                                            </div>
                                        </div>

                                        <!-- Groups Panel -->
                                        <div class="flex-1 bg-white p-6 overflow-y-auto custom-scrollbar">
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                                <button type="button" @click="addGroup" class="border-2 border-dashed border-slate-200 rounded-2xl p-6 flex items-center justify-center text-slate-400 font-black uppercase text-xs tracking-widest hover:text-orange-500 hover:border-orange-500 transition-all h-32">
                                                    + Add Group
                                                </button>
                                                <template x-for="(group, gIndex) in manualGroups" :key="group.id || gIndex">
                                                    <div class="bg-slate-50 rounded-2xl p-4 border shadow-sm flex flex-col h-full min-h-[200px] transition-colors" :class="group.members.length > group_limit ? 'border-red-300' : 'border-slate-200'">
                                                        <div class="flex justify-between items-center mb-4 border-b border-slate-200 pb-2">
                                                            <input type="text" x-model="group.name" class="bg-transparent text-slate-800 font-bold text-sm outline-none w-full" placeholder="Group Name">
                                                            <span class="text-[10px] font-black mr-2" :class="group.members.length > group_limit ? 'text-red-500 animate-pulse' : (group.members.length == group_limit ? 'text-green-500' : 'text-slate-500')" x-text="'(' + group.members.length + '/' + group_limit + ')'"></span>
                                                            <button type="button" @click="removeGroup(gIndex)" class="text-slate-400 hover:text-red-500 font-bold">&times;</button>
                                                        </div>
                                                        <div class="flex-1 space-y-2">
                                                            <template x-for="(member, mIndex) in group.members" :key="member.MasterID">
                                                                <div class="bg-white p-2 rounded-md flex items-center gap-2 group/mem border border-slate-200 hover:border-slate-300"><button type="button" @click="toggleLeader(gIndex, mIndex)" class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] transition-colors" :class="member.isLeader ? 'bg-yellow-400 text-black' : 'bg-slate-200 text-slate-500 hover:bg-slate-300'" title="Toggle Leader">👑</button><span class="text-xs font-bold text-slate-600 flex-1 truncate" x-text="member.Full_Name"></span><button type="button" @click="returnToRoster(gIndex, mIndex)" class="text-slate-400 hover:text-red-400 font-bold">&times;</button></div>
                                                            </template>
                                                            <div x-show="selectedStudent" @click="placeStudent(gIndex)" class="border border-dashed border-orange-500/50 bg-orange-500/10 p-3 rounded text-center text-[10px] font-bold text-orange-400 cursor-pointer hover:bg-orange-500/20 animate-pulse transition-all" x-cloak>Click to add <span x-text="selectedStudent?.Full_Name"></span></div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="absolute bottom-0 left-0 w-full pt-8 flex justify-between">
                                    <button type="button" x-show="step > 1" @click="prevStep()" class="bg-gray-200 text-gray-700 py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-gray-300 transition-all" x-cloak>&larr; Previous</button>
                                    <div class="ml-auto">
                                        <button type="button" x-show="step < 3" @click="nextStep()" class="bg-orange-500 text-white py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20">Next &rarr;</button>                                        
                                        <button type="button" x-show="step === 3 && mode === 'Group' && grouping === 'Manual'" @click="goToGroupingStep()" class="bg-orange-500 text-white py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20" x-cloak>Configure Groups &rarr;</button>
                                        <button type="submit" x-show="step === 3 && !(mode === 'Group' && grouping === 'Manual')" class="bg-green-500 text-white py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-green-600 transition-all shadow-lg shadow-green-500/20" x-cloak>
                                            <span x-text="editMode ? 'Update Activity' : 'Publish Activity'"></span>
                                        </button>
                                        <button type="submit" x-show="step === 4" class="bg-green-500 text-white py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-green-600 transition-all shadow-lg shadow-green-500/20" x-cloak>
                                            <span x-text="editMode ? 'Confirm & Update' : 'Confirm & Publish'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </section>

                    <!-- Right Column: Preview -->
                    <aside class="lg:col-span-1 sticky top-24">
                        <div class="bg-white rounded-2xl shadow-lg border border-slate-200/50 p-4">
                            <div class="flex gap-1 mb-4 bg-slate-100 p-1 rounded-lg">
                                <button @click="previewTab = 'details'" class="flex-1 py-2 rounded-md text-xs font-bold transition-all" :class="previewTab === 'details' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500'">Details</button>
                                <button @click="previewTab = 'logistics'" class="flex-1 py-2 rounded-md text-xs font-bold transition-all" :class="previewTab === 'logistics' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500'">Logistics</button>
                                <button @click="previewTab = 'settings'" class="flex-1 py-2 rounded-md text-xs font-bold transition-all" :class="previewTab === 'settings' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500'">Settings</button>
                                <button @click="previewTab = 'grouping'" x-show="mode === 'Group' && grouping === 'Manual'" class="flex-1 py-2 rounded-md text-xs font-bold transition-all" :class="previewTab === 'grouping' ? 'bg-white shadow-sm text-slate-800' : 'text-slate-500'" x-cloak>Grouping</button>
                            </div>

                            <div class="min-h-[450px]">
                                <!-- State 1: Details -->
                                <div x-show="previewTab === 'details'" x-transition.opacity>
                                    <div class="bg-slate-50/70 rounded-xl p-4">
                                        <div class="h-40 bg-slate-200 rounded-lg flex items-center justify-center mb-4 relative overflow-hidden"><iframe x-show="pdfUrl" :src="pdfUrl" class="absolute inset-0 w-full h-full bg-white" x-cloak></iframe><p x-show="!pdfUrl" class="text-xs text-slate-400 font-bold uppercase">PDF Preview</p></div>
                                        <div class="text-center"><span x-show="mode" x-text="mode" class="text-xs font-bold text-slate-500 uppercase tracking-wide"></span><h3 class="text-xl font-bold text-slate-800 mt-1 min-h-[28px]" x-text="title || 'Activity Title'"></h3></div>
                                        <div class="bg-white p-3 rounded-lg mt-4 border border-slate-200 min-h-[50px]"><p class="text-xs text-slate-500 leading-relaxed" x-text="description || 'Description will appear here.'"></p></div>
                                        <div class="mt-4 space-y-2"><div class="flex justify-between items-center bg-white p-2 rounded-lg border border-slate-200"><span class="text-xs font-semibold text-slate-500">Deadline:</span><span class="text-sm font-bold text-slate-800" x-text="deadline ? new Date(deadline).toLocaleDateString() : 'Not set'"></span></div></div>
                                    </div>
                                </div>

                                <!-- State 2: Logistics -->
                                <div x-show="previewTab === 'logistics'" x-transition.opacity x-cloak>
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Required Items (<span x-text="selectedItems.length"></span>)</h4>
                                    <div class="space-y-3 max-h-[450px] overflow-y-auto pr-2 custom-scrollbar">
                                        <template x-for="(item, i) in selectedItems" :key="item.id">
                                            <div class="bg-white p-3 rounded-xl shadow-sm border border-slate-100">
                                                <div class="flex items-center gap-3">
                                                    <img :src="`../../assets/img/items/${item.id}.png`" class="w-12 h-12 object-contain bg-slate-50 rounded-lg p-1 border border-slate-200" onerror="this.src='../../assets/img/placeholder.png'">
                                                    <div class="flex-1"><span class="text-sm font-bold text-slate-700" x-text="item.name"></span></div>
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" x-model.number="item.qty" min="1" class="w-16 bg-slate-100 text-center text-sm font-bold p-2 rounded-lg border border-slate-200 focus:ring-1 focus:ring-orange-500 outline-none">
                                                        <span x-show="item.is_consumable == 1 && item.is_scalable != 1 && item.unit" x-text="item.unit" class="text-xs font-bold text-slate-500" x-cloak></span>
                                                    </div>
                                                    <button type="button" @click="removeItem(i)" class="text-red-400 hover:text-red-600 font-bold p-2 rounded-full hover:bg-red-50 transition-colors">&times;</button>
                                                </div>
                                                <!-- Variant Selector for Scalable Items -->
                                                <div x-show="item.is_scalable == 1" class="mt-2 pt-2 border-t border-slate-100" x-cloak>
                                                    <select x-model="item.selectedVariantId" class="w-full text-xs bg-slate-100 border-slate-200 rounded-md p-2 focus:ring-1 focus:ring-orange-500 outline-none font-medium">
                                                        <option value="">Select a size...</option>
                                                        <template x-for="variant in item.variants" :key="variant.VariantID">
                                                            <option :value="variant.VariantID" x-text="`${variant.Size_Value}${variant.Unit || ''} (Stock: ${variant.Variant_Available_Qty})`"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                        </template>
                                        <div x-show="selectedItems.length === 0" class="text-center text-slate-400 text-xs italic pt-20" x-cloak>No items added yet. Click items from the inventory to add them here.</div>
                                        </div>
                                    </div>

                                <!-- State 3: Settings -->
                                <div x-show="previewTab === 'settings'" x-transition.opacity x-cloak>
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Assigned Classes (<span x-text="selectedClasses.length"></span>)</h4>
                                    <div class="bg-slate-50/70 rounded-xl p-4 mb-4">
                                        <ul class="space-y-2 max-h-40 overflow-y-auto custom-scrollbar pr-2">
                                            <template x-for="classId in selectedClasses" :key="classId">
                                                <li class="text-sm font-semibold text-slate-700" x-text="findClassName(classId)"></li>
                                            </template>
                                            <li x-show="selectedClasses.length === 0" class="text-xs text-slate-400 italic">No classes assigned yet.</li>
                                        </ul>
                                    </div>
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Workflow</h4>
                                    <div class="bg-slate-50/70 rounded-xl p-4 space-y-2">
                                        <div class="flex justify-between items-center"><span class="text-sm font-semibold text-slate-500">Mode:</span><span class="text-sm font-bold text-slate-800" x-text="mode"></span></div>
                                        <div x-show="mode === 'Group'" class="flex justify-between items-center"><span class="text-sm font-semibold text-slate-500">Strategy:</span><span class="text-sm font-bold text-slate-800" x-text="grouping"></span></div>
                                        <div x-show="mode === 'Group'" class="flex justify-between items-center"><span class="text-sm font-semibold text-slate-500">Group Limit:</span><span class="text-sm font-bold text-slate-800" x-text="group_limit"></span></div>
                                    </div>
                                </div>

                                <!-- State 4: Grouping Preview -->
                                <div x-show="previewTab === 'grouping'" x-transition.opacity x-cloak>
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Manual Groups (<span x-text="manualGroups.length"></span>)</h4>
                                    <div class="space-y-3 max-h-[450px] overflow-y-auto pr-2 custom-scrollbar">
                                        <template x-for="(group, index) in manualGroups" :key="group.id || index">
                                            <div class="bg-slate-50/70 rounded-xl p-4">
                                                <div class="flex justify-between items-center mb-2"><h5 class="text-sm font-bold text-slate-800" x-text="group.name"></h5><span class="text-xs font-bold text-slate-500" x-text="group.members.length + ' members'"></span></div>
                                                <ul class="space-y-1">
                                                    <template x-for="member in group.members" :key="member.MasterID"><li class="flex items-center gap-2 text-xs"><span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px]" :class="member.isLeader ? 'bg-yellow-200 text-yellow-800' : 'bg-slate-200 text-slate-600'"><span x-text="member.isLeader ? '👑' : '👤'"></span></span><span class="font-medium text-slate-600" x-text="member.Full_Name"></span></li></template>
                                                    <li x-show="group.members.length === 0" class="text-xs text-slate-400 italic">No members assigned.</li>
                                                </ul>
                                            </div>
                                        </template>
                                        <div x-show="manualGroups.length === 0" class="text-center text-slate-400 text-xs italic pt-20" x-cloak>No groups created yet.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>

    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl"></div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-container');
            if (!toast) return;
            const iconContainer = document.getElementById('toast-icon-container');
            const messageContainer = document.getElementById('toast-message');
            toast.className = 'fixed bottom-10 right-10 z-[200] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal';
            iconContainer.className = 'inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl';
            messageContainer.textContent = message;
            if (type === 'success') {
                toast.classList.add('bg-emerald-600');
                iconContainer.classList.add('bg-emerald-100');
                iconContainer.innerHTML = `<svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>`;
            } else {
                toast.classList.add('bg-red-600');
                iconContainer.classList.add('bg-red-100');
                iconContainer.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`;
            }
            toast.classList.remove('hidden');
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('activityWizard', (inventoryData, categoryData, editData = null, classData = []) => ({
                step: 1,
                previewTab: 'details',
                showFilters: false,
                assetType: 'all',
                selectedCategory: 'all',
                fullInventory: inventoryData,
                allCategories: categoryData,
                search: '',
                selectedItems: [],
                mode: 'Individual',
                grouping: 'Auto',
                group_limit: 4,
                fileName: '',
                pdfUrl: null,
                editMode: false,
                activityId: null,
                groupPreview: [],
                isPreviewLoading: false,
                title: '',
                description: '',
                deadline: '',
                isAiSuggesting: false,
                allClasses: classData,
                selectedClasses: [],

                init() {
                    if (editData) {
                        this.editMode = true;
                        this.activityId = editData.details.ActivityID;
                        this.title = editData.details.Title;
                        this.description = editData.details.Description;
                        this.deadline = editData.details.Deadline ? editData.details.Deadline.replace(' ', 'T') : '';
                        this.mode = editData.details.type;
                        this.grouping = editData.details.grouping_mode;
                        this.group_limit = editData.details.group_limit;
                        this.selectedItems = editData.requirements.map(req => {
                            const fullItem = inventoryData.find(inv => inv.ItemID == req.ItemID);
                            return { id: req.ItemID, name: req.Item_Name, qty: req.Required_Qty, is_scalable: fullItem?.is_scalable ?? 0, is_consumable: fullItem?.is_consumable ?? 0, unit: fullItem?.Unit ?? null, variants: fullItem?.variants ?? [], selectedVariantId: req.VariantID || null };
                        });
                        this.selectedClasses = editData.assignments;
                    }
                    this.$watch('step', (newStep) => { 
                        if (newStep === 1) this.previewTab = 'details'; 
                        else if (newStep === 2) this.previewTab = 'logistics'; 
                        if (newStep === 3) this.previewTab = 'settings'; 
                        if (newStep === 4) this.previewTab = 'grouping';
                    });
                    this.$watch('assetType', () => { this.selectedCategory = 'all'; });
                },
                findClassName(classId) {
                    const cls = this.allClasses.find(c => c.ClassID == classId);
                    return cls ? `${cls.Class_Name} - ${cls.Section}` : 'Unknown Class';
                },
                get filteredCategories() {
                    if (!this.allCategories) return [];
                    if (this.assetType === 'all') return this.allCategories;
                    const isConsumable = this.assetType === 'consumable' ? 1 : 0;
                    return this.allCategories.filter(cat => cat.is_consumable == isConsumable);
                },
                get filteredInventory() {
                    if (!this.fullInventory) return [];
                    return this.fullInventory.filter(item => {
                        const categoryMatch = (this.selectedCategory === 'all' || item.CategoryID == this.selectedCategory);
                        const searchMatch = (item.Item_Name.toLowerCase().includes(this.search.toLowerCase()));
                        const assetMatch = (this.assetType === 'all' || (item.Asset_Type && item.Asset_Type.toLowerCase() === this.assetType));
                        return categoryMatch && searchMatch && assetMatch;
                    });
                },
                roster: [],
                manualGroups: [], 
                selectedStudent: null,
                totalClassSize: 0,
                get stepTitle() { return ['Resources', 'Logistics', 'Settings'][this.step - 1]; },
                previewPDF(e) { const file = e.target.files[0]; if (file) { this.fileName = file.name; this.pdfUrl = URL.createObjectURL(file); } },
                addItem(item) { if (!this.selectedItems.some(i => i.id === item.ItemID)) { this.selectedItems.push({ id: item.ItemID, name: item.Item_Name, qty: 1, is_scalable: item.is_scalable, is_consumable: item.is_consumable, unit: item.Unit, variants: item.variants || [], selectedVariantId: null }); } this.previewTab = 'logistics'; },
                removeItem(i) { this.selectedItems.splice(i, 1); },
                nextStep() { if (this.step === 1 && (!this.title.trim() || !this.deadline.trim())) { showToast('Please provide a title and deadline before proceeding.', 'error'); return; } if (this.step < 3) this.step++; },
                prevStep() { if (this.step > 1) this.step--; },
                async getAiSuggestions() {
                    if (!this.description.trim()) { showToast('Please enter the activity instructions in Step 1 first.', 'error'); return; }
                    this.isAiSuggesting = true;
                    showToast('AI is analyzing instructions...', 'success');
                    try {
                        const response = await fetch('../../dbRelated/ai_activity_suggester.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ instructions: this.description }) });
                        if (!response.ok) { const errorData = await response.json(); throw new Error(errorData.error || `Server responded with status ${response.status}`); }
                        const suggestedItems = await response.json();
                        if (suggestedItems.error) { throw new Error(suggestedItems.error); }
                        if (Array.isArray(suggestedItems) && suggestedItems.length > 0) {
                            let addedCount = 0;
                            suggestedItems.forEach(item => {
                                const alreadyExists = this.selectedItems.some(req => req.id === item.ItemID);
                                if (!alreadyExists) { this.addItem(item); addedCount++; }
                            });
                            showToast(`Added ${addedCount} new item(s) based on instructions.`, 'success');
                        } else { showToast('AI could not find any matching items in the inventory.', 'success'); }
                    } catch (error) { console.error('AI Suggestion Error:', error); showToast('AI suggestion failed: ' + error.message, 'error'); } finally { this.isAiSuggesting = false; }
                },
                async fetchGroupPreview() {
                    if (this.mode !== 'Group' || this.grouping !== 'Auto') { this.groupPreview = []; return; }
                    const checkboxes = document.querySelectorAll('input[name="target_classes[]"]:checked');
                    const classIDs = Array.from(checkboxes).map(cb => cb.value);
                    if (classIDs.length === 0 || this.group_limit < 1) { this.groupPreview = []; return; }
                    this.isPreviewLoading = true;
                    const fd = new FormData();
                    fd.append('class_ids', classIDs.join(','));
                    fd.append('limit', this.group_limit);
                    try {
                        const response = await fetch('../../dbRelated/ajax_preview_groups.php', { method: 'POST', body: fd });
                        const result = await response.json();
                        if (result.status === 'success') { this.groupPreview = result.data; } else { throw new Error(result.error || 'Failed to fetch preview.'); }
                    } catch (e) { showToast(e.message, 'error'); this.groupPreview = []; } finally { this.isPreviewLoading = false; }
                },
                finalSubmit() { if (this.mode === 'Group' && this.grouping === 'Manual') { this.validateAndSubmitWithGroups(); } else { this.submitForm(); } },
                goToGroupingStep() {
                    const checkboxes = document.querySelectorAll('input[name="target_classes[]"]:checked');
                    const classIDs = Array.from(checkboxes).map(cb => cb.value);
                    if (classIDs.length === 0) { showToast('Please assign this activity to at least one class.', 'error'); return; }
                    const fd = new FormData();
                    fd.append('classes', classIDs.join(','));
                    fetch('../../dbRelated/get_roster.php', { method: 'POST', body: fd })
                    .then(async response => {
                        const text = await response.text();
                        if (!response.ok) { throw new Error(`Server Error (${response.status}): ${text}`); }
                        try { return JSON.parse(text); } catch (e) { console.error("Raw Server Response:", text); throw new Error("Server returned invalid JSON. Check console for details."); }
                    })
                    .then(data => {
                        if (data.error) { throw new Error(data.error); }
                        this.roster = data; this.totalClassSize = data.length; this.manualGroups = [];
                        if (this.group_limit > 0 && this.totalClassSize > 0) {
                            const numGroups = Math.ceil(this.totalClassSize / this.group_limit);
                            for (let i = 0; i < numGroups; i++) { this.manualGroups.push({ name: 'Group ' + (i + 1), members: [] }); }
                        }
                        this.step = 4;
                    })
                    .catch(err => { console.error(err); showToast("Error fetching students: " + err.message, 'error'); });
                },
                addGroup() { this.manualGroups.push({ name: 'Group ' + (this.manualGroups.length + 1), members: [] }); },
                removeGroup(index) { const members = this.manualGroups[index].members; members.forEach(m => { m.isLeader = false; this.roster.push(m); }); this.manualGroups.splice(index, 1); },
                selectStudent(stu) { this.selectedStudent = (this.selectedStudent === stu) ? null : stu; },
                placeStudent(groupIndex) { if (!this.selectedStudent) return; const group = this.manualGroups[groupIndex]; const limit = parseInt(this.group_limit); if (group.members.length >= limit) { showToast(`This group is full! (Limit: ${limit})`, 'error'); return; } const stu = this.selectedStudent; stu.isLeader = false; this.manualGroups[groupIndex].members.push(stu); this.roster = this.roster.filter(s => s.MasterID !== stu.MasterID); this.selectedStudent = null; },
                returnToRoster(gIndex, mIndex) { const stu = this.manualGroups[gIndex].members[mIndex]; stu.isLeader = false; this.roster.push(stu); this.manualGroups[gIndex].members.splice(mIndex, 1); },
                toggleLeader(gIndex, mIndex) { const group = this.manualGroups[gIndex]; const clickedMember = group.members[mIndex]; const wasLeader = clickedMember.isLeader; group.members.forEach(m => m.isLeader = false); if (!wasLeader) { clickedMember.isLeader = true; } },
                randomlyAssignRemainder() {
                    if (this.roster.length === 0) { showToast('All students are already assigned.', 'info'); return; }
                    const targetGroups = this.manualGroups.filter(g => g.members.length > 0);
                    if (targetGroups.length === 0) { showToast('Please create groups and assign a leader to each before auto-assigning.', 'error'); return; }
                    let studentsToAssign = [...this.roster];
                    for (let i = studentsToAssign.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1));[studentsToAssign[i], studentsToAssign[j]] = [studentsToAssign[j], studentsToAssign[i]]; }
                    let groupIndex = 0;
                    while (studentsToAssign.length > 0) {
                        const student = studentsToAssign.shift(); let assigned = false;
                        for (let i = 0; i < targetGroups.length; i++) {
                            let currentGroup = targetGroups[groupIndex];
                            if (currentGroup.members.length < this.group_limit) { currentGroup.members.push(student); this.roster = this.roster.filter(s => s.MasterID !== student.MasterID); assigned = true; groupIndex = (groupIndex + 1) % targetGroups.length; break; }
                            groupIndex = (groupIndex + 1) % targetGroups.length;
                        }
                        if (!assigned) { showToast('All groups are full. Some students remain unassigned.', 'error'); studentsToAssign.unshift(student); break; }
                    }
                    this.selectedStudent = null; showToast('Remaining students have been randomly assigned.', 'success');
                },
                validateAndSubmitWithGroups() {
                    if (this.roster.length > 0) { showToast(`You have ${this.roster.length} unassigned student(s) left to assign.`, 'error'); return; }
                    if (this.manualGroups.some(g => g.members.length === 0)) { if(!confirm("⚠️ You have empty groups created. These will be ignored. Continue?")) return; this.manualGroups = this.manualGroups.filter(g => g.members.length > 0); }
                    const leaderlessGroups = this.manualGroups.filter(g => g.members.length > 0 && !g.members.some(m => m.isLeader));
                    if (leaderlessGroups.length > 0) { const names = leaderlessGroups.map(g => g.name).join(', '); showToast(`Missing leaders for the following groups: ${names}`, 'error'); return; }
                    this.submitForm();
                },
                submitForm() {
                    const checkboxes = document.querySelectorAll('input[name="target_classes[]"]:checked');
                    if (checkboxes.length === 0) { showToast('Please assign this activity to at least one class.', 'error'); return; }
                    const form = document.getElementById('wizardForm');
                    const formData = new FormData(form);
                    if (this.mode === 'Group' && this.grouping === 'Auto' && this.groupPreview.length > 0) { formData.append('auto_groups_json', JSON.stringify(this.groupPreview)); }
                    this.selectedItems.forEach((item, index) => { formData.append(`requirements[${index}][id]`, item.id); formData.append(`requirements[${index}][qty]`, item.qty); if (item.selectedVariantId) { formData.append(`requirements[${index}][selectedVariantId]`, item.selectedVariantId); } });
                    if (this.mode === 'Group' && this.grouping === 'Manual') { formData.append('manual_groups_json', JSON.stringify(this.manualGroups)); }
                    fetch('../../dashboard/save_activity.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(d => { if(d.status === 'success') window.location.href = d.redirect; else showToast(d.message, 'error'); })
                        .catch(err => { console.error(err); showToast("A server error occurred. Please try again.", 'error'); });
                }
            }));
        });
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>