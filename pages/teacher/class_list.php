<?php
session_start();
require_once '../../dbRelated/operation.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$class_id = $_GET['id'] ?? null;
$error = ""; // For displaying errors

// Handle Status Update (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_clearance'])) {
    $enrollment_id = $_POST['enrollment_id'];
    $current_status = $_POST['current_status'];
    
    $new_status = ($current_status === 'Cleared') ? 'Pending' : 'Cleared';

    if ($new_status === 'Cleared' && $db->hasUnresolvedDamages($enrollment_id)) {
        $error = "Action Blocked: This student has unresolved damages. Please resolve them first.";
    } else {
        $success = $db->updateClearanceStatus($enrollment_id, $new_status);
        if ($success) {
            header("Location: class_list.php?id=" . $class_id);
            exit();
        } else {
            $error = "System Error: Could not update status.";
        }
    }
}

// Fetch details via DataManager
$class_info = $db->getClassDetails($class_id);

if (!$class_info || ($class_info['TeacherID'] != $_SESSION['user_id'] && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: manage_classes.php");
    exit();
}

// --- Pagination and Search Logic ---
$search = trim($_GET['search'] ?? '');
$records_per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 15, 25, 50]) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

$pagination_options = [
    'limit' => $records_per_page,
    'page' => $page,
    'search' => $search
];

$paginated_students_data = $db->getPaginatedEnrolledStudents($class_id, $pagination_options);
$students = $paginated_students_data['data'];
$total_records = $paginated_students_data['total'];
$total_pages = $paginated_students_data['pages'];

// UI Variable for Header
$page_title = $class_info['Class_Name'] . " Enrollment";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class List | <?= htmlspecialchars($class_info['Class_Name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-12 flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                             <span class="bg-orange-500/10 text-orange-600 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-tighter">
                                <?= htmlspecialchars($class_info['Semester']) ?>
                            </span>
                            <span class="text-slate-300">•</span>
                            <span class="text-xs text-slate-400 font-bold uppercase tracking-widest"><?= htmlspecialchars($class_info['Section']) ?></span>
                        </div>
                        <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter">
                            Student <span class="text-orange-600">Roster.</span>
                        </h2>
                    </div>
                    
                    <div class="relative w-full md:w-80">
                        <input type="text" id="studentSearch" onkeyup="filterStudents()" 
                               placeholder="Search name or ID..." name="search" value="<?= htmlspecialchars($search) ?>"
                               class="w-full pl-12 pr-4 py-4 bg-white border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-orange-500 shadow-sm transition-all font-medium">
                        <svg class="w-6 h-6 absolute left-4 top-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </header>

                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl border border-red-200 font-bold text-sm">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-200/50 overflow-hidden">
                    <div class="p-6 border-b border-slate-50 bg-slate-50/30 flex justify-between items-center">
                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest italic">Enrollment Data (Page <?= $page ?> of <?= $total_pages ?>)</h3>
                        <span class="text-xs font-bold text-orange-600 bg-orange-50 px-3 py-1 rounded-lg"><?= $total_records ?> Records Found</span>
                    </div>

                    <?php if (empty($students) && empty($search)): ?>
                        <div class="p-20 text-center flex flex-col items-center">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                                <svg class="w-10 h-10 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </div>
                            <h3 class="text-[#0f172a] font-black text-xl italic uppercase tracking-tight">No Students Enrolled</h3>
                            <p class="text-slate-400 text-sm mt-2 max-w-xs">Use the Admission terminal in Manage Classes to add students to this section.</p>
                            <a href="manage_classes.php" class="mt-8 bg-[#0f172a] text-white px-8 py-3 rounded-2xl font-bold text-xs hover:bg-orange-600 transition-all">Go to Admission</a>
                        </div>
                    <?php elseif (empty($students) && !empty($search)): ?>
                        <div class="p-20 text-center flex flex-col items-center">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                                <svg class="w-10 h-10 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h3 class="text-[#0f172a] font-black text-xl italic uppercase tracking-tight">No Students Found</h3>
                            <p class="text-slate-400 text-sm mt-2 max-w-xs">Your search for "<?= htmlspecialchars($search) ?>" yielded no results.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="studentTable">
                                <thead class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-50">
                                    <tr>
                                        <th class="px-8 py-5">Student Identity</th>
                                        <th class="px-8 py-5">Account Status</th>
                                        <th class="px-8 py-5">Clearance Status</th>
                                        <th class="px-8 py-5 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php foreach ($students as $student): 
                                        $damages = $db->getStudentDamages($student['MasterID']);
                                        $has_damages = !empty($damages);
                                        $damages_json = htmlspecialchars(json_encode($damages), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <tr class="student-row hover:bg-orange-50/30 transition-all group">
                                            <td class="px-8 py-5">
                                                <p class="font-black text-[#0f172a] student-name leading-tight"><?= htmlspecialchars($student['Full_Name']) ?></p>
                                                <p class="font-mono text-xs text-slate-400 student-id"><?= htmlspecialchars($student['ID_Number']) ?></p>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <?php if ($student['Is_Verified'] == 1): ?>
                                                    <span class="px-3 py-1 bg-blue-500 text-white text-[9px] font-black rounded-lg uppercase tracking-tighter shadow-lg shadow-blue-500/20 italic">Verified</span>
                                                <?php else: ?>
                                                    <span class="px-3 py-1 bg-slate-100 text-slate-400 text-[9px] font-black rounded-lg uppercase tracking-tighter italic">Pending Auth</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-8 py-5">
                                                <div class="flex items-center gap-3">
                                                    <?php if ($student['ClearanceStatus'] === 'Cleared'): ?>
                                                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-black uppercase tracking-wide">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Cleared
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 border border-amber-100 text-amber-600 text-[10px] font-black uppercase tracking-wide">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span> Pending
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($has_damages): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-red-50 border border-red-100 text-red-500 text-[9px] font-bold uppercase tracking-wide animate-pulse">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                            Has Damages
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <form method="POST" id="form-<?= $student['EnrollmentID'] ?>">
                                                    <input type="hidden" name="enrollment_id" value="<?= $student['EnrollmentID'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $student['ClearanceStatus'] ?>">
                                                    <input type="hidden" name="toggle_clearance" value="1">
                                                    <button type="button" 
                                                            onclick="handleClearanceClick('<?= $student['EnrollmentID'] ?>', '<?= $student['ClearanceStatus'] ?>', '<?= $damages_json ?>', '<?= htmlspecialchars($student['Full_Name']) ?>')"
                                                            class="relative inline-flex items-center justify-center px-4 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all duration-200 border
                                                            <?= $student['ClearanceStatus'] === 'Cleared' 
                                                                ? 'bg-white border-red-100 text-red-500 hover:bg-red-50 hover:border-red-200' 
                                                                : 'bg-white border-orange-100 text-orange-600 hover:bg-orange-600 hover:text-white hover:border-orange-600' 
                                                            ?>">
                                                        <?= $student['ClearanceStatus'] === 'Cleared' ? 'Revoke' : 'Mark Cleared' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 0): ?>
                        <div class="p-6 border-t border-slate-50 flex justify-between items-center">
                            <!-- Left side: Items per page -->
                            <div class="flex items-center gap-2">
                                <form method="GET" action="class_list.php" class="flex items-center gap-2">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($class_id) ?>">
                                    <?php if (!empty($search)): ?>
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                    <?php endif; ?>
                                    <label for="per_page" class="text-xs font-bold text-slate-500">Show:</label>
                                    <select name="per_page" id="per_page" onchange="this.form.submit()" class="bg-slate-50 border-slate-200 rounded-md text-xs font-bold p-1 focus:ring-orange-500 focus:border-orange-500">
                                        <option value="10" <?= $records_per_page == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="15" <?= $records_per_page == 15 ? 'selected' : '' ?>>15</option>
                                        <option value="25" <?= $records_per_page == 25 ? 'selected' : '' ?>>25</option>
                                        <option value="50" <?= $records_per_page == 50 ? 'selected' : '' ?>>50</option>
                                    </select>
                                    <label class="text-xs font-bold text-slate-500">per page</label>
                                </form>
                            </div>

                            <!-- Right side: Page navigation -->
                            <?php if ($total_pages > 1): ?>
                            <div class="flex items-center gap-2">
                                <?php
                                    // Build the query string for pagination links
                                    $queryParams = ['id' => $class_id];
                                    if (!empty($search)) $queryParams['search'] = $search;
                                    if ($records_per_page !== 10) $queryParams['per_page'] = $records_per_page;
                                    $pagination_query_string = http_build_query($queryParams);

                                    $pagesToShow = 7;
                                    $pages = [];
                                    if ($total_pages <= $pagesToShow) {
                                        $pages = range(1, $total_pages);
                                    } else {
                                        $half = floor($pagesToShow / 2);
                                        if ($page <= $half + 1) {
                                            for ($i = 1; $i < $pagesToShow; $i++) { $pages[] = $i; }
                                            $pages[] = '...';
                                            $pages[] = $total_pages;
                                        } elseif ($page >= $total_pages - $half) {
                                            $pages[] = 1;
                                            $pages[] = '...';
                                            for ($i = $total_pages - ($pagesToShow - 2); $i <= $total_pages; $i++) { $pages[] = $i; }
                                        } else {
                                            $pages[] = 1;
                                            $pages[] = '...';
                                            for ($i = $page - ($half - 2); $i <= $page + ($half - 2); $i++) { $pages[] = $i; }
                                            $pages[] = '...';
                                            $pages[] = $total_pages;
                                        }
                                    }
                                ?>
                                <!-- Previous Button -->
                                <a href="?page=<?= max(1, $page - 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">Previous</a>

                                <!-- Page Numbers -->
                                <div class="flex items-center gap-1">
                                    <?php foreach ($pages as $p): ?>
                                        <div>
                                            <?php if ($p === '...'): ?>
                                                <span class="px-2 py-2 text-xs font-bold text-slate-400">…</span>
                                            <?php else: ?>
                                                <a href="?page=<?= $p ?>&<?= $pagination_query_string ?>" 
                                                   class="px-3 py-2 rounded-lg text-xs font-bold transition-colors 
                                                   <?= ($p == $page) 
                                                        ? 'bg-orange-500 text-white shadow-md' 
                                                        : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' 
                                                   ?>">
                                                    <?= $p ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Next Button -->
                                <a href="?page=<?= min($total_pages, $page + 1) ?>&<?= $pagination_query_string ?>" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">Next</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <div id="damageModal" class="fixed inset-0 z-50 hidden" style="z-index: 9999;">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-lg p-4">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden animate-reveal border border-slate-200">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-red-50">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center text-red-500 shadow-inner">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-slate-800 uppercase italic tracking-tighter">Cannot Clear Student</h3>
                            <p class="text-[10px] font-bold text-red-500 uppercase tracking-widest mt-1" id="modalStudentName">Student Name</p>
                        </div>
                    </div>
                    <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors bg-white rounded-full p-1 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6 bg-white">
                    <p class="text-sm text-slate-600 mb-6 font-medium">This student has <span class="font-bold text-red-500 underline decoration-red-200">unresolved damages</span>. Please resolve these items in the Settlement Reviews page before granting clearance.</p>
                    <div class="bg-slate-50 rounded-xl border border-slate-200 overflow-hidden shadow-inner">
                        <table class="w-full text-left"><thead class="bg-slate-100 border-b border-slate-200"><tr><th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-widest">Item / Qty</th><th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-widest">Issue</th><th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-widest text-right">Date</th></tr></thead><tbody id="modalDamageList" class="divide-y divide-slate-100"></tbody></table>
                    </div>
                </div>
                <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                    <button onclick="closeModal()" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all shadow-lg shadow-slate-200">Okay, I understand</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // The filterStudents function is no longer needed for client-side filtering
        // as filtering is now handled server-side via pagination.
        // However, the input field still exists, so we'll modify this to submit the form.
        function filterStudents() {
            // This function will now trigger a form submission for server-side filtering
            const searchInput = document.getElementById('studentSearch');
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('search', searchInput.value);
            currentUrl.searchParams.set('page', 1); // Reset to first page on new search
            window.location.href = currentUrl.toString();
        }

        function handleClearanceClick(enrollmentId, currentStatus, damagesJson, studentName) {
            if (currentStatus === 'Cleared') {
                document.getElementById('form-' + enrollmentId).submit();
                return;
            }
            const damages = JSON.parse(damagesJson);
            if (damages.length > 0) {
                showDamageModal(studentName, damages);
            } else {
                document.getElementById('form-' + enrollmentId).submit();
            }
        }

        function showDamageModal(name, damages) {
            const modal = document.getElementById('damageModal');
            const list = document.getElementById('modalDamageList');
            document.getElementById('modalStudentName').textContent = name;
            
            list.innerHTML = damages.map(item => `
                <tr class="hover:bg-red-50/50 transition-colors">
                    <td class="p-4"><div class="text-xs font-bold text-slate-800">${item.Item_Name || 'Unknown Item'}</div><div class="text-[9px] text-slate-400 font-mono mt-0.5">Qty: ${item.qty_damaged}</div></td>
                    <td class="p-4"><span class="inline-block px-2 py-0.5 rounded bg-red-100 text-red-600 text-[9px] font-bold uppercase italic border border-red-200">${item.damage_type || 'Broken'}</span><div class="text-[9px] text-slate-500 mt-1 italic">"${item.notes || 'No notes'}"</div></td>
                    <td class="p-4 text-right"><div class="text-[10px] text-slate-500 font-mono font-medium">${item.logged_at ? item.logged_at.split(' ')[0] : '-'}</div></td>
                </tr>
            `).join('');
            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('damageModal').classList.add('hidden');
        }
    </script>

    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>