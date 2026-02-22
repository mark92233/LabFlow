<?php
session_start();
require_once '../dbRelated/operation.php';

// Role-based Access Control
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../index.php");
    exit();
}

$db = new DataManager();
$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// Fetch data
$myClasses = ($role === 'Teacher') ? $db->getTeacherClasses($user_id) : [];
$pendingCount = ($role === 'Teacher') ? $db->countPendingRequests($user_id) : 0;

// UI Variable for Header
$page_title = $role . " Dashboard";


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $role ?> Dashboard | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <section class="lg:col-span-2 space-y-8">
                        
                        <div class="glass-card p-1 border-blue-500/10 bg-gradient-to-br from-white to-slate-50">
                            <div class="p-8 flex flex-col md:flex-row justify-between items-center gap-6">
                                <div class="flex items-center gap-6 text-center md:text-left">
                                    <div class="w-16 h-16 bg-[#0f172a] text-white rounded-2xl flex items-center justify-center shadow-2xl shadow-blue-500/20">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-black text-[#0f172a] uppercase italic">Handover Terminal</h3>
                                        <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest">Process apparatus requisitions.</p>
                                    </div>
                                </div>
                                <a href="../pages/teacher/handover.php" class="w-full md:w-auto bg-blue-600 text-white px-10 py-4 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-[#0f172a] transition-all text-center shadow-lg shadow-blue-200">
                                    Open Terminal
                                </a>
                            </div>
                        </div>

                        <?php if ($role === 'Admin'): ?>
                            <div class="bg-[#0f172a] p-8 rounded-[2rem] text-white shadow-2xl shadow-slate-900/10">
                                <h3 class="text-xl font-bold mb-2 uppercase italic">Master Inventory Control</h3>
                                <p class="text-sm text-slate-400 mb-6 font-medium">Full authority over apparatus masterlist and category logic.</p>
                                <div class="flex flex-wrap gap-3">
                                    <a href="../pages/admin/manage_inventory.php" class="bg-white text-[#0f172a] px-6 py-3 rounded-xl font-bold text-xs uppercase hover:bg-blue-500 hover:text-white transition-all">Manage Shelf</a>
                                    <a href="../pages/admin/category_mgr.php" class="bg-white/10 text-white border border-white/20 px-6 py-3 rounded-xl font-bold text-xs uppercase hover:bg-white/20 transition-all">Category Manager</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="glass-card p-8">
                            <div class="flex justify-between items-center mb-10">
                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] italic">Assigned Classes</h3>
                                <div class="h-px flex-1 bg-slate-100 mx-6"></div>
                                <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-3 py-1 rounded-full uppercase">Select a Class</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <?php if (empty($myClasses) && $role === 'Teacher'): ?>
                                    <div class="col-span-2 py-16 text-center border-2 border-dashed border-slate-200 rounded-[2.5rem]">
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
                        </div>
                    </section>

                    <aside class="space-y-8">
                        <div class="glass-card p-8 border-l-8 border-l-blue-600 shadow-xl">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-6 italic">Borrowing Queue</h3>
                            <a href="../pages/teacher/request_list.php" class="flex items-center justify-between p-6 bg-[#f8fafc] rounded-3xl border border-slate-100 hover:border-blue-200 transition-all group">
                                <div class="flex items-center gap-4">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full animate-pulse shadow-lg shadow-blue-500/50"></div>
                                    <span class="text-xs font-black text-slate-800 uppercase italic tracking-widest">Waiting</span>
                                </div>
                                <span class="bg-[#0f172a] text-white px-4 py-2 rounded-2xl text-lg font-black italic shadow-lg">
                                    <?= $pendingCount ?>
                                </span>
                            </a>
                        </div>

                        <div class="glass-card p-8">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-6 italic">Teacher Tools</h3>
                            <ul class="space-y-4">
                                <li>
                                    <a href="../pages/teacher/manage_classes.php" class="text-slate-600 hover:text-blue-600 font-bold text-xs uppercase italic flex items-center gap-3 group">
                                        <div class="w-2 h-2 bg-slate-200 rounded-full group-hover:bg-blue-600 transition-colors"></div> 
                                        Master Class List
                                    </a>
                                </li>
                                <li>
                                    <a href="../pages/common/profile.php" class="text-slate-600 hover:text-blue-600 font-bold text-xs uppercase italic flex items-center gap-3 group">
                                        <div class="w-2 h-2 bg-slate-200 rounded-full group-hover:bg-blue-600 transition-colors"></div> 
                                        Account Settings
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="p-8 rounded-[2.5rem] bg-blue-50 border border-blue-100 relative overflow-hidden">
                            <div class="absolute -right-4 -bottom-4 opacity-10 text-blue-600">
                                <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.047a1 1 0 00-1.6 0l-8.6 11a1 1 0 00.8 1.623H4.6l1.326 4.972a1 1 0 001.935 0L9.183 13.67l2.164 2.165a1 1 0 001.414 0l6-6a1 1 0 00-1.414-1.414l-5.343 5.343L10.293 12.05a1 1 0 00-1.414 0l-7.465 9.53a1 1 0 011.414 1.414l8.6-11z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                                <p class="font-black text-blue-900 text-[10px] uppercase tracking-widest italic">LMS Engine</p>
                            </div>
                            <p class="text-blue-800 text-[10px] font-bold leading-relaxed uppercase opacity-70">
                                Context-aware browsing enabled. Activities are now filtered by class environment to ensure grading precision.
                            </p>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/layout_footer.php'; ?>

</body>
</html>