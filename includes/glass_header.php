<?php
// --- Breadcrumb Generation ---
$breadcrumbs = [];
$base_url = "/LabFlow/";
$current_script = $_SERVER['PHP_SELF'];

// A map of pages. Key is the script path. Value is an array with label and parent path.
$page_map = [
    // Dashboard
    $base_url . 'dashboard/router.php' => ['label' => 'Home', 'parent' => null],

    // Teacher/Class Management
    $base_url . 'pages/teacher/manage_classes.php' => ['label' => 'Class Registry', 'parent' => $base_url . 'dashboard/router.php'],
    $base_url . 'pages/teacher/class_list.php' => ['label' => 'Enrollment', 'parent' => $base_url . 'pages/teacher/manage_classes.php'],
    $base_url . 'pages/teacher/class_activities.php' => ['label' => 'Activities', 'parent' => $base_url . 'pages/teacher/manage_classes.php'],
    $base_url . 'pages/teacher/add_activity.php' => ['label' => 'New Activity', 'parent' => $base_url . 'pages/teacher/class_activities.php'],
    $base_url . 'pages/teacher/activity_hub.php' => ['label' => 'Activity Hub', 'parent' => $base_url . 'pages/teacher/class_activities.php'],
    $base_url . 'pages/teacher/grading_view.php' => ['label' => 'Grading', 'parent' => $base_url . 'pages/teacher/activity_hub.php'],
    $base_url . 'pages/teacher/clearance_hub.php' => ['label' => 'Clearance', 'parent' => $base_url . 'pages/teacher/manage_classes.php'],
    
    // Admin/LabTech
    $base_url . 'pages/teacher/handover.php' => ['label' => 'Handover Terminal', 'parent' => $base_url . 'dashboard/router.php'],
    $base_url . 'pages/teacher/settlement_reviews.php' => ['label' => 'Settlement Reviews', 'parent' => $base_url . 'dashboard/router.php'],
    $base_url . 'pages/teacher/request_list.php' => ['label' => 'Borrowing Queue', 'parent' => $base_url . 'dashboard/router.php'],
    $base_url . 'pages/teacher/process_return.php' => ['label' => 'Process Return', 'parent' => $base_url . 'pages/teacher/handover.php'],

    // Common
    $base_url . 'pages/common/inventory_hub.php' => ['label' => 'Inventory', 'parent' => $base_url . 'dashboard/router.php'],
    $base_url . 'pages/common/cart_page.php' => ['label' => 'My Cart', 'parent' => $base_url . 'pages/common/inventory_hub.php'],

    // Admin
    $base_url . 'pages/admin/manage_inventory.php' => ['label' => 'Manage Inventory', 'parent' => $base_url . 'pages/common/inventory_hub.php'],
    $base_url . 'pages/admin/manage_users.php' => ['label' => 'User Management', 'parent' => $base_url . 'dashboard/router.php'],

    // Student
    $base_url . 'dashboard/student_dash.php' => ['label' => 'Dashboard', 'parent' => $base_url . 'dashboard/router.php'],
    $base_url . 'pages/student/my_classes.php' => ['label' => 'My Classes', 'parent' => $base_url . 'dashboard/student_dash.php'],
    $base_url . 'pages/student/lab_list.php' => ['label' => 'Class Activities', 'parent' => $base_url . 'pages/student/my_classes.php'],
    $base_url . 'pages/student/activity_view.php' => ['label' => 'Lab Manual', 'parent' => $base_url . 'pages/student/lab_list.php'],
    $base_url . 'pages/student/workspace.php' => ['label' => 'Workspace', 'parent' => $base_url . 'pages/student/activity_view.php'],
    $base_url . 'pages/student/editor.php' => ['label' => 'Editor', 'parent' => $base_url . 'pages/student/workspace.php'],
    $base_url . 'pages/student/preview_compiler.php' => ['label' => 'Final Review', 'parent' => $base_url . 'pages/student/workspace.php'],
    $base_url . 'pages/student/active_slips.php' => ['label' => 'Transaction History', 'parent' => $base_url . 'dashboard/router.php'],
    $base_url . 'pages/student/settlement_cases.php' => ['label' => 'My Liabilities', 'parent' => $base_url . 'dashboard/router.php'],
];

// Build breadcrumbs by tracing parents
$path_trail = [];
$current_path = $current_script;
while ($current_path !== null && isset($page_map[$current_path])) {
    array_unshift($path_trail, $current_path);
    $current_path = $page_map[$current_path]['parent'];
}

foreach ($path_trail as $index => $path) {
    $page_info = $page_map[$path];
    $label = ($index === count($path_trail) - 1 && isset($page_title)) ? $page_title : $page_info['label'];
    $is_current_page = ($index === count($path_trail) - 1);
    $url = $is_current_page ? '#' : $path;

    // Preserve query parameters for parent links to maintain context
    if (!$is_current_page) {
        $paramsToCarry = [];
        
        // This makes the breadcrumb context-aware. If a page defines $activity, we can use its IDs.
        $context_class_id = $_GET['class_id'] ?? ($activity['ClassID'] ?? null);
        $context_activity_id = $_GET['activity_id'] ?? ($activity['ActivityID'] ?? null);

        // Carry 'class_id' to relevant pages. Note that class_list.php uses 'id'.
        if ($context_class_id) {
            if ($path === $base_url . 'pages/teacher/class_list.php') {
                $paramsToCarry['id'] = $context_class_id;
            } elseif (in_array($path, [
                $base_url . 'pages/teacher/class_activities.php', $base_url . 'pages/teacher/activity_hub.php', $base_url . 'pages/teacher/clearance_hub.php',
                $base_url . 'pages/student/lab_list.php'
            ])) {
                $paramsToCarry['class_id'] = $context_class_id;
            }
        }
        // Carry 'activity_id' to relevant pages
        if ($context_activity_id && in_array($path, [$base_url . 'pages/teacher/activity_hub.php', $base_url . 'pages/student/activity_view.php'])) {
            $paramsToCarry['activity_id'] = $context_activity_id;
            // activity_view also needs class_id, so we ensure it's carried over.
            if ($context_class_id) {
                $paramsToCarry['class_id'] = $context_class_id;
            }
        }
        if (!empty($paramsToCarry)) { $url .= '?' . http_build_query($paramsToCarry); }
    }

    $breadcrumbs[] = ['label' => $label, 'url' => $url];
}

// If breadcrumbs are empty (e.g., on a page not in the map), default to the page title
if (empty($breadcrumbs)) {
    $breadcrumbs[] = ['label' => $page_title ?? 'Portal', 'url' => '#'];
}
?>
<header class="sticky top-0 z-40 px-8 py-6 flex justify-between items-center bg-white/80 backdrop-blur-md border-b border-gray-200">
    <div class="flex items-center gap-4">
        <button onclick="toggleSidebar()" title="Toggle Sidebar" class="p-3 bg-gray-100 shadow-sm border border-gray-200 rounded-2xl text-gray-500 hover:bg-orange-500 hover:text-white transition-all cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
        <div>
            <!-- Breadcrumb Navigation -->
            <nav class="flex mb-1" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <li class="inline-flex items-center">
                            <?php if ($index > 0): ?>
                                <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                                </svg>
                            <?php endif; ?>
                            
                            <?php if ($crumb['url'] !== '#'): ?>
                                <a href="<?= htmlspecialchars($crumb['url']) ?>" class="text-sm font-bold text-gray-500 hover:text-orange-600 transition-colors truncate max-w-[200px] md:max-w-none">
                                    <?= htmlspecialchars($crumb['label']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-sm font-bold text-gray-800 truncate max-w-[200px] md:max-w-none">
                                    <?= htmlspecialchars($crumb['label']) ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">CSM Laboratory</p>
        </div>
    </div>

    <div class="flex items-center gap-6"
         x-data="{ cartCount: 0 }"
         @cart-updated.window="cartCount = $event.detail.cart.length"
         x-cloak>
        <div class="relative cursor-pointer text-gray-500 hover:text-orange-500 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <span class="absolute top-0 right-0 w-2 h-2 bg-amber-500 rounded-full border-2 border-white"></span>
        </div>

        <a href="/LabFlow/pages/common/cart_page.php" title="View Cart" class="relative cursor-pointer text-gray-500 hover:text-orange-500 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            <span x-show="cartCount > 0" x-text="cartCount" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center animate-pulse">
            </span>
        </a>

        <div class="flex items-center gap-3 pl-6 border-l-2 border-gray-100">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-gray-800"><?= $_SESSION['user_name'] ?></p>
                <p class="text-[10px] text-gray-500 uppercase"><?= $_SESSION['user_role'] ?></p>
            </div>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user_name']) ?>&background=f97316&color=fff" 
                 class="w-10 h-10 rounded-2xl border-2 border-orange-500 shadow-md shadow-orange-500/20" alt="">
        </div>
    </div>
</header>