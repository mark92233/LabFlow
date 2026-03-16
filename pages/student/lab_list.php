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
$class_info = ($class_id) ? $db->getClassDetails($class_id) : null;

// --- Get Filters, Search, and Pagination ---
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'deadline_desc';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;

$options = [
    'search' => $search,
    'sort' => $sort,
    'page' => $page,
    'limit' => $limit,
];

// Fetch data using the updated student-specific function
$result = ($class_id) ? $db->getActivitiesByClassForStudent($class_id, $student_id, $options) : ['data' => [], 'total' => 0, 'pages' => 1];
$activities = $result['data'];

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

            <main class="p-8 animate-reveal max-w-7xl mx-auto w-full">
                
                <header class="mb-12 flex items-center gap-6">
                    <a href="my_classes.php" class="p-3 bg-white border border-slate-100 rounded-2xl text-slate-400 hover:text-blue-600 transition-all shadow-sm group">
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

                <!-- Search and Filter Controls -->
                <div class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-4">
                    <form id="filterForm" method="GET" class="md:col-span-3">
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id) ?>">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by activity title..." class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-blue-500 transition-all">
                    </form>
                    <form id="sortForm" method="GET" class="md:col-span-2">
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id) ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <select name="sort" onchange="this.form.submit()" class="w-full bg-white border-gray-200 p-4 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-blue-500 transition-all h-full">
                            <option value="deadline_desc" <?= $sort == 'deadline_desc' ? 'selected' : '' ?>>Sort by: Deadline (Soonest)</option>
                            <option value="created_asc" <?= $sort == 'created_asc' ? 'selected' : '' ?>>Sort by: Date Added (Oldest)</option>
                            <option value="title_asc" <?= $sort == 'title_asc' ? 'selected' : '' ?>>Sort by: Title (A-Z)</option>
                        </select>
                    </form>
                </div>

                <!-- Activities Table -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200/50 overflow-hidden">
                    <?php if (empty($activities)): ?>
                        <div class="p-20 text-center">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-10 h-10 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </div>
                            <p class="text-slate-400 font-bold italic uppercase text-xs tracking-widest">No lab activities assigned yet.</p>
                        </div>
                    <?php else: ?>
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Status</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Deadline</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($activities as $act): 
                                    $statusClass = match($act['submission_status']) {
                                        'Submitted' => 'bg-blue-100 text-blue-600',
                                        'Graded' => 'bg-green-100 text-green-600',
                                        'Returned' => 'bg-amber-100 text-amber-600',
                                        'Open' => 'bg-slate-100 text-slate-500',
                                        default => 'bg-slate-100 text-slate-500'
                                    };
                                ?>
                                <tr class="hover:bg-orange-50/50 transition-colors">
                                    <td class="px-6 py-4 align-middle">
                                        <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($act['Title']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($act['type']) ?> Activity</p>
                                    </td>
                                    <td class="px-6 py-4 text-center align-middle">
                                        <span class="px-3 py-1.5 rounded-full text-[9px] font-black uppercase <?= $statusClass ?>">
                                            <?= htmlspecialchars($act['submission_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-middle">
                                        <span class="text-xs font-bold text-red-500 uppercase">
                                            <?= date('M d, Y', strtotime($act['Deadline'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center align-middle">
                                        <a href="activity_view.php?activity_id=<?= $act['ActivityID'] ?>&class_id=<?= $class_id ?>" class="px-5 py-2.5 bg-slate-800 text-white rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-blue-600 transition-all">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <!-- Pagination -->
                        <div class="p-6 border-t border-gray-100 flex justify-between items-center">
                            <p class="text-xs font-bold text-gray-500">
                                Page <?= $result['current_page'] ?> of <?= $result['pages'] ?>
                            </p>
                            <div class="flex gap-2">
                                <?php
                                    $pagination_params = ['class_id' => $class_id, 'search' => $search, 'sort' => $sort];
                                ?>
                                <a href="?<?= http_build_query(array_merge($pagination_params, ['page' => max(1, $page - 1)])) ?>" 
                                   class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                    Previous
                                </a>
                                <a href="?<?= http_build_query(array_merge($pagination_params, ['page' => min($result['pages'], $page + 1)])) ?>" 
                                   class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page >= $result['pages'] ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                    Next
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div> 
            </main>
        </div>
    </div>

    <script>
        // Auto-submit search form after user stops typing
        let debounceTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', () => {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    </script>

    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>