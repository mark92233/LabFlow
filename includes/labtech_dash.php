<?php
session_start();
require_once __DIR__ . '/../dbRelated/operation.php';

// 1. Access Control: LabTech or Admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['LabTech', 'Admin'])) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$labtechKPI = $db->getLabTechDashboardData(); // Fetch mock data

// 2. Fetch Dashboard KPIs from mock data
$pendingReqsCount = $labtechKPI['pending_reqs_count'] ?? 0;
$approvedReqsCount = $labtechKPI['approved_reqs_count'] ?? 0;
$issuedItemsCount = $labtechKPI['issued_items_count'] ?? 0;
$pendingSettlementsCount = $labtechKPI['pending_settlements_count'] ?? 0;


$page_title = "LabTech Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
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
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include 'glass_header.php'; ?>
            <main class="p-8 animate-reveal" x-data>
                <header class="mb-8">
                    <h2 class="text-4xl font-extrabold text-gray-800 tracking-tighter">
                        Welcome, <span class="text-orange-500"><?= htmlspecialchars($_SESSION['user_name']) ?></span>.
                    </h2>
                    <p class="text-slate-400 font-medium text-xs">Here's your operational overview for today.</p>
                </header>

                <!-- KPI Widgets -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Widget 1: Pending Requisitions -->
                    <div id="kpi-card-pending" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6 active-card" data-target="graphs-pending">
                        <div class="w-14 h-14 rounded-full bg-gray-100 text-orange-500 flex items-center justify-center"><i class="fas fa-inbox text-2xl"></i></div>
                        <div>
                            <span class="text-4xl font-black text-gray-800"><?= $pendingReqsCount ?></span>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Pending Slips</p>
                        </div>
                    </div>
                    
                    <!-- Widget 2: Approved (For Handover) -->
                    <div id="kpi-card-handover" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-handover">
                        <div class="w-14 h-14 rounded-full bg-gray-100 text-blue-500 flex items-center justify-center"><i class="fas fa-dolly text-2xl"></i></div>
                        <div>
                            <span class="text-4xl font-black text-gray-800"><?= $approvedReqsCount ?></span>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">For Handover</p>
                        </div>
                    </div>

                    <!-- Widget 3: Issued (For Return) -->
                    <div id="kpi-card-return" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-return">
                        <div class="w-14 h-14 rounded-full bg-gray-100 text-indigo-500 flex items-center justify-center"><i class="fas fa-undo-alt text-2xl"></i></div>
                        <div>
                            <span class="text-4xl font-black text-gray-800"><?= $issuedItemsCount ?></span>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Awaiting Return</p>
                        </div>
                    </div>

                    <!-- Widget 4: Pending Settlements -->
                    <div id="kpi-card-settlements" class="kpi-card cursor-pointer bg-white p-6 rounded-2xl border border-gray-200 flex items-center gap-6" data-target="graphs-settlements">
                        <div class="w-14 h-14 rounded-full bg-gray-100 text-red-500 flex items-center justify-center"><i class="fas fa-gavel text-2xl"></i></div>
                        <div>
                            <span class="text-4xl font-black text-gray-800"><?= $pendingSettlementsCount ?></span>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">Open Cases</p>
                        </div>
                    </div>
                </div>

                <!-- Visual Analytics -->
                <div id="visual-analytics" class="mt-8 flex flex-col gap-8">
                    <!-- Pending Requisitions Graph -->
                    <div id="graphs-pending" class="graph-container" style="order: 0;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Pending Requests by Class</h3>
                            <p class="text-sm text-gray-500 mb-6">Breakdown of current requisitions awaiting approval.</p>
                            <div class="h-72"><canvas id="pendingByClassChart"></canvas></div>
                        </div>
                    </div>

                    <!-- Handover Trend Graph -->
                    <div id="graphs-handover" class="graph-container" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Handover Trend</h3>
                            <p class="text-sm text-gray-500 mb-6">Number of slips ready for handover in the last 7 days.</p>
                            <div class="h-72"><canvas id="handoverTrendChart"></canvas></div>
                        </div>
                    </div>

                    <!-- Awaiting Return Graph -->
                    <div id="graphs-return" class="graph-container" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Most Overdue Items</h3>
                            <p class="text-sm text-gray-500 mb-6">Top items that are past their expected return date.</p>
                            <div class="h-72"><canvas id="overdueItemsChart"></canvas></div>
                        </div>
                    </div>

                    <!-- Settlements Graph -->
                    <div id="graphs-settlements" class="graph-container" style="order: 1;">
                        <div class="bg-white p-8 rounded-2xl border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Pending Damage Types</h3>
                            <p class="text-sm text-gray-500 mb-6">Breakdown of unresolved damage reports.</p>
                            <div class="h-72 flex justify-center"><canvas id="damageTypesChart" style="max-width: 320px;"></canvas></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include 'layout_footer.php'; ?>
    <script>
        window.myCharts = [];

        function initCharts() {
            window.myCharts.forEach(chart => chart.destroy());
            window.myCharts = [];

            const kpiData = <?= json_encode($labtechKPI) ?>;

            const pendingChart = new Chart(document.getElementById('pendingByClassChart'), {
                type: 'bar',
                data: { labels: kpiData.pending_by_class.map(d => d.class_name), datasets: [{ label: 'Pending Slips', data: kpiData.pending_by_class.map(d => d.count), backgroundColor: 'rgba(249, 115, 22, 0.5)', borderColor: 'rgba(249, 115, 22, 1)', borderWidth: 2, borderRadius: 8 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            window.myCharts.push(pendingChart);

            const handoverChart = new Chart(document.getElementById('handoverTrendChart'), {
                type: 'line',
                data: { labels: kpiData.handover_trend.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })), datasets: [{ label: 'Handovers', data: kpiData.handover_trend.map(d => d.count), backgroundColor: 'rgba(59, 130, 246, 0.2)', borderColor: 'rgba(59, 130, 246, 1)', borderWidth: 3, tension: 0.3, fill: true }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            window.myCharts.push(handoverChart);

            const overdueChart = new Chart(document.getElementById('overdueItemsChart'), {
                type: 'bar',
                data: { labels: kpiData.overdue_items.map(d => d.item_name), datasets: [{ label: 'Days Overdue', data: kpiData.overdue_items.map(d => d.days_overdue), backgroundColor: 'rgba(139, 92, 246, 0.5)', borderColor: 'rgba(139, 92, 246, 1)', borderWidth: 2, borderRadius: 8 }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
            window.myCharts.push(overdueChart);

            const damageChart = new Chart(document.getElementById('damageTypesChart'), {
                type: 'doughnut',
                data: { labels: kpiData.damage_types.map(d => d.type), datasets: [{ data: kpiData.damage_types.map(d => d.count), backgroundColor: ['#ef4444', '#f97316', '#eab308', '#8b5cf6'], borderWidth: 8, borderColor: '#f8fafc' }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
            window.myCharts.push(damageChart);
        }

        document.addEventListener('DOMContentLoaded', () => {
            initCharts();
            
            const kpiCards = document.querySelectorAll('.kpi-card');
            const analyticsContainer = document.getElementById('visual-analytics');
            const graphContainers = Array.from(analyticsContainer.children);

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