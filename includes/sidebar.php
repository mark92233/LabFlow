<?php
// Ensure DB operations are available for the badge count
// Adjust the path 'dbRelated/operation.php' if your directory structure differs relative to this file
require_once __DIR__ . '/../dbRelated/operation.php';

$base_url = "/LabFlow/"; 
$current_script = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? 'Student';

// --- FETCH NOTIFICATION COUNTS (TEACHER ONLY) ---
$settlement_badge = 0;
if (($role === 'Teacher' || $role === 'Admin') && class_exists('DataManager')) {
    $db_nav = new DataManager();
    // Check if function exists to avoid crashing if operation.php isn't updated yet
    if (method_exists($db_nav, 'getSettlementCases')) {
        $settlement_badge = count($db_nav->getSettlementCases('pending'));
    }
}
?>

<aside class="w-72 bg-[#0f172a] h-screen sticky top-0 hidden md:flex flex-col p-6 shadow-2xl z-50">
    <div class="mb-12 px-2 flex items-center gap-3">
        <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center font-black text-white shadow-lg shadow-blue-500/20">S</div>
        <h1 class="text-white font-black text-xl tracking-tighter uppercase">SNHS <span class="font-light opacity-50">INV</span></h1>
    </div>

    <nav class="flex-1 space-y-2 overflow-y-auto custom-scrollbar">
        <?php 
        // Helper function with BADGE support
        function navItem($link, $label, $svg, $current_script, $badge_count = 0) {
            $link_script = basename(parse_url($link, PHP_URL_PATH));
            
            // Active State Logic
            $isActive = ($link_script === $current_script) 
                ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20 border-blue-500' 
                : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent';
            
            // Badge HTML
            $badge_html = '';
            if ($badge_count > 0) {
                $badge_html = "<span class='ml-auto bg-red-500 text-white text-[9px] font-black px-2 py-0.5 rounded-full shadow-sm animate-pulse'>$badge_count</span>";
            }

            return "
            <a href='$link' class='flex items-center gap-4 p-4 rounded-2xl border transition-all group $isActive'>
                <svg class='w-5 h-5 flex-shrink-0' fill='none' stroke='currentColor' viewBox='0 0 24 24' stroke-width='2'>$svg</svg>
                <span class='font-bold text-xs uppercase tracking-widest truncate'>$label</span>
                $badge_html
            </a>";
        }

        // 1. DASHBOARD (All Users)
        echo navItem($base_url . "dashboard/router.php", "Home Console", '<path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>', $current_script);

        // 2. INVENTORY HUB (All Users)
        $hub_label = ($role === 'Student') ? "Apparatus Shop" : "Inventory Hub";
        echo navItem($base_url . "pages/common/inventory_hub.php", $hub_label, '<path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>', $current_script);

        // 3. STUDENT SPECIFIC
        if ($role === 'Student') {
            echo "<p class='text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] px-4 mt-8 mb-4'>My Laboratory</p>";
            echo navItem($base_url . "pages/student/active_slips.php", "Transaction History", '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>', $current_script);
        }

        // 4. TEACHER / ADMIN SPECIFIC
        if ($role === 'Teacher' || $role === 'Admin') {
            echo "<p class='text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] px-4 mt-8 mb-4'>Laboratory Hub</p>";
            
            echo navItem($base_url . "pages/teacher/add_activity.php", "Post Lab Activity", '<path d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>', $current_script);
            
            echo navItem($base_url . "pages/teacher/handover.php", "Handover Terminal", '<path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>', $current_script);

            // --- NEW: SETTLEMENT REVIEWS ---
            echo navItem($base_url . "pages/teacher/settlement_reviews.php", "Settlement Reviews", '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>', $current_script, $settlement_badge);
            
            echo "<p class='text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] px-4 mt-8 mb-4'>Class Control</p>";
            echo navItem($base_url . "pages/teacher/manage_classes.php", "Class Registry", '<path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>', $current_script);
            
            echo navItem($base_url . "pages/teacher/request_list.php", "Pending Requests", '<path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>', $current_script);
        }

        // 5. ADMIN ONLY
        if ($role === 'Admin') {
            echo "<p class='text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] px-4 mt-8 mb-4'>System Mgmt</p>";
            echo navItem($base_url . "pages/admin/manage_inventory.php", "Register Apparatus", '<path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>', $current_script);
        }
        ?>
    </nav>

    <div class="pt-6 border-t border-slate-800">
        <a href="<?= $base_url ?>logout.php" class="flex items-center gap-4 p-4 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-2xl transition-all group">
            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-6 0v-1m6 0H9"/>
            </svg>
            <span class="font-bold text-xs uppercase tracking-widest">Sign Out</span>
        </a>
    </div>
</aside>