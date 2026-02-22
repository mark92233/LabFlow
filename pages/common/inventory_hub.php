<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control [cite: 2025-12-06]
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../../index.php"); 
    exit(); 
}

$db = new DataManager();
$items = $db->getInventoryShop();
$categories = $db->getCategories();
$role = $_SESSION['user_role'] ?? 'Student';
$page_title = ($role === 'Student') ? "Apparatus Shop" : "Inventory Hub";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .shop-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); 
            gap: 1.5rem; 
            align-items: stretch;
        }

        .apparatus-card { 
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .sticky-sidebar { height: calc(100vh - 120px); position: sticky; top: 100px; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 flex gap-8 animate-reveal">
                <div class="flex-1">
                    <header class="mb-10">
                        <h2 class="text-4xl font-black text-[#0f172a] tracking-tighter uppercase italic">
                            Lab <span class="text-blue-600">Equipment.</span>
                        </h2>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">
                            <?= ($role === 'Admin') ? 'Manage Stock Levels' : 'Browse Lab Apparatus' ?>
                        </p>
                    </header>

                    <section class="mb-8 flex flex-col md:flex-row gap-4">
                        <div class="flex-1 relative">
                            <span class="absolute inset-y-0 left-4 flex items-center text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input type="text" id="inventory-search" placeholder="Search by name or ID..." 
                                   class="w-full bg-white border-none pl-12 pr-4 py-4 rounded-2xl font-bold text-sm shadow-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>

                        <div class="w-full md:w-64">
                            <select id="category-filter" 
                                    class="w-full bg-white border-none px-6 py-4 rounded-2xl font-bold text-sm shadow-sm focus:ring-2 focus:ring-blue-500 outline-none appearance-none cursor-pointer">
                                <option value="all">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['Category_Name']) ?>"><?= htmlspecialchars($cat['Category_Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </section>

                    <div class="shop-grid" id="inventory-grid">
                        <?php foreach($items as $item): ?>
                        <div class="apparatus-card glass-card p-6 group cursor-pointer" 
                             onclick="viewPokemonCard(<?= $item['ItemID'] ?>)"
                             data-item-id="<?= $item['ItemID'] ?>"
                             data-item-name="<?= strtolower(htmlspecialchars($item['Item_Name'])) ?>"
                             data-item-category="<?= htmlspecialchars($item['Category_Name']) ?>">
                            
                            <div class="flex justify-between items-start mb-4">
                                <span class="text-[9px] font-black text-slate-300 uppercase">#<?= $item['ItemID'] ?></span>
                                <span class="qty-badge text-[9px] font-black <?= $item['Available_Qty'] > 0 ? 'text-blue-600' : 'text-red-500' ?> text-right uppercase">
                                    <?= $item['Available_Qty'] ?><br>Stock
                                </span>
                            </div>

                            <div class="h-32 flex items-center justify-center mb-6">
                                <img src="../../assets/img/items/<?= $item['ItemID'] ?>.png" 
                                     class="max-h-full object-contain group-hover:scale-110 transition-transform duration-300" 
                                     onerror="this.src='../../assets/img/placeholder.png'">
                            </div>

                            <div class="mt-auto">
                                <h4 class="text-sm font-black text-slate-800 uppercase italic leading-tight mb-1"><?= htmlspecialchars($item['Item_Name']) ?></h4>
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest"><?= htmlspecialchars($item['Category_Name']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="empty-results" class="hidden py-20 text-center">
                        <p class="text-slate-400 font-black uppercase italic tracking-widest">No matching apparatus found.</p>
                    </div>
                </div>

                <aside class="w-80 sticky-sidebar">
                    <?php if ($role === 'Student'): ?>
                        <div class="glass-card h-full flex flex-col p-6 border-t-8 border-blue-600 shadow-2xl">
                            <h3 class="font-black text-slate-800 italic uppercase text-center mb-6">Requisition Cart</h3>
                            
                            <div id="cart-items-list" class="flex-1 overflow-y-auto space-y-3 border-y border-dashed border-slate-100 py-4 mb-4">
                                <div class="text-center text-[10px] text-slate-300 italic py-10 uppercase font-black">Empty Bag</div>
                            </div>

                            <form action="../../dbRelated/submit_requisition.php" method="POST" onsubmit="return validateSubmission()">
                                <input type="hidden" name="cart_data" id="cart-data-input">
                                <button type="submit" class="w-full bg-[#0f172a] text-white py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-blue-600 transition-all shadow-xl">
                                    Submit Request
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="glass-card h-full flex flex-col p-8 border-t-8 border-slate-800 shadow-2xl">
                            <h3 class="font-black text-slate-800 italic uppercase mb-2">Inventory Hub</h3>
                            <p class="text-[9px] text-slate-400 font-bold tracking-widest uppercase mb-8">Management Mode</p>
                            <div class="p-4 bg-blue-50 rounded-2xl border border-blue-100">
                                <p class="text-[10px] font-black text-blue-600 uppercase mb-1">Status</p>
                                <p class="text-xs font-medium text-slate-600 italic">Syncing live stock levels. Admin can manipulate total units [cite: 2025-12-06].</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </main>
        </div>
    </div>

    <div id="pokemon-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-[#0f172a]/90 backdrop-blur-sm p-4"></div>

    <script src="../../assets/js/inventory_engine.js?v=<?= time(); ?>"></script>
    <script>
        function validateSubmission() {
            if (typeof cart === 'undefined' || cart.length === 0) {
                alert("Your bag is empty.");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>