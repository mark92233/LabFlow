<?php
session_start();
require_once '../../dbRelated/operation.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$class_id = $_GET['class_id'] ?? null;
if (!$class_id) {
    header("Location: manage_classes.php");
    exit();
}

// --- Get Filters, Search, and Pagination ---
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$records_per_page = 10; // Fixed for now, can be made dynamic
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// --- Fetch Data ---
$options = [
    'page' => $page,
    'limit' => $records_per_page,
    'search' => $search,
    'sort' => $sort,
];
$result = $db->getPaginatedActivitiesForClass($class_id, $options);
$activities = $result['data'];
$total_records = $result['total'];
$total_pages = $result['pages'];

// Fetch Class Info for title
$class_info = $db->getClassDetails($class_id);
$page_title = $class_info ? "Activities for " . htmlspecialchars($class_info['Class_Name']) : "Class Activities";
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
                <header class="mb-10 flex justify-between items-center">
        <div>
            <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter mt-2">
                <?= htmlspecialchars($class_info['Class_Name'] ?? 'Class') ?> <span class="text-orange-600">Activities</span>
            </h2>
        </div>
        
        <div class="flex gap-3">
            <a href="clearance_hub.php?class_id=<?= $class_id ?>" class="bg-white border border-slate-200 text-slate-600 px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest hover:border-orange-500 hover:text-orange-600 transition-all shadow-sm">
            Clearance Hub
            </a>
            
            <a href="add_activity.php?class_id=<?= $class_id ?>" class="bg-orange-500 text-white px-6 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20">
            Post New Lab
            </a>
        </div>
    </header>

                <!-- Filter and Search Section -->
                <div class="mb-8 bg-white p-6 rounded-2xl border border-gray-200/50 shadow-sm">
                    <form id="filterForm" method="GET" action="class_activities.php" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($class_id) ?>">
                        <div class="md:col-span-2">
                            <label for="search" class="text-xs font-bold text-gray-500 mb-2 block">Search by Title or Description</label>
                            <input type="search" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. Titration Experiment" class="w-full bg-gray-50 border-gray-200 p-3 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500">
                        </div>
                        <div>
                            <label for="sort" class="text-xs font-bold text-gray-500 mb-2 block">Sort by</label>
                            <select name="sort" id="sort" onchange="this.form.submit()" class="w-full bg-gray-50 border-gray-200 p-3 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500">
                                <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                <option value="deadline" <?= $sort == 'deadline' ? 'selected' : '' ?>>By Deadline</option>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- Activities Table -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-200/50 overflow-hidden">
                    <?php if (empty($activities)): ?>
                        <div class="p-20 text-center">
                            <p class="text-slate-400 italic">No lab activities found matching your criteria.</p>
                        </div>
                    <?php else: ?>
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Deadline</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($activities as $act): ?>
                                <tr class="hover:bg-orange-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($act['Title']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($act['type']) ?> Activity</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-xs text-gray-600 font-medium truncate max-w-md" title="<?= htmlspecialchars($act['Description']) ?>">
                                            <?= htmlspecialchars($act['Description']) ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-bold text-red-500 uppercase italic">
                                            <?= date('M d, Y h:i A', strtotime($act['Deadline'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center items-center gap-2">
                                            <a href="activity_hub.php?activity_id=<?= $act['ActivityID'] ?>&class_id=<?= $class_id ?>" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-200 transition-all">View Hub</a>
                                            <a href="add_activity.php?edit_id=<?= $act['ActivityID'] ?>&class_id=<?= $class_id ?>" class="px-3 py-2 bg-orange-500 text-white rounded-lg text-xs font-bold shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-all">
                                                Edit
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <!-- Pagination Controls -->
                        <div class="p-6 border-t border-gray-100 flex justify-between items-center">
                            <p class="text-xs font-bold text-gray-500">
                                Showing <?= count($activities) ?> of <?= $total_records ?> activities
                            </p>
                            <div class="flex gap-2">
                                <?php
                                    $queryParams = ['class_id' => $class_id, 'search' => $search, 'sort' => $sort];
                                    $pagination_query_string = http_build_query(array_filter($queryParams));
                                ?>
                                <a href="?page=<?= max(1, $page - 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">Previous</a>
                                <a href="?page=<?= min($total_pages, $page + 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">Next</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script>
        // Debounce search input
        const searchInput = document.getElementById('search');
        let debounceTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimeout);
                debounceTimeout = setTimeout(() => {
                    document.getElementById('filterForm').submit();
                }, 500); // 500ms delay
            });
        }

        // Placed here to be globally accessible
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-container');
            if (!toast) return;

            const iconContainer = document.getElementById('toast-icon-container');
            const messageContainer = document.getElementById('toast-message');

            // Reset classes
            toast.className = 'fixed bottom-10 right-10 z-[200] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal';
            iconContainer.className = 'inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl';

            messageContainer.textContent = message;

            if (type === 'success') {
                toast.classList.add('bg-emerald-600');
                iconContainer.classList.add('bg-emerald-100');
                iconContainer.innerHTML = `<svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>`;
            } else { // error
                toast.classList.add('bg-red-600');
                iconContainer.classList.add('bg-red-100');
                iconContainer.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`;
            }

            toast.classList.remove('hidden');
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
            toast.style.transition = 'all 0.5s ease';

            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
        }
        <?php
        if (isset($_SESSION['toast_message'])) {
            $toast = $_SESSION['toast_message'];
            unset($_SESSION['toast_message']);
            echo "document.addEventListener('DOMContentLoaded', () => showToast('" . addslashes($toast['text']) . "', '" . $toast['type'] . "'));";
        }
        ?>
    </script>
    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl"></div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>
                    </div>
                </main>
            </div>
        </div>
    </body>
    </html>