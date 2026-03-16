<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control: Logged-in users only
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$student_id = $_SESSION['user_id'];
 
// --- Get Filters, Search, and Pagination ---
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status_filter'] ?? 'all';
$date_sort = $_GET['date_sort'] ?? 'desc';
$records_per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 15, 20, 50, 100]) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// --- Build Query ---
$params = ['student_id' => $student_id];
$where_clauses = "bs.StudentID = :student_id";

if ($status_filter !== 'all') {
    $where_clauses .= " AND bs.Status = :status";
    $params['status'] = $status_filter;
}

if (!empty($search)) {
    // Search by SessionID, Activity Title, or Remarks (Purpose)
    $where_clauses .= " AND (bs.SessionID LIKE :search OR la.Title LIKE :search OR bs.Remarks LIKE :search)";
    $params['search'] = "%$search%";
}

// Base query for both count and data
$base_query = "FROM borrowing_sessions bs
               JOIN users u ON bs.StudentID = u.UserID
               JOIN lookup_masterlist m ON u.MasterID = m.MasterID
               LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
               WHERE $where_clauses";

// Get total records
$count_query = "SELECT COUNT(DISTINCT bs.SessionID) " . $base_query;
$count_stmt = $db->db->prepare($count_query);
$count_stmt->execute($params);
$total_records = (int) $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Fetch paginated data
$sort_direction = (strtolower($date_sort) === 'asc') ? 'ASC' : 'DESC';
$query = "SELECT bs.SessionID, bs.Status, bs.CreatedAt, m.Full_Name as StudentName, m.ID_Number as studentId, COALESCE(la.Title, 'Independent Research') as Title, bs.Remarks, bs.QR_Code_Data
          " . $base_query . "
          GROUP BY bs.SessionID
          ORDER BY bs.CreatedAt {$sort_direction}
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
    $iStmt = $db->db->prepare("SELECT i.Item_Name, bi.Quantity, i.is_consumable, iv.Size_Value, iv.Unit FROM borrowed_items bi JOIN inventory i ON bi.ItemID = i.ItemID LEFT JOIN item_variants iv ON bi.VariantID = iv.VariantID WHERE bi.SessionID = ?");
    $iStmt->execute([$session['SessionID']]);
    $sessionData = $session;
    $sessionData['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
    $sessionsForJs[$session['SessionID']] = $sessionData;
}
$page_title = "My Transaction History";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap');
        .thermal-font { font-family: 'Courier Prime', 'Courier New', Courier, monospace; }
        .sticky-sidebar { height: calc(100vh - 120px); position: sticky; top: 100px; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const filterForm = document.getElementById('filterForm');
            let debounceTimeout;

            if (searchInput && filterForm) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(() => {
                        const urlParams = new URLSearchParams(window.location.search);
                        const status = urlParams.get('status_filter');
                        if (status) {
                            let statusInput = filterForm.querySelector('input[name="status_filter"]');
                            if (!statusInput) { statusInput = document.createElement('input'); statusInput.type = 'hidden'; statusInput.name = 'status_filter'; filterForm.appendChild(statusInput); }
                            statusInput.value = status;
                        }
                        filterForm.submit();
                    }, 400);
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
                    <header class="mb-2">
                        <h2 class="text-4xl font-extrabold text-gray-800 tracking-tighter">My Slips.</h2>
                        <p class="text-slate-400 font-medium text-xs">A record of your borrowing history.</p>
                    </header>

                    <div class="bg-white p-4 rounded-2xl border border-gray-200/50 shadow-sm">
                        <form id="filterForm" method="GET" action="active_slips.php" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div class="md:col-span-2">
                                <label for="search" class="text-xs font-bold text-gray-500 mb-2 block">Search by Slip #, Activity, or Purpose</label>
                                <input type="search" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. 123 or Titration" class="w-full bg-gray-50 border-gray-200 p-3 rounded-xl font-medium text-sm shadow-sm focus:ring-2 focus:ring-orange-500">
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

                    <div class="bg-white p-2 rounded-2xl border border-gray-200/50 shadow-sm">
                        <div class="flex items-center gap-2">
                            <?php
                                $tabs = ['all' => 'All', 'Pending' => 'Pending', 'Approved' => 'Approved', 'Issued' => 'Issued', 'Returned' => 'Returned'];
                                $base_params = ['search' => $search, 'date_sort' => $date_sort];
                            ?>
                            <?php foreach ($tabs as $key => $label):
                                $current_params = $base_params;
                                $current_params['status_filter'] = $key;
                                $queryString = http_build_query(array_filter($current_params));
                                $isActive = ($status_filter === $key);
                            ?>
                                <a href="?<?= $queryString ?>" class="flex-1 text-center px-4 py-3 rounded-xl font-bold text-xs uppercase tracking-wider transition-all duration-300 <?= $isActive ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/20' : 'text-gray-500 hover:bg-gray-50' ?>"><?= $label ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200/50 flex-1 flex flex-col overflow-hidden">
                        <?php if (empty($sessions)): ?>
                            <div class="flex-1 flex flex-col items-center justify-center text-center p-10"><div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4"><svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></div><h3 class="font-bold text-gray-700">No Slips Found</h3><p class="text-sm text-gray-400">There are no transaction slips matching your current filters.</p></div>
                        <?php else: ?>
                            <div class="overflow-y-auto flex-1">
                                <table class="w-full text-left">
                                    <thead class="bg-gray-50 border-b border-gray-100 sticky top-0">
                                        <tr>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Activity / Purpose</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($sessions as $session):
                                            $statusClass = match($session['Status']) {
                                                'Approved' => 'bg-orange-100 text-orange-600',
                                                'Issued' => 'bg-indigo-100 text-indigo-600',
                                                'Pending' => 'bg-amber-100 text-amber-600',
                                                'Returned' => 'bg-green-100 text-green-600',
                                                default => 'bg-slate-100 text-slate-500'
                                            };
                                            $sessionData = $sessionsForJs[$session['SessionID']] ?? [];
                                            $sessionJSON = htmlspecialchars(json_encode($sessionData), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr id="row-<?= $session['SessionID'] ?>" onclick='showReceipt(<?= $sessionJSON ?>)' class="hover:bg-orange-50/50 transition-colors cursor-pointer">
                                                <td class="px-6 py-4">
                                                    <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($session['Title']) ?></p>
                                                    <p class="text-xs text-gray-500 italic truncate max-w-xs">"<?= htmlspecialchars($session['Remarks'] ?? 'No reason provided.') ?>"</p>
                                                </td>
                                                <td class="px-6 py-4 text-xs text-gray-600 font-medium">
                                                    <?= date('M d, Y H:i', strtotime($session['CreatedAt'])) ?>
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
                            <div class="p-6 border-t border-gray-100 flex justify-between items-center">
                                <div class="flex items-center gap-4">
                                    <p class="text-xs font-bold text-gray-500">Page <?= $page ?> of <?= $total_pages ?></p>
                                    <form method="GET" action="active_slips.php" class="flex items-center gap-2">
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                                        <input type="hidden" name="date_sort" value="<?= htmlspecialchars($date_sort) ?>">
                                        <select name="per_page" onchange="this.form.submit()" class="bg-gray-50 border-gray-200 rounded-md text-xs font-bold p-1 focus:ring-orange-500 focus:border-orange-500">
                                            <option value="10" <?= $records_per_page == 10 ? 'selected' : '' ?>>10</option>
                                            <option value="15" <?= $records_per_page == 15 ? 'selected' : '' ?>>15</option>
                                            <option value="20" <?= $records_per_page == 20 ? 'selected' : '' ?>>20</option>
                                            <option value="50" <?= $records_per_page == 50 ? 'selected' : '' ?>>50</option>
                                            <option value="100" <?= $records_per_page == 100 ? 'selected' : '' ?>>100</option>
                                        </select>
                                        <label class="text-xs font-bold text-gray-500">per page</label>
                                    </form>
                                </div>
                                <div class="flex gap-2">
                                    <?php
                                        $queryParams = ['search' => $search, 'status_filter' => $status_filter, 'date_sort' => $date_sort, 'per_page' => $records_per_page];
                                        $pagination_query_string = http_build_query(array_filter($queryParams));
                                    ?>
                                    <a href="?page=<?= max(1, $page - 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">Previous</a>
                                    <a href="?page=<?= min($total_pages, $page + 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-xs font-bold hover:bg-gray-50 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">Next</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Column (Details Panel) -->
                <aside class="w-96 sticky-sidebar">
                    <div id="receipt-panel-container" class="h-full">
                        <div id="receipt-empty-state" class="h-full flex flex-col items-center justify-center text-center p-8 text-slate-400 border-2 border-dashed border-slate-200 rounded-3xl">
                            <p class="text-sm mt-1">Click on a slip to view its details.</p>
                        </div>
                        <div id="receipt-content-wrapper" class="hidden h-full">
                            <!-- JS will inject receipt content here -->
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>

    <script>
        function downloadReceipt(sessionId) {
            const element = document.getElementById(`receipt-capture-${sessionId}`);
            if (!element) {
                console.error('Receipt element not found for download');
                return;
            }
            html2canvas(element, {
                scale: 3,
                backgroundColor: null 
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = `SNHS-Receipt-${sessionId}.png`;
                link.href = canvas.toDataURL();
                link.click();
            });
        }
        function showReceipt(sessionData) {
            const allRows = document.querySelectorAll('tbody tr');
            allRows.forEach(row => row.classList.remove('bg-orange-100'));
            const selectedRow = document.getElementById(`row-${sessionData.SessionID}`);
            if (selectedRow) selectedRow.classList.add('bg-orange-100');

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
                        <span class="uppercase">${item.Item_Name}${size}</span>
                        <span>${item.Quantity}</span>
                    </div>`;
                }).join('');
            }

            if (consumables.length > 0) {
                itemsHtml += '<div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 mt-4">Consumables</div>';
                itemsHtml += consumables.map(item => {
                    const size = item.Size_Value ? ` (${item.Size_Value}${item.Unit || ''})` : '';
                    return `
                    <div class="flex justify-between text-[10px] font-bold py-1 border-b border-dashed border-slate-200">
                        <span class="uppercase">${item.Item_Name}${size}</span>
                        <span>${item.Quantity}</span>
                    </div>`;
                }).join('');
            }

            const receiptContent = `
                <div id="receipt-capture-${sessionData.SessionID}" class="receipt-container thermal-font bg-white rounded-2xl shadow-xl border border-slate-200/50 h-full flex flex-col">
                    <div class="p-6 flex-1 overflow-y-auto custom-scrollbar flex flex-col" >
                        <div class="text-center mb-6 border-b-2 border-black/10 pb-4">
                            <h4 class="text-xl font-bold uppercase tracking-widest">CSM LAB</h4>
                            <p class="text-[9px] uppercase mt-1 text-slate-500">Requisition Slip</p>
                        </div>
                        <div class="space-y-1 mb-6 text-[10px] uppercase font-bold">
                            <div class="flex justify-between"><span>Student:</span><span>${sessionData.StudentName}</span></div>
                            <div class="flex justify-between"><span>ID:</span><span>${sessionData.studentId}</span></div>
                            <div class="flex justify-between"><span>Date:</span><span>${new Date(sessionData.CreatedAt).toLocaleString()}</span></div>
                            <div class="flex justify-between items-start"><span>Activity:</span><span class="text-right w-1/2 truncate">${sessionData.Title}</span></div>
                            <div class="flex justify-between"><span>Session:</span><span>#${sessionData.SessionID}</span></div>
                        </div>
                        <div class="mb-6 flex-1 overflow-y-auto custom-scrollbar pr-2">
                            <div class="flex justify-between text-xs font-bold border-b border-dashed border-black mb-2 pb-1">
                                <span>ITEM</span>
                                <span>QTY</span>
                            </div>
                            <div class="space-y-1" id="receipt-items">
                                ${itemsHtml}
                            </div>
                        </div>
                        <div class="flex flex-col items-center pt-4 mt-auto">
                            <div id="qrcode-container-${sessionData.SessionID}" class="mb-4 mix-blend-multiply opacity-90"></div>
                        </div>
                        <div class="mt-auto pt-4 text-center text-xs text-slate-500">
                            *** ${sessionData.Status.toUpperCase()} ***
                        </div>
                    </div>
                    <div class="p-4 border-t border-dashed border-slate-200 bg-slate-50/50">
                        <button onclick="downloadReceipt(${sessionData.SessionID})" 
                           class="block w-full text-center bg-slate-800 text-white py-3 rounded-xl text-xs font-bold uppercase hover:bg-blue-600 transition-all shadow-lg shadow-slate-200">
                            Save as Image
                        </button>
                    </div>
                </div>
            `;

            receiptWrapper.innerHTML = receiptContent;

            // --- QR Code Generation ---
            const qrContainer = document.getElementById(`qrcode-container-${sessionData.SessionID}`);
            const qrData = sessionData.QR_Code_Data || '';
            const qrEligible = ['Pending', 'Approved', 'Issued'];

            if(qrContainer && qrData !== "" && qrEligible.includes(sessionData.Status)) {
                qrContainer.innerHTML = ""; // Clear previous QR code
                new QRCode(qrContainer, {
                    text: qrData,
                    width: 120,
                    height: 120,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
                qrContainer.insertAdjacentHTML('afterend', '<p class="text-[8px] font-bold text-center uppercase text-slate-400">Scan for Release/Return</p>');
            } else if (qrContainer) {
                qrContainer.innerHTML = `<div class="w-24 h-24 border-2 border-dashed border-slate-200 rounded-lg flex items-center justify-center mb-4"><span class="text-2xl">🔒</span></div><p class="text-[8px] font-bold text-center uppercase text-slate-400">Transaction Closed</p>`;
            }

            emptyState.classList.add('hidden');
            receiptWrapper.classList.remove('hidden');
        }
    </script>

    <?php
    $toast_message = null;
    $toast_type = 'success'; // Default type

    if (isset($_SESSION['toast_message'])) {
        $toast_message = $_SESSION['toast_message']['text'];
        $toast_type = $_SESSION['toast_message']['type'];
        unset($_SESSION['toast_message']);
    }
    ?>

    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl">
            <!-- Icon will be inserted by JS -->
        </div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <script>
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast-container');
        if (!toast) return;
        const iconContainer = document.getElementById('toast-icon-container');
        const messageContainer = document.getElementById('toast-message');
        toast.className = 'fixed bottom-10 right-10 z-[200] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal';
        iconContainer.className = 'inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl';
        messageContainer.textContent = message;
        if (type === 'success') { toast.classList.add('bg-emerald-600'); iconContainer.classList.add('bg-emerald-100'); iconContainer.innerHTML = `<svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>`; } else { toast.classList.add('bg-red-600'); iconContainer.classList.add('bg-red-100'); iconContainer.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`; }
        toast.classList.remove('hidden'); toast.style.opacity = '1'; toast.style.transform = 'translateY(0)';
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
    }
    <?php if ($toast_message): ?>
    document.addEventListener('DOMContentLoaded', function() { showToast('<?php echo addslashes($toast_message); ?>', '<?php echo $toast_type; ?>'); });
    <?php endif; ?>
    </script>
     <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>