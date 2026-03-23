<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Security & Role Check - Admin, Teacher, or LabTech
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Teacher', 'LabTech'])) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$search_id = trim($_GET['search_id'] ?? '');
$clearanceData = null;
$error = null;

if (!empty($search_id)) {
    $clearanceData = $db->getStudentClearanceSummary($search_id);
    if (!$clearanceData) {
        $error = "No student found with the ID Number: " . htmlspecialchars($search_id);
    }
}

$page_title = "Clearance Hub";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        #qr-scanner-container { position: relative; width: 100%; aspect-ratio: 1 / 1; margin: auto; overflow: hidden; border-radius: 1.5rem; background: #1e293b; box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.25); }
        #qr-reader { width: 100%; height: 100%; }
        #qr-reader video { width: 100% !important; height: 100% !important; object-fit: cover; }
        .qr-guide-overlay { position: absolute; inset: 0; pointer-events: none; }
        .qr-guide-box { position: absolute; inset: 15%; }
        .corner { position: absolute; width: 40px; height: 40px; border-color: rgba(255, 255, 255, 0.8); border-style: solid; }
        .corner.top-left { top: 0; left: 0; border-width: 5px 0 0 5px; border-top-left-radius: 1rem; }
        .corner.top-right { top: 0; right: 0; border-width: 5px 5px 0 0; border-top-right-radius: 1rem; }
        .corner.bottom-left { bottom: 0; left: 0; border-width: 0 0 5px 5px; border-bottom-left-radius: 1rem; }
        .corner.bottom-right { bottom: 0; right: 0; border-width: 0 5px 5px 0; border-bottom-right-radius: 1rem; }
        .scan-laser { position: absolute; top: 15%; left: 15%; right: 15%; height: 3px; background: #f97316; box-shadow: 0 0 10px #f97316, 0 0 20px #f97316; border-radius: 3px; animation: scan-animation 3s infinite ease-in-out; }
        @keyframes scan-animation { 0% { top: 15%; } 50% { top: 85%; } 100% { top: 15%; } }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            <main class="p-8 animate-reveal">
                <header class="mb-8">
                    <h2 class="text-4xl font-extrabold text-gray-800 tracking-tighter">
                        Student <span class="text-orange-500">Clearance Hub.</span>
                    </h2>
                    <p class="text-slate-400 font-medium text-xs">Verify student liabilities and clearance status.</p>
                </header>

                <div id="search-container" class="bg-white p-6 rounded-2xl border border-gray-200/50 shadow-sm mb-8">
                    <form method="GET" action="clearance_hub.php" class="flex items-center gap-4">
                        <div class="relative flex-1">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="search" name="search_id" placeholder="Enter Student ID Number..." value="<?= htmlspecialchars($search_id) ?>" class="w-full pl-12 pr-4 py-4 bg-slate-50 border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-orange-500 shadow-sm transition-all font-medium">
                        </div>
                        <button type="submit" class="px-6 py-4 bg-slate-800 text-white rounded-xl font-bold text-sm hover:bg-orange-600 transition-all">Search</button>
                        <button type="button" onclick="startScanner()" class="p-4 bg-orange-500 text-white rounded-xl font-bold hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20">
                            <i class="fas fa-qrcode fa-lg"></i>
                        </button>
                    </form>
                </div>

                <div id="camera-view" class="hidden p-6 flex-col items-center justify-center bg-white rounded-2xl border border-gray-200/50 shadow-sm mb-8">
                    <div class="w-full max-w-lg mx-auto">
                        <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-bold text-slate-800">Scan Student ID or Clearance Slip</h3><button onclick="stopScanner()" class="text-sm font-bold text-slate-500 hover:text-red-500 transition-colors">Cancel Scan</button></div>
                        <div id="qr-scanner-container"><div id="qr-reader"></div><div class="qr-guide-overlay"><div class="qr-guide-box"><div class="corner top-left"></div><div class="corner top-right"></div><div class="corner bottom-left"></div><div class="corner bottom-right"></div></div><div class="scan-laser"></div></div></div>
                        <div id="qr-reader-results" class="text-center text-sm font-bold text-red-500 mt-4 h-5"></div>
                    </div>
                </div>

                <div id="results-container">
                    <?php if ($error): ?>
                        <div class="text-center p-10 bg-white rounded-2xl border-2 border-dashed border-red-200"><p class="font-bold text-red-600"><?= $error ?></p></div>
                    <?php elseif (!$clearanceData): ?>
                        <div class="text-center p-10 bg-white rounded-2xl border-2 border-dashed border-slate-200"><i class="fas fa-user-check fa-3x text-slate-300 mb-4"></i><h3 class="font-bold text-slate-500">Awaiting Student Search</h3><p class="text-sm text-slate-400 mt-1">Use the search bar or QR scanner to look up a student.</p></div>
                    <?php else: ?>
                        <div class="bg-white p-8 rounded-t-2xl border-x border-t border-slate-200/50 shadow-sm flex justify-between items-center">
                            <div><h3 class="text-2xl font-black text-slate-800"><?= htmlspecialchars($clearanceData['student']['Full_Name']) ?></h3><p class="font-mono text-slate-500"><?= htmlspecialchars($clearanceData['student']['ID_Number']) ?></p></div>
                            <?php if ($clearanceData['is_cleared']): ?>
                                <div class="px-6 py-3 bg-emerald-100 text-emerald-600 rounded-xl font-black uppercase text-sm tracking-widest flex items-center gap-3"><i class="fas fa-check-circle"></i><span>CLEARED</span></div>
                            <?php else: ?>
                                <div class="px-6 py-3 bg-red-100 text-red-600 rounded-xl font-black uppercase text-sm tracking-widest flex items-center gap-3 animate-pulse"><i class="fas fa-exclamation-triangle"></i><span>NOT CLEARED</span></div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-white p-2 rounded-b-2xl border border-slate-200/50 shadow-lg" x-data="{ tab: 'damages' }">
                            <div class="flex items-center gap-2 border-b border-slate-100 mb-4">
                                <button @click="tab = 'damages'" :class="{ 'bg-slate-800 text-white': tab === 'damages', 'text-slate-500 hover:bg-slate-100': tab !== 'damages' }" class="flex-1 px-4 py-3 rounded-t-lg font-bold text-xs uppercase tracking-wider transition-all">Damages (<?= count($clearanceData['damages']) ?>)</button>
                                <button @click="tab = 'sessions'" :class="{ 'bg-slate-800 text-white': tab === 'sessions', 'text-slate-500 hover:bg-slate-100': tab !== 'sessions' }" class="flex-1 px-4 py-3 rounded-t-lg font-bold text-xs uppercase tracking-wider transition-all">Borrowing History (<?= count($clearanceData['sessions']) ?>)</button>
                            </div>

                            <div x-show="tab === 'damages'" class="p-4 space-y-3 max-h-96 overflow-y-auto custom-scrollbar">
                                <?php if (empty($clearanceData['damages'])): ?>
                                    <p class="text-center text-slate-400 text-sm py-4">No damage history found.</p>
                                <?php else: foreach($clearanceData['damages'] as $damage): 
                                    $isUnresolved = $damage['status'] !== 'Resolved';
                                    $link = $isUnresolved ? "settlement_reviews.php?search=" . urlencode($clearanceData['student']['ID_Number']) . "&highlight_id=" . $damage['damage_id'] : "#";
                                    $tag = $isUnresolved ? 'a' : 'div';
                                ?>
                                    <<?= $tag ?> href="<?= $link ?>" class="block p-4 rounded-xl flex justify-between items-center <?= $isUnresolved ? 'bg-red-50 border border-red-100 hover:bg-blue-50 hover:border-blue-200 cursor-pointer' : 'bg-green-50 border border-green-100 opacity-70' ?>">
                                        <div>
                                            <p class="font-bold text-slate-800"><?= htmlspecialchars($damage['Item_Name']) ?></p>
                                            <p class="text-xs text-slate-500">Type: <?= htmlspecialchars($damage['damage_type']) ?> | Qty: <?= htmlspecialchars($damage['qty_damaged']) ?></p>
                                            <p class="text-xs text-slate-400 italic">"<?= htmlspecialchars($damage['notes']) ?>"</p>
                                        </div>
                                        <span class="px-3 py-1 rounded-md text-[9px] font-black uppercase <?= $isUnresolved ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600' ?>"><?= htmlspecialchars($damage['status']) ?></span>
                                    </<?= $tag ?>>
                                <?php endforeach; endif; ?>
                            </div>

                            <div x-show="tab === 'sessions'" class="p-4 space-y-3 max-h-96 overflow-y-auto custom-scrollbar">
                                <?php if (empty($clearanceData['sessions'])): ?>
                                    <p class="text-center text-slate-400 text-sm py-4">No borrowing history found.</p>
                                <?php else: foreach($clearanceData['sessions'] as $session): 
                                    $isIssued = $session['Status'] === 'Issued';
                                    $tag = $isIssued ? 'a' : 'div';
                                    $href = $isIssued ? "handover.php?status_filter=Issued&show_receipt_sid=" . $session['SessionID'] : '#';
                                ?>
                                    <<?= $tag ?> href="<?= $href ?>" class="block p-4 rounded-xl flex justify-between items-center <?= !in_array($session['Status'], ['Returned', 'Cancelled']) ? 'bg-orange-50 border border-orange-100' : 'bg-slate-50 border border-slate-100 opacity-70' ?> <?= $isIssued ? 'hover:bg-blue-50 hover:border-blue-200 cursor-pointer' : '' ?>">
                                        <div>
                                            <p class="font-bold text-slate-800">Slip #<?= htmlspecialchars($session['SessionID']) ?>: <?= htmlspecialchars($session['ActivityTitle']) ?></p>
                                            <p class="text-xs text-slate-500"><?= date('M d, Y', strtotime($session['CreatedAt'])) ?></p>
                                        </div>
                                        <span class="px-3 py-1 rounded-md text-[9px] font-black uppercase <?= !in_array($session['Status'], ['Returned', 'Cancelled']) ? 'bg-orange-100 text-orange-600' : 'bg-slate-100 text-slate-500' ?>"><?= htmlspecialchars($session['Status']) ?></span>
                                    </<?= $tag ?>>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <!-- Audio cues for scanner -->
    <audio id="scan-success-sound" src="../../assets/audio/scan_su.wav" preload="auto"></audio>
    <audio id="scan-error-sound" src="../../assets/audio/scan_f.wav" preload="auto"></audio>

    <script>
        let html5QrCode;
        function startScanner() {
            document.getElementById('search-container').classList.add('hidden');
            const cameraView = document.getElementById('camera-view');
            cameraView.classList.remove('hidden');
            cameraView.classList.add('flex');
            if (!html5QrCode) html5QrCode = new Html5Qrcode("qr-reader");
            const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                let studentId = decodedText;
                try {
                    const url = new URL(decodedText);
                    if (url.searchParams.has('search_id')) studentId = url.searchParams.get('search_id');
                } catch (e) { /* Not a URL, use as is */ }
                document.getElementById('scan-success-sound').play();
                stopScanner();
                window.location.href = `clearance_hub.php?search_id=${encodeURIComponent(studentId)}`;
            };
            html5QrCode.start({ facingMode: "environment" }, { fps: 10 }, qrCodeSuccessCallback)
                .catch(err => { document.getElementById('qr-reader-results').innerText = "Error: Could not access camera."; });
        }
        function stopScanner() {
            if (html5QrCode && html5QrCode.isScanning) html5QrCode.stop().catch(err => console.error("Failed to stop scanner.", err));
            const cameraView = document.getElementById('camera-view');
            cameraView.classList.add('hidden');
            cameraView.classList.remove('flex');
            document.getElementById('search-container').classList.remove('hidden');
            document.getElementById('qr-reader-results').innerText = '';
        }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>