<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// --- Data for JS ---
$db = new DataManager();
$inventory = $db->getInventoryShop() ?? [];
// Create a quick lookup map (ItemID => ItemData) for JS to use for hydration
$inventoryMap = [];
foreach ($inventory as $invItem) {
    $inventoryMap[$invItem['ItemID']] = $invItem;
}

$page_title = "Requisition Cart";
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
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal" x-data="inventoryApp(<?= htmlspecialchars(json_encode($inventoryMap)) ?>)">
                <header class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-800">Requisition Cart</h2>
                    <p class="text-sm text-gray-500 mt-1">Review your items before submitting a borrow request.</p>
                </header>

                <div class="max-w-4xl mx-auto">
                    <!-- Empty Cart State -->
                    <div x-show="cart.length === 0" x-transition x-cloak class="bg-white rounded-2xl p-12 text-center border-2 border-dashed border-gray-200">
                        <div class="w-16 h-16 bg-gray-100 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <h3 class="font-bold text-gray-600">Your Cart is Empty</h3>
                        <p class="text-sm text-gray-400 mt-2 mb-6">Looks like you haven't added any items yet.</p>
                        <a href="inventory_hub.php" class="bg-orange-500 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20">
                            Browse Apparatus
                        </a>
                    </div>

                    <!-- Cart Items -->
                    <div x-show="cart.length > 0" x-transition x-cloak class="bg-white p-8 rounded-2xl shadow-lg border border-gray-200">
                        <div class="divide-y divide-gray-100">
                            <!-- Header -->
                            <div class="grid grid-cols-12 gap-4 pb-4 text-xs font-bold text-gray-400 uppercase tracking-wider">
                                <div class="col-span-6">Product</div>
                                <div class="col-span-3 text-center">Quantity</div>
                                <div class="col-span-3 text-right"></div>
                            </div>

                            <!-- Items Loop -->
                            <template x-for="item in cart" :key="item.id">
                                <div class="grid grid-cols-12 gap-4 py-6 items-center">
                                    <div class="col-span-6 flex items-center gap-6">
                                        <img :src="'../../assets/img/items/' + item.itemId + '.png'" class="w-20 h-20 object-contain bg-gray-50 rounded-xl p-2 border border-gray-100" onerror="this.src='../../assets/img/placeholder.png'">
                                        <div>
                                             <p class="font-bold text-gray-800 text-lg">
                                                <span x-text="item.name"></span>
                                                <template x-if="item.size">
                                                    <span class="text-base font-medium text-gray-500" x-text="'(' + item.size + (item.unit || '') + ')'"></span>
                                                </template>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                In Stock: <span x-text="item.maxQty"></span>
                                                <span x-show="item.unit && !item.size" x-text="item.unit"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-span-3 flex justify-center items-center gap-2">
                                        <div class="flex items-center justify-center bg-gray-100 rounded-xl p-1 max-w-[128px]">
                                            <button @click="updateQuantity(item.id, item.qty - 1)" class="p-2 text-gray-500 hover:text-gray-800 rounded-lg transition-colors" :disabled="item.qty <= 1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"></path></svg>
                                            </button>
                                            <input type="text" x-model.number="item.qty" @change="updateQuantity(item.id, $event.target.value)" class="w-10 text-center font-bold bg-transparent border-none focus:ring-0 p-0">
                                            <button @click="updateQuantity(item.id, item.qty + 1)" class="p-2 text-gray-500 hover:text-gray-800 rounded-lg transition-colors" :disabled="item.qty >= item.maxQty">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                            </button>
                                        </div>
                                        <span x-show="item.unit && !item.size" x-text="item.unit" class="text-sm font-bold text-gray-500"></span>
                                    </div>
                                    <div class="col-span-3 text-right">
                                        <button @click="removeItem(item.id)" title="Remove Item" class="text-gray-400 hover:text-red-500 hover:bg-red-50 transition-all p-3 rounded-full">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <form method="POST" action="../../dbRelated/submit_requisition.php" class="mt-12">
                            <input type="hidden" name="cart_data" :value="JSON.stringify(cart)">
                            <div class="text-left mb-8">
                                <label for="reason" class="text-sm font-bold text-gray-700">Purpose of Requisition (Optional)</label>
                                <textarea name="reason" id="reason" rows="3" class="mt-2 w-full text-sm bg-gray-50 border-gray-200 rounded-xl p-4 outline-none focus:ring-2 focus:ring-orange-400 shadow-sm" placeholder="e.g., For thesis research on soil composition..."></textarea>
                            </div>
                            <div class="flex justify-between items-center">
                                <button type="button" @click="clearCart()" class="text-sm font-bold text-gray-500 hover:text-red-600 transition-colors">
                                    Clear Cart
                                </button>
                                <button type="submit" class="bg-orange-500 text-white px-10 py-4 rounded-xl font-bold uppercase text-sm hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20">
                                    Submit Requisition
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
    function inventoryApp(inventoryMap) {
        return {
            cart: [],

            init() {
                let storedCart = JSON.parse(localStorage.getItem('labflow_cart') || '[]');
                this.hydrateCart(storedCart);

                // This event listener helps sync cart across tabs if needed.
                window.addEventListener('storage', (e) => {
                    if (e.key === 'labflow_cart') {
                        let newCart = JSON.parse(e.newValue || '[]');
                        this.hydrateCart(newCart);
                    }
                });
            },

            /**
             * Processes the cart from localStorage, ensuring all items have up-to-date
             * properties like 'id' and 'unit', making the component robust against
             * data added before code updates.
             */
            hydrateCart(cartData) {
                this.cart = cartData.map(item => {
                    const fullItemData = inventoryMap[item.itemId];
                    if (fullItemData) {
                        // 1. Ensure composite 'id' is present for Alpine's :key
                        if (!item.id) {
                            item.id = `${item.itemId}-${item.variantId || '0'}`;
                        }

                        // 2. Ensure 'unit' is present for non-scalable consumables
                        // This fixes items added to the cart before the 'unit' field was tracked.
                        if (fullItemData.is_consumable == 1 && !item.variantId && !item.unit) {
                            item.unit = fullItemData.Unit;
                        }
                    }
                    return item;
                });
            },

            removeItem(id) {
                this.cart = this.cart.filter(item => item.id !== id);
                this.saveCart();
            },

            updateQuantity(id, newQty) {
                const item = this.cart.find(i => i.id === id);
                if (!item) return;
                let qty = parseInt(newQty);
                if (isNaN(qty) || qty < 1) { qty = 1; }
                // Silently cap the quantity at the maximum available stock.
                if (qty > item.maxQty) { qty = item.maxQty; }
                item.qty = qty;
                this.saveCart();
            },

            clearCart() {
                if (confirm('Are you sure you want to clear your cart?')) {
                    this.cart = [];
                    this.saveCart();
                }
            },
            saveCart() {
                localStorage.setItem('labflow_cart', JSON.stringify(this.cart));
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { cart: this.cart } }));
            }
        }
    }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html> 