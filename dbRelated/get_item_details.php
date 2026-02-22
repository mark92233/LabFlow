<?php
session_start(); // Required to check the user_role
require_once __DIR__ . '/operation.php';
$db = new DataManager();

if(isset($_GET['id'])) {
    $item = $db->getItemDetails($_GET['id']);
    $role = $_SESSION['user_role'] ?? 'Student';
    ?>
    <div class="glass-card w-full max-w-sm overflow-hidden animate-reveal relative border-t-8 border-blue-600 bg-white">
        <div class="p-8 text-center">
            <span class="bg-slate-900 text-white text-[9px] font-black px-4 py-1 rounded-full uppercase tracking-widest mb-4 inline-block italic">
                <?= $item['Category_Name'] ?>
            </span>

            <div class="h-48 flex items-center justify-center mb-6">
                <img src="../../assets/img/items/<?= $item['ItemID'] ?>.png" class="max-h-full drop-shadow-2xl hover:scale-110 transition-transform duration-500" onerror="this.src='../../assets/img/placeholder.png'">
            </div>

            <h3 class="text-2xl font-black text-slate-800 uppercase italic tracking-tighter"><?= $item['Item_Name'] ?></h3>
            
            <div class="bg-slate-50 p-4 rounded-2xl mt-4 border border-slate-100">
                <p class="text-[11px] text-slate-500 leading-relaxed italic font-medium">
                    <?= $item['Description'] ?: 'No description available for this apparatus.' ?>
                </p>
            </div>
            
            <div class="mt-8">
                <?php if ($role === 'Student'): ?>
                    <div class="flex items-center justify-center gap-4 mb-6">
                        <div class="flex items-center bg-slate-100 rounded-xl px-4 py-2 border border-slate-200">
                            <button onclick="changeModalQty(-1)" class="font-black text-blue-600 text-lg px-2">-</button>
                            <input type="number" id="modal-qty" value="1" min="1" max="<?= $item['Available_Qty'] ?>" 
                                   class="w-12 text-center bg-transparent font-black text-slate-800 outline-none">
                            <button onclick="changeModalQty(1)" class="font-black text-blue-600 text-lg px-2">+</button>
                        </div>
                    </div>
                    <button onclick="addToCart(<?= $item['ItemID'] ?>, '<?= addslashes($item['Item_Name']) ?>')" 
                            class="w-full bg-blue-600 text-white py-5 rounded-3xl font-black uppercase text-xs tracking-[0.2em] shadow-xl hover:bg-slate-900 transition-all">
                        Add to Requisition
                    </button>

                <?php elseif ($role === 'Admin'): ?>
                    <div class="space-y-4">
                        <div class="flex flex-col gap-1 text-left">
                            <label class="text-[9px] font-black text-slate-400 uppercase ml-2 tracking-widest">Update Total Stock</label>
                            <div class="flex gap-2">
                                <input type="number" id="admin-total-qty" value="<?= $item['Total_Qty'] ?>" 
                                       class="flex-1 bg-slate-100 p-4 rounded-2xl font-black text-slate-800 border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
                                <div class="bg-slate-800 text-white px-4 py-4 rounded-2xl text-[10px] font-black flex items-center uppercase italic">Units</div>
                            </div>
                        </div>
                        <button onclick="updateStockLevel(<?= $item['ItemID'] ?>)" 
                                class="w-full bg-blue-600 text-white py-5 rounded-3xl font-black uppercase text-xs tracking-[0.2em] shadow-xl hover:bg-blue-700 transition-all">
                            Save Changes
                        </button>
                    </div>

                <?php else: ?>
                    <div class="p-5 bg-blue-50 rounded-3xl border border-blue-100">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[10px] font-black text-blue-400 uppercase tracking-widest">Storage Location</span>
                            <span class="text-[10px] font-black text-slate-800 uppercase italic"><?= $item['Location'] ?: 'Unassigned' ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-black text-blue-400 uppercase tracking-widest">Available Today</span>
                            <span class="text-[10px] font-black text-slate-800 italic"><?= $item['Available_Qty'] ?> of <?= $item['Total_Qty'] ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button onclick="closeModal()" class="mt-6 text-[9px] font-black text-slate-300 hover:text-red-400 uppercase tracking-[0.3em] transition-colors">
                Dismiss Card
            </button>
        </div>
    </div>
    <?php
}