<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$class_id = $_GET['class_id'] ?? null;
$student_id = $_SESSION['user_id'];

// Fetch data using the student-specific function that includes submission status
$activities = ($class_id) ? $db->getActivitiesByClassForStudent($class_id, $student_id) : [];
$class_info = ($class_id) ? $db->getClassDetails($class_id) : null;

$page_title = "Class Activities";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                
                <header class="mb-12 flex items-center gap-6">
                    <a href="student_dash.php" class="p-3 bg-white border border-slate-100 rounded-2xl text-slate-400 hover:text-blue-600 transition-all shadow-sm group">
                        <svg class="w-6 h-6 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter leading-none mb-2">
                            <?= htmlspecialchars($class_info['Class_Name'] ?? 'Class') ?> 
                            <span class="text-blue-600">Activities.</span>
                        </h2>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                            <p class="text-slate-400 font-bold uppercase text-[10px] tracking-widest italic">
                                Section: <?= htmlspecialchars($class_info['Section'] ?? 'N/A') ?> • Semester: <?= htmlspecialchars($class_info['Semester'] ?? 'N/A') ?>
                            </p>
                        </div>
                    </div>
                </header>

                <div class="grid gap-6 max-w-5xl">
                    <?php if (empty($activities)): ?>
                        <div class="glass-card p-20 text-center border-2 border-dashed border-slate-200 rounded-[2.5rem]">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                            <p class="text-slate-400 font-bold italic uppercase text-xs tracking-widest">No lab activities assigned yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $act): 
    // 🛠️ FIX: Fetch Smart Status (Ensures Group Members see Grade & Status)
    $statusData = $db->getStudentActivityStatus($act['ActivityID'], $student_id);
    
    // Override the basic list data with the smart data
    $rawStatus = $statusData['status']; 
    $act['Grade'] = $statusData['grade']; // This populates the missing grade for members

    // Configure Badges (Added 'Returned' case)
    $statusConfig = match($rawStatus) {
        'Graded' => [
            'label' => 'Graded',
            'color' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
            'icon' => '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'
        ],
        'Submitted' => [
            'label' => 'Submitted',
            'color' => 'bg-blue-50 text-blue-600 border-blue-100',
            'icon' => '<path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'
        ],
        'Returned' => [
            'label' => 'Revision Needed',
            'color' => 'bg-amber-50 text-amber-600 border-amber-100',
            'icon' => '<path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>'
        ],
        default => [
            'label' => 'New Task',
            'color' => 'bg-slate-100 text-slate-500 border-slate-200',
            'icon' => '<path d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>'
        ]
    };
?>
                            <div class="glass-card p-8 border-l-8 <?= $rawStatus === 'Graded' ? 'border-emerald-500' : 'border-blue-600' ?> flex flex-col md:flex-row justify-between items-center group hover:shadow-2xl transition-all duration-500">
                                <div class="mb-6 md:mb-0">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h4 class="text-2xl font-black text-slate-800 uppercase italic tracking-tighter group-hover:text-blue-600 transition-colors">
                                            <?= htmlspecialchars($act['Title']) ?>
                                        </h4>
                                        <span class="flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase border <?= $statusConfig['color'] ?>">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $statusConfig['icon'] ?></svg>
                                            <?= $statusConfig['label'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center gap-4">
                                        <div class="flex items-center gap-2">
                                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Deadline:</p>
                                            <span class="text-[10px] text-red-500 font-bold uppercase italic">
                                                <?= date('M d, Y - H:i', strtotime($act['Deadline'])) ?>
                                            </span>
                                        </div>
                                        
                                        <?php if($rawStatus === 'Graded'): ?>
                                            <div class="h-4 w-px bg-slate-200"></div>
                                            <div class="flex items-center gap-2">
                                                <p class="text-[9px] font-black text-emerald-600 uppercase tracking-widest italic">Result:</p>
                                                <span class="text-xs font-black text-emerald-600"><?= $act['Grade'] ?>/100</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4 w-full md:w-auto">
                                    <a href="activity_view.php?activity_id=<?= $act['ActivityID'] ?>" 
                                       class="flex-1 md:flex-none text-center bg-[#0f172a] text-white px-10 py-5 rounded-2xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-blue-600 transition-all shadow-xl active:scale-95">
                                        <?= $rawStatus === 'Graded' ? 'Review Grade' : 'Open Manual' ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div> 
            </main>
        </div>
    </div>

    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>