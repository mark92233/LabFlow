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
        'total_activities' => 25,
        'total_classes' => 3,
        'pending_requests' => 8,
        'my_classes' => [
            ['ClassID' => 1, 'Class_Name' => 'BS in Biology', 'Section' => 'BIO-1A'],
            ['ClassID' => 2, 'Class_Name' => 'BS in Chemistry', 'Section' => 'CHEM-2B'],
            ['ClassID' => 3, 'Class_Name' => 'BS in Physics', 'Section' => 'PHY-3C'],
        ],
        'upcoming_deadlines' => [
            [ 'Title' => 'Lab Report on Cell Mitosis', 'Deadline' => date('Y-m-d H:i:s', strtotime('+2 days')) ],
            [ 'Title' => 'Titration Experiment Analysis', 'Deadline' => date('Y-m-d H:i:s', strtotime('+5 days')) ],
            [ 'Title' => 'Newtonian Physics Problem Set', 'Deadline' => date('Y-m-d H:i:s', strtotime('+1 week')) ],
            [ 'Title' => 'Organic Synthesis', 'Deadline' => date('Y-m-d H:i:s', strtotime('+10 days')) ],
        ],
        'borrowing_by_class' => [
            ['Class_Name' => 'BS in Chemistry', 'Section' => 'CHEM-2B', 'session_count' => 45],
            ['Class_Name' => 'BS in Biology', 'Section' => 'BIO-1A', 'session_count' => 32],
            ['Class_Name' => 'BS in Physics', 'Section' => 'PHY-3C', 'session_count' => 18],
        ],
        'recent_activities' => [
            ['ActivityID' => 1, 'ClassID' => 1, 'Title' => 'Cell Mitosis Report', 'CreatedAt' => date('Y-m-d H:i:s', strtotime('-1 day'))],
            ['ActivityID' => 2, 'ClassID' => 2, 'Title' => 'Titration Analysis', 'CreatedAt' => date('Y-m-d H:i:s', strtotime('-3 days'))],
            ['ActivityID' => 3, 'ClassID' => 3, 'Title' => 'Newtonian Physics Set', 'CreatedAt' => date('Y-m-d H:i:s', strtotime('-5 days'))],
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
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
            position: relative;
            overflow: hidden;
        }
        .kpi-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(135deg, rgba(249, 115, 22, 0.05), transparent 40%);
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        .kpi-card:hover:before {
            opacity: 1;
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
        .kpi-icon {
            transition: transform 0.3s ease-in-out;
        }
        .kpi-card:hover .kpi-icon {
            transform: scale(1.1) rotate(-5deg);
        }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-10">
                    <h2 class="text-3xl font-bold text-gray-800">
                        Welcome, <span class="text-orange-500"><?= htmlspecialchars($_SESSION['user_name']) ?>.</span>
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Here is an overview of your classes and laboratory activities.</p>
                </header>

                <!-- KPI Grids -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div id="kpi-card-classes" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-classes">
                        <div class="kpi-icon w-14 h-14 rounded-full bg-gray-100 text-orange-500 flex items-center justify-center"><i class="fa-solid fa-chalkboard-user text-2xl"></i></div>
                        <div>
                            <p class="text-4xl font-black text-gray-900"><?= $dashboardData['total_classes'] ?? 0 ?></p>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Classes</p>
                        </div>
                    </div>
                    <div id="kpi-card-students" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-students">
                        <div class="kpi-icon w-14 h-14 rounded-full bg-gray-100 text-cyan-500 flex items-center justify-center"><i class="fa-solid fa-users text-2xl"></i></div>
                        <div>
                            <p class="text-4xl font-black text-gray-900"><?= $dashboardData['total_students'] ?? 0 ?></p>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Students</p>
                        </div>
                    </div>
                    <div id="kpi-card-activities" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6 active-card" data-target="graphs-activities">
                        <div class="kpi-icon w-14 h-14 rounded-full bg-gray-100 text-violet-500 flex items-center justify-center"><i class="fa-solid fa-flask-vial text-2xl"></i></div>
                        <div>
                            <p class="text-4xl font-black text-gray-900"><?= $dashboardData['total_activities'] ?? 0 ?></p>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Activities</p>
                        </div>
                    </div>
                    <div id="kpi-card-requests" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-requests">
                        <div class="kpi-icon w-14 h-14 rounded-full bg-gray-100 text-amber-500 flex items-center justify-center"><i class="fa-solid fa-clock text-2xl"></i></div>
                        <div>
                            <p class="text-4xl font-black text-gray-900"><?= $dashboardData['pending_requests'] ?? 0 ?></p>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Pending Requests</p>
                        </div>
                    </div>
                </div>

                <!-- Visual Analytics -->
                <div id="visual-analytics" class="mt-8 flex flex-col gap-8">
                    <div id="graphs-activities" class="graph-container grid grid-cols-1 lg:grid-cols-2 gap-8" style="order: 0;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Borrowing Activity by Class</h3>
                            <p class="text-sm text-gray-500 mb-6">Total number of borrowing sessions initiated per class.</p>
                            <div class="h-80">
                                <canvas id="borrowingByClassChart"></canvas>
                            </div>
                        </div>
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Recently Posted Activities</h3>
                            <p class="text-sm text-gray-500 mb-6">A quick look at your 5 most recent lab activities.</p>
                            <div class="space-y-3 h-80 overflow-y-auto custom-scrollbar pr-2">
                                <?php if (empty($dashboardData['recent_activities'])): ?>
                                    <p class="text-center text-sm text-gray-400 pt-10">No recent activities found.</p>
                                <?php else: ?>
                                    <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                                        <a href="../pages/teacher/activity_hub.php?activity_id=<?= $activity['ActivityID'] ?>&class_id=<?= $activity['ClassID'] ?>" class="block p-4 border-2 border-gray-100 rounded-2xl hover:border-violet-500 hover:bg-violet-50 transition-all duration-300 group">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="font-bold text-gray-800 group-hover:text-violet-600 transition-colors"><?= htmlspecialchars($activity['Title']) ?></p>
                                                    <p class="text-xs text-gray-500 mt-1">Posted: <?= date('M d, Y', strtotime($activity['CreatedAt'])) ?></p>
                                                </div>
                                                <span class="px-4 py-2 text-xs font-bold text-violet-600 bg-violet-100 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">&rarr;</span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="graphs-requests" class="graph-container grid grid-cols-1 gap-8" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Upcoming Activity Deadlines</h3>
                            <p class="text-sm text-gray-500 mb-6">The next 5 deadlines across all your classes.</p>
                            <div class="h-80">
                                <canvas id="upcomingDeadlinesChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div id="graphs-classes" class="graph-container grid grid-cols-1 gap-8" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">My Class Registry</h3>
                            <p class="text-sm text-gray-500 mb-6">Quick access to manage your classes.</p>
                            <div class="space-y-3 h-80 overflow-y-auto custom-scrollbar pr-2">
                                <?php if (empty($dashboardData['my_classes'])): ?>
                                    <p class="text-center text-sm text-gray-400 pt-10">You have not created any classes yet.</p>
                                <?php else: ?>
                                    <?php foreach ($dashboardData['my_classes'] as $class): ?>
                                        <a href="../pages/teacher/class_list.php?id=<?= $class['ClassID'] ?>" class="block p-4 border-2 border-gray-100 rounded-2xl hover:border-orange-500 hover:bg-orange-50 transition-all duration-300 group">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="font-bold text-gray-800 group-hover:text-orange-600 transition-colors"><?= htmlspecialchars($class['Class_Name'] . ' - ' . $class['Section']) ?></p>
                                                </div>
                                                <span class="px-4 py-2 text-xs font-bold text-orange-600 bg-orange-100 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity">Manage &rarr;</span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
     <?php include '../includes/layout_footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Data from PHP
            const borrowingData = <?= json_encode($dashboardData['borrowing_by_class'] ?? []) ?>;
            const deadlinesData = <?= json_encode($dashboardData['upcoming_deadlines'] ?? []) ?>;

            // Chart Colors
            const chartColors = ['rgba(249, 115, 22, 0.7)', 'rgba(6, 182, 212, 0.7)', 'rgba(139, 92, 246, 0.7)', 'rgba(234, 179, 8, 0.7)', 'rgba(16, 185, 129, 0.7)'];
            const chartBorderColors = chartColors.map(c => c.replace('0.7', '1'));
            // Borrowing by Class
            new Chart(document.getElementById('borrowingByClassChart'), {
                type: 'doughnut',
                data: {
                    labels: borrowingData.map(d => `${d.Class_Name} (${d.Section})`),
                    datasets: [{ data: borrowingData.map(d => d.session_count), backgroundColor: chartColors, borderWidth: 8, borderColor: '#fff' }]
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
            });

            // Upcoming Deadlines
            new Chart(document.getElementById('upcomingDeadlinesChart'), {
                type: 'bar',
                data: {
                    labels: deadlinesData.map(d => d.Title),
                    datasets: [{
                        label: 'Days Remaining',
                        data: deadlinesData.map(d => Math.max(0, Math.ceil((new Date(d.Deadline) - new Date()) / (1000 * 60 * 60 * 24)))),
                        backgroundColor: chartColors,
                        borderColor: chartBorderColors,
                        borderWidth: 2,
                        borderRadius: 8,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });

            // KPI Card Click Handler
            const kpiCards = document.querySelectorAll('.kpi-card');
            const graphContainers = Array.from(document.querySelectorAll('.graph-container'));
            kpiCards.forEach(card => {
                card.addEventListener('click', () => {
                    kpiCards.forEach(c => c.classList.remove('active-card'));
                    card.classList.add('active-card');
                    const targetId = card.dataset.target;
                    const firstPositions = new Map();
                    graphContainers.forEach(c => firstPositions.set(c, c.getBoundingClientRect()));
                    graphContainers.forEach(c => { c.style.order = (c.id === targetId) ? '0' : '1'; });
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
</body>
</html>