<?php
session_start();
require_once __DIR__ . '/../dbRelated/operation.php';

// Role-based Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$db = new DataManager();
$role = $_SESSION['user_role'];

// Fetch data
$adminKPI = $db->getAdminKPIs();
$pendingCount = $adminKPI['pending_reqs'] ?? 0;

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
<body class="bg-gray-50 min-h-screen">

    <div class="flex min-h-screen">
        
        <?php include '../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            
            <?php include '../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-800">Analytics Dashboard</h2>
                    <p class="text-sm text-gray-500 mt-1">An overview of key laboratory metrics.</p>
                </header>

                <!-- Admin KPI Grids -->
                <div class="space-y-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- KPI Card: Total Stock -->
                        <div id="kpi-card-stock" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-stock">
                            <div class="w-14 h-14 rounded-full bg-gray-100 text-orange-500 flex items-center justify-center"><i class="fa-solid fa-boxes-stacked text-2xl"></i></div>
                            <div>
                                <p class="text-4xl font-black text-gray-900"><?= number_format($adminKPI['total_stock'] ?? 0) ?></p>
                                <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Total Stock</p>
                            </div>
                        </div>
                            <!-- KPI Card: Users -->
                        <div id="kpi-card-users" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-users">
                            <div class="w-14 h-14 rounded-full bg-gray-100 text-orange-500 flex items-center justify-center"><i class="fa-solid fa-users text-2xl"></i></div>
                            <div>
                                <p class="text-4xl font-black text-gray-900"><?= number_format($adminKPI['total_users'] ?? 0) ?></p>
                                <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Users</p>
                            </div>
                        </div>
                        <!-- KPI Card: Borrow Sessions -->
                        <div id="kpi-card-borrow" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6 active-card" data-target="graphs-borrow">
                            <div class="w-14 h-14 rounded-full bg-gray-100 text-amber-500 flex items-center justify-center"><i class="fa-solid fa-clock text-2xl"></i></div>
                            <div>
                                <p class="text-4xl font-black text-gray-900"><?= number_format($adminKPI['pending_reqs'] ?? 0) ?></p>
                                <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Borrow Sessions</p>
                            </div>
                        </div>
                        <!-- KPI Card: Damages -->
                        <div id="kpi-card-damages" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-damages">
                            <div class="w-14 h-14 rounded-full bg-gray-100 text-amber-500 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation text-2xl"></i></div>
                            <div>
                                <p class="text-4xl font-black text-gray-900"><?= number_format($adminKPI['open_damages'] ?? 0) ?></p>
                                <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Damages</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visual Analytics -->
                <div id="visual-analytics" class="mt-8 flex flex-col gap-8">
                    <!-- Stock Graphs -->
                    <div id="graphs-stock" class="graph-container grid grid-cols-1 lg:grid-cols-10 gap-8" style="order: 1;">
                        <div class="lg:col-span-3 bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Inventory Composition</h3>
                            <p class="text-sm text-gray-500 mb-6">Distribution of items across categories.</p>
                            <div class="h-96">
                                <canvas id="inventoryChart"></canvas>
                            </div>
                        </div>
                        <div class="lg:col-span-7 bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Lowest Stock Items</h3>
                            <p class="text-sm text-gray-500 mb-6">Top 20 items with the lowest available quantity.</p>
                            <div class="h-96">
                                <canvas id="lowestStockChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- User Graphs -->
                    <div id="graphs-users" class="graph-container grid grid-cols-1 gap-8" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Class Demographics</h3>
                            <p class="text-sm text-gray-500 mb-6">Student and teacher population per class.</p>
                            <div class="h-96">
                                <canvas id="userPopulationChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Borrow Session Graphs -->
                    <div id="graphs-borrow" class="graph-container grid grid-cols-1 gap-8" style="order: 0;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Borrowing Activity</h3>
                            <p class="text-sm text-gray-500 mb-6">Total requisitions grouped by status.</p>
                            <div class="h-72">
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Damage Graphs -->
                    <div id="graphs-damages" class="graph-container grid grid-cols-1 lg:grid-cols-2 gap-8" style="order: 1;">
                            <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Overall Damage Status</h3>
                            <p class="text-sm text-gray-500 mb-6">Breakdown of all reported damages.</p>
                            <div class="h-72 flex justify-center">
                                <canvas id="damageOverviewChart" style="max-width: 320px;"></canvas>
                            </div>
                        </div>
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Unresolved Cases</h3>
                            <p class="text-sm text-gray-500 mb-6">Breakdown of cases needing attention.</p>
                            <div class="h-72">
                                <canvas id="unresolvedDetailChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                        window.myCharts = [];

                        function initCharts() {
                            // Data from PHP
                            const catData = <?= json_encode($adminKPI['categories'] ?? []) ?>;
                            const sessData = <?= json_encode($adminKPI['session_stats'] ?? []) ?>;
                            const lowestStockData = <?= json_encode($adminKPI['lowest_stock_items'] ?? []) ?>;
                            const userPopData = <?= json_encode($adminKPI['user_population_by_role'] ?? []) ?>;
                            const damageData = <?= json_encode($adminKPI['damage_stats'] ?? []) ?>;
                            const totalClasses = <?= $adminKPI['total_classes'] ?? 0 ?>;
                            const classDemographicsData = <?= json_encode($adminKPI['class_demographics'] ?? []) ?>;

                            // Chart Initializations
                            const inventoryChart = new Chart(document.getElementById('inventoryChart'), {
                                type: 'radar',
                                data: {
                                    labels: catData.map(d => d.Category_Name),
                                    datasets: [{
                                        label: 'Items per Category',
                                        data: catData.map(d => d.count),
                                        fill: true,
                                        backgroundColor: 'rgba(249, 115, 22, 0.2)',
                                        borderColor: 'rgba(249, 115, 22, 1)',
                                        pointBackgroundColor: 'rgba(249, 115, 22, 1)',
                                        pointBorderColor: '#fff',
                                        pointHoverBackgroundColor: '#fff',
                                        pointHoverBorderColor: 'rgba(249, 115, 22, 1)'
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { display: false, labels: { color: '#4b5563' } } },
                                    scales: {
                                        r: {
                                            beginAtZero: true,
                                            angleLines: { color: 'rgba(0, 0, 0, 0.05)' },
                                            grid: { color: 'rgba(0, 0, 0, 0.05)' },
                                            pointLabels: { display: false },
                                            ticks: {
                                                display: false,
                                                backdropColor: 'rgba(0,0,0,0)',
                                                color: 'transparent'
                                            }
                                        },
                                    },
                                    datasets: {
                                        radar: { borderColor: '#ffffff' }
                                    },
                                }
                            });
                            window.myCharts.push(inventoryChart);

                            const activityChart = new Chart(document.getElementById('activityChart'), {
                                type: 'line',
                                data: {
                                    labels: sessData.map(d => d.Status),
                                    datasets: [{
                                        label: 'Sessions',
                                        data: sessData.map(d => d.count),
                                        backgroundColor: 'rgba(249, 115, 22, 0.2)',
                                        borderColor: 'rgba(249, 115, 22, 1)',
                                        borderWidth: 3,
                                        pointBackgroundColor: 'rgba(249, 115, 22, 1)',
                                        pointRadius: 4,
                                        pointHoverRadius: 6,
                                        tension: 0.3,
                                        fill: true,
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    scales: { 
                                        y: { beginAtZero: true, grid: { drawBorder: false, color: 'rgba(0, 0, 0, 0.05)' }, ticks: { precision: 0, color: '#6b7280' } }, 
                                        x: { grid: { display: false }, ticks: { color: '#6b7280' } } 
                                    },
                                    plugins: { legend: { display: false, labels: { color: '#4b5563' } } },
                                    interaction: { intersect: false, mode: 'index' }
                                }
                            });
                            window.myCharts.push(activityChart);

                            const lowestStockChart = new Chart(document.getElementById('lowestStockChart'), {
                                type: 'line',
                                data: {
                                    labels: lowestStockData.map(d => d.Item_Name),
                                    datasets: [{
                                        label: 'Available Quantity',
                                        data: lowestStockData.map(d => d.Available_Qty),
                                        backgroundColor: 'rgba(249, 115, 22, 0.2)',
                                        borderColor: 'rgba(249, 115, 22, 1)',
                                        borderWidth: 3,
                                        pointBackgroundColor: 'rgba(249, 115, 22, 1)',
                                        pointRadius: 4,
                                        pointHoverRadius: 6,
                                        tension: 0.3,
                                        fill: true,
                                    }]
                                },
                                options: {
                                    responsive: true, 
                                    maintainAspectRatio: false,
                                    scales: { 
                                        y: { 
                                            beginAtZero: true,
                                            grid: { drawBorder: false, color: 'rgba(0, 0, 0, 0.05)' }, 
                                            ticks: { precision: 0, color: '#6b7280' } 
                                        }, 
                                        x: { 
                                            grid: { display: false },
                                            ticks: { color: '#6b7280' }
                                        } 
                                    },
                                    plugins: { legend: { display: false, labels: { color: '#4b5563' } } },
                                }
                            });
                            window.myCharts.push(lowestStockChart);

                            const demographicLabels = ['Teachers', 'Students'];
                            const demographicDatasets = classDemographicsData.map((classData, index) => {
                                const colors = [
                                    'rgba(249, 115, 22, 1)',  // orange-500
                                    'rgba(6, 182, 212, 1)',   // cyan-500
                                    'rgba(139, 92, 246, 1)',  // violet-500
                                    'rgba(234, 179, 8, 1)',   // yellow-500
                                    'rgba(236, 72, 153, 1)',  // pink-500
                                    'rgba(16, 185, 129, 1)',  // emerald-500
                                    'rgba(239, 68, 68, 1)',   // red-500
                                    'rgba(34, 197, 94, 1)',   // green-500
                                ];
                                const color = colors[index % colors.length];
                                const bgColor = color.replace(', 1)', ', 0.2)');

                                return {
                                    label: `${classData.Class_Name} - ${classData.Section}`,
                                    data: [1, classData.student_count], // [teacher_count, student_count]
                                    borderColor: color,
                                    backgroundColor: bgColor,
                                    borderWidth: 2,
                                    borderRadius: 8,
                                };
                            });

                            const userPopulationChart = new Chart(document.getElementById('userPopulationChart'), {
                                type: 'bar',
                                data: {
                                    labels: demographicLabels,
                                    datasets: demographicDatasets
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    scales: { 
                                        y: { 
                                            beginAtZero: true,
                                            grid: { drawBorder: false, color: 'rgba(0, 0, 0, 0.05)' }, 
                                            ticks: { precision: 0, color: '#6b7280' } 
                                        }, 
                                        x: { grid: { drawBorder: false }, ticks: { color: '#6b7280' } } 
                                    },
                                    plugins: { 
                                        legend: { 
                                            position: 'top',
                                            labels: { color: '#4b5563' }
                                        } 
                                    },
                                    interaction: { intersect: false, mode: 'index' }
                                }
                            });
                            window.myCharts.push(userPopulationChart);

                            // Process damage data for hierarchical view
                            const resolvedCount = damageData.find(d => d.status === 'Resolved')?.count || 0;
                            const unresolvedCount = damageData.find(d => d.status === 'Unresolved')?.count || 0;
                            const underReviewCount = damageData.find(d => d.status === 'Under Review')?.count || 0;
                            const totalUnresolved = unresolvedCount + underReviewCount;

                            // Chart 1: Overall Status (Pie)
                            const damageOverviewChart = new Chart(document.getElementById('damageOverviewChart'), {
                                type: 'pie',
                                data: {
                                    labels: ['Resolved', 'Unresolved'],
                                    datasets: [{
                                        data: [resolvedCount, totalUnresolved],
                                        backgroundColor: ['#10b981', '#f59e0b'], // Emerald for resolved, Amber for unresolved
                                        borderWidth: 8
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom', labels: { color: '#4b5563' } }
                                    }
                                }
                            });
                            window.myCharts.push(damageOverviewChart);

                            // Chart 2: Unresolved Details (Bar)
                            const unresolvedDetailChart = new Chart(document.getElementById('unresolvedDetailChart'), {
                                type: 'bar',
                                data: {
                                    labels: ['Awaiting Action', 'Under Review'],
                                    datasets: [{
                                        label: 'Case Count',
                                        data: [unresolvedCount, underReviewCount],
                                        backgroundColor: ['rgba(239, 68, 68, 0.5)', 'rgba(217, 119, 6, 0.5)'], // Red for no action, Amber for under review
                                        borderColor: ['rgba(239, 68, 68, 1)', 'rgba(217, 119, 6, 1)'],
                                        borderWidth: 2,
                                        borderRadius: 8
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true, 
                                            grid: { drawBorder: false, color: 'rgba(0, 0, 0, 0.05)' }, 
                                            ticks: { precision: 0, color: '#6b7280' }
                                        },
                                        x: { grid: { display: false }, ticks: { color: '#6b7280' } }
                                    },
                                    plugins: { legend: { display: false, labels: { color: '#4b5563' } } }
                                }
                            });
                            window.myCharts.push(unresolvedDetailChart);
                        }

                        document.addEventListener('DOMContentLoaded', () => {
                            initCharts();
                            
                            // Click Handler for KPI Cards
                            const kpiCards = document.querySelectorAll('.kpi-card');
                            const analyticsContainer = document.getElementById('visual-analytics');
                            const graphContainers = Array.from(analyticsContainer.children);

                            kpiCards.forEach(card => {
                                card.addEventListener('click', () => {
                                    kpiCards.forEach(c => c.classList.remove('active-card'));
                                    card.classList.add('active-card');
 
                                    const targetId = card.dataset.target;
                                    const targetContainer = document.getElementById(targetId);
                                    if (!targetContainer) return;

                                    // --- FLIP Animation ---
                                    const firstPositions = new Map();
                                    graphContainers.forEach(c => firstPositions.set(c, c.getBoundingClientRect()));

                                    // Trigger layout change
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
            </main>
        </div>
    </div>

    <?php include '../includes/layout_footer.php'; ?>

</body>
</html>