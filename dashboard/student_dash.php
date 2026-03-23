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

// --- MOCK DATA BLOCK ---
// To use real data from the database, change $useMockData to false.
$useMockData = true;

if ($useMockData) {
    $dashboardData = [
        'my_classes' => [
            ['ClassID' => 1, 'Class_Name' => 'General Chemistry', 'Section' => 'STEM-12A', 'Semester' => '1st Sem', 'TeacherName' => 'Jose Rizal'],
            ['ClassID' => 2, 'Class_Name' => 'General Physics', 'Section' => 'STEM-12A', 'Semester' => '1st Sem', 'TeacherName' => 'Andres Bonifacio'],
            ['ClassID' => 3, 'Class_Name' => 'General Biology', 'Section' => 'STEM-12A', 'Semester' => '1st Sem', 'TeacherName' => 'Apolinario Mabini'],
        ],
        'class_activity_counts' => [
            ['Class_Name' => 'General Chemistry', 'Section' => 'STEM-12A', 'activity_count' => 8],
            ['Class_Name' => 'General Physics', 'Section' => 'STEM-12A', 'activity_count' => 5],
            ['Class_Name' => 'General Biology', 'Section' => 'STEM-12A', 'activity_count' => 12],
        ],
        'upcoming_deadlines' => [
            ['ActivityID' => 101, 'Title' => 'Titration Experiment', 'Deadline' => date('Y-m-d H:i:s', strtotime('+3 days')), 'Class_Name' => 'General Chemistry', 'ClassID' => 1],
            ['ActivityID' => 102, 'Title' => 'Free Fall Lab Report', 'Deadline' => date('Y-m-d H:i:s', strtotime('+7 days')), 'Class_Name' => 'General Physics', 'ClassID' => 2],
            ['ActivityID' => 103, 'Title' => 'Cell Mitosis Observation', 'Deadline' => date('Y-m-d H:i:s', strtotime('+10 days')), 'Class_Name' => 'General Biology', 'ClassID' => 3],
        ],
        'pending_sessions' => 2, 'issued_sessions' => 1, 'unresolved_liabilities' => 1,
        'session_stats' => [ 'Pending' => 2, 'Approved' => 1, 'Issued' => 1, 'Returned' => 15, 'Cancelled' => 3, ],
    ];
} else {
    $db = new DataManager();
    $student_id = $_SESSION['user_id'];
    $dashboardData = $db->getStudentDashboardData($student_id);
}

// Extract data for easier access
$myClasses = $dashboardData['my_classes'] ?? [];
$classActivityCounts = $dashboardData['class_activity_counts'] ?? [];
$upcomingDeadlines = $dashboardData['upcoming_deadlines'] ?? [];
$pendingCount = $dashboardData['pending_sessions'] ?? 0;
$borrowedCount = $dashboardData['issued_sessions'] ?? 0;
$unresolvedLiabilities = $dashboardData['unresolved_liabilities'] ?? 0;
$sessionStats = $dashboardData['session_stats'] ?? [];

$page_title = "Student Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-image: radial-gradient(circle at top left, rgba(249, 115, 22, 0.04), transparent 35%);
        }
        .kpi-card {
            transition: all 0.3s ease-in-out;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
            border-color: #f97316;
            box-shadow: 0 10px 25px -5px rgba(249, 115, 22, 0.1), 0 8px 10px -6px rgba(249, 115, 22, 0.1);
        }
        .kpi-card.active-card {
            border-color: #f97316;
            box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.4), 0 10px 25px -5px rgba(249, 115, 22, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-10">
                    <h2 class="text-3xl font-bold text-gray-800">
                        Welcome, <span class="text-orange-500"><?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?>.</span>
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Here is an overview of your laboratory activities and status.</p>
                </header>

                <!-- KPI Grids -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- KPI Card: Enrolled Classes -->
                    <div id="kpi-card-classes" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6 active-card" data-target="graphs-classes">
                        <div class="w-14 h-14 rounded-full bg-gray-100 text-orange-500 flex items-center justify-center"><i class="fa-solid fa-chalkboard-user text-2xl"></i></div>
                        <div>
                            <p class="text-4xl font-black text-gray-900"><?= count($myClasses) ?></p>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Classes</p>
                        </div>
                    </div>
                    <!-- KPI Card: Pending Slips -->
                    <div id="kpi-card-pending" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-pending">
                        <div class="w-14 h-14 rounded-full bg-gray-100 text-amber-500 flex items-center justify-center"><i class="fa-solid fa-clock text-2xl"></i></div>
                        <div>
                            <p class="text-4xl font-black text-gray-900"><?= $pendingCount ?></p>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Pending Slips</p>
                        </div>
                    </div>
                    <!-- KPI Card: Borrowed Items -->
                    <div id="kpi-card-borrowed" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-borrowed">
                        <div class="w-14 h-14 rounded-full bg-gray-100 text-cyan-500 flex items-center justify-center"><i class="fa-solid fa-hand-holding-hand text-2xl"></i></div>
                        <div>
                            <p class="text-4xl font-black text-gray-900"><?= $borrowedCount ?></p>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Borrowed</p>
                        </div>
                    </div>
                    <!-- KPI Card: Liabilities -->
                    <div id="kpi-card-liabilities" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-liabilities">
                        <div class="w-14 h-14 rounded-full bg-gray-100 text-red-500 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation text-2xl"></i></div>
                        <div>
                            <p class="text-4xl font-black text-gray-900"><?= $unresolvedLiabilities ?></p>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Liabilities</p>
                        </div>
                    </div>
                </div>

                <!-- Visual Analytics -->
                <div id="visual-analytics" class="mt-8 flex flex-col gap-8">
                    <!-- Classes Graphs -->
                    <div id="graphs-classes" class="graph-container grid grid-cols-1 lg:grid-cols-2 gap-8" style="order: 0;">
                        <div class="lg:col-span-1 bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Activities per Class</h3>
                            <p class="text-sm text-gray-500 mb-6">Number of lab activities in each of your classes.</p>
                            <div class="h-80">
                                <canvas id="classActivityChart"></canvas>
                            </div>
                        </div>
                        <div class="lg:col-span-1 bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">My Classes</h3>
                            <p class="text-sm text-gray-500 mb-6">Quick access to your enrolled subjects.</p>
                            <div class="space-y-3 h-80 overflow-y-auto custom-scrollbar pr-2">
                                <?php if (empty($myClasses)): ?>
                                    <p class="text-center text-sm text-gray-400 pt-10">Not enrolled in any classes.</p>
                                <?php else: ?>
                                    <?php foreach ($myClasses as $class): ?>
                                        <a href="../pages/student/lab_list.php?class_id=<?= $class['ClassID'] ?>" class="block p-4 border-2 border-gray-100 rounded-2xl hover:border-orange-500 hover:bg-orange-50 transition-all duration-300 group">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="font-bold text-gray-800 group-hover:text-orange-600 transition-colors"><?= htmlspecialchars($class['Class_Name'] . ' - ' . $class['Section']) ?></p>
                                                    <p class="text-xs text-gray-500 mt-1">Instructor: <?= htmlspecialchars($class['TeacherName']) ?></p>
                                                </div>
                                                <span class="px-4 py-2 text-xs font-bold text-orange-600 bg-orange-100 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">&rarr;</span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Slips Graph -->
                    <div id="graphs-pending" class="graph-container grid grid-cols-1 gap-8" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">My Transaction Status</h3>
                            <p class="text-sm text-gray-500 mb-6">A summary of all your borrowing slips by status.</p>
                            <div class="h-80">
                                <canvas id="sessionStatusChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Borrowed Items / Deadlines Graph -->
                    <div id="graphs-borrowed" class="graph-container grid grid-cols-1 gap-8" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Upcoming Deadlines</h3>
                            <p class="text-sm text-gray-500 mb-6">Your next 5 activity deadlines.</p>
                            <div class="h-80">
                                <canvas id="upcomingDeadlinesChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Liabilities Info -->
                    <div id="graphs-liabilities" class="graph-container grid grid-cols-1 gap-8" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200 text-center">
                            <div class="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fa-solid fa-file-invoice-dollar text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Manage Liabilities</h3>
                            <?php if ($unresolvedLiabilities > 0): ?>
                                <p class="text-sm text-gray-500 mb-6">You have <?= $unresolvedLiabilities ?> unresolved case(s). Please settle them to ensure clearance.</p>
                                <a href="../pages/student/settlement_cases.php" class="inline-block bg-red-500 text-white px-8 py-3 rounded-xl font-bold text-sm hover:bg-red-600 transition-all shadow-lg shadow-red-500/20">
                                    Go to Settlement Page
                                </a>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 mb-6">You have no pending liabilities. Great job!</p>
                                <a href="../pages/student/active_slips.php" class="inline-block bg-green-500 text-white px-8 py-3 rounded-xl font-bold text-sm hover:bg-green-600 transition-all shadow-lg shadow-green-500/20">
                                    View My History
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        window.myCharts = [];

        function initCharts() {
            // Data from PHP
            const classActivityData = <?= json_encode($classActivityCounts) ?>;
            const sessionStatsData = <?= json_encode($sessionStats) ?>;
            const upcomingDeadlinesData = <?= json_encode($upcomingDeadlines) ?>;

            // Chart Colors
            const chartColors = [
                'rgba(249, 115, 22, 0.7)',  // orange-500
                'rgba(6, 182, 212, 0.7)',   // cyan-500
                'rgba(139, 92, 246, 0.7)',  // violet-500
                'rgba(234, 179, 8, 0.7)',   // yellow-500
                'rgba(16, 185, 129, 0.7)',  // emerald-500
            ];
            const chartBorderColors = chartColors.map(c => c.replace('0.7', '1'));

            // Chart 1: Class Activities
            const classActivityChart = new Chart(document.getElementById('classActivityChart'), {
                type: 'bar',
                data: {
                    labels: classActivityData.map(d => `${d.Class_Name} (${d.Section})`),
                    datasets: [{
                        label: 'Number of Activities',
                        data: classActivityData.map(d => d.activity_count),
                        backgroundColor: chartColors,
                        borderColor: chartBorderColors,
                        borderWidth: 2,
                        borderRadius: 8,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            window.myCharts.push(classActivityChart);

            // Chart 2: Session Status
            const sessionStatusChart = new Chart(document.getElementById('sessionStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(sessionStatsData),
                    datasets: [{
                        data: Object.values(sessionStatsData),
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#6b7280', '#8b5cf6'], // Amber, Blue, Emerald, Gray, Violet
                        borderWidth: 8,
                        borderColor: '#fff'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
            });
            window.myCharts.push(sessionStatusChart);

            // Chart 3: Upcoming Deadlines
            const upcomingDeadlinesChart = new Chart(document.getElementById('upcomingDeadlinesChart'), {
                type: 'bar',
                data: {
                    labels: upcomingDeadlinesData.map(d => d.Title),
                    datasets: [{
                        label: 'Days Remaining',
                        data: upcomingDeadlinesData.map(d => {
                            const deadline = new Date(d.Deadline);
                            const now = new Date();
                            const diffTime = deadline - now;
                            return Math.max(0, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
                        }),
                        backgroundColor: chartColors,
                        borderColor: chartBorderColors,
                        borderWidth: 2,
                        borderRadius: 8,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            window.myCharts.push(upcomingDeadlinesChart);
        }

        document.addEventListener('DOMContentLoaded', () => {
            initCharts();
            
            const kpiCards = document.querySelectorAll('.kpi-card');
            const graphContainers = Array.from(document.querySelectorAll('.graph-container'));

            kpiCards.forEach(card => {
                card.addEventListener('click', () => {
                    kpiCards.forEach(c => c.classList.remove('active-card'));
                    card.classList.add('active-card');

                    const targetId = card.dataset.target;
                    const firstPositions = new Map();
                    graphContainers.forEach(c => firstPositions.set(c, c.getBoundingClientRect()));

                    graphContainers.forEach(c => {
                        c.style.order = (c.id === targetId) ? '0' : '1';
                    });

                    graphContainers.forEach(c => {
                        const lastPos = c.getBoundingClientRect();
                        const firstPos = firstPositions.get(c);
                        const dx = firstPos.left - lastPos.left;
                        const dy = firstPos.top - lastPos.top;

                        if (dx !== 0 || dy !== 0) {
                            requestAnimationFrame(() => {
                                c.style.transform = `translate(${dx}px, ${dy}px)`;
                                c.style.transition = 'transform 0s';
                                requestAnimationFrame(() => {
                                    c.style.transform = '';
                                    c.style.transition = 'transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                                });
                            });
                        }
                    });
                });
            });
        });
    </script>
    <?php include '../includes/layout_footer.php'; ?>
</body>
</html>