<?php
session_start();
require_once __DIR__ . '/../../dbRelated/operation.php';

// Access Control: Teacher or Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();

// Handle Actions (Resolve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['damage_id'])) {
    $id = $_POST['damage_id'];
    if ($_POST['action'] === 'resolve') {
        $db->resolveDamage($id);
    } elseif ($_POST['action'] === 'reject') {
        $db->rejectDamage($id);
    }
    header("Location: settlement_reviews.php");
    exit();
}

$cases = $db->getSettlementCases('pending');
$page_title = "Settlement Reviews";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-8">
                    <h2 class="text-3xl font-black text-[#0f172a] uppercase italic tracking-tighter">Settlement Cases</h2>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Review damaged items and payment proofs</p>
                </header>

                <?php if (empty($cases)): ?>
                    <div class="glass-card p-12 text-center border-2 border-dashed border-slate-200 rounded-[2rem]">
                        <div class="w-16 h-16 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <h3 class="text-slate-800 font-bold text-lg">All Clear</h3>
                        <p class="text-slate-400 text-sm">No pending settlement cases found.</p>
                    </div>
                <?php else: ?>
                    <div class="grid gap-6">
                        <?php foreach ($cases as $case): ?>
                            <div class="glass-card p-6 rounded-3xl border-l-8 <?= $case['status'] === 'Under Review' ? 'border-blue-500' : 'border-orange-500' ?> flex flex-col md:flex-row gap-6">
                                
                                <!-- Evidence / Proof Image -->
                                <div class="w-full md:w-48 h-48 bg-slate-100 rounded-2xl overflow-hidden flex-shrink-0 relative group">
                                    <?php 
                                        $img = $case['proof_image'] ?? $case['evidence_image'];
                                        $folder = $case['proof_image'] ? 'settlements' : 'evidence';
                                    ?>
                                    <?php if ($img): ?>
                                        <img src="../../uploads/<?= $folder ?>/<?= $img ?>" class="w-full h-full object-cover transition-transform group-hover:scale-110" onclick="window.open(this.src)">
                                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                            <span class="text-white text-xs font-bold uppercase tracking-widest">View</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center justify-center h-full text-slate-400 text-xs font-bold uppercase">No Image</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Details -->
                                <div class="flex-1">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h4 class="text-xl font-black text-slate-800 uppercase italic"><?= htmlspecialchars($case['Item_Name']) ?></h4>
                                            <p class="text-xs text-slate-500 font-bold uppercase tracking-widest">
                                                Reported by: <span class="text-blue-600"><?= htmlspecialchars($case['Full_Name']) ?></span>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest <?= $case['status'] === 'Under Review' ? 'bg-blue-100 text-blue-600' : 'bg-orange-100 text-orange-600' ?>">
                                            <?= $case['status'] ?>
                                        </span>
                                    </div>

                                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 mb-4">
                                        <div class="grid grid-cols-2 gap-4 text-xs">
                                            <div>
                                                <span class="block text-slate-400 font-bold uppercase text-[9px]">Damage Type</span>
                                                <span class="font-bold text-slate-700"><?= htmlspecialchars($case['damage_type']) ?></span>
                                            </div>
                                            <div>
                                                <span class="block text-slate-400 font-bold uppercase text-[9px]">Quantity</span>
                                                <span class="font-bold text-slate-700"><?= $case['qty_damaged'] ?> Units</span>
                                            </div>
                                            <div class="col-span-2">
                                                <span class="block text-slate-400 font-bold uppercase text-[9px]">Notes</span>
                                                <p class="italic text-slate-600"><?= htmlspecialchars($case['notes'] ?: 'No remarks provided.') ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex gap-3">
                                        <?php if ($case['status'] === 'Under Review'): ?>
                                            <form method="POST" class="flex-1">
                                                <input type="hidden" name="damage_id" value="<?= $case['damage_id'] ?>">
                                                <input type="hidden" name="action" value="resolve">
                                                <button type="submit" class="w-full py-3 bg-green-600 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:bg-green-700 transition-all shadow-lg shadow-green-200">Accept & Resolve</button>
                                            </form>
                                            <form method="POST" class="flex-1">
                                                <input type="hidden" name="damage_id" value="<?= $case['damage_id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="w-full py-3 bg-white border border-red-100 text-red-500 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-red-50 transition-all">Reject Proof</button>
                                            </form>
                                        <?php else: ?>
                                            <div class="w-full py-3 bg-slate-100 text-slate-400 rounded-xl text-xs font-black uppercase tracking-widest text-center">Waiting for Student Proof</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>