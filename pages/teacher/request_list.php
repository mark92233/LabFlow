<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control: Teacher/Admin Only
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$teacher_id = $_SESSION['user_id'];

// Handle Approval/Rejection Actions
if (isset($_GET['action']) && isset($_GET['sid'])) {
    $newStatus = ($_GET['action'] === 'approve') ? 'Approved' : 'Rejected';
    $db->updateSessionStatus($_GET['sid'], $newStatus);
    header("Location: request_list.php?msg=updated");
    exit();
}

// 1. Fetch Pending Requests
$query = "SELECT bs.*, 
                 COALESCE(la.Title, 'Independent Research') as Title, 
                 m.Full_Name as StudentName, 
                 'General' as Class_Name
          FROM borrowing_sessions bs
          LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
          JOIN users u ON bs.StudentID = u.UserID
          JOIN lookup_masterlist m ON u.MasterID = m.MasterID
          WHERE bs.Status = 'Pending'
          ORDER BY bs.CreatedAt DESC";

$stmt = $db->db->prepare($query);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// UI Variable for Header
$page_title = "Borrowing Queue";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incoming Borrow Requests | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                
                <header class="mb-12 flex justify-between items-end">
                    <div>
                        <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter mb-2">
                            Incoming <span class="text-blue-600">Requests.</span>
                        </h2>
                        <p class="text-slate-400 font-medium">Review and vet student apparatus requisitions.</p>
                    </div>
                    <div class="hidden md:block">
                        <span class="bg-[#0f172a] text-white text-[10px] px-4 py-2 rounded-full font-black uppercase tracking-widest">
                            Pending: <?= count($requests) ?>
                        </span>
                    </div>
                </header>

                <?php if (empty($requests)): ?>
                    <div class="glass-card p-20 text-center flex flex-col items-center">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Inbox Clear</h3>
                        <p class="text-slate-400">There are no student requests waiting for your approval.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($requests as $req): 
                            $itemsQuery = "SELECT bi.Quantity, i.Item_Name 
                                           FROM borrowed_items bi 
                                           JOIN inventory i ON bi.ItemID = i.ItemID 
                                           WHERE bi.SessionID = :sid";
                            $iStmt = $db->db->prepare($itemsQuery);
                            $iStmt->execute(['sid' => $req['SessionID']]);
                            $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                            <div class="glass-card p-8 group hover:border-blue-500/30 transition-all duration-500">
                                <div class="flex flex-col lg:flex-row justify-between items-start gap-8">
                                    
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-4">
                                            <span class="bg-blue-500/10 text-blue-600 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-tighter">
                                                <?= htmlspecialchars($req['Class_Name']) ?>
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-bold italic"><?= date('h:i A • M d', strtotime($req['CreatedAt'])) ?></span>
                                        </div>
                                        
                                        <h3 class="text-2xl font-black text-[#0f172a] mb-1"><?= htmlspecialchars($req['StudentName']) ?></h3>
                                        <p class="text-sm text-slate-500 mb-6 font-medium italic">Re: <?= htmlspecialchars($req['Title']) ?></p>
                                        
                                        <div class="mb-6 p-5 bg-slate-50 rounded-[1.5rem] border-l-4 border-blue-500">
                                            <p class="text-[9px] font-black text-blue-600 uppercase tracking-widest mb-1">Purpose of Requisition</p>
                                            <p class="text-sm text-slate-700 font-medium">"<?= htmlspecialchars($req['Request_Reason'] ?? 'No reason provided.') ?>"</p>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach ($items as $item): ?>
                                                <div class="flex items-center gap-2 bg-white border border-slate-100 px-3 py-2 rounded-xl shadow-sm">
                                                    <span class="text-xs font-black text-blue-600"><?= $item['Quantity'] ?>x</span>
                                                    <span class="text-[11px] font-bold text-slate-600 lowercase tracking-tight"><?= htmlspecialchars($item['Item_Name']) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="flex lg:flex-col gap-3 w-full lg:w-48">
                                        <a href="?action=approve&sid=<?= $req['SessionID'] ?>" 
                                           class="flex-1 text-center bg-blue-600 text-white py-4 rounded-2xl font-bold text-sm shadow-xl shadow-blue-500/20 hover:bg-[#0f172a] transition-all transform active:scale-95">
                                            Approve
                                        </a>
                                        <a href="?action=reject&sid=<?= $req['SessionID'] ?>" 
                                           class="flex-1 text-center bg-white text-red-500 border border-red-100 py-4 rounded-2xl font-bold text-sm hover:bg-red-50 transition-all">
                                            Reject
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include '../../includes/layout_footer.php'; ?>

</body>
</html>