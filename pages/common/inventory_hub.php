<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control [cite: 2025-12-06]
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$inventory = $db->getInventoryShop() ?? [];
$categories = $db->getCategories() ?? []; // Ensure this is always an array
$role = $_SESSION['user_role'] ?? 'Student';
$page_title = ($role === 'Student') ? "Apparatus Shop" : "Inventory Hub";
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
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
        }
        [x-cloak] { display: none !important; }
    </style>
    <script>
    // Placed at the top to be accessible by all other functions
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast-container');
        if (!toast) return;

        const iconContainer = document.getElementById('toast-icon-container');
        const messageContainer = document.getElementById('toast-message');

        // Reset classes
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

    function inventoryHubApp(inventoryData, categoryData) {
        return {
            search: '',
            showFilters: false,
            assetType: 'all',
            selectedCategory: 'all',
            items: inventoryData,
            allCategories: categoryData,
            currentPage: 1,
            itemsPerPage: 12,
            get totalPages() { return Math.ceil(this.filteredItems.length / this.itemsPerPage) },

            // AI Search State
            isAiSearching: false,
            aiSearchResults: [],
            aiMode: false,
            aiQuery: '',

            selectedItem: null,
            isEditing: false,
            originalItem: null,
            draggingItemId: null,
            dragCounter: 0,
            qtyToAdd: 1,
            selectedVariantId: null,

            init() {
                this.$watch('assetType', () => {
                    this.selectedCategory = 'all';
                    this.currentPage = 1;
                });
                this.$watch('selectedCategory', () => { this.currentPage = 1; });
                this.$watch('search', () => {
                    this.currentPage = 1;
                });
                this.$watch('selectedItem', (newItem) => {
                    // When a new item is selected, reset quantities and variant selection
                    this.qtyToAdd = 1;
                    if (newItem && newItem.is_scalable == 1 && newItem.variants && newItem.variants.length > 0) {
                        // Find the first available variant to select by default
                        const firstAvailable = newItem.variants.find(v => v.Variant_Available_Qty > 0);
                        this.selectedVariantId = firstAvailable ? firstAvailable.VariantID : newItem.variants[0].VariantID;
                    } else {
                        this.selectedVariantId = null;
                    }
                });
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

            get paginatedItems() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                // When totalPages changes, currentPage might be out of bounds.
                if (this.currentPage > this.totalPages && this.totalPages > 0) {
                    this.currentPage = this.totalPages;
                }
                return this.filteredItems.slice(start, end);
            },

            get selectedVariant() {
                if (this.selectedItem && this.selectedVariantId) {
                    return this.selectedItem.variants.find(v => v.VariantID == this.selectedVariantId);
                }
                return null;
            },

            get filteredCategories() {
                if (!Array.isArray(this.allCategories)) return [];
                if (this.assetType === 'all') return this.allCategories;
                const isConsumable = this.assetType === 'consumable' ? 1 : 0;
                return this.allCategories.filter(cat => cat.is_consumable == isConsumable);
            },

            get filteredItems() {
                if (!Array.isArray(this.items)) return [];

                // The logic is now simpler. It just filters based on the standard controls.
                // The AI search will manipulate the `search` property to filter the grid.
                return this.items.filter(item => {
                    const categoryMatch = (this.selectedCategory === 'all' || this.selectedCategory == item.CategoryID);
                    const searchMatch = (this.search === '' || item.Item_Name.toLowerCase().includes(this.search.toLowerCase()));
                    const assetMatch = (this.assetType === 'all' || (item.Asset_Type && item.Asset_Type.toLowerCase() === this.assetType));
                    return categoryMatch && searchMatch && assetMatch;
                });
            },

            addToCart(item, qty, variant = null) {
                if (!item || !qty || qty < 1) return;

                let cart = JSON.parse(localStorage.getItem('labflow_cart') || '[]');
                
                const itemId = item.ItemID;
                const variantId = variant ? variant.VariantID : null;
                const itemName = item.Item_Name;
                const maxQty = variant ? variant.Variant_Available_Qty : item.Available_Qty;
                const size = variant ? variant.Size_Value : null; // Size is only for variants
                const unit = variant ? variant.Unit : (item.is_consumable == 1 ? item.Unit : null); // Unit for variants or consumables

                const existingItem = cart.find(i => i.itemId === itemId && i.variantId === variantId);

                if (existingItem) {
                    existingItem.qty = Math.min(parseInt(existingItem.qty) + parseInt(qty), maxQty);
                } else {
                    cart.push({ 
                        id: `${itemId}-${variantId || '0'}`, // Composite ID for Alpine's :key
                        itemId: itemId,
                        variantId: variantId,
                        name: itemName, 
                        qty: parseInt(qty), 
                        maxQty: parseInt(maxQty),
                        size: size,
                        unit: unit
                    });
                }
                localStorage.setItem('labflow_cart', JSON.stringify(cart));
                showToast(`${itemName} ${variant ? '('+size+(unit || '')+')' : ''} added to cart!`);
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { cart: cart } }));
            },

            async performAiSearch() {
                if (!this.search.trim()) {
                    showToast('Please enter a description to use AI search.', 'error');
                    return;
                }
                this.isAiSearching = true;
                this.aiMode = true;
                this.aiQuery = this.search;
                this.aiSearchResults = [];

                try {
                    const response = await fetch('../../dbRelated/ai_inventory_search.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ query: this.aiQuery })
                    });

                    if (!response.ok) { throw new Error('Network response was not ok.'); }

                    const results = await response.json();
                    if (results.error) { throw new Error(results.error); }

                    this.aiSearchResults = results;
                    this.search = ''; // Clear search bar to show all items in the grid below
                } catch (error) {
                    console.error('AI Search Error:', error);
                    showToast('AI search failed. ' + error.message, 'error');
                    this.aiSearchResults = [];
                } finally {
                    this.isAiSearching = false;
                }
            },

            clearAiSearch() {
                this.aiMode = false;
                this.aiSearchResults = [];
                this.aiQuery = '';
                this.search = ''; // Also clear the search term
                this.currentPage = 1;
            },

            searchForItem(itemName) {
                // Set the search term to filter the grid, but keep the AI panel open.
                this.search = itemName;
                this.currentPage = 1;
            },
        }
    }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 flex gap-8 animate-reveal"
                  x-data='inventoryHubApp(
                      <?= json_encode($inventory, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                      <?= json_encode($categories, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>
                  )'
                  x-init="init()">
                <div class="flex-1">
                    <header class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-800"><?= $page_title ?></h2>
                        <p class="text-sm text-gray-500 mt-1">Browse and manage laboratory apparatus and equipment.</p>
                    </header>

                    <div class="mb-8 space-y-4">
                        <!-- Top row: Search bar and Filter button -->
                        <div class="flex items-center gap-4">
                            <!-- Long Search Bar -->
                            <div class="relative w-full flex-1 group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400 group-focus-within:text-orange-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <input type="text" x-model.debounce.300ms="search" placeholder="Search by name, or describe and click AI Search..." class="w-full pl-12 pr-36 py-3.5 bg-white border-2 border-gray-100 rounded-2xl outline-none focus:border-orange-500/50 focus:ring-4 focus:ring-orange-500/10 hover:border-gray-200 transition-all duration-300 font-medium text-sm text-gray-800 placeholder:text-gray-400 shadow-sm">
                                
                                <!-- AI Search Button (Inside) -->
                                <div class="absolute inset-y-0 right-0 flex items-center pr-2">
                                    <button @click="performAiSearch()" 
                                            class="flex items-center gap-2 px-3 py-2 bg-orange-500 text-white rounded-xl hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20"
                                            title="Use AI to search by description">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                                        <span class="text-xs font-bold">AI Search</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Filter Toggle Button -->
                            <button @click="showFilters = !showFilters" 
                                    class="flex-shrink-0 p-3.5 bg-white border-2 border-gray-100 rounded-2xl text-gray-500 hover:bg-orange-50 hover:text-orange-600 hover:border-orange-200 transition-all shadow-sm"
                                    title="Toggle Filters">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                            </button>
                        </div>

                        <!-- Collapsible Filter Section -->
                        <div x-show="showFilters" 
                             x-transition:enter="transition ease-out duration-400" 
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1" 
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0" 
                             x-transition:leave="transition ease-in duration-300" 
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0" 
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1" class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm grid md:grid-cols-2 gap-6 origin-top" x-cloak>
                            <!-- Asset Type Filter -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Asset Type</label>
                                <div class="flex items-center gap-2 bg-gray-100 p-2 rounded-2xl border border-gray-200 shadow-sm w-full">
                                    <button @click="assetType = 'all'" :class="{ 'bg-white text-orange-600 shadow-md': assetType === 'all', 'text-gray-500 hover:bg-gray-50': assetType !== 'all' }" class="flex-1 px-5 py-2 text-xs font-bold rounded-xl transition-all duration-300">All</button>
                                    <button @click="assetType = 'consumable'" :class="{ 'bg-white text-orange-600 shadow-md': assetType === 'consumable', 'text-gray-500 hover:bg-gray-50': assetType !== 'consumable' }" class="flex-1 px-5 py-2 text-xs font-bold rounded-xl transition-all duration-300 whitespace-nowrap">Consumable</button>
                                    <button @click="assetType = 'non-consumable'" :class="{ 'bg-white text-orange-600 shadow-md': assetType === 'non-consumable', 'text-gray-500 hover:bg-gray-50': assetType !== 'non-consumable' }" class="flex-1 px-5 py-2 text-xs font-bold rounded-xl transition-all duration-300 whitespace-nowrap">Non-Consumable</button>
                                </div>
                            </div>

                            <!-- Category Filter -->
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Category</label>
                                <div class="relative w-full">
                                    <select x-model="selectedCategory" class="w-full pl-4 pr-10 py-3 bg-white border border-gray-200 rounded-2xl outline-none focus:ring-2 focus:ring-orange-500 font-medium text-sm text-gray-700 shadow-sm appearance-none cursor-pointer">
                                        <option value="all">All Categories</option>
                                        <?php if (empty($categories)): ?>
                                            <option disabled>No categories were found.</option>
                                        <?php else: ?>
                                            <template x-for="cat in filteredCategories" :key="cat.CategoryID">
                                                <option :value="cat.CategoryID" x-text="cat.Category_Name"></option>
                                            </template>
                                        <?php endif; ?>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Search Results Section -->
                    <div x-show="aiMode" x-cloak x-transition class="mb-8 bg-white p-6 rounded-2xl border border-slate-200 shadow-lg">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-sm font-bold text-orange-800 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                                AI Suggestions
                            </h3>
                            <button @click="clearAiSearch()" class="text-xs font-bold text-orange-500 hover:text-red-500 transition-colors">Close</button>
                        </div>

                        <div x-show="isAiSearching" class="text-center py-2"><p class="text-orange-600 font-bold animate-pulse">Thinking...</p></div>

                        <div x-show="!isAiSearching">
                            <p class="text-xs text-orange-700" x-show="aiSearchResults.length > 0 && Array.isArray(aiSearchResults)">Found <strong x-text="aiSearchResults.length"></strong> potential matches for: "<span class="italic font-bold" x-text="aiQuery"></span>"</p>
                            <p class="text-xs text-orange-700" x-show="!Array.isArray(aiSearchResults) || aiSearchResults.length === 0">No items matched your description.</p>
                            <div class="mt-4 space-y-2 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                                <template x-for="result in aiSearchResults" :key="result.item_name">
                                    <a href="#" @click.prevent="searchForItem(result.item_name)" class="block p-3 rounded-lg hover:bg-orange-50 transition-colors group">
                                        <h4 class="font-bold text-sm text-slate-800 group-hover:text-orange-600" x-text="result.item_name"></h4>
                                        <p class="text-xs text-slate-500 italic mt-1" x-text="result.reason"></p>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Grid -->
                    <div class="shop-grid">
                        <template x-if="items.length === 0">
                            <div class="col-span-full bg-white rounded-2xl p-12 text-center border-2 border-dashed border-gray-200">
                                <p class="font-bold text-gray-600">No inventory items found.</p>
                                <p class="text-sm text-gray-400 mt-2">You can add items from the 'Register Apparatus' page if you are an Admin.</p>
                            </div>
                        </template>
                        <template x-if="items.length > 0">
                            <template x-for="item in paginatedItems" :key="item.ItemID">
                                <div @click="isEditing = false; selectedItem = item"
                                     draggable="true"
                                     @dragstart="draggingItemId = item.ItemID; event.dataTransfer.setData('text/plain', JSON.stringify(item))"
                                     @dragend="draggingItemId = null"
                                     class="bg-white p-6 rounded-2xl border flex flex-col group cursor-grab active:cursor-grabbing hover:border-orange-500 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 select-none"
                                     :class="{
                                        'border-orange-500 ring-2 ring-orange-200': selectedItem && selectedItem.ItemID === item.ItemID,
                                        'border-gray-200': !selectedItem || selectedItem.ItemID !== item.ItemID,
                                        'opacity-50 scale-95': draggingItemId === item.ItemID
                                     }"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 transform scale-95"
                                     x-transition:enter-end="opacity-100 transform scale-100"
                                     x-cloak>
                                    
                                    <div class="relative">
                                        <img :src="'../../assets/img/items/' + item.ItemID + '.png'" 
                                             class="h-32 object-contain mx-auto mb-6 transition-transform duration-500 group-hover:scale-110" 
                                             onerror="this.src='../../assets/img/placeholder.png'">
                                        <span class="absolute top-0 right-0 bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-0.5 rounded-full group-hover:bg-orange-100 group-hover:text-orange-600 transition-colors" x-text="item.Category_Name"></span>
                                    </div>
                                    
                                    <h3 class="font-bold text-gray-800 text-center text-base leading-tight mb-4 group-hover:text-orange-600 transition-colors" :title="item.Item_Name" x-text="item.Item_Name"></h3>
                                    
                                    <div class="mt-auto pt-4 border-t border-gray-100 flex justify-around items-center text-center">
                                        <div>
                                            <p class="text-2xl font-black text-orange-500">
                                                <span x-text="item.Available_Qty"></span>
                                                <span x-show="item.is_consumable == 1 && item.Unit" class="text-lg align-baseline" x-text="item.Unit"></span>
                                            </p>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Available</p>
                                        </div>
                                        <div class="h-10 w-px bg-gray-200"></div>
                                        <div>
                                            <p class="text-2xl font-black text-gray-600">
                                                <span x-text="item.Total_Qty"></span>
                                                <span x-show="item.is_consumable == 1 && item.Unit" class="text-lg align-baseline" x-text="item.Unit"></span>
                                            </p>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total</p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                    
                    <!-- Empty state for filters -->
                    <div x-show="items.length > 0 && filteredItems.length === 0" class="col-span-full bg-white rounded-2xl p-12 text-center border-2 border-dashed border-gray-200 mt-8"
                         x-transition x-cloak>
                         <p class="font-bold text-gray-600">No items match your filter.</p>
                         <p class="text-sm text-gray-400 mt-2">Try selecting a different category or clearing your search.</p>
                    </div>

                    <!-- Pagination Controls -->
                    <div x-show="totalPages > 1" class="mt-8 flex justify-between items-center bg-white p-4 rounded-2xl border border-gray-200 shadow-sm" x-cloak>
                        <div class="flex items-center gap-2">
                            <label for="itemsPerPage" class="text-xs font-bold text-gray-500">Show:</label>
                            <select x-model.number="itemsPerPage" @change="currentPage = 1" id="itemsPerPage" class="bg-gray-50 border-gray-200 rounded-md text-xs font-bold p-1 focus:ring-orange-500 focus:border-orange-500">
                                <option value="12">12</option>
                                <option value="24">24</option>
                                <option value="36">36</option>
                                <option value="48">48</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            <div class="flex items-center gap-1">
                                <template x-for="(page, index) in pages" :key="index">
                                    <div>
                                        <span x-show="page === '...'" class="px-2 py-2 text-xs font-bold text-gray-400">&hellip;</span>
                                        <button x-show="page !== '...'"
                                                @click="currentPage = page"
                                                x-text="page"
                                                class="px-3 py-2 rounded-lg text-xs font-bold transition-colors"
                                                :class="{
                                                    'bg-orange-500 text-white shadow-md': currentPage === page,
                                                    'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50': currentPage !== page
                                                }"></button>
                                    </div>
                                </template>
                            </div>
                            <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <aside 
                    class="w-80 flex flex-col rounded-2xl sticky top-28 h-[calc(100vh-9rem)]"
                    @dragover.prevent
                    @dragenter="dragCounter++"
                    @dragleave="dragCounter--"
                    @drop.prevent="
                        selectedItem = JSON.parse(event.dataTransfer.getData('text/plain'));
                        isEditing = false;
                        dragCounter = 0;
                    "
                >
                    <!-- Default empty state -->
                    <div x-show="!selectedItem" x-transition class="bg-white h-full flex flex-col items-center justify-center text-center p-8 rounded-2xl border-2 border-dashed border-gray-200 transition-all duration-300" 
                         :class="{ 'border-orange-400 bg-orange-50': dragCounter > 0, 'blur-md opacity-50': draggingItemId !== null }" x-cloak>
                        <div class="w-16 h-16 bg-gray-100 text-orange-500 rounded-full flex items-center justify-center mb-4 transition-transform" :class="{ 'scale-110': dragCounter > 0 }">
                            <svg x-show="dragCounter === 0" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            <svg x-show="dragCounter > 0" x-cloak class="w-8 h-8 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4 4v12"></path></svg>
                        </div>
                        <h3 class="font-bold text-gray-600" x-text="dragCounter > 0 ? 'Drop to View' : 'Select an Item'"></h3>
                        <p class="text-sm text-gray-400 mt-2" x-text="dragCounter > 0 ? 'Release the item to see its details.' : 'Click or drag an apparatus here to view its details.'"></p>
                    </div>

                    <!-- Details view -->
                    <div x-show="selectedItem" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" class="bg-white h-full flex flex-col rounded-2xl border-t-4 border-orange-500 shadow-lg transition-all duration-300" 
                         :class="{ 'blur-md opacity-50': draggingItemId !== null }" x-cloak>
                        <form method="POST" action="../../dbRelated/update_inventory.php" class="flex flex-col h-full p-6">
                            <input type="hidden" name="ItemID" :value="selectedItem.ItemID">

                            <?php if ($role === 'Admin'): ?>
                                <div class="absolute top-4 right-4 flex gap-2 z-10">
                                    <button x-show="!isEditing" 
                                            @click.prevent="if(confirm('Are you sure you want to permanently delete this item? This cannot be undone.')) { $refs.deleteForm.submit() }"
                                            type="button" 
                                            class="px-4 py-2 bg-red-600 text-white text-xs font-bold rounded-lg hover:bg-red-700 transition-colors shadow-md">Delete</button>
                                    <button x-show="!isEditing" 
                                            @click.prevent="isEditing = true; originalItem = JSON.parse(JSON.stringify(selectedItem))"
                                            type="button"
                                            class="px-4 py-2 bg-gray-800 text-white text-xs font-bold rounded-lg hover:bg-gray-900 transition-colors shadow-md">Edit</button>
                                    <button x-show="isEditing" @click.prevent="isEditing = false; selectedItem = originalItem" type="button" class="px-4 py-2 bg-gray-200 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-300 transition-colors">Cancel</button>
                                    <button x-show="isEditing" type="submit" name="update_item" class="px-4 py-2 bg-orange-500 text-white text-xs font-bold rounded-lg hover:bg-orange-600 transition-colors shadow-md">Save</button>
                                </div>
                            <?php endif; ?>

                            <div class="relative mb-4 text-center">
                                <img :src="'../../assets/img/items/' + selectedItem.ItemID + '.png'" class="h-32 object-contain mx-auto" onerror="this.src='../../assets/img/placeholder.png'">
                            </div>
                            
                            <div class="text-center mb-4">
                                <h3 x-show="!isEditing" class="text-2xl font-black text-gray-800" x-text="selectedItem.Item_Name"></h3>
                                <input x-show="isEditing" type="text" name="Item_Name" x-model="selectedItem.Item_Name" class="w-full text-2xl font-black text-gray-800 text-center bg-gray-50 border border-gray-200 rounded-lg p-2 outline-none focus:ring-2 focus:ring-orange-400">
                                <p class="text-xs font-bold text-orange-500 uppercase tracking-wider mt-2" x-text="selectedItem.Category_Name"></p>
                            </div>

                            <div class="text-sm text-gray-600 leading-relaxed mb-4 flex-1 overflow-y-auto pr-2 custom-scrollbar">
                                <p class="font-bold text-gray-800 mb-2">Description:</p>
                                <p x-show="!isEditing" x-text="selectedItem.Description || 'No description available.'" class="whitespace-pre-wrap"></p>
                                <textarea x-show="isEditing" name="Description" x-model="selectedItem.Description" rows="3" class="w-full text-sm bg-gray-50 border border-gray-200 rounded-lg p-2 outline-none focus:ring-2 focus:ring-orange-400"></textarea>
                            </div>

                            <div class="mt-auto pt-4 border-t border-gray-100 space-y-3" x-show="selectedItem">
                                <!-- Location (common for non-consumables) -->
                                <div x-show="selectedItem.Asset_Type === 'non-consumable'" class="flex justify-between items-center">
                                    <p class="text-xs font-bold text-gray-400 uppercase">Location</p>
                                    <p x-show="!isEditing" class="text-sm font-bold text-gray-800 text-right" x-text="selectedItem.Location || 'N/A'"></p>
                                    <input x-show="isEditing" type="text" name="Location" x-model="selectedItem.Location" class="w-1/2 text-sm font-bold text-gray-800 text-right bg-gray-50 border border-gray-200 rounded-lg p-1 outline-none focus:ring-2 focus:ring-orange-400">
                                </div>

                                <!-- For NON-SCALABLE items (Display and Edit) -->
                                <template x-if="selectedItem.is_scalable != 1 || (selectedItem.is_scalable == 1 && !isEditing)">
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center">
                                            <p class="text-xs font-bold text-gray-400 uppercase">Available</p>
                                            <p class="text-sm font-bold text-green-600">
                                                <span x-text="selectedItem.Available_Qty"></span>
                                                <span x-show="selectedItem.is_consumable == 1 && selectedItem.Unit" x-text="selectedItem.Unit" class="text-xs ml-1"></span>
                                            </p>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <p class="text-xs font-bold text-gray-400 uppercase">Total Stock</p>
                                            <p x-show="!isEditing" class="text-sm font-bold text-gray-800">
                                                <span x-text="selectedItem.Total_Qty"></span>
                                                <span x-show="selectedItem.is_consumable == 1 && selectedItem.Unit" x-text="selectedItem.Unit" class="text-xs ml-1"></span>
                                            </p>
                                            <input x-show="isEditing && selectedItem.is_scalable != 1" type="number" name="Total_Qty" x-model="selectedItem.Total_Qty" class="w-1/4 text-sm font-bold text-gray-800 text-right bg-gray-50 border border-gray-200 rounded-lg p-1 outline-none focus:ring-2 focus:ring-orange-400">
                                        </div>
                                    </div>
                                </template>

                                <!-- EDITING UI for SCALABLE items -->
                                <template x-if="isEditing && selectedItem.is_scalable == 1">
                                    <div class="space-y-2">
                                        <p class="text-xs font-bold text-gray-400 uppercase text-center">Manage Sizes</p>
                                        <div class="max-h-48 overflow-y-auto space-y-2 pr-2 custom-scrollbar">
                                            <template x-for="(variant, index) in selectedItem.variants" :key="variant.VariantID || `new-${index}`">
                                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                                    <div class="grid grid-cols-10 gap-2 items-center">
                                                        <input type="text" :name="`variants[${variant.VariantID || `new-${index}`}][size]`" x-model="variant.Size_Value" placeholder="e.g. 50" class="col-span-3 w-full text-sm font-bold bg-white border-gray-200 rounded-lg p-1 outline-none focus:ring-2 focus:ring-orange-400">
                                                        <input type="text" :name="`variants[${variant.VariantID || `new-${index}`}][unit]`" x-model="variant.Unit" placeholder="e.g. ml" class="col-span-3 w-full text-sm font-bold bg-white border-gray-200 rounded-lg p-1 outline-none focus:ring-2 focus:ring-orange-400">
                                                        <input type="number" :name="`variants[${variant.VariantID || `new-${index}`}][qty]`" x-model="variant.Variant_Total_Qty" placeholder="Qty" min="0" class="col-span-3 w-full text-sm font-bold bg-white border-gray-200 rounded-lg p-1 outline-none focus:ring-2 focus:ring-orange-400">
                                                        <button @click.prevent="selectedItem.variants.splice(index, 1)" type="button" title="Remove this size" class="col-span-1 text-gray-400 hover:text-red-500 transition-colors">
                                                            <svg class="w-4 h-4 mx-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        <button @click.prevent="selectedItem.variants.push({ VariantID: '', Size_Value: '', Unit: selectedItem.variants[0]?.Unit || '', Variant_Total_Qty: 0, Variant_Available_Qty: 0 })" type="button" class="w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-bold py-2 rounded-lg transition-colors">
                                            + Add New Size
                                        </button>
                                    </div>
                                </template>

                                <!-- DISPLAY/SELECTION UI for SCALABLE items -->
                                <template x-if="!isEditing && selectedItem.is_scalable == 1">
                                    <div class="space-y-4">
                                        <div>
                                            <p class="text-xs font-bold text-gray-400 uppercase mb-2">Available Sizes</p>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="variant in selectedItem.variants" :key="variant.VariantID">
                                                    <button @click.prevent="selectedVariantId = variant.VariantID"
                                                            type="button"
                                                            :class="{
                                                                'bg-orange-500 text-white ring-2 ring-orange-300 shadow-md': selectedVariantId == variant.VariantID,
                                                                'bg-gray-100 text-gray-700 hover:bg-gray-200': selectedVariantId != variant.VariantID,
                                                                'opacity-50 cursor-not-allowed': variant.Variant_Available_Qty <= 0 && selectedVariantId != variant.VariantID
                                                            }"
                                                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all"
                                                            :disabled="variant.Variant_Available_Qty <= 0">
                                                        <span x-text="`${variant.Size_Value}${variant.Unit || ''}`"></span>
                                                    </button>
                                                </template>
                                            </div>
                                            <div x-show="!selectedItem.variants || selectedItem.variants.length === 0" class="text-center text-xs text-gray-400 py-4" x-cloak>
                                                No sizes defined for this item.
                                            </div>
                                        </div>

                                        <!-- Stock info for selected variant -->
                                        <template x-if="selectedVariant">
                                            <div class="space-y-3 pt-4 border-t border-gray-100">
                                                <div class="flex justify-between items-center">
                                                    <p class="text-xs font-bold text-gray-400 uppercase">Available</p>
                                                    <p class="text-sm font-bold text-green-600" x-text="selectedVariant.Variant_Available_Qty"></p>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <p class="text-xs font-bold text-gray-400 uppercase">Total Stock</p>
                                                    <p class="text-sm font-bold text-gray-800" x-text="selectedVariant.Variant_Total_Qty"></p>
                                                </div>
                                            </div>
                                        </template>
                                        </div>
                                </template>
                            </div>

                            <!-- Add to Cart Section (for non-scalable items OR selected variants) -->
                            <div x-show="!isEditing && (selectedItem.is_scalable != 1 || selectedVariant)" class="pt-4 mt-4 border-t border-gray-100">
                                <div class="flex items-center gap-4 mb-4">
                                    <label for="quantity" class="text-xs font-bold text-gray-400 uppercase flex-shrink-0">Quantity</label>
                                    <input type="number" id="quantity" x-model.number="qtyToAdd" min="1" 
                                           :max="selectedItem.is_scalable == 1 ? (selectedVariant ? selectedVariant.Variant_Available_Qty : 0) : selectedItem.Available_Qty" 
                                           class="w-full text-center font-bold rounded-lg border-gray-200 focus:ring-orange-500 focus:border-orange-500" 
                                           :disabled="(selectedItem.is_scalable == 1 ? (selectedVariant ? selectedVariant.Variant_Available_Qty : 0) : selectedItem.Available_Qty) <= 0">
                                </div>
                                <button @click.prevent="addToCart(selectedItem, qtyToAdd, selectedVariant)" 
                                        :disabled="(selectedItem.is_scalable == 1 ? (selectedVariant ? selectedVariant.Variant_Available_Qty : 0) : selectedItem.Available_Qty) <= 0 || !qtyToAdd || qtyToAdd < 1" 
                                        class="w-full bg-orange-500 text-white py-4 rounded-xl font-bold uppercase text-xs tracking-wider hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20 disabled:bg-gray-300 disabled:shadow-none disabled:cursor-not-allowed">
                                    <span x-show="(selectedItem.is_scalable == 1 ? (selectedVariant ? selectedVariant.Variant_Available_Qty : 0) : selectedItem.Available_Qty) > 0">Add to Cart</span>
                                    <span x-show="(selectedItem.is_scalable == 1 ? (selectedVariant ? selectedVariant.Variant_Available_Qty : 0) : selectedItem.Available_Qty) <= 0" x-cloak>Out of Stock</span>
                                </button>
                            </div>
                        </form>

                        <!-- Hidden Delete Form -->
                        <form x-ref="deleteForm" method="POST" action="../../dbRelated/delete_inventory.php" class="hidden">
                            <input type="hidden" name="ItemID" :value="selectedItem ? selectedItem.ItemID : ''">
                            <input type="hidden" name="delete_item" value="1">
                        </form>
                    </div>

                    <!-- Drop Indicator Overlay -->
                    <div x-show="draggingItemId !== null" x-cloak x-transition
                         class="absolute inset-0 flex flex-col items-center justify-center bg-orange-50/50 border-4 border-dashed border-orange-400 rounded-2xl pointer-events-none">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg">
                            <svg class="w-10 h-10 text-orange-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4 4v12"></path></svg>
                        </div>
                        <p class="mt-4 font-bold text-orange-600">Drop to View</p>
                    </div>
                </aside>
            </main>
        </div>
    </div>

    <?php
    $toast_message = null;
    $toast_type = 'success'; // Default type

    if (isset($_SESSION['toast_message'])) {
        $toast_message = $_SESSION['toast_message']['text'];
        $toast_type = $_SESSION['toast_message']['type'];
        unset($_SESSION['toast_message']);
    }
    ?>

    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl">
            <!-- Icon will be inserted by JS -->
        </div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <script>
    <?php if ($toast_message): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('<?php echo addslashes($toast_message); ?>', '<?php echo $toast_type; ?>');
    });
    <?php endif; ?>
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>