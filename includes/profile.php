<?php
session_start();
require_once '../dbRelated/operation.php';

// Access Control: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /LabFlow/index.php");
    exit();
}

$db = new DataManager();
$userID = $_SESSION['user_id'];
$profileData = $db->getUserProfilePageData($userID);

$identity = $profileData['identity'] ?? [];
$accountability = $profileData['accountability'] ?? [];
$history = $profileData['history'] ?? [];
$stockroom = $profileData['stockroom_zone'] ?? [];

$page_title = "User Profile";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($identity['Full_Name'] ?? 'User') ?>'s Profile | LabFlow</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include 'glass_header.php'; ?>

            <main class="p-8 animate-reveal">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200/50">
                    <div class="mb-6">
                        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-4">Official Identity</h2>
                        <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($identity['Full_Name'] ?? 'N/A') ?></h3>
                        <p class="text-orange-600 font-mono text-sm"><?= htmlspecialchars($identity['ID_Number'] ?? 'N/A') ?></p>
                    </div>

                    <div class="space-y-4 text-sm">
                        <div class="flex justify-between items-center py-2 border-b border-slate-100">
                            <span class="text-slate-400">System Role:</span>
                            <span class="px-2 py-0.5 bg-orange-100 text-orange-600 text-[10px] font-bold rounded uppercase"><?= htmlspecialchars($identity['Role'] ?? 'N/A') ?></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-slate-100">
                            <span class="text-slate-400">Masterlist ID:</span>
                            <span class="text-slate-600 font-medium">#<?= htmlspecialchars($identity['MasterID'] ?? 'N/A') ?></span> </div>
                        <div class="pt-2">
                            <span class="text-slate-400 block mb-1">Affiliated Section:</span>
                            <span class="text-slate-600 font-medium"><?= htmlspecialchars($identity['SectionName'] ?? 'N/A') ?></span> </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200/50">
                    <h2 class="text-xs font-bold text-orange-500 uppercase mb-3 flex items-center">
                        <i class="fas fa-map-marker-alt mr-2"></i> Primary Stockroom Zone
                    </h2>
                    <p class="text-sm text-slate-300">Your assigned pickup location for laboratory apparatus:</p>
                    <div class="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <p class="text-gray-800 font-bold text-sm">Shelf ID: <?= htmlspecialchars($stockroom['shelf_id'] ?? 'N/A') ?></p>
                        <p class="text-xs text-slate-500 italic uppercase"><?= htmlspecialchars($stockroom['aisle'] ?? 'Unassigned') ?></p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200/50 group hover:border-orange-300 transition">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase">Active Accountability</h3>
                            <i class="fas fa-microscope text-orange-500/50"></i>
                        </div>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-4xl font-black text-gray-800"><?= htmlspecialchars($accountability['IssuedItemsCount'] ?? 0) ?></span>
                            <span class="text-slate-500 text-xs uppercase">Items Held</span>
                        </div>
                        <p class="text-[10px] text-slate-500 mt-2">Status: <span class="<?= ($accountability['IssuedItemsCount'] ?? 0) > 0 ? 'text-amber-400' : 'text-emerald-400' ?>"><?= ($accountability['IssuedItemsCount'] ?? 0) > 0 ? 'With Items' : 'Clear' ?></span></p>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200/50">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-xs font-bold text-slate-400 uppercase">Academic Reports</h3>
                            <i class="fas fa-file-invoice text-orange-500/50"></i>
                        </div>
                        <div class="flex items-baseline space-x-2">
                            <span class="text-4xl font-black text-gray-800"><?= htmlspecialchars($accountability['SubmittedReportsCount'] ?? 0) ?></span>
                            <span class="text-slate-500 text-xs uppercase">Submitted</span>
                        </div>
                        <div class="w-full bg-slate-100 h-1 rounded-full mt-4 overflow-hidden">
                            <div class="bg-orange-500 h-full w-[100%]"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200/50">
                    <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-6 flex items-center">
                        <i class="fas fa-history mr-3 text-orange-500"></i> Recent Lab Activity
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-slate-500 text-xs border-b border-slate-100 uppercase">
                                <tr>
                                    <th class="pb-3 px-2">Slip ID</th>
                                    <th class="pb-3 px-2">Activity/Reason</th>
                                    <th class="pb-3 px-2">Return Status</th>
                                    <th class="pb-3 px-2">Date</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-600">
                                <?php if (empty($history)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-8 text-slate-500 italic">No recent activity found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($history as $item): ?>
                                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                                            <td class="py-4 px-2 font-mono">SLIP-<?= htmlspecialchars($item['SessionID']) ?></td>
                                            <td class="py-4 px-2"><?= htmlspecialchars($item['Title']) ?></td>
                                            <td class="py-4 px-2">
                                                <?php
                                                    $status = $item['Status'] ?? 'N/A';
                                                    $colorClass = 'text-slate-400';
                                                    if ($status === 'Returned') $colorClass = 'text-emerald-400';
                                                    if ($status === 'Issued') $colorClass = 'text-amber-400';
                                                    if ($status === 'Pending') $colorClass = 'text-blue-400';
                                                ?>
                                                <span class="<?= $colorClass ?>"><?= htmlspecialchars($status) ?></span>
                                            </td>
                                            <td class="py-4 px-2 text-slate-500"><?= date('Y-m-d', strtotime($item['CreatedAt'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
            </main>
        </div>
    </div>
    <?php include 'layout_footer.php'; ?>
</body>
</html>