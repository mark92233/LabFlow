<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control: Teacher/Admin Only
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$teacher_id = $_SESSION['user_id'];

// Handle Approval/Rejection Actions & Set Toast Message
if (isset($_GET['action']) && isset($_GET['sid'])) {
    $newStatus = ($_GET['action'] === 'approve') ? 'Approved' : 'Rejected';
    if ($db->updateSessionStatus($_GET['sid'], $newStatus)) {
        $_SESSION['toast_message'] = ['text' => "Request has been " . strtolower($newStatus) . ".", 'type' => 'success'];
    } else {
        $_SESSION['toast_message'] = ['text' => "Action failed.", 'type' => 'error'];
    }
    header("Location: request_list.php"); // Redirect to clean URL
    exit();
}

// --- Get Filters, Search, and Pagination ---
$search = trim($_GET['search'] ?? '');
$class_filter = $_GET['class_filter'] ?? 'all';
$records_per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 15, 25, 50]) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// --- Build Query ---
$role = $_SESSION['user_role'];
$params = [];
$where_clauses = "bs.Status = 'Pending'";

// If the user is a Teacher, only show their classes' requests or general requests.
// Admins can see all pending requests.
if ($role === 'Teacher') {
    $where_clauses .= " AND (c.TeacherID = :tid OR bs.ActivityID IS NULL)";
    $params['tid'] = $teacher_id;
}

if (!empty($search)) {
    $where_clauses .= " AND (m.Full_Name LIKE :search OR la.Title LIKE :search)";
    $params['search'] = "%$search%";
}

if ($class_filter !== 'all' && is_numeric($class_filter)) {
    $where_clauses .= " AND c.ClassID = :class_id";
    $params['class_id'] = $class_filter;
}

// Base query for both count and data
$base_query = "FROM borrowing_sessions bs
               JOIN users u ON bs.StudentID = u.UserID
               JOIN lookup_masterlist m ON u.MasterID = m.MasterID
               LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
               LEFT JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
               LEFT JOIN classes c ON aa.ClassID = c.ClassID
               WHERE $where_clauses";

// 1. Get total number of records for pagination
$count_query = "SELECT COUNT(DISTINCT bs.SessionID) " . $base_query;
$count_stmt = $db->db->prepare($count_query);
$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// 2. Fetch paginated requests
$query = "SELECT bs.*,
                  COALESCE(la.Title, 'Independent Research') as Title,
                  m.Full_Name as StudentName,
                  COALESCE(c.Class_Name, 'General') as Class_Name
          " . $base_query . "
          GROUP BY bs.SessionID
          ORDER BY bs.CreatedAt DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->db->prepare($query);
// Bind base params
foreach ($params as $key => &$val) {
    $stmt->bindParam(":$key", $val);
}
// Bind pagination params
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch classes for the filter dropdown
if ($role === 'Admin') {
    $teacher_classes = $db->getAllClasses();
} else {
    $teacher_classes = $db->getTeacherClasses($teacher_id);
}

// UI Variable for Header
$page_title = "Borrowing Queue";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incoming Borrow Requests | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-container');
            if (!toast) return;

            const iconContainer = document.getElementById('toast-icon-container');
            const messageContainer = document.getElementById('toast-message');

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
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const filterForm = document.getElementById('filterForm');
            let debounceTimeout;

            if (searchInput && filterForm) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(() => {
                        filterForm.submit();
                    }, 400); // Submit form 400ms after user stops typing
                });
            }
        });
    </script>
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                
                <header class="mb-8">
                    <div>
                        <h2 class="text-5xl font-extrabold text-gray-800 tracking-tighter mb-2">
                            Incoming <span class="text-orange-500">Requests.</span>
                        </h2>
                        <p class="text-slate-400 font-medium">Review and vet student apparatus requisitions.</p>
                    </div>
                </header>

                <!-- Filter and Search Section -->
                <div class="mb-8 bg-white p-6 rounded-2xl border border-gray-200/50 shadow-sm">
                    <form id="filterForm" method="GET" action="request_list.php" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                        <div class="md:col-span-2">
                            <label for="search" class="text-xs font-bold text-gray-500 mb-2 block">Search by Student or Activity</label>
                            <input type="search" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. John Doe or Titration" class="w-full bg-gray-50 border-gray-200 p-3 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500">
                        </div>
                        <div>
                            <label for="class_filter" class="text-xs font-bold text-gray-500 mb-2 block">Filter by Class</label>
                            <select name="class_filter" id="class_filter" onchange="this.form.submit()" class="w-full bg-gray-50 border-gray-200 p-3 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500">
                                <option value="all">All Classes</option>
                                <?php foreach ($teacher_classes as $class): ?>
                                    <option value="<?= $class['ClassID'] ?>" <?= $class_filter == $class['ClassID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['Class_Name'] . ' - ' . $class['Section']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if (empty($requests)): ?>
                    <div class="glass-card p-20 text-center flex flex-col items-center">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                            <svg class="w-10 h-10 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Inbox Clear</h3>
                        <p class="text-slate-400">There are no student requests waiting for your approval.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200/50 overflow-hidden">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Activity / Purpose</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Items</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($requests as $req): ?>
                                    <tr class="hover:bg-orange-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($req['StudentName']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($req['Class_Name']) ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($req['Title']) ?></p>
                                            <p class="text-xs text-gray-500 italic truncate max-w-xs">"<?= htmlspecialchars($req['Remarks'] ?? 'No reason provided.') ?>"</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                                $itemsQuery = "SELECT bi.Quantity, i.Item_Name FROM borrowed_items bi JOIN inventory i ON bi.ItemID = i.ItemID WHERE bi.SessionID = :sid";
                                                $iStmt = $db->db->prepare($itemsQuery);
                                                $iStmt->execute(['sid' => $req['SessionID']]);
                                                $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);
                                                $item_summary = array_map(fn($item) => $item['Quantity'] . 'x ' . htmlspecialchars($item['Item_Name']), $items);
                                            ?>
                                            <p class="text-xs text-gray-600 font-medium truncate max-w-xs" title="<?= implode(', ', $item_summary) ?>">
                                                <?= implode(', ', $item_summary) ?>
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <a href="?action=reject&sid=<?= $req['SessionID'] ?>" class="px-4 py-2 bg-white border border-red-100 text-red-500 rounded-lg text-xs font-bold hover:bg-red-50 transition-all">Reject</a>
                                                <a href="?action=approve&sid=<?= $req['SessionID'] ?>" class="px-4 py-2 bg-orange-500 text-white rounded-lg text-xs font-bold shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-all">Approve</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <!-- Pagination Controls -->
                        <div class="p-6 border-t border-gray-100 flex justify-between items-center">
                            <div class="flex items-center gap-4">
                                <p class="text-xs font-bold text-gray-500">Page <?= $page ?> of <?= $total_pages ?></p>
                                <form method="GET" action="request_list.php" class="flex items-center gap-2">
                                    <!-- Hidden fields to preserve other filters -->
                                    <?php if (!empty($search)): ?>
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                    <?php endif; ?>
                                    <?php if ($class_filter !== 'all'): ?>
                                        <input type="hidden" name="class_filter" value="<?= htmlspecialchars($class_filter) ?>">
                                    <?php endif; ?>
                                    <select name="per_page" onchange="this.form.submit()" class="bg-gray-50 border-gray-200 rounded-md text-xs font-bold p-1 focus:ring-orange-500 focus:border-orange-500">
                                        <option value="10" <?= $records_per_page == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="15" <?= $records_per_page == 15 ? 'selected' : '' ?>>15</option>
                                        <option value="25" <?= $records_per_page == 25 ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= $records_per_page == 50 ? 'selected' : '' ?>>50</option>
                                    </select>
                                    <label class="text-xs font-bold text-gray-500">per page</label>
                                </form>
                            </div>
                            <div class="flex gap-2">
                                <?php
                                    // Build the query string for pagination links
                                    $queryParams = [];
                                    if (!empty($search)) $queryParams['search'] = $search;
                                    if ($class_filter !== 'all') $queryParams['class_filter'] = $class_filter;
                                    if ($records_per_page !== 10) $queryParams['per_page'] = $records_per_page;
                                    $pagination_query_string = http_build_query($queryParams);
                                ?>
                                <a href="?page=<?= max(1, $page - 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">Previous</a>
                                <a href="?page=<?= min($total_pages, $page + 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">Next</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl"></div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <script>
    <?php
    if (isset($_SESSION['toast_message'])) {
        $toast_message = $_SESSION['toast_message'];
        unset($_SESSION['toast_message']);
        echo "document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($toast_message['text']) . "', '" . $toast_message['type'] . "'); });";
    }
    ?>
    </script>

</body>
</html>