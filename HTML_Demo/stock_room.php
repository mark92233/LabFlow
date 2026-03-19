<?php
session_start();
require_once '../dbRelated/operation.php';

// Access Control: Only Teacher and Admin can see this page
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Teacher'])) {
    header("Location: /LabFlow/index.php");
    exit();
}

$db = new DataManager();
$role = $_SESSION['user_role'];

// --- Fetch Shelf Data ---
$shelves_data = $db->getShelves(); // Assuming a getShelves() method in DataManager

// Encode the data for JavaScript
$shelves_json = json_encode($shelves_data, JSON_THROW_ON_ERROR);

// --- Fetch Category Data for Alpine ---
$all_categories = $db->getCategories();
$all_categories_json = json_encode($all_categories, JSON_THROW_ON_ERROR);

$page_title = "Stock Room Layout";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        #blueprint-canvas {
            /* Light grid for the light theme */
            background-image: radial-gradient(rgba(0, 0, 0, 0.1) 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .shelf-rect {
            /* Orange theme for shelves */
            fill: rgba(249, 115, 22, 0.2); /* bg-orange-500 with opacity */
            stroke: #f97316; /* stroke-orange-500 */
            stroke-width: 2;
            cursor: pointer;
            transition: fill 0.2s;
        }
        .shelf-rect:hover {
            fill: rgba(249, 115, 22, 0.4); /* More opaque on hover */
        }
        .shelf-rect.highlight {
            /* Run the blink animation 3 times over 1.5 seconds */
            animation: shelf-blink 0.5s ease-in-out 3;
        }
        @keyframes shelf-blink {
            50% {
                fill: rgba(249, 115, 22, 0.6);
                stroke-width: 4;
            }
        }
        .shelf-rect.selected {
            stroke: #f97316; /* orange-500 */
            stroke-width: 3;
        }
        body.dragging-active,
        body.dragging-active * {
            cursor: grabbing !important;
        }
        .shelf-name-display {
            font-family: sans-serif;
            font-weight: bold;
            color: #c2410c; /* dark orange */
            font-size: 14px;
            text-align: center;
            /* Vertical and horizontal centering */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            padding: 2px;
            word-break: break-word;
            -webkit-user-select: none; user-select: none;
        }
        .shelf-input {
            width: 100%;
            height: 100%;
            border: none;
            background-color: rgba(255, 255, 255, 0.9);
            text-align: center;
            font-family: sans-serif;
            font-weight: bold;
            color: #c2410c;
            font-size: 14px;
            outline: 2px solid #f97316; /* orange-500 */
            border-radius: 2px;
            box-sizing: border-box;
        }
        .tooltip {
            position: absolute;
            background-color: #1f2937; /* bg-gray-800 */
            color: white;
            padding: 6px 12px;
            border-radius: 8px; /* rounded-lg */
            font-size: 12px;
            font-weight: 600; /* semibold */
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s, transform 0.2s;
            white-space: nowrap;
            z-index: 10;
            font-family: sans-serif;
            transform: translate(-50%, -100%); /* Center above the reference point */
            margin-top: -12px; /* Add space for the arrow */
        }
        .tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent; /* bg-gray-800 */
        }
        #selection-rect {
            fill: rgba(249, 115, 22, 0.1); /* orange, semi-transparent */
            stroke: #f97316; /* orange */
            stroke-dasharray: 4;
            pointer-events: none;
        }
        #blueprint-canvas.edit-mode {
            cursor: crosshair;
        }
        #blueprint-canvas.placing-mode .shelf-rect:not(.selected) {
            cursor: copy;
            fill: rgba(34, 197, 94, 0.2); /* green-500 with opacity */
            stroke: #16a34a; /* green-600 */
        }
        #blueprint-canvas.placing-mode .shelf-rect:hover:not(.selected) {
            fill: rgba(34, 197, 94, 0.4);
        }
        .place-text-display {
            display: none; /* Hidden by default */
        }
        #blueprint-canvas.placing-mode .place-text-display {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        #blueprint-canvas.placing-mode .shelf-name-display {
            display: none; /* Hide the regular name when placing */
        }
        .place-text-content {
            font-family: sans-serif;
            font-weight: bold;
            color: #15803d; /* green-700 */
            font-size: 14px;
            text-align: center;
        }
        /* --- New Focus Mode Styles --- */
        #blueprint-canvas.focus-mode {
            cursor: pointer;
        }

        /* Add a transition to all shelf groups for smooth effect */
        #shelves-group > g {
            transition: all 0.5s ease-in-out; /* Slower, more dramatic animation */
        }

        /* In focus mode, blur all shelves by default */
        #blueprint-canvas.focus-mode #shelves-group > g {
            filter: blur(2px);
            opacity: 0.3;
        }

        /* The focused shelf should be sharp and opaque */
        #blueprint-canvas.focus-mode #shelves-group > g.focused-shelf {
            filter: none;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal" 
                  x-data="stockRoomApp(<?= htmlspecialchars($shelves_json, ENT_QUOTES, 'UTF-8') ?>)"
                  x-init="init()"
                  @locate-item.window="handleLocateItem($event.detail)"
                  @mouseup.window="handleMouseUp($event)"
                  @click="handleViewModeClick($event)">
                <header class="mb-12 flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-800">Stock Room Layout</h2>
                        <p class="text-sm text-gray-500 mt-1" x-text="editMode ? 'Draw new shelves on the blueprint.' : 'Live view of the mapped shelves.'"></p>
                    </div>
                    <div class="flex items-center gap-4">
                        <?php if (in_array($role, ['Admin', 'Teacher'])): ?>
                            <!-- View Mode Button -->
                            <button x-show="!editMode" @click="toggleEditMode()" class="px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg flex items-center gap-2 bg-gray-800 text-white hover:bg-gray-900">
                                <svg x-show="!editMode" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                <span>Edit Layout</span>
                            </button>

                            <!-- Edit Mode Buttons -->
                            <div x-show="editMode" x-cloak class="flex items-center gap-4">
                                <button @click="saveLayout($event)" class="px-6 py-3 bg-green-500 text-white rounded-xl font-bold text-xs uppercase hover:bg-green-600 transition-all shadow-lg shadow-green-500/20 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Save Layout</span>
                                </button>
                                <button @click="clearDrawnShelves()" class="px-4 py-3 bg-gray-200 text-gray-700 rounded-xl font-bold text-xs uppercase hover:bg-gray-300 transition-colors">Clear</button>
                                <button @click="toggleEditMode()" class="px-4 py-3 bg-red-600 text-white rounded-xl font-bold text-xs uppercase hover:bg-red-700 transition-colors shadow-lg">Cancel</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 lg:items-start">
                    <div class="lg:col-span-1 space-y-6 relative">
                        <!-- Apparatus List -->
                        <div x-show="!editMode" x-transition x-cloak x-data="apparatusList()" x-init="init(<?= htmlspecialchars($all_categories_json, ENT_QUOTES, 'UTF-8') ?>)"
                             @filter-by-shelf.window="searchTerm = $event.detail"
                             :class="{ 'blur-sm brightness-90 pointer-events-none': placingItemId }">
                            <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-100 pb-2">Apparatus on Shelves</h3>
                            
                            <!-- Filters -->
                            <div class="space-y-4 mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-grow">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                        </div>
                                        <input type="text" x-model.debounce.300ms="searchTerm" placeholder="Search apparatus..." class="w-full p-2.5 pl-9 border text-sm border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500">
                                    </div>
                                    <button @click="showCategoryFilter = !showCategoryFilter" 
                                            class="flex-shrink-0 p-2.5 bg-white border border-gray-300 rounded-lg text-gray-500 hover:bg-orange-50 hover:text-orange-600 hover:border-orange-200 transition-all"
                                            title="Toggle Category Filter">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                    </button>
                                </div>

                                <!-- Asset Type Filter -->
                                <div x-show="showCategoryFilter" x-transition>
                                    <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-lg border border-gray-200 w-full text-[11px] tracking-tighter">
                                        <button @click="assetType = 'all'" :class="{ 'bg-white text-orange-600 shadow-md shadow-orange-500/10': assetType === 'all', 'text-gray-500 hover:bg-orange-50/50': assetType !== 'all' }" class="flex-1 px-2 py-1.5 font-bold rounded-md transition-all duration-200">All</button>
                                        <button @click="assetType = 'consumable'" :class="{ 'bg-white text-orange-600 shadow-md shadow-orange-500/10': assetType === 'consumable', 'text-gray-500 hover:bg-orange-50/50': assetType !== 'consumable' }" class="flex-1 px-2 py-1.5 font-bold rounded-md transition-all duration-200 whitespace-nowrap">Consumable</button>
                                        <button @click="assetType = 'non-consumable'" :class="{ 'bg-white text-orange-600 shadow-md shadow-orange-500/10': assetType === 'non-consumable', 'text-gray-500 hover:bg-orange-50/50': assetType !== 'non-consumable' }" class="flex-1 px-2 py-1.5 font-bold rounded-md transition-all duration-200 whitespace-nowrap">Non-Consumable</button>
                                    </div>
                                </div>
                                
                                <!-- Custom Category Dropdown -->
                                <div x-show="showCategoryFilter" x-transition class="relative" @click.away="isCategoryOpen = false">
                                    <button type="button" @click="isCategoryOpen = !isCategoryOpen" class="w-full p-2 border text-sm border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500 text-left flex justify-between items-center bg-white">
                                        <span x-text="selectedCategoryName"></span>
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>

                                    <div x-show="isCategoryOpen" x-transition class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-lg border border-gray-200 p-2 space-y-1" style="display: none;">
                                        <input type="text" x-model.debounce.300ms="categorySearch" placeholder="Search categories..." @click.stop class="w-full p-2 border text-sm border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500 mb-1">
                                        <div class="max-h-48 overflow-y-auto custom-scrollbar pr-1">
                                            <div @click="selectCategory('all', 'All Categories')" class="p-2 text-sm font-medium text-slate-700 rounded-lg hover:bg-gray-100 cursor-pointer">All Categories</div>
                                            <template x-for="category in filteredCategories" :key="category.CategoryID">
                                                <div @click="selectCategory(category.CategoryID, category.Category_Name)" class="p-2 text-sm font-medium text-slate-700 rounded-lg hover:bg-gray-100 cursor-pointer" x-text="category.Category_Name"></div>
                                            </template>
                                            <template x-if="filteredCategories.length === 0 && categorySearch">
                                                <div class="text-center text-xs text-gray-400 py-2">No match found.</div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item List -->
                            <div class="space-y-2 h-96 overflow-y-auto pr-2 custom-scrollbar">
                                <template x-if="loading">
                                    <p class="text-center text-gray-400 animate-pulse pt-10">Loading...</p>
                                </template>
                                <template x-if="!loading && items.length === 0">
                                    <p class="text-center text-gray-500 pt-10">No apparatus found.</p>
                                </template>
                                <template x-for="item in items" :key="item.ItemID">
                                    <div @click="locateItem(item)" class="flex justify-between items-center p-3 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                                        <span class="text-sm font-semibold text-gray-700" x-text="item.Item_Name"></span>
                                        <template x-if="item.shelf_id">
                                            <span class="text-xs text-gray-400 font-mono bg-gray-100 px-2 py-0.5 rounded" x-text="item.shelf_id"></span>
                                        </template>
                                        <!-- Future feature: <button @click="locateItem(item.ItemID)" class="text-blue-500">Locate</button> -->
                                    </div>
                                </template>
                            </div>

                            <!-- Pagination -->
                            <div x-show="totalPages > 1" class="mt-4 pt-4 border-t border-gray-100 flex justify-center items-center gap-4" x-cloak>
                                <div class="flex items-center gap-2">
                                    <label for="itemsPerPage" class="text-xs font-bold text-gray-500">Show:</label>
                                    <select x-model.number="itemsPerPage" id="itemsPerPage" class="bg-gray-50 border-gray-200 rounded-md text-xs font-bold p-1 focus:ring-orange-500 focus:border-orange-500">
                                        <option value="10">10</option>
                                        <option value="15">15</option>
                                        <option value="20">20</option>
                                        <option value="25">25</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-1">
                                    <template x-for="(page, index) in pages" :key="index">
                                        <div>
                                            <span x-show="page === '...'" class="px-2 py-1 text-xs font-bold text-gray-400">&hellip;</span>
                                            <button x-show="page !== '...'" @click="changePage(page)" x-text="page" class="px-2 py-1 rounded-md text-xs font-bold transition-colors" :class="{
                                                        'bg-orange-500 text-white shadow-md': currentPage === page,
                                                        'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50': currentPage !== page
                                                    }"></button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div x-show="editMode" x-transition x-cloak class="bg-orange-50 border-l-4 border-orange-400 text-orange-800 p-4 rounded-r-lg shadow-md" role="alert">
                            <p class="font-bold text-sm">Editing Instructions</p>
                            <ul class="mt-2 list-disc list-inside text-xs space-y-1">
                                <li><b>Draw:</b> Click & drag on an empty area.</li>
                                <li><b>Move:</b> Click and drag a shelf to move it.</li>
                                <li><b>Name/Rename:</b> Double-click a shelf.</li>
                                <li><b>Multi-Select:</b> Hold Ctrl and click shelves, then drag the group.</li>
                                <li><b>Rotate:</b> Right-click a selected shelf.</li>
                                <li><b>Copy/Paste:</b> Select an item and press Ctrl+C / Ctrl+V.</li>
                                <li><b>Delete:</b> Select item(s) and press Backspace/Delete.</li>
                                <li><b>Undo/Redo:</b> Press Ctrl+Z / Ctrl+Y.</li>
                            </ul>
                        </div>

                        <!-- Placing Mode Cancel Overlay -->
                        <div x-show="placingItemId && !editMode" x-cloak x-transition
                            @click="cancelPlacing()"
                            class="absolute inset-0 bg-gray-800/20 backdrop-blur-sm flex flex-col items-center justify-center text-center p-4 rounded-2xl cursor-pointer z-10">
                            <div class="w-12 h-12 bg-white/80 rounded-full flex items-center justify-center mb-4 shadow-lg">
                                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </div>
                            <h3 class="font-bold text-white" style="text-shadow: 0 1px 3px rgba(0,0,0,0.5);">Click to Cancel</h3>
                            <p class="text-xs text-white/80 mt-1" style="text-shadow: 0 1px 3px rgba(0,0,0,0.3);">Cancel placing item.</p>
                        </div>

                    </div>

                    <div class="lg:col-span-3 space-y-4">
                        <div class="bg-white rounded-2xl overflow-hidden relative shadow-2xl border border-gray-200/50 p-4">
                            <svg id="blueprint-canvas" width="100%" height="500" :class="{ 'edit-mode': editMode, 'placing-mode': placingItemId }"
                                 @mousedown="handleMouseDown($event)" @mousemove="handleMouseMove($event)" @mouseleave="endDrawing()"
                                 @dblclick="handleDoubleClick($event)">
                                <g id="shelves-group"></g>
                                <rect id="selection-rect" x="0" y="0" width="0" height="0" visibility="hidden" />
                            </svg>
                            <div id="tooltip" class="tooltip"></div>
                        </div>

                        <!-- Quick Tips Box -->
                        <div x-show="!placingItemId" x-transition class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200/50">
                            <h3 class="font-bold text-gray-800 mb-2 border-b border-gray-100 pb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                                <span>Quick Tips</span>
                            </h3>
                            <p class="text-sm text-gray-600 mt-3">Click an item in the list to locate it on the blueprint or to assign it to a shelf.</p>
                        </div>

                        <!-- Placing Mode Box -->
                        <div x-show="placingItemId" x-transition x-cloak class="bg-green-50 p-6 rounded-2xl shadow-lg border-l-4 border-green-400">
                            <h3 class="font-bold text-green-800 mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span>Placing Item</span>
                            </h3>
                            <p class="text-sm text-green-700 mt-3">
                                Click on a shelf in the blueprint to place <strong x-text="placingItemName"></strong>.
                            </p>
                            <button @click="cancelPlacing()" class="mt-4 text-xs font-bold text-gray-500 hover:text-red-600">Cancel Placement</button>
                        </div>
                    </div>
                </div>

                <!-- Deletion Confirmation Modal -->
                <div x-show="showConfirmationModal" x-cloak x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <!-- Backdrop -->
                    <div @click="showConfirmationModal = false" class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

                    <!-- Panel -->
                    <div x-show="showConfirmationModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-200">
                        <div class="p-8">
                            <div class="text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <h3 class="mt-5 text-xl font-bold text-gray-900">Confirm Deletion</h3>
                                <div class="mt-2 text-sm text-gray-500">
                                    <p>The following shelves contain items. Please select a new shelf to transfer them to before deleting.</p>
                                    <p>The following shelves contain items. Deleting them will make these items unassigned. Are you sure you want to continue?</p>
                                </div>
                            </div>

                            <div class="mt-6 max-h-60 overflow-y-auto space-y-4 rounded-lg bg-gray-50 p-4 border custom-scrollbar">
                                <template x-for="(items, shelfName) in shelvesToConfirm" :key="shelfName">
                                    <div class="text-sm">
                                        <p class="font-bold text-gray-800" x-text="shelfName"></p>
                                        <ul class="mt-1 list-disc list-inside pl-2 space-y-0.5 text-gray-600">
                                            <template x-for="item in items" :key="item">
                                                <li x-text="item"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex gap-4 bg-gray-50 px-8 py-4">
                            <button @click="showConfirmationModal = false; isSaving = false; isDeleting = false" type="button" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-bold text-gray-700 hover:bg-gray-100 transition-colors">
                                Cancel
                            </button>
                            <button @click="handleConfirmation()" type="button" class="w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white hover:bg-red-700 transition-colors shadow-lg shadow-red-500/20" x-text="confirmationContext === 'delete' ? 'Confirm & Delete' : 'Save Anyway'">
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl">
            <!-- Icon will be inserted by JS -->
        </div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <script>
        function apparatusList() {
            return {
                items: [],
                currentPage: 1,
                totalPages: 1,
                itemsPerPage: 15,
                loading: true,
                searchTerm: '',
                selectedCategory: 'all',
                assetType: 'all',

                // New properties for custom dropdown
                isCategoryOpen: false,
                categorySearch: '',
                allCategories: [],
                selectedCategoryName: 'All Categories',
                showCategoryFilter: true,

                init(categories = []) {
                    this.allCategories = categories;
                    this.fetchItems();
                    this.$watch('searchTerm', () => { this.currentPage = 1; this.fetchItems(); });
                    this.$watch('selectedCategory', () => { this.currentPage = 1; this.fetchItems(); });
                    
                    window.addEventListener('refresh-inventory', () => this.fetchItems());

                    this.$watch('assetType', () => {
                        this.currentPage = 1;

                        // If the current category is no longer valid for the selected asset type, reset it.
                        // This prevents having e.g. a non-consumable category selected while the 'Consumable' filter is active.
                        if (this.selectedCategory !== 'all') {
                            const isConsumable = this.assetType === 'consumable' ? 1 : 0;
                            const currentCat = this.allCategories.find(c => c.CategoryID == this.selectedCategory);

                            if (this.assetType !== 'all' && currentCat && currentCat.is_consumable != isConsumable) {
                                // The category is now invalid, reset it.
                                // The watcher on `selectedCategory` will trigger the fetch.
                                this.selectCategory('all', 'All Categories');
                            } else {
                                // The category is still valid, just fetch with the new asset type.
                                this.fetchItems();
                            }
                        } else {
                            // If 'All Categories' is selected, just fetch.
                            this.fetchItems();
                        }
                    });
                    this.$watch('itemsPerPage', () => { this.currentPage = 1; this.fetchItems(); });
                },

                get pages() {
                    const total = this.totalPages;
                    if (total <= 1) return [];
                    if (total <= 7) {
                        return Array.from({ length: total }, (_, i) => i + 1);
                    }

                    const current = this.currentPage;
                    if (current < 5) {
                        return [1, 2, 3, 4, 5, '...', total];
                    }
                    if (current > total - 4) {
                        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
                    }

                    return [1, '...', current - 1, current, current + 1, '...', total];
                },

                get filteredCategories() {
                    let categories = this.allCategories;

                    // 1. Filter by Asset Type
                    if (this.assetType !== 'all') {
                        const isConsumable = this.assetType === 'consumable' ? 1 : 0;
                        categories = categories.filter(cat => cat.is_consumable == isConsumable);
                    }

                    // 2. Filter by Search Term
                    if (!this.categorySearch) {
                        return categories;
                    }
                    return categories.filter(
                        cat => cat.Category_Name.toLowerCase().includes(this.categorySearch.toLowerCase())
                    );
                },

                selectCategory(id, name) {
                    this.selectedCategory = id;
                    this.selectedCategoryName = name;
                    this.isCategoryOpen = false;
                    this.categorySearch = ''; // Reset search on select
                },

                locateItem(item) {
                    this.$dispatch('locate-item', item);
                },

                fetchItems() {
                    this.loading = true;
                    const params = new URLSearchParams({
                        page: this.currentPage,
                        search: this.searchTerm,
                        category: this.selectedCategory,
                        asset_type: this.assetType,
                        limit: this.itemsPerPage
                    });
                    // This endpoint needs to be created. It should call getPaginatedInventory from DataManager.
                    fetch(`../dbRelated/ajax_get_paginated_inventory.php?${params}`)
                        .then(res => res.json())
                        .then(data => {
                            this.items = data.items;
                            this.totalPages = data.pages;
                            this.currentPage = data.currentPage;
                            this.loading = false;
                        })
                        .catch(err => {
                            console.error('Error fetching inventory:', err);
                            this.loading = false;
                        });
                },

                changePage(page) {
                    if (page > 0 && page <= this.totalPages && page !== '...') {
                        this.currentPage = page;
                        this.fetchItems();
                    }
                }
            }
        }

        function stockRoomApp(initialShelves) {
            return {
                editMode: false, // Is the app in edit mode?
                placingItemId: null,
                placingItemName: null,
                isSaving: false,
                showConfirmationModal: false,
                shelvesToConfirm: {},
                confirmationContext: 'save', // 'save' or 'delete'
                isDeleting: false, // To prevent multiple delete actions
                // For view-mode focus
                focusedShelfId: null, // For view-mode focus

                shelvesData: initialShelves, // Original data from PHP
                drawnShelves: [], // Holds all shelves in edit mode (initial + new)
                isDrawing: false, // For drawing new shelves
                startX: 0,
                startY: 0,
                
                draggingShelfId: null, // ID of the shelf being dragged
                dragStartMouseX: 0,
                dragStartMouseY: 0,
                dragStartShelfX: 0,
                dragStartShelfY: 0,
                
                pasting: false, // Flag to prevent double paste
                isMultiDragging: false,
                multiDragStartPositions: {},

                history: [],
                redoStack: [],
                clipboard: null,
                selectedShelfIds: [], // For multi-select (Ctrl+click)
                selectedShelfId: null, // For single hover/select
                editingNameId: null, // ID of the shelf currently being renamed

                lastMouseX: 0,
                lastMouseY: 0,

                canvas: null,
                shelvesGroup: null,
                selectionRect: null,
                shelfCountDisplay: null,
                tooltip: null,

                init() {
                    this.canvas = document.getElementById('blueprint-canvas');
                    this.shelvesGroup = document.getElementById('shelves-group');
                    this.selectionRect = document.getElementById('selection-rect');
                    this.shelfCountDisplay = document.getElementById('shelf-count');
                    this.tooltip = document.getElementById('tooltip');

                    this.canvas.addEventListener('click', (e) => {
                        if (this.placingItemId && e.target.classList.contains('shelf-rect')) {
                            const shelfName = e.target.getAttribute('data-id');
                            this.assignItem({
                                itemId: this.placingItemId,
                                itemName: this.placingItemName,
                                shelfName: shelfName
                            });
                        }
                    });
                    
                    // For view-mode focus
                    this.$watch('focusedShelfId', () => this.updateFocusVisuals());
                    window.addEventListener('keydown', this.handleKeyDown.bind(this));
                    window.addEventListener('keyup', this.handleKeyUp.bind(this));
                    this.renderShelves();
                },

                renderDoor() {
                    // Add a default "door" element visually
                    const canvasWidth = this.canvas.getBoundingClientRect().width;
                    if (canvasWidth > 0) {
                        // Using a different structure for the door to avoid edit interactions
                        const doorData = {
                            pos_x: canvasWidth - 120,
                            pos_y: 495,
                            width: 100,
                            height: 5,
                            shelf_name: 'door'
                        };
                        this.createVisualShelf(doorData, false); // false = not editable
                    }
                },

                toggleEditMode() {
                    this.editMode = !this.editMode;
                    this.clearAllShelves();
                    if (this.editMode) {
                        // Clear history for the new editing session
                        this.history = [];
                        this.redoStack = [];
                        this.clipboard = null;
                        this.selectedShelfId = null;
                        this.editingNameId = null;
                        this.selectedShelfIds = [];

                        // Deep copy initial shelves to make them editable
                        this.drawnShelves = JSON.parse(JSON.stringify(this.shelvesData)).map((shelf, index) => ({
                            id: shelf.shelf_name || `shelf_${Date.now()}_${index}`,
                            x: parseFloat(shelf.pos_x),
                            y: parseFloat(shelf.pos_y),
                            w: parseFloat(shelf.width),
                            h: parseFloat(shelf.height),
                            rotation: shelf.rotation || 0,
                            name: shelf.shelf_name
                        }));
                        // Save the initial state so we can undo back to it
                        this.history.push(JSON.parse(JSON.stringify(this.drawnShelves)));

                        this.renderDrawnShelves();
                    } else {
                        this.renderShelves(); // Re-render original state
                        this.drawnShelves = [];
                    }
                },

                // --- Event Handlers ---
                handleViewModeClick(e) {
                    // This handler is on the <main> element, so it catches clicks everywhere
                    if (this.editMode || this.placingItemId) return;

                    const shelfRect = e.target.closest('.shelf-rect');

                    // If the click is on a shelf, handle focus/unfocus
                    if (shelfRect) {
                        const shelfId = shelfRect.getAttribute('data-id');
                        const isUnfocusing = this.focusedShelfId === shelfId;
                        this.focusedShelfId = isUnfocusing ? null : shelfId;

                        // Dispatch event to filter apparatus list.
                        // If we are focusing on a shelf, filter by its name.
                        // If we are unfocusing (or clicking the same shelf again), clear the filter.
                        this.$dispatch('filter-by-shelf', isUnfocusing ? '' : shelfId);

                        return; // Done
                    }

                    // If the click is anywhere else and we are in focus mode, unfocus.
                    if (this.focusedShelfId !== null) {
                        this.focusedShelfId = null;
                        // Also clear the filter when clicking away
                        this.$dispatch('filter-by-shelf', '');
                    }
                },
                handleLocateItem(item) {
                    if (this.editMode) return;

                    // If the user clicks the same item that is already in placing mode, cancel it.
                    if (this.placingItemId && this.placingItemId === item.ItemID) {
                        this.cancelPlacing();
                        return;
                    }

                    // If a shelf is currently focused, unfocus it to avoid visual confusion.
                    if (this.focusedShelfId) {
                        this.focusedShelfId = null;
                    }

                    // Always enter placing mode for the selected item.
                    this.placingItemId = item.ItemID;
                    this.placingItemName = item.Item_Name;

                    // If the item is already on a shelf, highlight its current location and inform the user.
                    if (item.shelf_id) {
                        showToast(`Placing '${item.Item_Name}'. Click a new shelf to move it.`, 'success');
                        const shelfGroup = this.shelvesGroup.querySelector(`g[data-id='${item.shelf_id}']`);
                        if (shelfGroup) {
                            const shelfRect = shelfGroup.querySelector('.shelf-rect');
                            shelfRect.classList.add('highlight');
                            shelfGroup.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                            setTimeout(() => shelfRect.classList.remove('highlight'), 1500);
                        } else {
                            showToast(`Current shelf '${item.shelf_id}' not found on blueprint.`, 'error');
                        }
                    } else {
                        // If the item is unassigned, just give a simple placing message.
                        showToast(`Placing '${item.Item_Name}'. Click a shelf to assign it.`, 'success');
                    }
                },

                cancelPlacing() {
                    this.placingItemId = null;
                    this.placingItemName = null;
                },
                async assignItem(assignmentData) {
                    // Destructure the object to guarantee variables are not mixed up.
                    const { itemId, itemName, shelfName } = assignmentData;
                    try {
                        console.log(`Frontend AJAX Send: Sending itemId=${itemId}, shelfName="${shelfName}"`);
                        const response = await fetch('../dbRelated/ajax_assign_shelf.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            // The body now uses the correctly scoped shelfName variable.
                            body: JSON.stringify({ itemId: itemId, shelfName: shelfName })
                        });
                        const result = await response.json();
                        if (!response.ok) { throw new Error(result.message || 'Server error'); }
                        showToast(`Assigned '${itemName}' to shelf '${shelfName}'.`, 'success');
                        this.cancelPlacing();
                        window.dispatchEvent(new CustomEvent('refresh-inventory'));
                    } catch (error) {
                        showToast('Error: ' + error.message, 'error');
                    }
                },

                handleMouseDown(e) {
                    // Only respond to left-clicks for drawing/dragging logic
                    if (e.button !== 0) return;
                    if (!this.editMode) return;

                    // Prevent starting a new action if one is already in progress
                    if (this.isDrawing || this.draggingShelfId || this.isMultiDragging) return;

                    if (this.isMultiDragging) {
                    }

                    // Check if clicking on an existing shelf (rect within a group)
                    const clickedGroup = e.target.closest('g[data-id]');
                    if (clickedGroup && clickedGroup.querySelector('.shelf-rect') === e.target) {
                        e.stopPropagation();
                        const shelfId = clickedGroup.getAttribute('data-id');

                        if (e.ctrlKey) {
                            // Holding Ctrl: Toggle selection for multi-select
                            this.draggingShelfId = null; // Ensure single-drag is off
                            const index = this.selectedShelfIds.indexOf(shelfId);
                            if (index > -1) {
                                this.selectedShelfIds.splice(index, 1);
                            } else {
                                this.selectedShelfIds.push(shelfId);
                            }
                            this.updateSelectionVisuals();
                        } else {
                            // If the clicked shelf is part of a multi-selection, start multi-drag.
                            if (this.selectedShelfIds.includes(shelfId)) {
                                this.startMultiDrag(e);
                            } else {
                                this.startDrag(e);
                            }
                        }
                        return;
                    }

                    // Otherwise, start drawing a new shelf
                    this.isDrawing = true;
                    this.draggingShelfId = null; // Ensure not in drag mode
                    const rect = this.canvas.getBoundingClientRect();
                    this.startX = e.clientX - rect.left;
                    this.startY = e.clientY - rect.top;

                    this.selectionRect.setAttribute('x', this.startX);
                    this.selectionRect.setAttribute('y', this.startY);
                    this.selectionRect.setAttribute('width', 0);
                    this.selectionRect.setAttribute('height', 0);
                    this.selectionRect.setAttribute('visibility', 'visible');
                },

                handleMouseMove(e) {
                    this.lastMouseX = e.clientX;
                    this.lastMouseY = e.clientY;

                    if (!this.editMode) return;

                    if (this.isMultiDragging) {
                        this.dragMultipleShelves(e);
                    } else if (this.draggingShelfId) {
                        this.dragShelf(e);
                    } else if (this.isDrawing) {
                        const rect = this.canvas.getBoundingClientRect();
                        const currentX = e.clientX - rect.left;
                        const currentY = e.clientY - rect.top;
                        const width = currentX - this.startX;
                        const height = currentY - this.startY;

                        this.selectionRect.setAttribute('x', width < 0 ? currentX : this.startX);
                        this.selectionRect.setAttribute('y', height < 0 ? currentY : this.startY);
                        this.selectionRect.setAttribute('width', Math.abs(width));
                        this.selectionRect.setAttribute('height', Math.abs(height));
                    }
                },

                handleMouseUp(e) {
                    if (this.isDrawing) {
                        this.endDrawing(e);
                    } else if (this.draggingShelfId) {
                        this.endDrag(e);
                    } else if (this.isMultiDragging) {
                        this.endMultiDrag(e);
                    }
                },

                endDrawing(e = null) {
                    if (!this.isDrawing) return;
                    this.isDrawing = false;
                    this.selectionRect.setAttribute('visibility', 'hidden');

                    // If event is null, it means mouse left canvas, so cancel drawing.
                    if (!e) {
                        this.selectionRect.setAttribute('width', 0);
                        this.selectionRect.setAttribute('height', 0);
                        return;
                    }

                    const x = parseFloat(this.selectionRect.getAttribute('x'));
                    const y = parseFloat(this.selectionRect.getAttribute('y'));
                    const w = parseFloat(this.selectionRect.getAttribute('width'));
                    const h = parseFloat(this.selectionRect.getAttribute('height'));

                    // Reset selection rectangle to prevent recreation on single click
                    this.selectionRect.setAttribute('width', 0);
                    this.selectionRect.setAttribute('height', 0);

                    if (w > 10 && h > 10) {
                        const tempShelf = { x, y, w, h, rotation: 0, id: 'temp' };
                        let collision = false;
                        for (const shelf of this.drawnShelves) {
                            if (this.checkAABBCollision(tempShelf, shelf)) {
                                collision = true;
                                break;
                            }
                        }

                        if (collision) {
                            showToast('New shelf overlaps with an existing one.', 'error');
                        } else {
                            this.addDrawnShelf(x, y, w, h);
                        }
                    }
                },
                
                handleKeyDown(e) {
                    if (!this.editMode) return;

                    // Ignore shortcuts if user is typing in an input (none here, but good practice)
                    const targetNode = e.target.nodeName.toLowerCase();
                    if (targetNode === 'input' || targetNode === 'textarea') return;

                    if (e.ctrlKey) {
                        switch (e.key.toLowerCase()) {
                            case 'z':
                                e.preventDefault();
                                this.undo();
                                break;
                            case 'y':
                                e.preventDefault();
                                this.redo();
                                break;
                            case 'c':
                                e.preventDefault();
                                this.copyShelf();
                                break;
                            case 'v':
                                e.preventDefault();
                                this.pasteShelf();
                                break;
                        }
                    } else if (e.key === 'Backspace' || e.key === 'Delete') {
                        e.preventDefault();
                        this.initiateDelete();
                    }
                },

                handleDoubleClick(e) {
                    const clickedGroup = e.target.closest('g[data-id]');
                    if (!this.editMode || !clickedGroup || clickedGroup.querySelector('.shelf-rect') !== e.target || this.editingNameId) return;
                    e.stopPropagation();

                    const shelfId = clickedGroup.getAttribute('data-id');
                    const shelf = this.drawnShelves.find(s => s.id === shelfId);
                    if (!shelf) return;

                    this.editingNameId = shelfId;

                    const group = e.target.parentElement;
                    const displayForeignObject = group.querySelector('foreignObject');
                    const rectElement = e.target;

                    if (!displayForeignObject) return;

                    displayForeignObject.style.display = 'none'; // Hide the display text

                    const editForeignObject = document.createElementNS('http://www.w3.org/2000/svg', 'foreignObject');                    
                    let textWidth = (shelf.rotation === 90 || shelf.rotation === 270) ? shelf.h : shelf.w;
                    let textHeight = (shelf.rotation === 90 || shelf.rotation === 270) ? shelf.w : shelf.h;
                    // Position relative to the group's origin (0,0)

                    editForeignObject.setAttribute('x', shelf.w / 2 - textWidth / 2);
                    editForeignObject.setAttribute('y', shelf.h / 2 - textHeight / 2);
                    editForeignObject.setAttribute('width', textWidth);
                    editForeignObject.setAttribute('height', textHeight);

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.value = shelf.name || '';
                    input.className = 'shelf-input';

                    const finishEdit = () => {
                        if (!this.editingNameId) return; // Prevent double execution
                        const newName = input.value.trim();
                        if (newName && shelf.name !== newName) {
                            this.saveState();
                            shelf.name = newName;
                            const div = displayForeignObject.querySelector('div');
                            if (div) div.textContent = newName;
                        } else if (!newName) {
                            showToast('Shelf name cannot be empty.', 'error');
                        }
                        group.removeChild(editForeignObject);
                        displayForeignObject.style.display = 'block';
                        this.editingNameId = null;
                    };

                    input.addEventListener('blur', finishEdit);
                    input.addEventListener('keydown', (ev) => {
                        if (ev.key === 'Enter') input.blur();
                        else if (ev.key === 'Escape') {
                            this.editingNameId = null; // Mark as not editing to prevent saving on blur
                            group.removeChild(editForeignObject);
                            displayForeignObject.style.display = 'block';
                        }
                    });

                    editForeignObject.appendChild(input);
                    group.appendChild(editForeignObject);

                    input.focus();
                    input.select();
                },


                handleKeyUp(e) {
                    if (!this.editMode) return;

                },

                // --- Shelf Manipulation ---
                async deleteShelvesFromDB(shelfNames) {
                    await fetch('../dbRelated/ajax_delete_shelves.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ shelf_names: shelfNames })
                    });
                },

                async initiateDelete() {
                    if (this.isDeleting || (this.selectedShelfIds.length === 0 && !this.selectedShelfId)) {
                        return; // Nothing selected or already deleting
                    }
                    this.isDeleting = true;

                    const shelvesToDelete = this.selectedShelfIds.length > 0 
                        ? this.drawnShelves.filter(s => this.selectedShelfIds.includes(s.id))
                        : [this.drawnShelves.find(s => s.id === this.selectedShelfId)];
                    
                    const shelfNamesToDelete = shelvesToDelete.map(s => s.name);
                    const existingShelfNames = shelfNamesToDelete.filter(name => name && !name.startsWith('new_shelf_'));

                    if (existingShelfNames.length === 0) {
                        // Only deleting newly drawn, unsaved shelves. No need to check DB.
                        this.deleteSelectedShelvesFromUI();
                        this.isDeleting = false;
                        return;
                    }

                    try {
                        const response = await fetch('../dbRelated/ajax_check_shelf_contents.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ shelf_names: existingShelfNames })
                        });

                        if (response.ok) {
                            // No items on shelves, safe to delete from DB and then UI
                            await this.deleteShelvesFromDB(existingShelfNames);
                            this.deleteSelectedShelvesFromUI();
                        } else if (response.status === 409) {
                            // Items found, confirmation needed
                            const result = await response.json();
                            this.shelvesToConfirm = result.data;
                            this.confirmationContext = 'delete';
                            this.showConfirmationModal = true;
                        } else {
                            const result = await response.json();
                            throw new Error(result.message || 'Failed to check shelf contents.');
                        }
                    } catch (error) {
                        showToast('Error: ' + error.message, 'error');
                    } finally {
                        if (!this.showConfirmationModal) {
                            this.isDeleting = false;
                        }
                    }
                },

                async forceDeleteShelves() {
                    if (this.isDeleting) return;
                    this.isDeleting = true;
                    const shelfNames = Object.keys(this.shelvesToConfirm);
                    try {
                        // Step 1: Unassign items from the shelves
                        const response = await fetch('../dbRelated/ajax_unassign_from_shelves.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ shelf_names: shelfNames })
                        });
                        if (!response.ok) {
                            const result = await response.json();
                            throw new Error(result.message || 'Failed to unassign items.');
                        }

                        // Step 2: Delete the shelves themselves from the database
                        const deleteResponse = await fetch('../dbRelated/ajax_delete_shelves.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ shelf_names: shelfNames })
                        });
                        if (!deleteResponse.ok) {
                            const deleteResult = await deleteResponse.json();
                            throw new Error(deleteResult.message || 'Failed to delete shelves.');
                        }

                        // Both operations successful, now update UI
                        this.deleteSelectedShelvesFromUI();
                        showToast('Items unassigned and shelf deleted.', 'success');
                        this.showConfirmationModal = false;
                    } catch (error) {
                        showToast('Error: ' + error.message, 'error');
                    } finally {
                        this.isDeleting = false;
                    }
                },

                startDrag(e) {
                    const shelfId = e.target.getAttribute('data-id');
                    // Find the shelf data from drawnShelves (edit mode)
                    const shelf = this.drawnShelves.find(s => s.id === shelfId); 

                    if (!shelf) return;

                    // Save state before starting a drag
                    this.saveState();

                    // Clear multi-select and select just this one
                    this.selectedShelfIds = [];
                    this.selectedShelfId = shelfId;

                    document.body.classList.add('dragging-active');

                    this.draggingShelfId = shelfId;
                    const rect = this.canvas.getBoundingClientRect();
                    const mouseX = e.clientX - rect.left;
                    const mouseY = e.clientY - rect.top;

                    this.dragStartMouseX = mouseX;
                    this.dragStartMouseY = mouseY;
                    this.dragStartShelfX = shelf.x;
                    this.dragStartShelfY = shelf.y;
                },

                dragShelf(e) {
                    if (!this.draggingShelfId) return;
                    const shelf = this.drawnShelves.find(s => s.id === this.draggingShelfId);
                    if (!shelf) return;

                    const rect = this.canvas.getBoundingClientRect();
                    const mouseX = e.clientX - rect.left;
                    const mouseY = e.clientY - rect.top;

                    const deltaX = mouseX - this.dragStartMouseX;
                    const deltaY = mouseY - this.dragStartMouseY;

                    shelf.x = this.dragStartShelfX + deltaX;
                    shelf.y = this.dragStartShelfY + deltaY;

                    this.updateShelfElement(shelf);
                },

                endDrag() {
                    const shelf = this.drawnShelves.find(s => s.id === this.draggingShelfId);
                    if (shelf && this.isOverlapping(shelf)) {
                        showToast('Placement overlaps with another shelf.', 'error');
                        shelf.x = this.dragStartShelfX;
                        shelf.y = this.dragStartShelfY;
                        this.updateShelfElement(shelf);
                    }

                    document.body.classList.remove('dragging-active');
                    this.draggingShelfId = null;
                    // Don't clear selection, let mouseleave handle it for consistency
                },

                rotateShelf(e) {
                    const clickedGroup = e.target.closest('g[data-id]');
                    if (!clickedGroup) return;
                    const shelfId = clickedGroup.getAttribute('data-id');
                    const shelf = this.drawnShelves.find(s => s.id === shelfId);
                    if (!shelf) return;

                    this.saveState();
                    shelf.rotation = (shelf.rotation + 90) % 360;
                    this.updateShelfElement(shelf);
                },

                startMultiDrag(e) {
                    this.saveState(); // Save state before starting drag
                    document.body.classList.add('dragging-active');
                    this.isMultiDragging = true;
                    this.multiDragStartPositions = {};
                    const rect = this.canvas.getBoundingClientRect();
                    this.dragStartMouseX = e.clientX - rect.left;
                    this.dragStartMouseY = e.clientY - rect.top;

                    this.saveState(); // Save state before starting drag

                    this.selectedShelfIds.forEach(id => {
                        const shelf = this.drawnShelves.find(s => s.id === id);
                        if (shelf) {
                            this.multiDragStartPositions[id] = { x: shelf.x, y: shelf.y };
                        }
                    });
                },

                dragMultipleShelves(e) {
                    const rect = this.canvas.getBoundingClientRect();
                    const mouseX = e.clientX - rect.left;
                    const mouseY = e.clientY - rect.top;
                    const deltaX = mouseX - this.dragStartMouseX;
                    const deltaY = mouseY - this.dragStartMouseY;

                    this.selectedShelfIds.forEach(id => {
                        const shelf = this.drawnShelves.find(s => s.id === id);
                        const startPos = this.multiDragStartPositions[id];
                        if (shelf && startPos) {
                            shelf.x = startPos.x + deltaX;
                            shelf.y = startPos.y + deltaY;
                            this.updateShelfElement(shelf);
                        }
                    });
                },

                endMultiDrag() {
                    let collision = false;
                    for (const id of this.selectedShelfIds) {
                        const shelf = this.drawnShelves.find(s => s.id === id);
                        if (shelf && this.isOverlapping(shelf, this.selectedShelfIds)) {
                            collision = true;
                            break;
                        }
                    }

                    if (collision) {
                        showToast('Placement overlaps with another shelf.', 'error');
                        // Revert all
                        this.selectedShelfIds.forEach(id => {
                            const shelf = this.drawnShelves.find(s => s.id === id);
                            const startPos = this.multiDragStartPositions[id];
                            if (shelf && startPos) {
                                shelf.x = startPos.x;
                                shelf.y = startPos.y;
                                this.updateShelfElement(shelf);
                            }
                        });
                    }
                    document.body.classList.remove('dragging-active');
                    this.isMultiDragging = false;
                },

                addDrawnShelf(x, y, w, h) {
                    this.saveState();
                    const shelfId = 'new_shelf_' + Date.now();
                    const newShelf = { id: shelfId, x, y, w, h, rotation: 0, name: '' };
                    this.drawnShelves.push(newShelf);
                    this.createVisualShelf(newShelf, true);
                    this.shelfCountDisplay.innerText = this.drawnShelves.length;
                },

                deleteSelectedShelvesFromUI() {
                    if (this.draggingShelfId || this.isMultiDragging) return;
                    this.saveState();

                    if (this.selectedShelfIds.length > 0) {
                        // Delete multi-selected items
                        this.drawnShelves = this.drawnShelves.filter(s => !this.selectedShelfIds.includes(s.id));
                        this.selectedShelfIds = [];
                    } else if (this.selectedShelfId) {
                        // Delete single hover-selected item
                        this.drawnShelves = this.drawnShelves.filter(s => s.id !== this.selectedShelfId);
                        this.selectedShelfId = null;
                    }

                    this.renderDrawnShelves();
                },

                copyShelf() {
                    let shelfToCopy = null;
                    if (this.selectedShelfIds.length > 0) {
                        // If multi-selecting, copy the last one added
                        const lastSelectedId = this.selectedShelfIds[this.selectedShelfIds.length - 1];
                        shelfToCopy = this.drawnShelves.find(s => s.id === lastSelectedId);
                    } else if (this.selectedShelfId) {
                        // If just hovering, copy the hovered item
                        shelfToCopy = this.drawnShelves.find(s => s.id === this.selectedShelfId);
                    }

                    if (shelfToCopy) {
                        this.clipboard = JSON.parse(JSON.stringify(shelfToCopy));
                    }
                },

                pasteShelf() {
                    if (!this.clipboard || this.pasting) return;

                    this.pasting = true;
                    this.saveState();
                    const newShelf = JSON.parse(JSON.stringify(this.clipboard));
                    newShelf.id = 'new_shelf_' + Date.now();

                    const rect = this.canvas.getBoundingClientRect();
                    newShelf.x = (this.lastMouseX - rect.left) - (newShelf.w / 2);
                    newShelf.y = (this.lastMouseY - rect.top) - (newShelf.h / 2);

                    this.drawnShelves.push(newShelf);
                    this.renderDrawnShelves();

                    setTimeout(() => { this.pasting = false; }, 100);
                },

                // --- Collision Detection ---
                checkCollision(rectA, rectB) {
                    return !(
                        rectA.right < rectB.left || 
                        rectA.left > rectB.right || 
                        rectA.bottom < rectB.top || 
                        rectA.top > rectB.bottom
                    );
                },

                checkAABBCollision(shelfA, shelfB) {
                    return shelfA.x < shelfB.x + shelfB.w &&
                           shelfA.x + shelfA.w > shelfB.x &&
                           shelfA.y < shelfB.y + shelfB.h &&
                           shelfA.h + shelfA.y > shelfB.y;
                },

                isOverlapping(shelfToTest, ignoreIds = []) {
                    const testEl = this.shelvesGroup.querySelector(`[data-id='${shelfToTest.id}']`);
                    if (!testEl) return false;
                    const testRect = testEl.getBoundingClientRect();

                    return this.drawnShelves.some(shelf => {
                        if (shelf.id === shelfToTest.id || ignoreIds.includes(shelf.id)) return false;
                        const otherEl = this.shelvesGroup.querySelector(`[data-id='${shelf.id}']`);
                        return otherEl && this.checkCollision(testRect, otherEl.getBoundingClientRect());
                    });
                },

                // --- History Management ---
                saveState() {
                    this.history.push(JSON.parse(JSON.stringify(this.drawnShelves)));
                    this.redoStack = [];
                    // Limit history to a reasonable size
                    if (this.history.length > 50) {
                        this.history.shift();
                    }
                },

                undo() {
                    if (this.history.length <= 1) return;
                    this.redoStack.push(JSON.parse(JSON.stringify(this.drawnShelves)));
                    this.drawnShelves = this.history.pop();
                    this.renderDrawnShelves();
                    this.selectedShelfId = null;
                    this.selectedShelfIds = [];
                    this.updateSelectionVisuals();
                },

                redo() {
                    if (this.redoStack.length === 0) return;
                    this.history.push(JSON.parse(JSON.stringify(this.drawnShelves)));
                    this.drawnShelves = this.redoStack.pop();
                    this.renderDrawnShelves();
                    this.selectedShelfId = null;
                    this.selectedShelfIds = [];
                    this.updateSelectionVisuals();
                },

                // --- Visual Updates ---
                // For view-mode focus
                updateFocusVisuals() {
                    // This function is now responsible for both blurring other shelves
                    // and smoothly animating the focused shelf to the top-left corner.
                    // It works by switching from setting the SVG 'transform' attribute
                    // to setting the CSS 'transform' style property, which allows
                    // the 'transition' in the <style> block to apply to the movement.

                    const hasFocus = this.focusedShelfId !== null;

                    if (hasFocus) {
                        this.canvas.classList.add('focus-mode');
                    } else {
                        this.canvas.classList.remove('focus-mode');
                    }

                    const shelfGroups = this.shelvesGroup.querySelectorAll('g[data-id]'); // Select only actual shelf groups
                    let focusedGroup = null;

                    shelfGroups.forEach(group => {
                        const rect = group.querySelector('.shelf-rect');
                        if (!rect) return; // Skip non-shelf groups or malformed ones

                        const shelfId = group.getAttribute('data-id'); // Get ID from the group
                        const shelfData = this.shelvesData.find(s => s.shelf_name === shelfId); // In view mode, data comes from shelvesData

                        if (!shelfData) return;

                        if (hasFocus && shelfId === this.focusedShelfId) {
                            group.classList.add('focused-shelf');
                            focusedGroup = group;

                            // Get original position and rotation to keep it in place
                            const originalX = parseFloat(group.getAttribute('data-original-x'));
                            const originalY = parseFloat(group.getAttribute('data-original-y'));
                            const originalRotation = parseFloat(group.getAttribute('data-original-rotation'));
                            const currentW = parseFloat(shelfData.width);
                            const currentH = parseFloat(shelfData.height);

                            // Apply transform to scale in place, keeping original position and rotation.
                            group.style.transformOrigin = `${currentW / 2}px ${currentH / 2}px`;
                            group.style.transform = `translate(${originalX}px, ${originalY}px) rotate(${originalRotation}deg) scale(1.02)`;

                            // No need to adjust the foreignObject since the rotation is preserved.
                        } else {
                            group.classList.remove('focused-shelf');
                            // Restore original position and rotation
                            const originalX = parseFloat(group.getAttribute('data-original-x'));
                            const originalY = parseFloat(group.getAttribute('data-original-y'));
                            const originalRotation = parseFloat(group.getAttribute('data-original-rotation'));
                            const originalW = parseFloat(shelfData.width);
                            const originalH = parseFloat(shelfData.height);

                            // Restore original transform via CSS
                            group.style.transformOrigin = `${originalW / 2}px ${originalH / 2}px`;
                            group.style.transform = `translate(${originalX}px, ${originalY}px) rotate(${originalRotation}deg)`;
                            
                            // Restore foreignObject position relative to the group's original origin
                            const foreignObjectElement = group.querySelector('foreignObject');
                            if (foreignObjectElement) {
                                let textWidth, textHeight;
                                if (originalRotation === 90 || originalRotation === 270) {
                                    textWidth = originalH;
                                    textHeight = originalW;
                                } else {
                                    textWidth = originalW;
                                    textHeight = originalH;
                                }
                                foreignObjectElement.setAttribute('x', originalW / 2 - textWidth / 2);
                                foreignObjectElement.setAttribute('y', originalH / 2 - textHeight / 2);
                                foreignObjectElement.setAttribute('width', textWidth);
                                foreignObjectElement.setAttribute('height', textHeight);
                            }
                        }
                    });

                    // Bring the focused group to the front so its shadow/outline isn't clipped
                    if (focusedGroup) {
                        this.shelvesGroup.appendChild(focusedGroup);
                    }
                },
                updateShelfElement(shelf) {
                    const groupElement = this.shelvesGroup.querySelector(`g[data-id='${shelf.id}']`);
                    if (groupElement) {                        const rectElement = groupElement.querySelector('.shelf-rect');
                        const foreignObjectElement = groupElement.querySelector('foreignObject');

                        // Update the group's transform directly using CSS properties for transitions
                        groupElement.style.transformOrigin = `${shelf.w / 2}px ${shelf.h / 2}px`;
                        groupElement.style.transform = `translate(${shelf.x}px, ${shelf.y}px) rotate(${shelf.rotation || 0}deg)`;

                        // Rect and foreignObject now have fixed x,y relative to the group's origin (0,0)                        rectElement.setAttribute('width', shelf.w);
                        rectElement.setAttribute('height', shelf.h);

                        if (foreignObjectElement && foreignObjectElement.tagName.toLowerCase() === 'foreignobject') {                            let textWidth, textHeight;
                            if (shelf.rotation === 90 || shelf.rotation === 270) {                                textWidth = shelf.h;
                                textHeight = shelf.w;
                            } else {                                textWidth = shelf.w;
                                textHeight = shelf.h;
                            }                            foreignObjectElement.setAttribute('x', shelf.w / 2 - textWidth / 2);
                            foreignObjectElement.setAttribute('y', shelf.h / 2 - textHeight / 2);
                            foreignObjectElement.setAttribute('width', textWidth);
                            foreignObjectElement.setAttribute('height', textHeight);

                            const div = foreignObjectElement.querySelector('div');
                            if (div) {
                                div.textContent = shelf.name || 'Untitled';
                                div.style.display = (textWidth < 40 || textHeight < 20) ? 'none' : 'flex';
                            }
                        }
                    }
                },

                clearDrawnShelves() {
                    this.saveState();
                    this.selectedShelfId = null;
                    this.editingNameId = null;
                    this.selectedShelfIds = [];

                    // In edit mode, this resets to the initial state before drawing
                    this.drawnShelves = JSON.parse(JSON.stringify(this.shelvesData)).map((shelf, index) => ({
                        id: shelf.shelf_name || `shelf_${Date.now()}_${index}`,
                        x: parseFloat(shelf.pos_x),
                        y: parseFloat(shelf.pos_y),
                        w: parseFloat(shelf.width),
                        h: parseFloat(shelf.height),
                        rotation: shelf.rotation || 0,
                        name: shelf.shelf_name
                    }));
                    this.renderDrawnShelves();
                },

                async saveLayout(force = false) {
                    if (this.isSaving) return;

                    // Check for any unnamed shelves before saving
                    const unnamedShelf = this.drawnShelves.find(s => !s.name || s.name.trim() === '');
                    if (unnamedShelf) {
                        showToast('All shelves must have a name before saving. Double-click a shelf to name it.', 'error');
                        this.selectedShelfId = unnamedShelf.id;
                        this.selectedShelfIds = [];
                        this.updateSelectionVisuals();
                        return;
                    }

                    this.isSaving = true;
                    this.confirmationContext = 'save'; // Set context before making the call

                    try {
                        const payload = {
                            layout: this.drawnShelves.map(s => ({
                                shelf_name: s.name,
                                pos_x: s.x,
                                pos_y: s.y,
                                width: s.w,
                                height: s.h,
                                rotation: s.rotation
                            })),
                            force: force
                        };

                        const response = await fetch('../dbRelated/ajax_save_shelves.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });

                        const result = await response.json();

                        if (response.ok) {
                            showToast(result.message || 'Layout saved successfully!', 'success');
                            this.showConfirmationModal = false; // Close modal if it was open
                            setTimeout(() => window.location.reload(), 1500);
                        } else if (response.status === 409 && result.status === 'confirmation_required') {
                            // This is our custom response for confirmation
                            this.shelvesToConfirm = result.data;
                            this.showConfirmationModal = true;
                            // isSaving will be reset in the finally block, or by the modal cancel button
                        } else {
                            throw new Error(result.message || 'Failed to save layout.');
                        }
                    } catch (error) {
                        console.error('Error saving layout:', error);
                        showToast('An error occurred: ' + error.message, 'error');
                    } finally {
                        if (!this.showConfirmationModal) {
                            this.isSaving = false;
                        }
                    }
                },
                handleConfirmation() {
                    if (this.confirmationContext === 'save') {
                        this.confirmAndSaveLayout();
                    } else if (this.confirmationContext === 'delete') {
                        this.forceDeleteShelves();
                    }
                },

                confirmAndSaveLayout() {
                    this.saveLayout(true);
                },

                // --- Rendering ---
                renderShelves() {
                    this.clearAllShelves();
                    if (this.shelvesData) {
                        this.shelvesData.forEach(shelf => this.createVisualShelf(shelf, false));
                        this.shelfCountDisplay.innerText = this.shelvesData.length;
                    }
                    this.renderDoor();
                },

                renderDrawnShelves() {
                    this.clearAllShelves();
                    this.drawnShelves.forEach(shelf => this.createVisualShelf(shelf, true));
                    this.shelfCountDisplay.innerText = this.drawnShelves.length;
                    this.renderDoor();
                },

                createVisualShelf(shelf, isEditable) {
                    if (shelf.shelf_name === 'door') {
                        const group = document.createElementNS("http://www.w3.org/2000/svg", "g");
                        group.style.pointerEvents = 'none'; // Make the group non-interactive

                        // Create a thin rectangle to act as the door line
                        const rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
                        rect.setAttribute('x', shelf.pos_x);
                        rect.setAttribute('y', shelf.pos_y);
                        rect.setAttribute('width', shelf.width);
                        rect.setAttribute('height', 5); // Made thicker
                        rect.setAttribute('fill', '#f97316'); // Changed to orange
                        group.appendChild(rect);

                        // Add a text label for the door
                        const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
                        text.setAttribute('x', shelf.pos_x + shelf.width / 2);
                        text.setAttribute('y', shelf.pos_y - 8); // Position text above the line
                        text.setAttribute('fill', '#c2410c'); // Changed to dark orange
                        text.setAttribute('font-size', '12px'); // Made bigger
                        text.setAttribute('font-family', 'sans-serif');
                        text.setAttribute('text-anchor', 'middle');
                        text.setAttribute('font-weight', 'bold');
                        text.setAttribute('letter-spacing', '1');
                        text.textContent = 'DOOR';
                        group.appendChild(text);

                        this.shelvesGroup.appendChild(group);
                    } else {
                        const group = document.createElementNS("http://www.w3.org/2000/svg", "g");
                        const rect = document.createElementNS("http://www.w3.org/2000/svg", "rect");
                        const foreignObject = document.createElementNS('http://www.w3.org/2000/svg', 'foreignObject');
                        const placeTextForeignObject = document.createElementNS('http://www.w3.org/2000/svg', 'foreignObject');
                        const div = document.createElement('div');
                        const placeTextDiv = document.createElement('div');

                        const shelfId = isEditable ? shelf.id : shelf.shelf_name;
                        const shelfName = isEditable ? shelf.name : shelf.shelf_name;
                        const x = isEditable ? shelf.x : shelf.pos_x;
                        const y = isEditable ? shelf.y : shelf.pos_y;
                        const w = isEditable ? shelf.w : shelf.width;
                        const h = isEditable ? shelf.h : shelf.height;
                        const rotation = shelf.rotation || 0;

                        // Store original position and rotation on the group element itself for easy retrieval
                        group.setAttribute('data-original-x', x);
                        group.setAttribute('data-original-y', y);
                        group.setAttribute('data-original-rotation', rotation);
                        group.setAttribute('data-id', shelfId); // Also set data-id on group for easier selection

                        // Apply initial transform (translate + rotate) to the group using CSS properties for transitions
                        group.style.transformOrigin = `${w / 2}px ${h / 2}px`;
                        group.style.transform = `translate(${x}px, ${y}px) rotate(${rotation}deg)`;

                        // Configure the rectangle
                        rect.setAttribute('x', 0); // Relative to group's origin
                        rect.setAttribute('y', 0); // Relative to group's origin
                        rect.setAttribute('width', w);
                        rect.setAttribute('height', h);
                        rect.setAttribute('class', 'shelf-rect');
                        rect.setAttribute('data-id', shelfId);

                        let textWidth, textHeight; // For foreignObject positioning
                        if (rotation === 90 || rotation === 270) {
                            textWidth = h;
                            textHeight = w;
                        } else {
                            textWidth = w;
                            textHeight = h;
                        }
                        foreignObject.setAttribute('x', w / 2 - textWidth / 2); // Center horizontally relative to group
                        foreignObject.setAttribute('y', h / 2 - textHeight / 2); // Center vertically relative to group
                        foreignObject.setAttribute('width', textWidth);
                        foreignObject.setAttribute('height', textHeight);
                        foreignObject.style.pointerEvents = 'none';

                        div.setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
                        div.className = 'shelf-name-display';
                        div.textContent = shelfName || 'Untitled';
                        div.style.display = (textWidth < 40 || textHeight < 20) ? 'none' : 'flex';
                        foreignObject.appendChild(div);
                        
                        // Configure the "place here" text display (relative to group's origin)
                        placeTextForeignObject.setAttribute('x', w / 2 - textWidth / 2);
                        placeTextForeignObject.setAttribute('y', h / 2 - textHeight / 2);
                        placeTextForeignObject.setAttribute('width', textWidth);
                        placeTextForeignObject.setAttribute('height', textHeight);
                        placeTextForeignObject.style.pointerEvents = 'none';
                        placeTextForeignObject.classList.add('place-text-display');

                        placeTextDiv.setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
                        placeTextDiv.className = 'place-text-content';
                        placeTextDiv.textContent = 'Click to Place';
                        placeTextForeignObject.appendChild(placeTextDiv);

                        // Append elements to the group
                        group.appendChild(rect);
                        group.appendChild(foreignObject);
                        group.appendChild(placeTextForeignObject);

                        // Add event listeners to the rectangle
                        rect.addEventListener('contextmenu', (e) => {
                            if (!this.editMode) return;
                            e.preventDefault();
                            this.rotateShelf(e);
                        });

                        rect.addEventListener('mouseenter', (e) => {
                            if (this.editMode && !this.draggingShelfId && !this.isMultiDragging) {
                                this.selectedShelfId = shelfId;
                                this.updateSelectionVisuals();
                            }
                            this.tooltip.textContent = `Size: ${Math.round(w)}x${Math.round(h)}`;
                            const shelfRect = e.target.getBoundingClientRect();
                            this.tooltip.style.left = (shelfRect.left + window.scrollX + shelfRect.width / 2) + 'px';
                            this.tooltip.style.top = (shelfRect.top + window.scrollY) + 'px';
                            this.tooltip.style.opacity = 1;
                        });

                        rect.addEventListener('mouseleave', (e) => {
                            if (this.editMode && this.selectedShelfId === shelfId && !this.draggingShelfId && !this.isMultiDragging) {
                                this.selectedShelfId = null;
                                this.updateSelectionVisuals();
                            }
                            this.tooltip.style.opacity = 0;
                        });

                        this.shelvesGroup.appendChild(group);
                    }
                },

                updateSelectionVisuals() {
                    this.shelvesGroup.querySelectorAll('g[data-id]').forEach(groupEl => { // Select group elements
                        const id = groupEl.getAttribute('data-id');
                        const rectEl = groupEl.querySelector('.shelf-rect'); // Get the rect inside the group
                        if (rectEl) {
                            // An item is selected if it's in the multi-select array OR it's the single hover-selected item
                            if (this.selectedShelfIds.includes(id) || id === this.selectedShelfId) {
                                rectEl.classList.add('selected');
                            } else {
                                rectEl.classList.remove('selected');
                            }
                        }
                    });
                },

                clearAllShelves() {
                    this.shelvesGroup.innerHTML = '';
                }
            }
        }
    </script>

    <script>
        // Placed here to be globally accessible
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
            } else { // error
                toast.classList.add('bg-red-600');
                iconContainer.classList.add('bg-red-100');
                iconContainer.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`;
            }

            toast.classList.remove('hidden');
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
            toast.style.transition = 'all 0.5s ease';

            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
        }
    </script>

    <?php include '../includes/layout_footer.php'; ?>
</body>
</html>