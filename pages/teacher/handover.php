<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control - Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$teacher_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$error = "";
$success = "";

// Handle Actions (Approve, Reject, Issue)
if (isset($_GET['action']) && isset($_GET['sid'])) {
    $sid = $_GET['sid'];
    $action = $_GET['action'];
    $newStatus = '';

    if ($action === 'approve') $newStatus = 'Approved';
    if ($action === 'reject') $newStatus = 'Rejected';

    if ($newStatus) {
        if ($db->updateSessionStatus($sid, $newStatus)) {
            $_SESSION['toast_message'] = ['text' => "Request #{$sid} has been " . strtolower($newStatus) . ".", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => "Action failed for Request #{$sid}.", 'type' => 'error'];
        }
    } elseif ($action === 'issue') {
        if ($db->finalizeHandover($sid)) {
            $_SESSION['toast_message'] = ['text' => "Apparatus successfully issued for Request #{$sid}!", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => "Handover failed for Request #{$sid}.", 'type' => 'error'];
        }
    }
    header("Location: handover.php"); // Redirect to clean URL
    exit();
}

// --- Get Filters, Search, and Pagination ---
$search = trim($_GET['search'] ?? '');
$class_filter = $_GET['class_filter'] ?? 'all';
$status_filter = $_GET['status_filter'] ?? 'all'; // New status filter
$date_sort = $_GET['date_sort'] ?? 'desc';
$records_per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 15, 25, 50]) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// --- Build Query ---
$params = [];
$where_clauses = "1"; // Start with a truthy value

// Base statuses for the terminal
$allowed_statuses = ['Pending', 'Approved', 'Issued', 'Returned'];

if ($status_filter !== 'all' && in_array($status_filter, $allowed_statuses)) {
    $where_clauses .= " AND bs.Status = :status";
    $params['status'] = $status_filter;
} else {
    $where_clauses .= " AND bs.Status IN ('Pending', 'Approved', 'Issued', 'Returned')";
}

// Search filtering
if (!empty($search)) {
    $where_clauses .= " AND (m.Full_Name LIKE :search OR la.Title LIKE :search)";
    $params['search'] = "%$search%";
}

// Class filtering
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

// Get total records
$count_query = "SELECT COUNT(DISTINCT bs.SessionID) " . $base_query;
$count_stmt = $db->db->prepare($count_query);
$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

$sort_direction = (strtolower($date_sort) === 'asc') ? 'ASC' : 'DESC';
// Fetch paginated data
$query = "SELECT bs.SessionID, bs.Status, bs.CreatedAt, m.Full_Name as StudentName, m.ID_Number as studentId, COALESCE(c.Class_Name, 'General') as Class_Name, COALESCE(la.Title, 'Independent Research') as Title, bs.Remarks, bs.QR_Code_Data
          " . $base_query . "
          GROUP BY bs.SessionID
          ORDER BY FIELD(bs.Status, 'Pending', 'Approved', 'Issued', 'Returned'), bs.CreatedAt {$sort_direction}
          LIMIT :limit OFFSET :offset";

$stmt = $db->db->prepare($query);
foreach ($params as $key => &$val) { $stmt->bindParam(":$key", $val); }
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare a full data array for JavaScript, including items for each session
$sessionsForJs = [];
foreach ($sessions as $session) {
    $itemsQuery = "SELECT 
                        i.ItemID as id, 
                        i.Item_Name as name, 
                        bi.Quantity as qty,
                        i.is_consumable,
                        iv.Size_Value,
                        iv.Unit
                   FROM borrowed_items bi JOIN inventory i ON bi.ItemID = i.ItemID 
                   LEFT JOIN item_variants iv ON bi.VariantID = iv.VariantID WHERE bi.SessionID = ?";
    $iStmt = $db->db->prepare($itemsQuery);
    $iStmt->execute([$session['SessionID']]);
    $sessionData = $session;
    $sessionData['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
    $sessionData['studentName'] = $session['StudentName'];
    $sessionData['activityTitle'] = $session['Title'];
    $sessionData['date'] = $session['CreatedAt'];
    $sessionData['sessionId'] = $session['SessionID'];
    $sessionsForJs[$session['SessionID']] = $sessionData;
}

// Fetch classes for filter dropdown
$teacher_classes = $db->getAllClasses();

$page_title = "Handover Terminal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Handover Terminal | SNHS</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap');
        .thermal-font { font-family: 'Courier Prime', 'Courier New', Courier, monospace; }
        .sticky-sidebar { height: calc(100vh - 120px); position: sticky; top: 100px; }
        .receipt-container { background-color: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; }
        .receipt-tear-top {
            position: absolute; top: -5px; left: 0; width: 100%; height: 10px;
            background: linear-gradient(135deg, transparent 33%, #fff 33%, #fff 66%, transparent 66%) 0 0;
            background-size: 20px 10px;
        }
        .receipt-tear-bottom {
            position: absolute; bottom: -10px; left: 0; width: 100%; height: 10px;
            background: linear-gradient(45deg, transparent 33%, #fff 33%, #fff 66%, transparent 66%) 0 0;
            background-size: 20px 10px; transform: rotate(180deg);
        }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
        .shake-error { animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both; }
        .bg-crimson-gradient { background-image: linear-gradient(135deg, #ff8c00 0%, #dc143c 100%); }

        /* QR Scanner Styles */
        #qr-scanner-container {
            position: relative;
            width: 100%;
            aspect-ratio: 1 / 1;
            margin: auto;
            overflow: hidden;
            border-radius: 1.5rem; /* 24px */
            background: #1e293b; /* Dark background for contrast */
            box-shadow: 0 20px 40px -5px rgba(0, 0, 0, 0.25);
        }
        #qr-reader {
            width: 100%;
            height: 100%;
        }
        #qr-reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
        }
        .qr-guide-overlay { position: absolute; inset: 0; pointer-events: none; }
        .qr-guide-box { position: absolute; inset: 15%; }
        .corner {
            position: absolute; width: 40px; height: 40px;
            border-color: rgba(255, 255, 255, 0.8); border-style: solid;
        }
        .corner.top-left { top: 0; left: 0; border-width: 5px 0 0 5px; border-top-left-radius: 1rem; }
        .corner.top-right { top: 0; right: 0; border-width: 5px 5px 0 0; border-top-right-radius: 1rem; }
        .corner.bottom-left { bottom: 0; left: 0; border-width: 0 0 5px 5px; border-bottom-left-radius: 1rem; }
        .corner.bottom-right { bottom: 0; right: 0; border-width: 0 5px 5px 0; border-bottom-right-radius: 1rem; }
        .scan-laser {
            position: absolute; top: 15%; left: 15%; right: 15%; height: 3px;
            background: #f97316; box-shadow: 0 0 10px #f97316, 0 0 20px #f97316;
            border-radius: 3px; animation: scan-animation 3s infinite ease-in-out;
        }
        @keyframes scan-animation { 0% { top: 15%; } 50% { top: 85%; } 100% { top: 15%; } }
    </style>
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
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const filterForm = document.getElementById('filterForm');
            let debounceTimeout;

            if (searchInput && filterForm) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(() => {
                        // Preserve status filter when searching
                        const urlParams = new URLSearchParams(window.location.search);
                        const status = urlParams.get('status_filter');
                        if (status) {
                            let statusInput = filterForm.querySelector('input[name="status_filter"]');
                            if (!statusInput) {
                                statusInput = document.createElement('input');
                                statusInput.type = 'hidden';
                                statusInput.name = 'status_filter';
                                filterForm.appendChild(statusInput);
                            }
                            statusInput.value = status;
                        }
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
            
            <main class="p-8 flex gap-8 animate-reveal">
                
                <!-- Left Column (Main Content) -->
                <div class="flex-1 flex flex-col gap-6">
                    <header class="mb-2 flex-shrink-0 flex justify-between items-center">
                        <div>
                            <h2 class="text-4xl font-extrabold text-gray-800 tracking-tighter mb-2">Handover <span class="text-orange-500">Terminal.</span></h2>
                            <p class="text-slate-400 font-medium text-xs">Review, approve, and process student apparatus requisitions.</p>
                        </div>
                        <button onclick="startScanner()" class="flex items-center gap-3 bg-orange-500 text-white px-6 py-4 rounded-2xl font-bold shadow-lg shadow-orange-500/30 hover:bg-orange-600 transition-all text-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                            <span>Scan Slip</span>
                        </button>
                    </header>

                    <!-- Status Tabs -->
                    <div class="bg-white p-2 rounded-2xl border border-gray-200/50 shadow-sm">
                        <div class="flex items-center gap-2">
                            <?php
                                $tabs = ['all' => 'All', 'Pending' => 'Pending', 'Approved' => 'Approved', 'Issued' => 'Issued', 'Returned' => 'Returned'];
                                $base_params = ['search' => $search, 'class_filter' => $class_filter, 'per_page' => $records_per_page, 'date_sort' => $date_sort];
                            ?>
                            <?php foreach ($tabs as $key => $label):
                                $current_params = $base_params;
                                $current_params['status_filter'] = $key;
                                $queryString = http_build_query(array_filter($current_params));
                                $isActive = ($status_filter === $key);
                            ?>
                                <a href="?<?= $queryString ?>" 
                                   class="flex-1 text-center px-4 py-3 rounded-xl font-bold text-xs uppercase tracking-wider transition-all duration-300 
                                   <?= $isActive ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/20' : 'text-gray-500 hover:bg-gray-50' ?>">
                                    <?= $label ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Filter and Search Section -->
                    <div class="bg-white p-6 rounded-2xl border border-gray-200/50 shadow-sm">
                        <form id="filterForm" method="GET" action="handover.php" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
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
                            <div>
                                <label for="date_sort" class="text-xs font-bold text-gray-500 mb-2 block">Sort by Date</label>
                                <select name="date_sort" id="date_sort" onchange="this.form.submit()" class="w-full bg-gray-50 border-gray-200 p-3 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500">
                                    <option value="desc" <?= $date_sort == 'desc' ? 'selected' : '' ?>>Newest First</option>
                                    <option value="asc" <?= $date_sort == 'asc' ? 'selected' : '' ?>>Oldest First</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <!-- Queue Table & Pagination -->
                    <div id="main-content-area" class="bg-white rounded-2xl shadow-lg border border-gray-200/50 flex-1 flex flex-col overflow-hidden">
                        <!-- State 1: Table View -->
                        <div id="table-view" class="flex-1 flex flex-col">
                            <?php if (empty($sessions)): ?>
                                <div class="flex-1 flex flex-col items-center justify-center text-center p-10">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <h3 class="font-bold text-gray-700">No Requests Found</h3>
                                    <p class="text-sm text-gray-400">There are no requests matching your current filters.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-y-auto flex-1">
                                    <table class="w-full text-left">
                                        <thead class="bg-gray-50 border-b border-gray-100 sticky top-0">
                                            <tr>
                                                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Activity / Purpose</th>
                                                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Items</th>
                                                <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100" id="pending-requests-table">
                                            <?php foreach ($sessions as $session):
                                                $statusClass = match($session['Status']) {
                                                    'Approved' => 'bg-orange-100 text-orange-600',
                                                    'Issued' => 'bg-indigo-100 text-indigo-600',
                                                    'Pending' => 'bg-amber-100 text-amber-600',
                                                    'Returned' => 'bg-green-100 text-green-600',
                                                    default => 'bg-slate-100 text-slate-500'
                                                };
                                                
                                                // Prepare data for JS
                                                $sessionData = $sessionsForJs[$session['SessionID']] ?? [];
                                                $borrowedItems = $sessionData['items'] ?? [];
                                                $sessionJSON = htmlspecialchars(json_encode($sessionData), ENT_QUOTES, 'UTF-8');
                                            ?>
                                                <tr id="row-<?= $session['SessionID'] ?>" onclick='showReceipt(<?= $sessionJSON ?>)' class="hover:bg-orange-50/50 transition-colors cursor-pointer">
                                                    <td class="px-6 py-4">
                                                        <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($session['StudentName']) ?></p>
                                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($session['Class_Name']) ?></p>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($session['Title']) ?></p>
                                                        <p class="text-xs text-gray-500 italic truncate max-w-xs" title="<?= htmlspecialchars($session['Remarks'] ?? 'No reason provided.') ?>">"<?= htmlspecialchars($session['Remarks'] ?? 'No reason provided.') ?>"</p>
                                                    </td>
                                                    <td class="px-6 py-4 text-xs text-gray-600 font-medium truncate max-w-xs">
                                                        <?= count($borrowedItems) ?> item(s)
                                                    </td>
                                                    <td class="px-6 py-4 text-center">
                                                        <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase <?= $statusClass ?>">
                                                            <?= $session['Status'] ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Pagination Controls -->
                                <div class="p-6 border-t border-gray-100 flex justify-between items-center">
                                    <div class="flex items-center gap-4">
                                        <p class="text-xs font-bold text-gray-500">Page <?= $page ?> of <?= $total_pages ?></p>
                                        <form method="GET" action="handover.php" class="flex items-center gap-2">
                                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                            <input type="hidden" name="class_filter" value="<?= htmlspecialchars($class_filter) ?>">
                                            <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                                            <input type="hidden" name="date_sort" value="<?= htmlspecialchars($date_sort) ?>">
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
                                            $queryParams = ['search' => $search, 'class_filter' => $class_filter, 'status_filter' => $status_filter, 'per_page' => $records_per_page, 'date_sort' => $date_sort];
                                            $pagination_query_string = http_build_query(array_filter($queryParams));
                                        ?>
                                        <a href="?page=<?= max(1, $page - 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">Previous</a>
                                        <a href="?page=<?= min($total_pages, $page + 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">Next</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- State 2: Camera View -->
                        <div id="camera-view" class="hidden p-6 flex-1 flex-col items-center justify-center">
                            <div class="w-full max-w-lg mx-auto">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-bold text-slate-800">Scan Requisition Slip QR</h3>
                                    <button onclick="stopScanner()" class="text-sm font-bold text-slate-500 hover:text-red-500 transition-colors">Cancel Scan</button>
                                </div>
                                <div id="qr-scanner-container">
                                    <div id="qr-reader"></div>
                                    <div class="qr-guide-overlay">
                                        <div class="qr-guide-box">
                                            <div class="corner top-left"></div><div class="corner top-right"></div>
                                            <div class="corner bottom-left"></div><div class="corner bottom-right"></div>
                                        </div>
                                        <div class="scan-laser"></div>
                                    </div>
                                </div>
                                <div id="qr-reader-results" class="text-center text-sm font-bold text-red-500 mt-4 h-5"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column (Details Panel) -->
                <aside class="w-96 sticky-sidebar">
                    <div id="receipt-panel-container" class="h-full">
                        <div id="receipt-empty-state" class="h-full flex flex-col items-center justify-center text-center p-8 text-slate-400 border-2 border-dashed border-slate-200 rounded-3xl">
                            <svg class="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <h3 class="font-bold text-slate-500">Select a Session</h3>
                            <p class="text-sm mt-1">Click on a row to view its details here.</p>
                        </div>
                        <div id="receipt-content-wrapper" class="hidden h-full">
                            <!-- JS will inject receipt content here -->
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>

    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl"></div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <!-- Audio cues for scanner -->
    <audio id="scan-success-sound" src="../../assets/audio/scan_su.wav" preload="auto"></audio>
    <audio id="scan-error-sound" src="../../assets/audio/scan_f.wav" preload="auto"></audio>

    <script>
        const allSessionsData = <?= json_encode($sessionsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let html5QrCode;

        function closeReceiptPanel() {
            document.getElementById('receipt-empty-state').classList.remove('hidden');
            document.getElementById('receipt-content-wrapper').classList.add('hidden');
            document.getElementById('receipt-content-wrapper').innerHTML = '';

            const activeRow = document.querySelector('tr.bg-orange-100');
            if (activeRow) {
                activeRow.classList.remove('bg-orange-100');
            }
        }

        function showReceipt(sessionData) {
            // Highlight the selected row
            const allRows = document.querySelectorAll('#pending-requests-table tr');
            allRows.forEach(row => row.classList.remove('bg-orange-100'));
            const selectedRow = document.getElementById(`row-${sessionData.SessionID}`);
            if (selectedRow) {
                selectedRow.classList.add('bg-orange-100');
            }

            const receiptWrapper = document.getElementById('receipt-content-wrapper');
            const emptyState = document.getElementById('receipt-empty-state');
            
            const consumables = sessionData.items.filter(item => item.is_consumable == 1);
            const nonConsumables = sessionData.items.filter(item => item.is_consumable == 0);

            let itemsHtml = '';

            if (nonConsumables.length > 0) {
                itemsHtml += '<div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 mt-2">Non-Consumables</div>';
                itemsHtml += nonConsumables.map(item => {
                    const size = item.Size_Value ? ` (${item.Size_Value}${item.Unit || ''})` : '';
                    return `
                    <div class="flex justify-between text-[10px] font-bold py-1 border-b border-dashed border-slate-200">
                        <span class="uppercase">${item.name}${size}</span>
                        <span>${item.qty}</span>
                    </div>`;
                }).join('');
            }

            if (consumables.length > 0) {
                itemsHtml += '<div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 mt-4">Consumables</div>';
                itemsHtml += consumables.map(item => {
                    const size = item.Size_Value ? ` (${item.Size_Value}${item.Unit || ''})` : '';
                    return `
                    <div class="flex justify-between text-[10px] font-bold py-1 border-b border-dashed border-slate-200">
                        <span class="uppercase">${item.name}${size}</span>
                        <span>${item.qty}</span>
                    </div>`;
                }).join('');
            }
            let buttonsHtml = '';
            const sid = sessionData.SessionID;

            if (sessionData.Status === 'Pending') {
                buttonsHtml = `
                    <a href="?action=reject&sid=${sid}" class="w-full text-center rounded-lg bg-white border border-red-200 px-4 py-3 text-sm font-bold text-red-500 hover:bg-red-50 transition-colors">
                        Reject
                    </a>
                    <a href="?action=approve&sid=${sid}" class="w-full text-center rounded-lg bg-orange-500 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-all">
                        Approve
                    </a>
                `;
            } else if (sessionData.Status === 'Approved') {
                buttonsHtml = `
                    <a href="?action=issue&sid=${sid}" class="w-full text-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-500/20 hover:bg-indigo-700 transition-all">
                        Confirm Handover
                    </a>
                `;
            } else if (sessionData.Status === 'Issued') {
                buttonsHtml = `
                    <a href="process_return.php?sid=${sid}" class="w-full text-center rounded-lg bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700 transition-all">
                        Process Return
                    </a>
                `;
            } else {
                buttonsHtml = `
                    <button onclick="closeReceiptPanel()" class="w-full col-span-2 text-center rounded-lg bg-slate-200 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-300 transition-colors">
                        Close
                    </button>
                `;
            }

            let footerHtml = '';
            if (buttonsHtml.trim() !== '') {
                footerHtml = `
                    <div class="p-6 mt-auto border-t-2 border-dashed border-slate-300 bg-white flex-shrink-0 z-10 thermal-font">
                        <div class="grid ${sessionData.Status === 'Pending' ? 'grid-cols-2' : 'grid-cols-1'} gap-4">
                            ${buttonsHtml}
                        </div>
                    </div>
                `;
            }

            const receiptContent = `
                <div class="text-center mb-6 border-b-2 border-black/10 pb-4">
                    <h4 class="text-xl font-bold uppercase tracking-widest">CSM LAB</h4>
                    <p class="text-[9px] uppercase mt-1 text-slate-500">Requisition Slip</p>
                </div>

                <div class="space-y-1 mb-6 text-[10px] uppercase font-bold">
                    <div class="flex justify-between"><span>Student:</span><span>${sessionData.studentName}</span></div>
                    <div class="flex justify-between"><span>ID:</span><span>${sessionData.studentId}</span></div>
                    <div class="flex justify-between"><span>Date:</span><span>${new Date(sessionData.date).toLocaleString()}</span></div>
                    <div class="flex justify-between items-start"><span>Activity:</span><span class="text-right w-1/2 truncate">${sessionData.activityTitle}</span></div>
                    <div class="flex justify-between"><span>Session:</span><span>#${sessionData.sessionId}</span></div>
                </div>

                <div class="mb-6">
                    <div class="flex justify-between text-xs font-bold border-b border-dashed border-black mb-2 pb-1">
                        <span>ITEM</span>
                        <span>QTY</span>
                    </div>
                    <div class="space-y-1" id="receipt-items">
                        ${itemsHtml}
                    </div>
                </div>

                <div class="mt-auto pt-4 text-center text-xs text-slate-500">
                    *** ${sessionData.Status.toUpperCase()} ***
                </div>
            `;

            const finalHtml = `
                <div id="receipt-capture" class="receipt-container thermal-font bg-white rounded-2xl shadow-xl border border-slate-200/50 h-full flex flex-col animate__animated animate__fadeIn animate__faster">
                    <div class="receipt-tear-top"></div>
                    <div class="p-6 flex-1 overflow-y-auto custom-scrollbar flex flex-col">
                        ${receiptContent}
                    </div>
                    ${footerHtml}
                    <div class="receipt-tear-bottom"></div>
                </div>
            `;

            receiptWrapper.innerHTML = finalHtml;
            emptyState.classList.add('hidden');
            receiptWrapper.classList.remove('hidden');
        }

        function startScanner() {
            document.getElementById('table-view').classList.add('hidden');
            const cameraView = document.getElementById('camera-view');
            cameraView.classList.remove('hidden');
            cameraView.classList.add('flex');

            // Only create a new instance if one doesn't exist
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("qr-reader");
            }

            const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                // The decoded text is the full QR_Code_Data string.
                // We need to find the session that has this QR data.
                const sessionArray = Object.values(allSessionsData);
                const foundSession = sessionArray.find(session => session.QR_Code_Data === decodedText);

                if (foundSession) {
                    document.getElementById('scan-success-sound').play();
                    // We found the session, now show its details.
                    showReceipt(foundSession);
                    stopScanner();
                    
                    // Optional: scroll to the row for better UX
                    const row = document.getElementById(`row-${foundSession.SessionID}`);
                    if (row) {
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    document.getElementById('scan-error-sound').play();
                    const scannerContainer = document.getElementById('qr-scanner-container');
                    scannerContainer.classList.add('shake-error');
                    setTimeout(() => scannerContainer.classList.remove('shake-error'), 820);
                    // The QR code was scanned, but it doesn't match any session in the current list.
                    document.getElementById('qr-reader-results').innerText = `QR Code not found. Try clearing filters.`;
                }
            };

            // We remove qrbox from the config. The library will use the full video feed,
            // and our custom CSS overlay will provide the visual guide, which is more reliable
            // and stylable than the library's built-in box.
            const config = { fps: 10 };

            // Start scanning. Prefer the back camera.
            html5QrCode.start({ facingMode: "environment" }, config, qrCodeSuccessCallback)
                .catch(err => {
                    console.error("Unable to start scanning.", err);
                    document.getElementById('qr-reader-results').innerText = "Error: Could not access camera.";
                });
        }

        function stopScanner() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().catch(err => console.error("Failed to stop scanner.", err));
            }
            const cameraView = document.getElementById('camera-view');
            cameraView.classList.add('hidden');
            cameraView.classList.remove('flex');
            document.getElementById('table-view').classList.remove('hidden');
            document.getElementById('qr-reader-results').innerText = '';
        }

        <?php
        if (isset($_SESSION['toast_message'])) {
            $toast = $_SESSION['toast_message'];
            unset($_SESSION['toast_message']);
            echo "document.addEventListener('DOMContentLoaded', () => showToast('" . addslashes($toast['text']) . "', '" . $toast['type'] . "'));";
        }
        ?>
    </script>
     <?php include '../../includes/layout_footer.php'; ?>   
</body>
</html>