<?php
session_start();
require_once __DIR__ . '/../dbRelated/operation.php';

// Role-based Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../index.php");
    exit();
}

$db = new DataManager();
$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Fetch data
$myClasses = [];
$pendingCount = 0;
$adminKPI = [];

if ($role === 'Teacher') {
    $myClasses = $db->getTeacherClasses($user_id);
    $pendingCount = $db->countPendingRequests($user_id);
} elseif ($role === 'Admin') {
    $adminKPI = $db->getAdminKPIs();
    $pendingCount = $adminKPI['pending_reqs'] ?? 0;
}

// UI Variable for Header
$page_title = $role . " Dashboard";


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $role ?> Dashboard | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Smooth scale effect for clickable cards */
        .class-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .class-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
            box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.1), 0 10px 10px -5px rgba(59, 130, 246, 0.04);
        }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            
            <?php include '../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                
                <header class="mb-12">
                    <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter mb-2 italic">
                        <?= strtoupper($role) ?> <span class="text-blue-600 font-light not-italic">PORTAL</span>
                    </h2>
                    <p class="text-slate-400 font-medium italic uppercase text-xs tracking-widest">Select a class to manage activities and grading.</p>
                </header>

                <?php if ($role === 'Admin'): ?>
                    <!-- Admin KPI Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Total Stock</p>
                            <p class="text-4xl font-black text-[#0f172a]"><?= number_format($adminKPI['total_stock'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Users</p>
                            <p class="text-4xl font-black text-blue-600"><?= number_format($adminKPI['total_users'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Pending</p>
                            <p class="text-4xl font-black text-amber-500"><?= number_format($adminKPI['pending_reqs'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Damages</p>
                            <p class="text-4xl font-black text-red-500"><?= number_format($adminKPI['open_damages'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Students</p>
                            <p class="text-4xl font-black text-indigo-600"><?= number_format($adminKPI['student_pop'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Teachers</p>
                            <p class="text-4xl font-black text-emerald-600"><?= number_format($adminKPI['teacher_pop'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Classes</p>
                            <p class="text-4xl font-black text-orange-500"><?= number_format($adminKPI['total_classes'] ?? 0) ?></p>
                        </div>
                    </div>

                    <!-- Visual Analytics -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="glass-card p-8 rounded-[2.5rem]">
                            <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-6">Inventory Composition</h3>
                            <div class="h-64">
                                <canvas id="inventoryChart"></canvas>
                            </div>
                        </div>
                        <div class="glass-card p-8 rounded-[2.5rem]">
                            <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-6">Borrowing Activity</h3>
                            <div class="h-64">
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Data from PHP
                        const catData = <?= json_encode($adminKPI['categories'] ?? []) ?>;
                        const sessData = <?= json_encode($adminKPI['session_stats'] ?? []) ?>;

                        // 1. Inventory Chart
                        new Chart(document.getElementById('inventoryChart'), {
                            type: 'doughnut',
                            data: {
                                labels: catData.map(d => d.Category_Name),
                                datasets: [{
                                    data: catData.map(d => d.count),
                                    backgroundColor: ['#3b82f6', '#ef4444', '#f59e0b', '#10b981', '#6366f1', '#8b5cf6'],
                                    borderWidth: 0
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'right', labels: { font: { family: 'Inter', size: 10, weight: 'bold' } } } }
                            }
                        });

                        // 2. Activity Chart
                        new Chart(document.getElementById('activityChart'), {
                            type: 'bar',
                            data: {
                                labels: sessData.map(d => d.Status),
                                datasets: [{
                                    label: 'Sessions',
                                    data: sessData.map(d => d.count),
                                    backgroundColor: '#0f172a',
                                    borderRadius: 8
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } },
                                plugins: { legend: { display: false } }
                            }
                        });
                    </script>

                <?php else: ?>
                    <!-- Teacher View: Only Assigned Classes -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if (empty($myClasses)): ?>
                            <div class="col-span-full py-16 text-center border-2 border-dashed border-slate-200 rounded-[2.5rem]">
                                <p class="text-slate-400 font-bold uppercase italic text-xs">No classes found in your directory.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($myClasses as $class): ?>
                                <a href="../pages/teacher/class_activities.php?class_id=<?= $class['ClassID'] ?>" class="group">
                                    <div class="class-card bg-white border border-slate-100 p-8 rounded-[2.5rem] relative overflow-hidden">
                                        <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 rounded-full -mr-12 -mt-12 group-hover:bg-blue-600 transition-colors duration-500"></div>
                                        
                                        <div class="relative z-10">
                                            <h4 class="font-black text-slate-800 group-hover:text-blue-600 text-xl uppercase italic leading-tight transition-colors">
                                                <?= htmlspecialchars($class['Class_Name']) ?>
                                            </h4>
                                            <p class="text-[10px] text-slate-400 font-black uppercase mt-2 tracking-widest">
                                                Section: <?= htmlspecialchars($class['Section']) ?>
                                            </p>
                                            
                                            <div class="mt-8 flex items-center justify-between">
                                                <div class="flex flex-col">
                                                    <span class="text-[8px] font-black text-slate-300 uppercase tracking-tighter">Semester</span>
                                                    <span class="text-xs font-bold text-slate-600 uppercase italic"><?= htmlspecialchars($class['Semester']) ?></span>
                                                </div>
                                                <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-blue-600 group-hover:text-white transition-all">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <?php include '../includes/layout_footer.php'; ?>

</body>
</html>