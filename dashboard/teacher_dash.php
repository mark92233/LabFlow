<?php
session_start();
require_once '../dbRelated/operation.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Teacher') {
    header("Location: ../index.php");
    exit();
}

// --- MOCK DATA BLOCK ---
// To use real data from the database, change $useMockData to false.
$useMockData = true;

if ($useMockData) {
    $dashboardData = [
        'total_students' => 77,
        'pending_submissions' => 12,
        'total_classes' => 3,
        'clearance_progress' => [
            [ 'ClassID' => 1, 'Class_Name' => 'BS in Biology', 'Section' => 'BIO-1A', 'total_students' => 25, 'cleared_students' => 20 ],
            [ 'ClassID' => 2, 'Class_Name' => 'BS in Chemistry', 'Section' => 'CHEM-2B', 'total_students' => 30, 'cleared_students' => 30 ],
            [ 'ClassID' => 3, 'Class_Name' => 'BS in Physics', 'Section' => 'PHY-3C', 'total_students' => 22, 'cleared_students' => 15 ],
        ],
        'upcoming_deadlines' => [
            [ 'ActivityID' => 101, 'ClassID' => 1, 'Title' => 'Lab Report on Cell Mitosis', 'Class_Name' => 'BS in Biology', 'Deadline' => date('Y-m-d H:i:s', strtotime('+2 days')) ],
            [ 'ActivityID' => 102, 'ClassID' => 2, 'Title' => 'Titration Experiment Analysis', 'Class_Name' => 'BS in Chemistry', 'Deadline' => date('Y-m-d H:i:s', strtotime('+5 days')) ],
            [ 'ActivityID' => 103, 'ClassID' => 3, 'Title' => 'Newtonian Physics Problem Set', 'Class_Name' => 'BS in Physics', 'Deadline' => date('Y-m-d H:i:s', strtotime('+1 week')) ],
        ],
        'students_with_liabilities' => [
            ['Full_Name' => 'Jomar Jun', 'MasterID' => 1001, 'damage_id' => 1],
            ['Full_Name' => 'Kim Solis', 'MasterID' => 1002, 'damage_id' => 2],
            ['Full_Name' => 'Jane Doe', 'MasterID' => 1003, 'damage_id' => 3],
        ],
        'frequently_damaged_items' => [
            ['Item_Name' => 'Beaker 250ml', 'damage_count' => 5],
            ['Item_Name' => 'Test Tube', 'damage_count' => 3],
            ['Item_Name' => 'Microscope Slide', 'damage_count' => 2],
        ],
    ];
} else {
    // This block fetches live data from the database.
    $db = new DataManager();
    $teacher_id = $_SESSION['user_id'];
    $dashboardData = $db->getTeacherDashboardData($teacher_id);
}
// --- END OF MOCK DATA BLOCK ---

$page_title = "Teacher Dashboard";

function format_time_ago($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->d > 0) return 'in ' . $diff->d . ' day(s)';
    if ($diff->h > 0) return 'in ' . $diff->h . ' hour(s)';
    if ($diff->i > 0) return 'in ' . $diff->i . ' minute(s)';
    return 'Due now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-8">
                    <h2 class="text-4xl font-extrabold text-gray-800 tracking-tighter">
                        Welcome, <span class="text-orange-500"><?= htmlspecialchars($_SESSION['user_name']) ?>.</span>
                    </h2>
                    <p class="text-slate-400 font-medium text-sm">Here's your command center for today.</p>
                </header>

                <!-- Section 1: Action Center -->
                <section class="mb-10">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Action Center</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Total Students -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div class="space-y-1">
                                    <p class="text-sm font-bold text-slate-500">Total Students</p>
                                    <p class="text-4xl font-black text-slate-800"><?= $dashboardData['total_students'] ?? 0 ?></p>
                                </div>
                                <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                </div>
                            </div>
                        </div>
                        <!-- Submissions to Grade -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div class="space-y-1">
                                    <p class="text-sm font-bold text-slate-500">Submissions to Grade</p>
                                    <p class="text-4xl font-black text-slate-800"><?= $dashboardData['pending_submissions'] ?? 0 ?></p>
                                </div>
                                <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                            </div>
                        </div>
                        <!-- Number of Classes -->
                        <div class="bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div class="space-y-1">
                                    <p class="text-sm font-bold text-slate-500">Number of Classes</p>
                                    <p class="text-4xl font-black text-slate-800"><?= $dashboardData['total_classes'] ?? 0 ?></p>
                                </div>
                                <div class="w-12 h-12 bg-green-50 text-green-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Section 2: Class & Activity Pulse -->
                    <section class="lg:col-span-2 space-y-8">
                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Class Clearance Progress</h3>
                            <div class="bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm space-y-5">
                                <?php if (empty($dashboardData['clearance_progress'])): ?>
                                    <p class="text-sm text-slate-400 italic">No classes found.</p>
                                <?php else: ?>
                                    <?php foreach ($dashboardData['clearance_progress'] as $class):
                                        $total = (int)($class['total_students'] ?? 0);
                                        $cleared = (int)($class['cleared_students'] ?? 0);
                                        $percentage = ($total > 0) ? ($cleared / $total) * 100 : 0;
                                    ?>
                                    <a href="../pages/teacher/clearance_hub.php?class_id=<?= $class['ClassID'] ?>" class="block hover:bg-slate-50 p-3 rounded-lg">
                                        <div class="flex justify-between items-center mb-2">
                                            <p class="font-bold text-sm text-slate-700"><?= htmlspecialchars($class['Class_Name'] . ' - ' . $class['Section']) ?></p>
                                            <p class="text-xs font-bold text-slate-500"><?= $cleared ?> / <?= $total ?> Cleared</p>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-2.5">
                                            <div class="bg-green-500 h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Upcoming Deadlines</h3>
                            <div class="bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm space-y-3">
                                <?php if (empty($dashboardData['upcoming_deadlines'])): ?>
                                    <p class="text-sm text-slate-400 italic">No upcoming deadlines.</p>
                                <?php else: ?>
                                    <?php foreach ($dashboardData['upcoming_deadlines'] as $activity): ?>
                                    <a href="../pages/teacher/activity_hub.php?activity_id=<?= $activity['ActivityID'] ?>&class_id=<?= $activity['ClassID'] ?>" class="flex items-center justify-between p-3 hover:bg-slate-50 rounded-lg">
                                        <div>
                                            <p class="font-bold text-sm text-slate-700"><?= htmlspecialchars($activity['Title']) ?></p>
                                            <p class="text-xs text-slate-400"><?= htmlspecialchars($activity['Class_Name']) ?></p>
                                        </div>
                                        <span class="text-xs font-bold text-red-500 bg-red-50 px-2 py-1 rounded-md"><?= format_time_ago($activity['Deadline']) ?></span>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <!-- Section 3: Risk Management -->
                    <section class="space-y-8">
                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Students with Open Liabilities</h3>
                            <div class="bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm space-y-2">
                                <?php if (empty($dashboardData['students_with_liabilities'])): ?>
                                    <p class="text-sm text-slate-400 italic">All students are cleared.</p>
                                <?php else: ?>
                                    <?php foreach ($dashboardData['students_with_liabilities'] as $student): ?>
                                    <a href="../pages/teacher/settlement_reviews.php" class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-black text-slate-400">
                                            <?= substr($student['Full_Name'], 0, 1) ?>
                                        </div>
                                        <p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($student['Full_Name']) ?></p>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Frequently Damaged Items</h3>
                            <div class="bg-white p-6 rounded-2xl border border-slate-200/50 shadow-sm space-y-3">
                                <?php if (empty($dashboardData['frequently_damaged_items'])): ?>
                                    <p class="text-sm text-slate-400 italic">No damage trends recorded yet.</p>
                                <?php else: ?>
                                    <?php foreach ($dashboardData['frequently_damaged_items'] as $item): ?>
                                    <div class="flex items-center justify-between p-2">
                                        <p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($item['Item_Name']) ?></p>
                                        <span class="text-xs font-black text-red-500 bg-red-100 px-2 py-1 rounded-md"><?= $item['damage_count'] ?> reports</span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

</body>
</html>