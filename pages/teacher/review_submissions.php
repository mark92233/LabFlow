<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control [cite: 2025-12-06]
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Teacher') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$activity_id = $_GET['activity_id'] ?? null;

// Fetch Activity Details
$activity = $db->getActivityDetails($activity_id);
if (!$activity) {
    header("Location: dashboard.php?error=not_found");
    exit();
}

// As lab_submissions table is being removed, this page's functionality is deprecated.
$submissions = [];
$status_msg = "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review: <?= htmlspecialchars($activity['Title']) ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 animate-reveal">
                <header class="mb-10 flex justify-between items-center">
                    <div>
                        <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter">Review <span class="text-blue-600">Submissions.</span></h2>
                        <p class="text-slate-400 font-bold uppercase text-[10px] tracking-widest mt-2">Activity: <?= htmlspecialchars($activity['Title']) ?></p>
                    </div>
                    <a href="dashboard.php" class="px-6 py-3 bg-white border border-slate-100 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all">Back to Dashboard</a>
                </header>

                <div class="glass-card overflow-hidden shadow-xl border-t-8 border-blue-600">
                    <div class="p-20 text-center">
                        <p class="text-slate-400 italic">This feature is currently disabled.</p>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>