<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control [cite: 2025-12-06]
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();

// Handle Apparatus Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
    // Phase 1 value
    $isConsumable = $_POST['item_type'] === 'consumable' ? 1 : 0;

    // Phase 3 values
    $isScalable = isset($_POST['is_scalable']) && !$isConsumable ? 1 : 0;
    $itemId = null;
    $qty = $_POST['qty'] ?? 0;
    $location = $_POST['location'] ?? null;
    $unit = $_POST['unit'] ?? null;

    if ($isScalable) { // This branch is only for Non-Consumable, Scalable items
        // For scalable items, create the parent item. Qty is not at this level, but location is.
        $itemId = $db->addItem($_POST['cat_id'], $_POST['item_name'], $_POST['description'], $isConsumable, $isScalable, 0, $location, null);

        if ($itemId && isset($_POST['variants'])) {
            foreach ($_POST['variants'] as $variant) {
                if (!empty($variant['size']) && !empty($variant['unit']) && isset($variant['qty']) && $variant['qty'] !== '') {
                    $db->addVariant($itemId, $variant['size'], $variant['unit'], $variant['qty']);
                }
            }
            $db->updateInventoryTotalFromVariants($itemId);
        }
    } else { // This handles both Consumables and Non-Scalable Non-Consumables
        $itemId = $db->addItem(
            $_POST['cat_id'],
            $_POST['item_name'],
            $_POST['description'],
            $isConsumable,
            $isScalable,
            $qty,
            $isConsumable ? null : $location, // Location only for non-consumables
            $isConsumable ? $unit : null      // Unit only for consumables
        );
    }

    if ($itemId && isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($_FILES["item_image"]["tmp_name"], "../../assets/img/items/" . $itemId . ".png");
    }

    if ($itemId) {
        $_SESSION['toast_message'] = ['text' => 'Item registered successfully!', 'type' => 'success'];
    } else {
        $_SESSION['toast_message'] = ['text' => 'Failed to register item.', 'type' => 'error'];
    }
    header("Location: manage_inventory.php");
    exit();
}

$categories = $db->getCategories();
$page_title = "Item Registration";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Master | SNHS</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
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

        function inventoryForm() {
            return {
                step: 1,
                itemType: null, 
                isScalable: false,
                showImportModal: false,
                
                // Custom Dropdown State
                isCategoryOpen: false,
                categorySearch: '',
                allCategories: [],
                selectedCategoryName: 'Select a type first',
                selectedCategoryId: '',
                itemName: '',
                itemDescription: '',
                itemQty: '',
                itemUnit: '',
                itemLocation: '',
                itemVariants: [],
                itemImagePreviewUrl: '',

                get filteredCategories() {
                    if (!this.categorySearch) return this.allCategories;
                    return this.allCategories.filter(
                        cat => cat.Category_Name.toLowerCase().includes(this.categorySearch.toLowerCase())
                    );
                },
                
                selectType(type) {
                    this.itemType = type;
                    this.isScalable = false;
                    if (document.getElementById('is_scalable_checkbox')) {
                        document.getElementById('is_scalable_checkbox').checked = false;
                    }
                    this.itemQty = '';
                    this.itemUnit = '';
                    this.itemLocation = '';
                    this.itemVariants = [];
                    this.updateRequiredFields();
                    this.fetchCategories(type);
                },
                
                nextStep() {
                    if (this.step === 1) {
                        if (!this.itemType) {
                            showToast('Please select an item nature.', 'error');
                            return;
                        }
                        if (!this.selectedCategoryId) {
                            showToast('Please select a category.', 'error');
                            return;
                        }
                        const itemNameInput = this.$root.querySelector('[name="item_name"]');
                        if (!itemNameInput.value.trim()) {
                            showToast('Please enter an item name.', 'error');
                            itemNameInput.focus();
                            return;
                        }
                    } else if (this.step === 2) {
                        if (this.itemType === 'consumable' && (!this.itemQty || this.itemQty <= 0)) {
                            showToast('Please enter a valid quantity for the consumable item.', 'error');
                            this.$root.querySelector('#consumable_qty').focus();
                            return;
                        }
                        if (this.itemType === 'non-consumable' && !this.isScalable && (!this.itemQty || this.itemQty <= 0)) {
                            showToast('Please enter a valid quantity for the non-scalable item.', 'error');
                            this.$root.querySelector('#non_scalable_qty').focus();
                            return;
                        }
                        if (this.itemType === 'non-consumable' && this.isScalable) {
                            if (this.itemVariants.length === 0) {
                                showToast('Please add at least one variant for scalable items.', 'error');
                                return;
                            }
                            let totalVariantQty = 0;
                            for (const variant of this.itemVariants) {
                                if (!variant.size.trim() || !variant.unit.trim() || !variant.qty || variant.qty <= 0) {
                                    showToast('Please ensure all variant fields (size, unit, quantity) are filled and quantity is greater than 0.', 'error');
                                    return;
                                }
                                totalVariantQty += parseInt(variant.qty);
                            }
                            if (totalVariantQty <= 0) {
                                showToast('Total quantity across all variants must be greater than 0.', 'error');
                                return;
                            }
                        }
                    }
                    if (this.step < 3) this.step++;
                },
                prevStep() {
                    if (this.step > 1) this.step--;
                },

                selectCategory(id, name) {
                    this.selectedCategoryId = id;
                    this.selectedCategoryName = name;
                    this.isCategoryOpen = false;
                },

                addVariantRow() {
                    this.itemVariants.push({ size: '', unit: '', qty: '' });
                },

                removeVariantRow(index) {
                    this.itemVariants.splice(index, 1);
                },

                toggleScalable(checked) {
                    this.isScalable = checked;
                    this.updateRequiredFields();
                },

                updateRequiredFields() {
                    const consumableQty = document.getElementById('consumable_qty'), nonScalableQty = document.getElementById('non_scalable_qty');
                    if(consumableQty) consumableQty.required = (this.itemType === 'consumable');
                    if(nonScalableQty) nonScalableQty.required = (this.itemType === 'non-consumable' && !this.isScalable);
                },

                async fetchCategories(type) {
                    this.allCategories = [];
                    this.selectedCategoryName = 'Loading...';
                    this.selectedCategoryId = '';
                    this.categorySearch = '';

                    try {
                        const response = await fetch(`../../dbRelated/ajax_get_categories.php?type=${type}&_=${new Date().getTime()}`);
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                        const data = await response.json();
                        if (data.error) {
                            throw new Error(data.error);
                        }
                        // This defensive check ensures we always have an array.
                        if (!Array.isArray(data)) {
                            this.allCategories = []; // Reset to empty
                            throw new Error('Received invalid data format from server.');
                        }
                        this.allCategories = data;
                        this.selectedCategoryName = 'Select Category';
                    } catch (e) {
                        console.error("Failed to fetch categories:", e);
                        this.selectedCategoryName = 'Error loading';
                        showToast('Could not load categories. Check console.', 'error');
                    }
                }
            }
        }

        function fileUploader() {
            return {
                isDragging: false,
                previewUrl: '',
                fileName: '',
                handleDrop(e) {
                    this.isDragging = false;
                    const files = e.dataTransfer.files;
                    if (files.length > 0 && files[0].type.startsWith('image/')) {
                        document.getElementById('item_image_input').files = files;
                        this.updatePreview(files[0]);
                    } else {
                        showToast('Please drop a valid image file.', 'error');
                    }
                },
                handleFileSelect(e) {
                    const files = e.target.files;
                    if (files.length > 0) {
                        this.updatePreview(files[0]);
                    }
                },
                updatePreview(file) {
                    if (file && file.type.startsWith('image/')) {
                        this.previewUrl = URL.createObjectURL(file);
                        this.fileName = file.name;
                        // Dispatch an event so the parent component can see the preview URL
                        this.$dispatch('image-updated', { url: this.previewUrl });
                    } else {
                        this.removeFile();
                        showToast('Please select a valid image file.', 'error');
                    }
                },
                removeFile() {
                    const input = document.getElementById('item_image_input');
                    input.value = ''; // This clears the file selection
                    if (this.previewUrl) {
                        URL.revokeObjectURL(this.previewUrl);
                    }
                    // Also clear it in the parent
                    this.$dispatch('image-updated', { url: '' });
                    this.previewUrl = '';
                    this.fileName = '';
                }
            }
        }

        function toggleCategoryModal(opening = false, itemType = null, prefillName = '') {
            const modal = document.getElementById('cat-modal');
            const nameInput = document.getElementById('new_cat_name');
            if (opening) {
                if (!itemType) {
                    showToast('Please select an item nature first.', 'error');
                    return;
                }
                const isConsumable = itemType === 'consumable' ? '1' : '0';
                document.getElementById('new_cat_is_consumable').value = isConsumable;
                nameInput.value = prefillName;
                modal.classList.remove('hidden');
                setTimeout(() => nameInput.focus(), 50);
            } else {
                modal.classList.add('hidden');
                nameInput.value = '';
            }
        }

        async function saveNewCategory() {
            const name = document.getElementById('new_cat_name').value;
            const isConsumable = document.getElementById('new_cat_is_consumable').value;
            if (!name) return showToast("Enter a name", "error");

            const response = await fetch('../../dbRelated/ajax_add_category.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `category_name=${encodeURIComponent(name)}&is_consumable=${isConsumable}`
            });

            const data = await response.json();
            if (data.success) {
                const formElement = document.querySelector('[x-data="inventoryForm()"]');
                if (formElement && formElement.__x) {
                    const alpineInstance = formElement.__x;

                    const newCategory = {
                        CategoryID: data.new_id,
                        Category_Name: name,
                        is_consumable: parseInt(isConsumable)
                    };
                    // Create a new array with the new category, then sort it.
                    // Re-assigning the whole array is a more robust way to ensure Alpine's reactivity.
                    let updatedCategories = [...alpineInstance.allCategories, newCategory];
                    updatedCategories.sort((a, b) => a.Category_Name.localeCompare(b.Category_Name));
                    alpineInstance.allCategories = updatedCategories;

                    // Immediately select the newly added category
                    alpineInstance.selectCategory(data.new_id, name);
                }
                toggleCategoryModal(false);
                showToast("Category added successfully!", "success");
                document.getElementById('new_cat_name').value = '';
            } else {
                showToast(data.error || "Error adding category", "error");
            }
        }

    </script>
</head>
<body class="bg-gray-50 min-h-screen" x-data="inventoryForm()" @image-updated.window="itemImagePreviewUrl = $event.detail.url">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-10 flex justify-between items-start">
                    <div>
                        <h2 class="text-5xl font-extrabold text-slate-800 tracking-tighter mb-2">Register New Item<span class="text-orange-500">.</span></h2>
                        <p class="text-slate-400 font-medium">Add new apparatus, chemicals, and other assets to the inventory.</p>
                    </div>
                    <button @click="showImportModal = true"
                            class="flex items-center gap-2 bg-blue-600 text-white px-5 py-3 rounded-xl font-bold text-sm hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4 4v12"></path></svg>
                        <span>Import from CSV</span>
                    </button>
                </header>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    <section class="lg:col-span-2 bg-white p-8 rounded-3xl border border-slate-200/50 shadow-lg">
                        <form method="POST" enctype="multipart/form-data" class="space-y-8">
                            <input type="hidden" name="add_item" value="1">
                            <input type="hidden" name="item_type" :value="itemType">

                            <!-- Breadcrumb Stepper -->
                            <nav aria-label="Progress">
                                <ol role="list" class="space-y-4 md:flex md:space-x-8 md:space-y-0">
                                    <li class="md:flex-1">
                                        <a href="#" @click.prevent="step = 1" class="group flex flex-col border-l-4 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4" :class="step >= 1 ? 'border-orange-500' : 'border-gray-200 hover:border-gray-300'">
                                            <span class="text-sm font-medium" :class="step >= 1 ? 'text-orange-600' : 'text-gray-500 group-hover:text-gray-700'">Step 1</span>
                                            <span class="text-sm font-medium">Item Details</span>
                                        </a>
                                    </li>
                                    <li class="md:flex-1">
                                        <a href="#" @click.prevent="step >= 2 ? step = 2 : false" class="group flex flex-col border-l-4 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4" :class="step >= 2 ? 'border-orange-500' : 'border-gray-200 hover:border-gray-300'">
                                            <span class="text-sm font-medium" :class="step >= 2 ? 'text-orange-600' : 'text-gray-500 group-hover:text-gray-700'">Step 2</span>
                                            <span class="text-sm font-medium">Quantity & Sizing</span>
                                        </a>
                                    </li>
                                    <li class="md:flex-1">
                                        <a href="#" @click.prevent="step >= 3 ? step = 3 : false" class="group flex flex-col border-l-4 py-2 pl-4 md:border-l-0 md:border-t-4 md:pb-0 md:pl-0 md:pt-4" :class="step >= 3 ? 'border-orange-500' : 'border-gray-200 hover:border-gray-300'">
                                            <span class="text-sm font-medium" :class="step >= 3 ? 'text-orange-600' : 'text-gray-500 group-hover:text-gray-700'">Step 3</span>
                                            <span class="text-sm font-medium">Image & Finish</span>
                                        </a>
                                    </li>
                                </ol>
                            </nav>

                            <!-- Step 1: Item Details -->
                            <div x-show="step === 1" class="space-y-8">
                                <div class="pb-4 border-b border-gray-200/80">
                                    <h3 class="text-base font-semibold leading-6 text-gray-900">1. Select Item Nature</h3>
                                    <p class="mt-1 text-sm text-gray-500">Choose if the item is a consumable or a reusable apparatus.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4 pt-6">
                                    <button type="button" @click="selectType('non-consumable')" :class="{ 'bg-orange-500 text-white ring-2 ring-orange-300 shadow-lg': itemType === 'non-consumable', 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50': itemType !== 'non-consumable' }" class="p-6 text-center rounded-xl border transition-all font-bold flex flex-col items-center gap-2">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                        Non-Consumable
                                    </button>
                                    <button type="button" @click="selectType('consumable')" :class="{ 'bg-orange-500 text-white ring-2 ring-orange-300 shadow-lg': itemType === 'consumable', 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50': itemType !== 'consumable' }" class="p-6 text-center rounded-xl border transition-all font-bold flex flex-col items-center gap-2">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                                        Consumable
                                    </button>
                                </div>                                
                                <div class="space-y-6">
                                    <div class="pb-4 border-b border-gray-200/80">
                                        <h3 class="text-base font-semibold leading-6 text-gray-900">2. General Information</h3>
                                        <p class="mt-1 text-sm text-gray-500">Provide the basic details for the item.</p>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="relative" @click.away="isCategoryOpen = false">
                                            <label for="category_select_button" class="text-xs font-bold text-gray-500 mb-2 block">Category</label>

                                            <!-- The custom select button -->
                                            <button type="button" id="category_select_button" @click="isCategoryOpen = !isCategoryOpen" :disabled="!itemType" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm outline-none focus:ring-2 focus:ring-orange-500 shadow-sm text-left flex justify-between items-center disabled:bg-gray-100 disabled:cursor-not-allowed">
                                                <span x-text="selectedCategoryName"></span>
                                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                            <input type="hidden" name="cat_id" x-model="selectedCategoryId" :required="itemType">

                                            <!-- The dropdown panel -->
                                            <div x-show="isCategoryOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 transform -translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-xl border border-gray-200 p-2 space-y-2" style="display: none;">
                                                <div class="flex items-center gap-2">
                                                    <input type="text" x-model.debounce.300ms="categorySearch" placeholder="Search categories..." @click.stop class="flex-grow bg-gray-50 border-gray-200 p-3 rounded-lg font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500">
                                                    <button x-show="categorySearch && filteredCategories.length === 0" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" @click="toggleCategoryModal(true, itemType, categorySearch)" type="button" class="text-xs font-bold text-orange-600 hover:underline whitespace-nowrap pr-2">
                                                        + Add New
                                                    </button>
                                                </div>
                                                <div class="max-h-48 overflow-y-auto">
                                                    <template x-if="filteredCategories.length === 0 && !categorySearch"><div class="text-center text-xs text-gray-400 py-4">No categories found.</div></template>
                                                    <template x-if="filteredCategories.length === 0 && categorySearch">
                                                        <div class="text-center text-xs text-gray-400 py-4">No match for '<span x-text="categorySearch"></span>'.</div>
                                                    </template>
                                                    <template x-for="category in filteredCategories" :key="category.CategoryID">
                                                        <div @click="selectCategory(category.CategoryID, category.Category_Name)" class="p-3 text-sm font-medium text-slate-700 rounded-lg hover:bg-orange-50 cursor-pointer" x-text="category.Category_Name"></div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-gray-500 mb-2 block">Item Name</label>
                                            <input type="text" name="item_name" placeholder="e.g. Hydrochloric Acid" required class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" x-model="itemName">
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <label class="text-xs font-bold text-gray-500 mb-2 block">Description</label>
                                        <textarea name="description" rows="3" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" placeholder="Describe the item's purpose, e.g., 'Used for precise liquid measurement and titration.'" x-model="itemDescription"></textarea>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" @click="nextStep()" class="bg-orange-500 text-white py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20">Next &rarr;</button>
                                </div>
                            </div>

                            <!-- Step 2: Quantity & Sizing -->
                            <div x-show="step === 2" class="space-y-8">
                                <!-- Phase 3: Quantity & Sizing -->
                                <div class="space-y-6">
                                    <div class="pb-4 border-b border-gray-200/80">
                                        <h3 class="text-base font-semibold leading-6 text-gray-900">3. Quantity & Sizing</h3>
                                        <p class="mt-1 text-sm text-gray-500">Specify the quantity, units, and location of the item.</p>
                                    </div>
                                    
                                    <!-- Consumable Fields -->
                                    <div x-show="itemType === 'consumable'" x-transition class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-xl border">
                                        <div>
                                            <label class="text-xs font-bold text-gray-500 mb-2 block">Total Quantity</label>
                                            <input type="number" name="qty" id="consumable_qty" placeholder="e.g., 500" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" x-model="itemQty">
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-gray-500 mb-2 block">Unit</label>
                                            <input type="text" name="unit" placeholder="e.g., ml, g, pcs" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" x-model="itemUnit">
                                        </div>
                                    </div>

                                    <!-- Non-Consumable Fields -->
                                    <div x-show="itemType === 'non-consumable'" x-transition class="space-y-4">
                                        <div class="flex items-center space-x-3 p-4 bg-white rounded-xl border border-gray-200 shadow-sm">
                                            <input type="checkbox" id="is_scalable_checkbox" name="is_scalable" value="1" @change="toggleScalable($event.target.checked)" class="h-5 w-5 rounded text-orange-600 focus:ring-orange-500 border-slate-300">
                                            <label for="is_scalable_checkbox" class="font-bold text-sm text-slate-700">Item has multiple sizes?</label>
                                        </div>
                                        
                                        <!-- Location is always relevant for non-consumables -->
                                        <div>
                                            <label class="text-xs font-bold text-gray-500 mb-2 block">Location</label>
                                            <input type="text" name="location" placeholder="Cabinet A-1" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" x-model="itemLocation">
                                        </div>
                                        
                                        <!-- Quantity is only for non-scalable items -->
                                        <div x-show="!isScalable" x-transition class="bg-slate-50 p-4 rounded-xl border">
                                            <div>
                                                <label class="text-xs font-bold text-gray-500 mb-2 block">Total Quantity</label>
                                                <input type="number" name="qty" id="non_scalable_qty" placeholder="0" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" x-model="itemQty">
                                            </div>
                                        </div>

                                        <div x-show="isScalable" x-transition id="scalable-fields" class="space-y-4 bg-slate-50 p-6 rounded-2xl border border-gray-200">
                                            <div class="flex justify-between items-center mb-2"><label class="text-sm font-bold text-gray-700">Item Variants (Sizes)</label><button type="button" @click="addVariantRow()" class="text-xs font-bold text-orange-600 hover:underline">+ Add Size</button></div>
                                            <div id="variants-container" class="space-y-3">
                                                <template x-for="(variant, index) in itemVariants" :key="index">
                                                    <div class="grid grid-cols-4 gap-2 items-center animate-reveal-fast">
                                                        <input type="text" :name="'variants[' + index + '][size]'" x-model="variant.size" placeholder="Size (e.g. 50)" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" required>
                                                        <input type="text" :name="'variants[' + index + '][unit]'" x-model="variant.unit" placeholder="Unit (e.g. ml)" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500" required>
                                                        <input type="number" :name="'variants[' + index + '][qty]'" x-model="variant.qty" placeholder="Quantity" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm col-span-1 shadow-sm focus:ring-2 focus:ring-orange-500" required>
                                                        <button type="button" @click="removeVariantRow(index)" class="text-red-500 font-bold text-center hover:bg-red-100 rounded-full h-8 w-8 transition-all">✕</button>
                                                    </div>
                                                </template>
                                            </div>
                                            <p class="text-xs italic text-slate-400 mt-2">Total Stock is calculated from variants.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between">
                                    <button type="button" @click="prevStep()" class="bg-gray-200 text-gray-700 py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-gray-300 transition-all">&larr; Previous</button>
                                    <button type="button" @click="nextStep()" class="bg-orange-500 text-white py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20">Next &rarr;</button>
                                </div>
                            </div>

                            <!-- Step 3: Image & Finish -->
                            <div x-show="step === 3" class="space-y-8">
                                <!-- Final common fields -->
                                <div x-data="fileUploader()" class="p-6 border-2 border-dashed border-gray-200 rounded-2xl text-center transition-colors duration-300" :class="{ 'bg-orange-50 border-orange-300': isDragging }" @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false" @drop.prevent="handleDrop($event)">
                                    <input type="file" name="item_image" id="item_image_input" class="hidden" accept="image/*" @change="handleFileSelect($event)">

                                    <template x-if="!previewUrl">
                                        <label for="item_image_input" class="cursor-pointer">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                            <span class="mt-2 block text-sm font-bold text-orange-600">
                                                Drag & Drop or Click to Upload
                                            </span>
                                            <p class="mt-1 text-xs text-slate-400 italic">PNG, JPG, GIF up to 10MB</p>
                                        </label>
                                    </template>

                                    <template x-if="previewUrl">
                                        <div class="relative inline-block">
                                            <img :src="previewUrl" class="mx-auto max-h-40 rounded-lg shadow-md">
                                            <button @click="removeFile()" type="button" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full h-7 w-7 flex items-center justify-center font-bold text-xs shadow-lg">&times;</button>
                                            <p class="text-xs text-slate-500 mt-2 font-bold" x-text="fileName"></p>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <button type="button" @click="prevStep()" class="bg-gray-200 text-gray-700 py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-gray-300 transition-all">&larr; Previous</button>
                                    <button type="submit" class="bg-green-500 text-white py-3 px-8 rounded-xl font-bold uppercase text-sm hover:bg-green-600 transition-all shadow-lg shadow-green-500/20">Register Item</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <aside>
                        <div class="bg-white rounded-2xl shadow-lg border border-slate-200/50 p-4 sticky top-24">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Live Preview</h3>
                                <span x-show="itemType === 'consumable'" x-transition class="bg-sky-100 text-sky-700 text-xs font-bold px-2 py-0.5 rounded-full">Consumable</span>
                                <span x-show="itemType === 'non-consumable'" x-transition class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-0.5 rounded-full">Non-Consumable</span>
                            </div>
                            
                            <div class="bg-slate-50/70 rounded-xl p-4">
                                <div class="h-40 bg-slate-200 rounded-lg flex items-center justify-center mb-4 relative overflow-hidden">
                                    <img :src="itemImagePreviewUrl" x-show="itemImagePreviewUrl" class="absolute inset-0 w-full h-full object-cover transition-all" alt="Item Preview">
                                    <img src="../../assets/img/placeholder.png" x-show="!itemImagePreviewUrl" class="h-24 opacity-20" alt="Item Placeholder">
                                </div>

                                <div class="text-center">
                                    <span x-show="selectedCategoryName && selectedCategoryName !== 'Select a type first' && selectedCategoryName !== 'Select Category'" x-text="selectedCategoryName" class="text-xs font-bold text-slate-500 uppercase tracking-wide"></span>
                                    <span x-show="!selectedCategoryName || selectedCategoryName === 'Select a type first' || selectedCategoryName === 'Select Category'" class="text-xs font-bold text-slate-400 uppercase tracking-wide">Category</span>
                                    <h3 class="text-xl font-bold text-slate-800 mt-1 min-h-[28px]" x-text="itemName || 'Item Name'"></h3>
                                </div>
                                
                                <div class="bg-white p-3 rounded-lg mt-4 border border-slate-200 min-h-[50px]">
                                    <p class="text-xs text-slate-500 leading-relaxed" x-text="itemDescription || 'Description will appear here.'"></p>
                                </div>

                                <div class="mt-4 space-y-2">
                                    <div x-show="itemType === 'consumable'" x-transition class="flex justify-between items-center bg-white p-2 rounded-lg border border-slate-200">
                                        <span class="text-xs font-semibold text-slate-500">Total Stock:</span>
                                        <div><span class="text-sm font-bold text-slate-800" x-text="itemQty || 0"></span><span class="text-xs text-slate-500 ml-1" x-text="itemUnit || 'units'"></span></div>
                                    </div>
                                    <div x-show="itemType === 'non-consumable'" x-transition class="flex justify-between items-center bg-white p-2 rounded-lg border border-slate-200">
                                        <span class="text-xs font-semibold text-slate-500">Location:</span>
                                        <span class="text-sm font-bold text-slate-800" x-text="itemLocation || 'N/A'"></span>
                                    </div>
                                    <div x-show="itemType === 'non-consumable' && !isScalable" x-transition class="flex justify-between items-center bg-white p-2 rounded-lg border border-slate-200">
                                        <span class="text-xs font-semibold text-slate-500">Quantity:</span>
                                        <span class="text-sm font-bold text-slate-800" x-text="itemQty || 0"></span>
                                    </div>
                                    
                                    <div x-show="itemType === 'non-consumable' && isScalable && itemVariants.length > 0" x-transition>
                                        <h4 class="text-xs font-bold text-slate-400 uppercase pt-2 text-center">Variants</h4>
                                        <div class="space-y-1 max-h-24 overflow-y-auto p-1">
                                            <template x-for="(variant, index) in itemVariants" :key="index">
                                                <div class="grid grid-cols-3 items-center bg-white p-2 rounded-lg border border-slate-200 text-xs text-center">
                                                    <div class="font-bold text-slate-800" x-text="variant.size || '...' "></div>
                                                    <div class="text-slate-500" x-text="variant.unit || '...' "></div>
                                                    <div class="font-bold text-slate-800" x-text="'Qty: ' + (variant.qty || '0')"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>

    <!-- Import Modal -->
    <div x-show="showImportModal" class="fixed inset-0 z-[150] flex items-center justify-center p-6 bg-gray-900/70 backdrop-blur-sm" x-cloak>
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl p-8 relative" @click.outside="showImportModal = false">
            <button @click="showImportModal = false" type="button" class="absolute top-6 right-6 text-gray-400 hover:text-gray-700 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <h3 class="text-2xl font-bold text-gray-800 mb-2 pr-8">Import Inventory from CSV</h3>
            <p class="text-sm text-gray-500 mb-6">Upload a CSV file to add or update inventory items in bulk.</p>

            <div class="bg-blue-50 border-l-4 border-blue-400 text-blue-800 p-4 rounded-r-lg mb-6 text-xs">
                <p class="font-bold mb-2">CSV Format Instructions:</p>
                <p>Your file must contain a header row with the following columns:</p>
                <code class="block bg-blue-100 p-2 rounded mt-2 font-mono text-[10px]">Item_Name,Category_Name,Description,Total_Qty,Location,is_consumable,is_scalable,variants</code>
                <ul class="list-disc list-inside mt-2 space-y-1 text-[11px]">
                    <li><b class="text-blue-900">Item_Name & Category_Name</b> are required.</li>
                    <li><b class="text-blue-900">is_consumable & is_scalable</b> should be 1 (for yes) or 0 (for no).</li>
                    <li><b class="text-blue-900">Total_Qty</b> is for non-scalable items.</li>
                    <li><b class="text-blue-900">variants</b> is for scalable items. Format: <code class="font-mono">50ml:10,100ml:20</code></li>
                </ul>
            </div>

            <form action="../../dbRelated/import_inventory.php" method="POST" enctype="multipart/form-data">
                <div>
                    <label for="inventory-csv" class="block text-sm font-bold text-gray-700 mb-2">CSV File</label>
                    <input type="file" name="inventory_csv" id="inventory-csv" required accept=".csv"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>

                <div class="mt-8 flex justify-end gap-4">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700 transition-colors shadow-lg shadow-blue-500/20">
                        Upload and Process
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="cat-modal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-6">
        <div class="bg-white p-8 w-full max-w-sm rounded-2xl border-t-8 border-orange-500 shadow-2xl">
            <h4 class="text-xl font-bold text-slate-800 mb-6">New Category</h4>
            <input type="hidden" id="new_cat_is_consumable">
            <input type="text" id="new_cat_name" placeholder="e.g. Glassware" class="w-full bg-slate-50 border-gray-200 p-4 rounded-xl font-medium text-sm mb-4 shadow-sm focus:ring-2 focus:ring-orange-500">
            <button onclick="saveNewCategory()" class="w-full bg-orange-500 text-white py-3 rounded-xl font-bold uppercase text-sm hover:bg-orange-600 transition-all">Save Category</button>
            <button onclick="toggleCategoryModal(false)" class="w-full text-xs font-bold text-slate-400 uppercase mt-4">Close</button>
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