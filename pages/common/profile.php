<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control: User must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /LabFlow/index.php");
    exit();
}

$db = new DataManager();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$page_title = "My Profile";
$profileError = null;

// 2. Fetch Core Profile Data
$userProfile = $db->getUserProfileData($userId);
if (!$userProfile) {
    $profileError = "Error: Could not retrieve user profile data. Please contact an administrator.";
    // Initialize to prevent errors in the template
    $userProfile = ['Full_Name' => 'Unknown User', 'ID_Number' => 'N/A', 'Role' => 'N/A', 'Confirmed_Email' => 'N/A'];
    $roleSpecificData = [];
} else {
    // 3. Fetch Role-Specific Data
    $roleSpecificData = [];
    if ($userRole === 'Student') {
        $roleSpecificData['classes'] = $db->getStudentEnrolledClasses($userId);
        $roleSpecificData['liability'] = $db->checkLiability($userId);
    } elseif ($userRole === 'Teacher') {
        $roleSpecificData['classes'] = $db->getTeacherClasses($userId);
        $roleSpecificData['pending_requests'] = $db->countPendingRequests($userId);
    } elseif ($userRole === 'Admin') {
        // Fetch only the personal action log for the admin profile view.
        $roleSpecificData['personal_log'] = $db->getAdminActionLog($userId);
    }
}

$historySummary = [];
$fullHistory = [];
$qrCodeData = '';
if ($userRole === 'Student' && $userProfile) {
    // Fetch borrowing history summary
    $fullHistory = $db->getStudentFullTransactionHistory($userId);
    $borrowingSessions = array_filter($fullHistory, fn($h) => $h['type'] === 'borrow');
    $historySummary['total_sessions'] = count($borrowingSessions);
    $historySummary['last_borrow_date'] = !empty($borrowingSessions) ? array_values($borrowingSessions)[0]['date'] : null;

    if (!empty($userProfile['ID_Number'])) {
        $qrCodeData = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/LabFlow/pages/teacher/clearance_hub.php?search_id=' . urlencode($userProfile['ID_Number']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            background-image: radial-gradient(circle at top left, rgba(249, 115, 22, 0.04), transparent 35%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-slate-800">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <?php if ($profileError): ?>
                    <div class="max-w-7xl mx-auto bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                        <p class="font-bold">Error</p>
                        <p><?= htmlspecialchars($profileError) ?></p>
                    </div>
                <?php else: ?>
                <div class="max-w-7xl mx-auto">
                    <!-- Profile Header -->
                    <div class="bg-slate-900 rounded-3xl shadow-2xl shadow-slate-900/20 p-8 md:p-12 mb-12 relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-48 h-48 bg-orange-500/20 rounded-full blur-3xl"></div>
                        <div class="relative z-10 flex flex-col md:flex-row items-center space-y-6 md:space-y-0 md:space-x-10">
                            <div class="flex-shrink-0">
                                <div class="w-28 h-28 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 text-white flex items-center justify-center text-5xl font-black ring-8 ring-white/10 shadow-2xl">
                                    <?= htmlspecialchars(substr($userProfile['Full_Name'], 0, 1)) ?>
                                </div>
                            </div>
                            <div>
                                <span class="font-bold text-orange-400 uppercase text-xs tracking-widest"><?= htmlspecialchars($userProfile['Role']) ?></span>
                                <h2 class="text-4xl font-black text-white mt-1"><?= htmlspecialchars($userProfile['Full_Name']) ?></h2>
                                <p class="text-slate-400 mt-2 flex items-center gap-4">
                                    <span class="font-mono bg-slate-800 text-slate-300 px-3 py-1 rounded-lg text-sm"><?= htmlspecialchars($userProfile['ID_Number']) ?></span>
                                    <span class="text-sm"><?= htmlspecialchars($userProfile['Confirmed_Email']) ?></span>
                                </p>
                            </div>
                            <div class="w-full md:w-auto flex-grow flex items-center justify-end space-x-4 pt-4 md:pt-0">
                                <?php if ($userRole === 'Student'): ?>
                                    <button onclick="openPanel('clearanceReceiptPanel')" type="button" class="inline-block px-5 py-3 text-xs font-bold text-white bg-blue-500/20 hover:bg-blue-500/30 rounded-xl transition-colors flex items-center gap-2">
                                        <i class="fas fa-print"></i>
                                        Print Clearance
                                    </button>
                                <?php endif; ?>
                                <a href="change_password.php" class="px-5 py-3 text-xs font-bold text-white bg-white/5 hover:bg-white/10 rounded-xl transition-colors flex items-center gap-2">
                                    <i class="fas fa-key"></i>
                                    Change Password
                                </a>
                                <button onclick="openLogoutModal()" type="button" class="px-5 py-3 text-xs font-bold text-red-400 bg-red-500/10 hover:bg-red-500/20 rounded-xl transition-colors flex items-center gap-2">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Logout
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Student View -->
                    <?php if ($userRole === 'Student'): ?>
                        <div class="space-y-8">
                            <!-- My Classes -->
                            <div class="bg-white rounded-2xl shadow-lg p-8">
                                <h3 class="text-lg font-black text-slate-800 mb-6 uppercase tracking-wider">My Classes</h3>
                                <div class="space-y-4">
                                    <?php if (empty($roleSpecificData['classes'])): ?>
                                        <p class="text-slate-500 text-center py-10">You are not enrolled in any classes.</p>
                                    <?php else: ?>
                                        <?php foreach ($roleSpecificData['classes'] as $class): ?>
                                            <a href="/LabFlow/pages/student/lab_list.php?class_id=<?= $class['ClassID'] ?>" class="block p-6 border-2 border-slate-100 rounded-2xl flex justify-between items-center hover:border-orange-500 hover:bg-orange-50 transition-all duration-300 group">
                                                <div>
                                                    <p class="font-bold text-slate-800 group-hover:text-orange-600 transition-colors"><?= htmlspecialchars($class['Class_Name'] . ' - ' . $class['Section']) ?></p>
                                                    <p class="text-sm text-slate-500 mt-1">Instructor: <?= htmlspecialchars($class['TeacherName']) ?></p>
                                                </div>
                                                <span class="px-4 py-2 text-xs font-bold text-orange-600 bg-orange-100 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">View &rarr;</span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- My Liabilities -->
                            <?php if ($roleSpecificData['liability']['has_liability']): ?>
                            <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-6 rounded-r-lg shadow-md">
                                <h3 class="text-lg font-black mb-4 flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> Active Liabilities</h3>
                                <p class="mb-4">You have unresolved damages. Please settle them to ensure clearance.</p>
                                <ul class="space-y-2">
                                <?php foreach ($roleSpecificData['liability']['items'] as $item): ?>
                                    <li class="font-bold text-sm">- <?= htmlspecialchars($item['Item_Name']) ?> (<?= $item['qty_damaged'] ?> pcs)</li>
                                <?php endforeach; ?>
                                </ul>
                                <div class="mt-6 flex items-center gap-4">
                                    <a href="/LabFlow/pages/student/settlement_cases.php" class="inline-block px-5 py-2.5 bg-red-600 text-white font-bold text-xs rounded-lg hover:bg-red-700 shadow-lg shadow-red-500/20 uppercase tracking-wider">Settle Now</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Teacher View -->
                    <?php if ($userRole === 'Teacher'): ?>
                        <div class="bg-white rounded-2xl shadow-lg p-8">
                            <h3 class="text-lg font-black text-slate-800 mb-6 uppercase tracking-wider">My Classes</h3>
                            <div class="space-y-4">
                                <?php foreach ($roleSpecificData['classes'] as $class): ?>
                                    <a href="/LabFlow/pages/teacher/manage_class.php?class_id=<?= $class['ClassID'] ?>" class="block p-6 border-2 border-slate-100 rounded-2xl flex justify-between items-center hover:border-orange-500 hover:bg-orange-50 transition-all duration-300 group">
                                        <div>
                                            <p class="font-bold text-slate-800 group-hover:text-orange-600 transition-colors"><?= htmlspecialchars($class['Class_Name'] . ' - ' . $class['Section']) ?></p>
                                            <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($class['Semester']) ?></p>
                                        </div>
                                        <span class="px-4 py-2 text-xs font-bold text-orange-600 bg-orange-100 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">Manage &rarr;</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <a href="/LabFlow/pages/teacher/create_class.php" class="inline-block mt-8 px-6 py-3 bg-slate-800 text-white font-bold text-xs rounded-xl hover:bg-slate-900 transition-all shadow-lg shadow-slate-800/20 uppercase tracking-wider">Create New Class</a>
                        </div>
                    <?php endif; ?>

                    <!-- Admin View -->
                    <?php if ($userRole === 'Admin'): ?>
                        <div class="space-y-8">
                            <!-- Admin Personal Log -->
                            <div class="bg-white rounded-3xl shadow-lg p-8 border border-slate-100">
                                <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-6">My Recent Actions</h3>
                                <div class="space-y-6 max-h-96 overflow-y-auto custom-scrollbar pr-4">
                                    <?php if (empty($roleSpecificData['personal_log'])): ?>
                                        <p class="text-slate-500 text-center py-10">No recent actions recorded.</p>
                                    <?php else: ?>
                                        <?php foreach ($roleSpecificData['personal_log'] as $log): ?>
                                            <div class="flex items-start gap-4">
                                                <div class="w-10 h-10 rounded-full bg-<?= $log['color'] ?>-100 text-<?= $log['color'] ?>-600 flex items-center justify-center flex-shrink-0">
                                                    <i class="fas <?= $log['icon'] ?>"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm text-slate-600"><?= $log['description'] ?></p>
                                                    <p class="text-xs text-slate-400 mt-1"><?= date('M d, Y @ h:i A', strtotime($log['timestamp'])) ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Panel Backdrop -->
    <div id="panelBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden" onclick="closeAllPanels()"></div>

    <!-- Clearance Receipt Panel -->
    <?php if ($userRole === 'Student' && $userProfile): ?>
    <aside id="clearanceReceiptPanel" class="side-panel print-this-panel fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl transform translate-x-full z-50 flex flex-col">
        <header class="p-6 border-b border-slate-100 flex justify-between items-center flex-shrink-0 no-print">
            <div>
                <h3 class="font-black text-slate-800 text-lg tracking-tight uppercase">Clearance Slip</h3>
                <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase">Student Copy</p>
            </div>
            <button onclick="closeAllPanels()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-100 transition-colors text-slate-400">&times;</button>
        </header>
        <div id="printable-area" class="flex-1 overflow-y-auto">
            <div id="receipt-content" class="p-8 thermal-font text-slate-800">
                <header class="text-center mb-8 pb-6 border-b-2 border-dashed border-slate-200">
                    <h1 class="text-2xl font-bold uppercase tracking-widest">CSM Laboratory</h1>
                    <p class="text-xs uppercase mt-1 text-slate-400 font-bold">Student Clearance Summary</p>
                    <p class="text-[10px] mt-2 font-bold"><?= date('F j, Y, g:i a') ?></p>
                </header>

                <section class="mb-8">
                    <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Student Identity</h2>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="font-bold">Name:</span>
                            <span class="font-bold text-right"><?= htmlspecialchars($userProfile['Full_Name']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-bold">ID Number:</span>
                            <span class="font-bold"><?= htmlspecialchars($userProfile['ID_Number']) ?></span>
                        </div>
                    </div>
                </section>

                <section class="mb-8">
                    <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Transaction History</h2>
                    <div class="space-y-3 text-xs max-h-60 overflow-y-auto custom-scrollbar pr-2">
                        <?php if (empty($fullHistory)): ?>
                            <p class="italic text-slate-400">No history found.</p>
                        <?php else: ?>
                            <?php foreach ($fullHistory as $entry): 
                                $isBorrow = $entry['type'] === 'borrow';
                                $status = htmlspecialchars($entry['status']);
                                $title = htmlspecialchars($entry['title']);
                                $date = date('M d, Y', strtotime($entry['date']));

                                $icon = '';
                                $colorClass = '';
                                if ($isBorrow) {
                                    $icon = 'fa-hand-holding';
                                    if ($status === 'Returned') $colorClass = 'text-emerald-500';
                                    elseif ($status === 'Issued') $colorClass = 'text-amber-500';
                                    else $colorClass = 'text-slate-400';
                                } else { // damage
                                    $icon = 'fa-heart-broken';
                                    if ($status === 'Resolved') $colorClass = 'text-emerald-500';
                                    elseif (in_array($status, ['Unresolved', 'Under Review'])) $colorClass = 'text-red-500';
                                    else $colorClass = 'text-slate-400';
                                }
                            ?>
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 flex-shrink-0 flex items-center justify-center rounded-full <?= $colorClass ?> bg-opacity-10 <?= str_replace('text-', 'bg-', $colorClass) ?>">
                                    <i class="fas <?= $icon ?> fa-xs"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-700 leading-tight">
                                        <?= $isBorrow ? 'Borrowing slip for ' : 'Damage report for ' ?>
                                        <span class="italic"><?= $title ?></span>
                                    </p>
                                    <p class="text-[9px] font-bold <?= $colorClass ?>">
                                        Status: <?= $status ?> on <?= $date ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section>
                    <h2 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Accountability Status</h2>
                    
                    <?php if ($roleSpecificData['liability']['has_liability']): ?>
                        <div class="bg-red-50 border-2 border-dashed border-red-200 rounded-xl p-6">
                            <div class="text-center mb-4">
                                <p class="text-lg font-black text-red-600 uppercase">LIABILITY PENDING</p>
                                <p class="text-xs font-bold text-red-500">Student has unresolved damages.</p>
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between font-bold border-b border-dashed border-red-200 pb-1 mb-1">
                                    <span>ITEM</span>
                                    <span>QTY</span>
                                </div>
                                <?php foreach ($roleSpecificData['liability']['items'] as $item): ?>
                                    <div class="flex justify-between">
                                        <span class="font-bold"><?= htmlspecialchars($item['Item_Name']) ?></span>
                                        <span class="font-bold"><?= htmlspecialchars($item['qty_damaged']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-emerald-50 border-2 border-dashed border-emerald-300 rounded-xl p-8 text-center">
                            <p class="text-2xl font-black text-emerald-600 uppercase tracking-wider">CLEARED</p>
                            <p class="text-xs font-bold text-emerald-500 mt-1">No outstanding liabilities found.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <footer class="mt-8 pt-8 border-t-2 border-dashed border-slate-200 flex flex-col items-center">
                    <div id="panel-qrcode-container" class="p-2 bg-white border-4 border-slate-800 rounded-lg shadow-md mb-2"></div>
                    <p class="text-[9px] font-bold text-center uppercase text-slate-500">
                        Admin: Scan to verify liabilities
                    </p>
                </footer>

                <div class="mt-8 text-center">
                    <p class="text-[10px] text-slate-400">_________________________</p>
                    <p class="text-[10px] font-bold text-slate-500 mt-1">Signature over Printed Name</p>
                </div>
            </div>
        </div>
        <footer class="p-4 border-t border-slate-100 bg-slate-50/50 no-print">
            <button onclick="downloadClearanceSlip()" class="w-full bg-slate-800 text-white px-6 py-3 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-orange-600 transition-all shadow-lg">
                Save as Image
            </button>
        </footer>
    </aside>
    <?php endif; ?>

    <?php include '../../includes/layout_footer.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- JS and CSS for Panel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .side-panel { transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); }
        .thermal-font { font-family: 'Courier Prime', 'Courier New', Courier, monospace; }
        @media print {
            body.is-printing > *:not(.print-this-panel),
            body.is-printing .no-print {
                display: none !important;
            }
            body.is-printing .print-this-panel {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
                transform: translateX(0) !important;
            }
        }
    </style>
    <script>
        function openPanel(panelId) {
            const panel = document.getElementById(panelId);
            const backdrop = document.getElementById('panelBackdrop');
            if (panel && backdrop) {
                backdrop.classList.remove('hidden');
                panel.classList.remove('translate-x-full');
            }
        }

        function closeAllPanels() {
            const backdrop = document.getElementById('panelBackdrop');
            backdrop.classList.add('hidden');
            document.querySelectorAll('.side-panel').forEach(p => {
                p.classList.add('translate-x-full');
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const qrContainer = document.getElementById("panel-qrcode-container");
            const qrData = "<?= $qrCodeData ?? '' ?>";
            
            if(qrContainer && qrData) {
                qrContainer.innerHTML = "";
                new QRCode(qrContainer, {
                    text: qrData, width: 160, height: 160,
                    colorDark : "#0f172a", colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });

        function downloadClearanceSlip() {
            const element = document.getElementById('receipt-content');
            if (!element) {
                console.error('Printable area not found!');
                return;
            }

            const studentName = "<?= addslashes($userProfile['Full_Name'] ?? 'Student') ?>";
            const today = new Date().toISOString().slice(0, 10);
            const cleanStudentName = studentName.replace(/[^a-z0-9\s-]/gi, '').replace(/\s+/g, '_');
            const newFilename = `Clearance_${cleanStudentName}_${today}.png`;

            html2canvas(element, {
                scale: 2.5,
                backgroundColor: '#ffffff',
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = newFilename;
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
</body>
</html>