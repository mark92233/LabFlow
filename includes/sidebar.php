<?php
// Ensure DB operations are available for the badge count
// Adjust the path 'dbRelated/operation.php' if your directory structure differs relative to this file
require_once __DIR__ . '/../dbRelated/operation.php';

$base_url = "/LabFlow/"; 
$current_script = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? 'Student';

// --- FETCH NOTIFICATION COUNTS (TEACHER ONLY) ---
$settlement_badge = 0;
$my_liabilities_badge = 0;
if (class_exists('DataManager')) {
    $db_nav = new DataManager();
    if (in_array($role, ['Teacher', 'Admin', 'LabTech'])) {
        if (method_exists($db_nav, 'getSettlementCases')) {
            $settlement_badge = count($db_nav->getSettlementCases('pending'));
        }
    }
    if (method_exists($db_nav, 'countUnresolvedLiabilities')) {
        $my_liabilities_badge = $db_nav->countUnresolvedLiabilities($_SESSION['user_id']);
    }
}

// Determine the active group based on the current page to ensure the correct accordion is open.
$current_script_path = $_SERVER['PHP_SELF'];
$active_group = null; // Default to null, meaning no specific group is active

$group_map = [
    'history' => [
        '/LabFlow/pages/student/active_slips.php',
        '/LabFlow/pages/student/settlement_cases.php',
        '/LabFlow/pages/common/profile.php',
        '/LabFlow/pages/common/change_password.php',
    ],
    'lab_hub' => [
        '/LabFlow/pages/teacher/add_activity.php',
        '/LabFlow/pages/teacher/handover.php',
        '/LabFlow/pages/teacher/settlement_reviews.php',
        '/LabFlow/pages/teacher/clearance_hub.php',
        '/LabFlow/pages/teacher/process_return.php',
        '/LabFlow/pages/teacher/request_list.php',
    ],
    'class_control' => [
        '/LabFlow/pages/teacher/manage_classes.php',
        '/LabFlow/pages/teacher/class_list.php',
        '/LabFlow/pages/teacher/class_activities.php',
        '/LabFlow/pages/teacher/activity_hub.php',
        '/LabFlow/pages/teacher/grading_view.php',
    ],
    'system' => [
        '/LabFlow/pages/admin/manage_inventory.php',
        '/LabFlow/pages/admin/manage_users.php',
    ]
];

foreach ($group_map as $group => $paths) {
    if (in_array($current_script_path, $paths)) {
        $active_group = $group;
        break;
    }
}

// If a group is determined for the current page, use it. Otherwise, fall back to localStorage.
$initial_group_json = json_encode($active_group);

?>

<style>
    /* 
     * Sidebar Collapse Styles 
     * Placed here to load before the body renders, preventing the "flash" of the uncollapsed state.
    */
    body.sidebar-collapsed #sidebar {
        width: 96px; /* w-24 */
    }

    body.sidebar-collapsed #sidebar .sidebar-logo-text,
    body.sidebar-collapsed #sidebar .sidebar-label {
        display: none;
    }

    body.sidebar-collapsed #sidebar .sidebar-logo-container {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }

    body.sidebar-collapsed #sidebar .nav-item {
        justify-content: center;
    }
</style>

<script>
    // Immediately apply sidebar state to prevent animation flash on load.
    if (localStorage.getItem('sidebarState') === 'collapsed') {
        document.body.classList.add('sidebar-collapsed');
    }
</script>

<aside
    id="sidebar"
    class="w-72 bg-white border-r border-gray-100 h-screen sticky top-0 hidden md:flex flex-col p-6 shadow-lg z-50 relative"
    x-data="{ open: false, openGroup: '' }"
    x-init="
        let activeGroup = <?= htmlspecialchars($initial_group_json, ENT_QUOTES, 'UTF-8') ?>;
        openGroup = activeGroup || localStorage.getItem('sidebarOpenGroup') || '';
        $watch('openGroup', val => localStorage.setItem('sidebarOpenGroup', val));
    "
>
    <div class="mb-12 px-2 flex items-center gap-3 sidebar-logo-container">
        <img src="<?= $base_url ?>HTML_DEMO/img/labflow.jpg" alt="LabFlow Logo" class="w-10 h-10 object-contain">
        <h1 class="sidebar-logo-text text-gray-800 font-black text-xl tracking-tighter uppercase">Lab<span class="font-light text-gray-400">Flow</span></h1>
    </div>

    <nav class="flex-1 space-y-2 overflow-y-auto custom-scrollbar">
    <?php 
    // Helper function for nav items
    function navItem($link, $label, $svg, $current_script, $badge_count = 0) {
        $link_script = basename(parse_url($link, PHP_URL_PATH));
        $isActive = ($link_script === $current_script) 
            ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/20' 
            : 'text-gray-500 hover:text-orange-600 hover:bg-orange-50';
        
        $badge_html = ($badge_count > 0) 
            ? "<span class='ml-auto bg-red-500 text-white text-[9px] font-black px-2 py-0.5 rounded-full shadow-sm animate-pulse'>$badge_count</span>" 
            : '';

        return "
        <a href='$link' title='$label' class='nav-item flex items-center gap-4 p-4 rounded-2xl transition-all group $isActive'>
            <svg class='w-5 h-5 flex-shrink-0' fill='none' stroke='currentColor' viewBox='0 0 24 24' stroke-width='2'>$svg</svg>
            <span class='sidebar-label font-bold text-xs uppercase tracking-widest truncate'>$label</span>
            $badge_html
        </a>";
    }

    // 1. GLOBAL ITEMS (Always Shown)
    echo "<ul><li>" . navItem($base_url . "dashboard/router.php", "Home Console", '<path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>', $current_script) . "</li>";
    
    if ($role === 'Student') { 
        echo "<li>" . navItem($base_url . "pages/student/my_classes.php", "My Classes", '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />', $current_script) . "</li>"; 
    }
    
    $hub_label = ($role === 'Student') ? "Apparatus Shop" : "Inventory Hub";
    echo "<li>" . navItem($base_url . "pages/common/inventory_hub.php", $hub_label, '<path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>', $current_script) . "</li>";
    
    if (in_array($role, ['Admin', 'Teacher', 'LabTech'])) { 
        echo "<li>" . navItem($base_url . "HTML_Demo/stock_room.php", "Stock Room Layout", '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>', $current_script) . "</li>"; 
    }
    echo "</ul>";

    // 2. ROLE-SPECIFIC SECTIONS
    if (in_array($role, ['Admin', 'Student', 'Teacher', 'LabTech'])) { 
        // Admin and Students use Accordions for History
        ?>
        <div class="pt-4">
            <button @click="openGroup = (openGroup === 'history' ? '' : 'history')" class="w-full flex justify-between items-center px-4 py-2 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] hover:text-gray-600 transition-colors">
                <span>My History</span>
                <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-180': openGroup === 'history' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            <div x-show="openGroup === 'history'" x-transition x-cloak class="pl-4 pt-2 space-y-1">
                <ul>
                    <li><?= navItem($base_url . "pages/student/active_slips.php", "Transaction History", '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>', $current_script) ?></li>
                    <?php if (in_array($role, ['Student', 'Teacher', 'Admin', 'LabTech'])): ?>
                        <li><?= navItem($base_url . "pages/student/settlement_cases.php", "My Liabilities", '<path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>', $current_script, $my_liabilities_badge) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    <?php }

    // 3. PRIVILEGED SECTIONS (Admin & Teacher)
    if (in_array($role, ['Admin', 'Teacher', 'LabTech'])) { ?>
        <div class="pt-4">
            <button @click="openGroup = (openGroup === 'lab_hub' ? '' : 'lab_hub')" class="w-full flex justify-between items-center px-4 py-2 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] hover:text-gray-600 transition-colors">
                <span>Laboratory Hub</span>
                <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-180': openGroup === 'lab_hub' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            <div x-show="openGroup === 'lab_hub'" x-transition x-cloak class="pl-4 pt-2 space-y-1">
                <ul>
                    <?php if (in_array($role, ['Admin', 'Teacher'])): ?>
                        <li><?= navItem($base_url . "pages/teacher/add_activity.php", "Post Lab Activity", '<path d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>', $current_script) ?></li>
                    <?php endif; ?>
                    <?php if (in_array($role, ['Admin', 'LabTech'])): ?>
                        <li><?= navItem($base_url . "pages/teacher/handover.php", "Handover Terminal", '<path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>', $current_script) ?></li>
                        <li><?= navItem($base_url . "pages/teacher/settlement_reviews.php", "Settlement Reviews", '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>', $current_script, $settlement_badge) ?></li>
                        <li><?= navItem($base_url . "pages/teacher/clearance_hub.php", "Clearance Hub", '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />', $current_script) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="pt-4" x-show="'<?= $role ?>' === 'Admin' || '<?= $role ?>' === 'Teacher'">
            <button @click="openGroup = (openGroup === 'class_control' ? '' : 'class_control')" class="w-full flex justify-between items-center px-4 py-2 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] hover:text-gray-600 transition-colors">
                <span>Class Control</span>
                <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-180': openGroup === 'class_control' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            <div x-show="openGroup === 'class_control'" x-transition x-cloak class="pl-4 pt-2 space-y-1">
                <ul>
                    <li><?= navItem($base_url . "pages/teacher/manage_classes.php", "Class Registry", '<path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>', $current_script) ?></li>
                </ul>
            </div>
        </div>
    <?php }

    // 4. SYSTEM MGMT (Admin Only)
    if ($role === 'Admin') { ?>
        <div class="pt-4">
            <button @click="openGroup = (openGroup === 'system' ? '' : 'system')" class="w-full flex justify-between items-center px-4 py-2 text-left text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] hover:text-gray-600 transition-colors">
                <span>System Mgmt</span>
                <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-180': openGroup === 'system' }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </button>
            <div x-show="openGroup === 'system'" x-transition x-cloak class="pl-4 pt-2 space-y-1">
                <ul>
                    <li><?= navItem($base_url . "pages/admin/manage_inventory.php", "Register Apparatus", '<path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>', $current_script) ?></li>
                    <li><?= navItem($base_url . "pages/admin/manage_users.php", "User Management", '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-4.663M12 12a4.5 4.5 0 100-9 4.5 4.5 0 000 9z" />', $current_script) ?></li>
                </ul>
            </div>
        </div>
    <?php } ?>
</nav>
 
    <div class="mt-auto pt-6 border-t border-gray-100">
        <!-- Profile Button -->
        <button @click="open = !open" class="w-full flex items-center gap-3 p-2 rounded-2xl hover:bg-gray-100 transition-colors">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user_name']) ?>&background=f97316&color=fff" 
                 class="w-10 h-10 rounded-xl border-2 border-white shadow-md" alt="User Avatar">
            <div class="text-left sidebar-label flex-1">
                <p class="text-xs font-bold text-gray-800 truncate"><?= $_SESSION['user_name'] ?></p>
                <p class="text-[10px] text-gray-500 uppercase truncate"><?= $_SESSION['user_role'] ?></p>
            </div>
            <svg class="w-4 h-4 text-gray-400 sidebar-label" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path></svg>
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open"
             @click.away="open = false"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="transform opacity-0 scale-95"
             x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="transform opacity-100 scale-100"
             x-transition:leave-end="transform opacity-0 scale-95"
             class="absolute left-full bottom-6 ml-2 w-64 bg-white rounded-2xl shadow-2xl border border-gray-100 z-[60] origin-bottom-left"

             role="menu"
             style="display: none;">
            
            <div class="p-2">
                <!-- Profile Info -->
                <div class="flex items-center gap-3 p-2 rounded-lg">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user_name']) ?>&background=f97316&color=fff" class="w-10 h-10 rounded-xl" alt="User Avatar">
                    <div class="text-left">
                        <p class="text-sm font-bold text-gray-800 truncate"><?= $_SESSION['user_name'] ?></p>
                        <p class="text-xs text-gray-500 uppercase truncate"><?= $_SESSION['user_role'] ?></p>
                    </div>
                </div>
                <div class="my-1 h-px bg-gray-100"></div>
                <!-- Open Profile -->
                <a href="/LabFlow/pages/common/profile.php" role="menuitem" class="flex items-center gap-3 w-full px-3 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors"><svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg><span>Open Profile</span></a>
                <div class="my-1 h-px bg-gray-100"></div>
                <!-- Logout -->
                <button @click="open = false; openLogoutModal()" role="menuitem" class="flex items-center gap-3 w-full px-3 py-2 text-sm text-red-600 rounded-lg hover:bg-red-50 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-6 0v-1m6 0H9"></path></svg><span>Log Out</span></button>
            </div>
        </div>
    </div>
</aside>