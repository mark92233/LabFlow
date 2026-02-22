<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../../index.php"); 
    exit(); 
}

$db = new DataManager();
$activity_id = $_GET['activity_id'] ?? null;
$mode = $_GET['mode'] ?? 'class'; 
$student_id = $_SESSION['user_id'];

// Load data
$suggested = ($activity_id) ? $db->getActivityRequirements($activity_id) : [];
$all_inventory = $db->getInventoryItems();

// Process Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_borrow'])) {
    $qrHash = bin2hex(random_bytes(16)); 
    $reason = $_POST['request_reason'] ?? '';

    $sessionID = $db->createBorrowingSession($student_id, $activity_id, $qrHash, $reason);
    
    if ($sessionID && !empty($_POST['items'])) {
        foreach ($_POST['items'] as $index => $itemID) {
            $qty = intval($_POST['qtys'][$index]);
            if ($itemID && $qty > 0) { 
                $db->addItemToSlip($sessionID, $itemID, $qty); 
            }
        }
        header("Location: active_slips.php?status=success");
        exit();
    }
}

// UI Variable for Header
$page_title = ($mode === 'research') ? "Independent Research" : "Review Borrowing Slip";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                
                <header class="mb-12">
                    <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter mb-2">
                        Review <span class="text-blue-600">Slip.</span>
                    </h2>
                    <p class="text-slate-400 font-medium italic">Double check your requirements before generating the QR code.</p>
                </header>

                <form method="POST" class="max-w-4xl">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <div class="lg:col-span-2 space-y-6">
                            <div class="glass-card p-8">
                                <div class="flex justify-between items-center mb-8">
                                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Selected Apparatus</h3>
                                    <button type="button" onclick="document.getElementById('inventoryModal').classList.remove('hidden')" 
                                            class="text-blue-600 font-bold text-xs flex items-center gap-2 hover:bg-blue-50 px-4 py-2 rounded-xl transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Add Item
                                    </button>
                                </div>

                                <div id="cart-container" class="space-y-4">
                                    <?php
                                    $items_to_render = $suggested ?: []; 
                                    foreach ($items_to_render as $item):
                                        $itemID = htmlspecialchars($item['ItemID'] ?? '');
                                        $itemName = htmlspecialchars($item['Item_Name'] ?? 'Unknown Item');
                                        $itemQty = htmlspecialchars($item['Required_Qty'] ?? 1);
                                    ?>
                                        <div class="flex items-center gap-4 p-4 bg-slate-50/50 border border-slate-100 rounded-2xl group transition-all item-row">
                                            <input type="hidden" name="items[]" value="<?= $itemID ?>">
                                            <div class="flex-1">
                                                <p class="font-bold text-slate-800"><?= $itemName ?></p>
                                                <p class="text-[9px] text-blue-500 font-black uppercase tracking-widest">Required</p>
                                            </div>
                                            <div class="flex items-center bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                                                <input type="number" name="qtys[]" value="<?= $itemQty ?>" min="0" 
                                                       class="w-16 p-2 text-center font-black text-[#0f172a] outline-none">
                                            </div>
                                            <button type="button" onclick="this.parentElement.remove()" 
                                                    class="text-slate-300 hover:text-red-500 p-2 transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (empty($items_to_render)): ?>
                                        <div id="empty-msg" class="text-center py-10 border-2 border-dashed border-slate-100 rounded-3xl">
                                            <p class="text-slate-400 text-sm italic font-medium">Your requisition cart is empty.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <aside class="space-y-6">
                            <div class="glass-card p-8">
                                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Request Purpose</h3>
                                <textarea name="request_reason" 
                                          placeholder="e.g. For chemistry titration experiment..." 
                                          class="w-full bg-slate-50 border border-slate-100 rounded-2xl p-4 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-blue-500 transition-all mb-6" 
                                          rows="4" required></textarea>
                                
                                <div class="p-4 bg-blue-50 rounded-2xl border border-blue-100 mb-6">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <p class="text-[11px] text-blue-800 font-medium leading-relaxed">
                                            Submitting this will notify your instructor for digital approval.
                                        </p>
                                    </div>
                                </div>

                                <button type="submit" name="confirm_borrow" class="w-full bg-[#0f172a] text-white py-5 rounded-2xl font-bold shadow-2xl shadow-slate-900/20 hover:bg-blue-600 transition-all transform active:scale-95">
                                    Generate QR Pass
                                </button>
                            </div>
                        </aside>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <div id="inventoryModal" class="hidden fixed inset-0 bg-[#0f172a]/80 flex items-center justify-center p-4 z-50 backdrop-blur-md">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden animate-reveal active">
            <div class="p-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h3 class="font-black text-[#0f172a] text-xl tracking-tight">Add Apparatus</h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Select from inventory</p>
                </div>
                <button type="button" onclick="document.getElementById('inventoryModal').classList.add('hidden')" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-200 transition-colors">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 max-h-[50vh] overflow-y-auto space-y-2">
                <?php foreach ($all_inventory as $inv): ?>
                    <div class="flex justify-between items-center p-4 hover:bg-slate-50 rounded-2xl transition-colors group">
                        <div>
                            <p class="text-sm font-bold text-slate-800 group-hover:text-blue-600 transition-colors"><?= htmlspecialchars($inv['Item_Name']) ?></p>
                            <p class="text-[10px] text-slate-400 font-medium">Shelf: <?= htmlspecialchars($inv['Location'] ?? 'A1') ?></p>
                        </div>
                        <button type="button" 
                                data-id="<?= htmlspecialchars($inv['ItemID']) ?>" 
                                data-name="<?= htmlspecialchars($inv['Item_Name']) ?>" 
                                onclick="addFromModal(this)"
                                class="p-2 bg-slate-100 text-[#0f172a] rounded-xl hover:bg-[#0f172a] hover:text-white transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function addFromModal(btn) {
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            const container = document.getElementById('cart-container');
            
            const emptyMsg = document.getElementById('empty-msg');
            if(emptyMsg) emptyMsg.remove();

            const div = document.createElement('div');
            div.className = 'flex items-center gap-4 p-4 bg-blue-50/30 border border-blue-100 rounded-2xl animate-reveal active';
            div.innerHTML = `
                <input type="hidden" name="items[]" value="${id}">
                <div class="flex-1">
                    <p class="font-bold text-slate-800">${name}</p>
                    <p class="text-[9px] text-blue-600 font-black uppercase tracking-widest italic">User Added</p>
                </div>
                <div class="flex items-center bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <input type="number" name="qtys[]" value="1" min="1" class="w-16 p-2 text-center font-black text-[#0f172a] outline-none">
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-slate-300 hover:text-red-500 p-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            `;
            container.appendChild(div);
            document.getElementById('inventoryModal').classList.add('hidden');
        }
    </script>

    <?php include '../../includes/layout_footer.php'; ?>

</body>
</html>