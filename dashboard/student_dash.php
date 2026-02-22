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

// Fetch Core Data [cite: 2025-12-06]
$myClasses = $db->getStudentEnrolledClasses($student_id);

// Fetch Live Status Counters [cite: 2025-12-06]
$pendingCount = $db->countStudentSessions($student_id, 'Pending');
$borrowedCount = $db->countStudentSessions($student_id, 'Issued');

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
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-12">
                    <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter mb-2">
                        Welcome, <span class="text-blue-600"><?= explode(' ', $_SESSION['user_name'])[0] ?>.</span>
                    </h2>
                    <p class="text-slate-400 font-medium italic">Select a class to view assigned labs or begin independent research.</p>
                </header>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <section class="lg:col-span-2 space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Registered Classes</h3>
                            <span class="h-px flex-1 bg-slate-100 ml-4"></span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <?php if (empty($myClasses)): ?>
                                <div class="col-span-full glass-card py-20 text-center border-2 border-dashed border-slate-200">
                                    <p class="text-slate-400 font-bold italic">You are not enrolled in any lab classes yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($myClasses as $class): ?>
                                    <div class="glass-card p-8 hover:shadow-2xl hover:shadow-blue-500/5 transition-all duration-500 group border-t-4 border-transparent hover:border-blue-600">
                                        <div class="flex justify-between items-start mb-6">
                                            <span class="bg-blue-50 text-blue-600 text-[10px] font-black px-3 py-1 rounded-lg uppercase italic">
                                                <?= htmlspecialchars($class['Semester']) ?>
                                            </span>
                                        </div>

                                        <h4 class="text-2xl font-black text-slate-800 group-hover:text-blue-600 transition-colors mb-1 uppercase italic tracking-tighter">
                                            <?= htmlspecialchars($class['Class_Name']) ?>
                                        </h4>
                                        <p class="text-[10px] text-slate-400 mb-10 font-bold uppercase tracking-widest">
                                            Instructor: <?= htmlspecialchars($class['TeacherName']) ?>
                                        </p>
                                        
                                        <a href="../pages/student/lab_list.php?class_id=<?= $class['ClassID'] ?>" 
                                           class="flex items-center justify-center w-full py-4 bg-[#0f172a] text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl hover:bg-blue-600 transition-all shadow-xl shadow-slate-900/10">
                                            View Lab Activities
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <aside class="space-y-6">
                        <div class="bg-[#0f172a] p-10 rounded-[2.5rem] text-white shadow-2xl relative overflow-hidden border-b-8 border-blue-600">
                            <h3 class="font-black text-[10px] uppercase tracking-[0.3em] mb-10 opacity-30 italic">Live Tracking</h3>
                            
                            <div class="space-y-6 mb-12">
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Awaiting Approval</span>
                                    <span class="font-black text-4xl text-yellow-400 italic"><?= $pendingCount ?></span>
                                </div>
                                <div class="h-px bg-white/5"></div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Currently Borrowed</span>
                                    <span class="font-black text-4xl text-blue-400 italic"><?= $borrowedCount ?></span>
                                </div>
                            </div>

                            <a href="active_slips.php" class="block text-center bg-blue-600 text-white py-5 rounded-3xl font-black text-[10px] uppercase tracking-widest hover:bg-white hover:text-[#0f172a] transition-all shadow-lg">
                                View Active QR Slips
                            </a>
                        </div>

                        <div class="glass-card p-10">
                            <h4 class="font-black text-slate-800 text-sm uppercase italic mb-1">General Access</h4>
                            <p class="text-[9px] text-slate-400 mb-8 uppercase font-bold tracking-[0.2em]">Thesis / Independent Work</p>
                            
                            <a href="inventory_shop.php?mode=research" class="block text-center bg-slate-50 text-slate-800 py-4 rounded-2xl font-black text-[9px] uppercase tracking-widest border border-slate-100 hover:bg-[#0f172a] hover:text-white transition-all">
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