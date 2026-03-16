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
    if ($role === 'Teacher' || $role === 'Admin') {
        if (method_exists($db_nav, 'getSettlementCases')) {
            $settlement_badge = count($db_nav->getSettlementCases('pending'));
        }
    }
    if ($role === 'Student') {
        if (method_exists($db_nav, 'countUnresolvedLiabilities')) {
            $my_liabilities_badge = $db_nav->countUnresolvedLiabilities($_SESSION['user_id']);
        }
    }
}
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

<aside id="sidebar" class="w-72 bg-white border-r border-gray-100 h-screen sticky top-0 hidden md:flex flex-col p-6 shadow-lg z-50">
    <div class="mb-12 px-2 flex items-center gap-3 sidebar-logo-container">
        <img src="<?= $base_url ?>HTML_DEMO/img/labflow.jpg" alt="LabFlow Logo" class="w-10 h-10 object-contain">
        <h1 class="sidebar-logo-text text-gray-800 font-black text-xl tracking-tighter uppercase">Lab<span class="font-light text-gray-400">Flow</span></h1>
    </div>

    <nav class="flex-1 space-y-2 overflow-y-auto custom-scrollbar">
        <?php 
        // Helper function with BADGE support
        function navItem($link, $label, $svg, $current_script, $badge_count = 0) {
            $link_script = basename(parse_url($link, PHP_URL_PATH));
            
            // Active State Logic
            $isActive = ($link_script === $current_script) 
                ? 'bg-orange-500 text-white shadow-lg shadow-orange-500/20' 
                : 'text-gray-500 hover:text-orange-600 hover:bg-orange-50';
            
            // Badge HTML
            $badge_html = '';
            if ($badge_count > 0) {
                $badge_html = "<span class='ml-auto bg-red-500 text-white text-[9px] font-black px-2 py-0.5 rounded-full shadow-sm animate-pulse'>$badge_count</span>";
            }

            return "
            <a href='$link' title='$label' class='nav-item flex items-center gap-4 p-4 rounded-2xl transition-all group $isActive'>
                <svg class='w-5 h-5 flex-shrink-0' fill='none' stroke='currentColor' viewBox='0 0 24 24' stroke-width='2'>$svg</svg>
                <span class='sidebar-label font-bold text-xs uppercase tracking-widest truncate'>$label</span>
                $badge_html
            </a>";
        }

        // 1. DASHBOARD (All Users)
        echo navItem($base_url . "dashboard/router.php", "Home Console", '<path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>', $current_script);
        if ($role === 'Student') { echo navItem($base_url . "pages/student/my_classes.php", "My Classes", '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />', $current_script); }

        // 2. INVENTORY HUB (All Users)
        $hub_label = ($role === 'Student') ? "Apparatus Shop" : "Inventory Hub";
        echo navItem($base_url . "pages/common/inventory_hub.php", $hub_label, '<path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>', $current_script);
        
        // 3. STUDENT & ADMIN PERSONAL HISTORY
        if ($role === 'Student' || $role === 'Admin' || $role === 'Teacher') {
            echo "<p class='sidebar-label text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] px-4 mt-8 mb-4'>My History</p>";
            echo navItem($base_url . "pages/student/active_slips.php", "Transaction History", '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>', $current_script);
            if ($role === 'Student') {
                echo navItem($base_url . "pages/student/settlement_cases.php", "My Liabilities", '<path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>', $current_script, $my_liabilities_badge);
            }
        }

        // 4. TEACHER / ADMIN SPECIFIC
        if ($role === 'Teacher' || $role === 'Admin') {
            echo "<p class='sidebar-label text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] px-4 mt-8 mb-4'>Laboratory Hub</p>";
            
            echo navItem($base_url . "pages/teacher/add_activity.php", "Post Lab Activity", '<path d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>', $current_script);
            
            // --- ADMIN-ONLY TRANSACTIONAL LINKS ---
            if ($role === 'Admin') {
                // Handover Terminal for approving/issuing items
                echo navItem($base_url . "pages/teacher/handover.php", "Handover Terminal", '<path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>', $current_script);
                // Settlement Reviews for managing damages
                echo navItem($base_url . "pages/teacher/settlement_reviews.php", "Settlement Reviews", '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>', $current_script, $settlement_badge);
            }
            
            echo "<p class='sidebar-label text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] px-4 mt-8 mb-4'>Class Control</p>";
            echo navItem($base_url . "pages/teacher/manage_classes.php", "Class Registry", '<path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>', $current_script);
        }

        // 5. ADMIN ONLY
        if ($role === 'Admin') {
            echo "<p class='sidebar-label text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] px-4 mt-8 mb-4'>System Mgmt</p>";
            echo navItem($base_url . "pages/admin/manage_inventory.php", "Register Apparatus", '<path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>', $current_script);
            echo navItem($base_url . "pages/admin/manage_users.php", "User Management", '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m-7.5-2.952A3 3 0 0010.5 17.5 3 3 0 0010.5 17.5v-.214m-2.952a3 3 0 00-4.682-2.72A9.094 9.094 0 003.279 18.72m0 0a9.094 9.094 0 007.465.124m-7.465.124L3 21m14.73-2.124l1.732 1.732a3 3 0 01-4.243 4.243L12 21m-1.06-1.06l-2.72 2.72a3 3 0 01-4.243-4.243l2.72-2.72M12 12a5.25 5.25 0 100-10.5 5.25 5.25 0 000 10.5z" />', $current_script);
        }
        ?>
    </nav>

    <div class="pt-6 border-t border-gray-100">
        <button onclick="openLogoutModal()" title="Sign Out" class="nav-item w-full flex items-center gap-4 p-4 text-gray-500 hover:text-red-500 hover:bg-red-500/5 rounded-2xl transition-all group">
            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-6 0v-1m6 0H9"/>
            </svg>
            <span class="sidebar-label font-bold text-xs uppercase tracking-widest">Sign Out</span>
        </button>
    </div>
</aside>