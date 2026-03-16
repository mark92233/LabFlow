<?php
session_start();
require_once __DIR__ . '/../dbRelated/operation.php';

/**
 * Access Control: Ensure only authorized Students can view this page [cite: 2025-12-06]
 */
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: ../index.php");
    exit();
}

$db = new DataManager();
$student_id = $_SESSION['user_id'];
$dashboardData = $db->getStudentDashboardData($student_id);

// Extract data for easier access
$myClasses = $dashboardData['my_classes'] ?? [];
$upcomingDeadlines = $dashboardData['upcoming_deadlines'] ?? [];
$pendingCount = $dashboardData['pending_sessions'] ?? 0;
$borrowedCount = $dashboardData['issued_sessions'] ?? 0;

$page_title = "Student Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-10">
                    <h2 class="text-5xl font-extrabold text-gray-800 tracking-tighter mb-2">
                        Welcome, <span class="text-orange-500"><?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>.</span>
                    </h2>
                    <p class="text-gray-500 font-medium">Select a class to view assigned labs or begin independent research.</p>
                </header>

                <!-- Section 1: Action Center -->
                <section class="mb-12">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Up Next</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php if (empty($upcomingDeadlines)): ?>
                            <div class="md:col-span-3 bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm text-center">
                                <p class="text-sm font-bold text-slate-500">No upcoming deadlines. You're all caught up!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcomingDeadlines as $activity): ?>
                                <a href="../pages/student/activity_view.php?activity_id=<?= $activity['ActivityID'] ?>&class_id=<?= $activity['ClassID'] ?>" class="bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm hover-lift group">
                                    <div class="flex justify-between items-start">
                                        <div class="space-y-1">
                                            <p class="text-sm font-bold text-slate-500 group-hover:text-orange-600 transition-colors"><?= htmlspecialchars($activity['Title']) ?></p>
                                            <p class="text-xs text-slate-400"><?= htmlspecialchars($activity['Class_Name']) ?></p>
                                        </div>
                                        <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                    </div>
                                    <p class="text-2xl font-black text-red-500 mt-4"><?= date("M d", strtotime($activity['Deadline'])) ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    
                    <section class="lg:col-span-8 space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em]">Registered Classes</h3>
                            <span class="h-px flex-1 bg-gray-200 ml-4"></span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php if (empty($myClasses)): ?>
                                <div class="col-span-full bg-white rounded-2xl py-20 text-center border-2 border-dashed border-gray-200">
                                    <p class="text-gray-500 font-bold">You are not enrolled in any lab classes yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($myClasses as $class): ?>
                                    <a href="../pages/student/lab_list.php?class_id=<?= $class['ClassID'] ?>" class="block bg-white p-8 rounded-2xl border border-gray-200 hover-lift group">
                                        <div class="flex justify-between items-start mb-4">
                                            <div>
                                                <h4 class="text-xl font-black text-gray-800 group-hover:text-orange-500 transition-colors mb-1 uppercase tracking-tighter">
                                                    <?= htmlspecialchars($class['Class_Name']) ?>
                                                </h4>
                                                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">
                                                    <?= htmlspecialchars($class['Section']) ?> • <?= htmlspecialchars($class['TeacherName']) ?>
                                                </p>
                                            </div>
                                            <span class="bg-orange-100 text-orange-600 text-[10px] font-black px-3 py-1 rounded-lg uppercase"><?= htmlspecialchars($class['Semester']) ?></span>
                                        </div>

                                        <div class="mt-8">
                                            <?php
                                                $total = $class['total_activities'] ?? 0;
                                                $completed = $class['completed_activities'] ?? 0;
                                                $percentage = ($total > 0) ? ($completed / $total) * 100 : 0;
                                            ?>
                                            <div class="flex justify-between items-center mb-2">
                                                <p class="text-xs font-bold text-slate-500">Activity Progress</p>
                                                <p class="text-xs font-bold text-slate-500"><?= $completed ?> / <?= $total ?></p>
                                            </div>
                                            <div class="w-full bg-slate-100 rounded-full h-2.5">
                                                <div class="bg-green-500 h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <aside class="lg:col-span-4 space-y-6">
                        <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-200">
                            <h3 class="font-black text-[10px] uppercase tracking-[0.3em] mb-8 text-gray-400">My Status</h3>
                            
                            <div class="space-y-6 mb-12">
                                <a href="../pages/student/active_slips.php" class="flex justify-between items-center p-4 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-sm text-gray-600 font-bold">Awaiting Approval</span>
                                    <span class="font-black text-3xl text-amber-500"><?= $pendingCount ?></span>
                                </a>
                                <a href="../pages/student/active_slips.php" class="flex justify-between items-center p-4 rounded-xl hover:bg-slate-50 transition-colors">
                                    <span class="text-sm text-gray-600 font-bold">Currently Borrowed</span>
                                    <span class="font-black text-3xl text-cyan-500"><?= $borrowedCount ?></span>
                                </a>
                            </div>
                        </div>

                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h4 class="font-black text-gray-800 text-sm uppercase mb-1">General Access</h4>
                            <p class="text-[9px] text-gray-400 mb-6 uppercase font-bold tracking-[0.2em]">Thesis / Independent Work</p>
                            
                            <a href="../pages/student/inventory_shop.php?mode=research" class="block text-center bg-gray-800 text-white py-4 rounded-2xl font-black text-[9px] uppercase tracking-widest border border-gray-200 hover:bg-orange-500 transition-all">
                                Open Equipment Shop
                            </a>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/layout_footer.php'; ?>
</body>
</html>