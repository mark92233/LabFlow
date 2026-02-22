    <?php
    session_start();
    require_once '../../dbRelated/operation.php';

    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Teacher') {
        header("Location: ../../index.php");
        exit();
    }

    $db = new DataManager();
    $class_id = $_GET['class_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    // Fetch Class Context and Activities
    $activities = $db->getActivitiesByClass($class_id);
    // Simplified check: usually you'd fetch class name via a specific method, 
    // here we use the first activity's class name or a generic title.
    $page_title = "Class Activities";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Class Activities | SNHS</title>
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
            <a href="../../dashboard/router.php" class="text-xs font-bold text-blue-600 uppercase tracking-widest hover:underline">← Back to Dashboard</a>
            <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter mt-2">Class <span class="text-blue-600">Activities</span></h2>
        </div>
        
        <div class="flex gap-3">
            <a href="clearance_hub.php?class_id=<?= $class_id ?>" 
            class="bg-white border border-slate-200 text-slate-600 px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest hover:border-blue-500 hover:text-blue-600 transition-all shadow-sm">
            Clearance Hub
            </a>
            
            <a href="add_activity.php?class_id=<?= $class_id ?>" 
            class="bg-blue-600 text-white px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-[#0f172a] transition-all shadow-lg shadow-blue-600/30">
            Post New Lab
            </a>
        </div>
    </header>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if (empty($activities)): ?>
                            <div class="col-span-full py-20 text-center border-2 border-dashed border-slate-200 rounded-[2rem]">
                                <p class="text-slate-400 italic">No lab activities posted for this class yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activities as $act): ?>
    <a href="activity_hub.php?activity_id=<?= $act['ActivityID'] ?>&class_id=<?= $class_id ?>" class="group">
                                    <div class="glass-card p-8 border border-slate-100 group-hover:border-blue-500 group-hover:shadow-xl transition-all h-full">
                                        <h3 class="text-xl font-black text-slate-800 uppercase italic mb-3 group-hover:text-blue-600"><?= htmlspecialchars($act['Title']) ?></h3>
                                        <p class="text-slate-500 text-xs line-clamp-3 mb-6"><?= htmlspecialchars($act['Description']) ?></p>
                                        <div class="flex justify-between items-center pt-4 border-t border-slate-50">
                                            <span class="text-[9px] font-black text-slate-300 uppercase">Deadline</span>
                                            <span class="text-[10px] font-bold text-red-500 uppercase italic"><?= date('M d, Y', strtotime($act['Deadline'])) ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </main>
            </div>
        </div>
    </body>
    </html>