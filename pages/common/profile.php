<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control: User must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /LabFlow/index.php");
    exit();
}

$db = new DataManager();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$page_title = "My Profile";
$profileError = null;

// 2. Fetch Core Profile Data
$userProfile = $db->getUserProfileData($userId);
if (!$userProfile) {
    $profileError = "Error: Could not retrieve user profile data. Please contact an administrator.";
    // Initialize to prevent errors in the template
    $userProfile = ['Full_Name' => 'Unknown User', 'ID_Number' => 'N/A', 'Role' => 'N/A', 'Confirmed_Email' => 'N/A'];
    $roleSpecificData = [];
} else {
    // 3. Fetch Role-Specific Data
    $roleSpecificData = [];
    if ($userRole === 'Student') {
        $roleSpecificData['classes'] = $db->getStudentEnrolledClasses($userId);
        $roleSpecificData['liability'] = $db->checkLiability($userId);
    } elseif ($userRole === 'Teacher') {
        $roleSpecificData['classes'] = $db->getTeacherClasses($userId);
        $roleSpecificData['pending_requests'] = $db->countPendingRequests($userId);
    } elseif ($userRole === 'Admin') {
        $kpis = $db->getAdminKPIs();
        $roleSpecificData['pending_reqs'] = $kpis['pending_reqs'];
        $roleSpecificData['open_damages'] = $kpis['open_damages'];
        $roleSpecificData['total_users'] = $kpis['total_users'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <?php if ($profileError): ?>
                    <div class="max-w-7xl mx-auto bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                        <p class="font-bold">Error</p>
                        <p><?= htmlspecialchars($profileError) ?></p>
                    </div>
                <?php else: ?>
                <div class="max-w-7xl mx-auto">
                    <!-- Profile Header -->
                    <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
                        <div class="flex items-center space-x-6">
                            <div class="flex-shrink-0">
                                <!-- Placeholder for Profile Picture -->
                                <div class="w-24 h-24 rounded-full bg-orange-100 text-orange-500 flex items-center justify-center text-4xl font-bold">
                                    <?= htmlspecialchars(substr($userProfile['Full_Name'], 0, 1)) ?>
                                </div>
                            </div>
                            <div>
                                <h2 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($userProfile['Full_Name']) ?></h2>
                                <p class="text-gray-500 mt-1">
                                    <span class="font-mono bg-gray-100 text-gray-600 px-2 py-1 rounded-md text-sm"><?= htmlspecialchars($userProfile['ID_Number']) ?></span>
                                    <span class="mx-2 text-gray-300">|</span>
                                    <span class="font-semibold text-orange-600"><?= htmlspecialchars($userProfile['Role']) ?></span>
                                </p>
                                <p class="text-sm text-gray-500 mt-2"><?= htmlspecialchars($userProfile['Confirmed_Email']) ?></p>
                            </div>
                            <div class="flex-grow text-right">
                                <a href="/LabFlow/logout.php" class="px-4 py-2 text-sm font-bold text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Left Column: Role-specific info -->
                        <div class="lg:col-span-2 space-y-8">
                            <!-- Student View -->
                            <?php if ($userRole === 'Student'): ?>
                                <!-- My Classes -->
                                <div class="bg-white rounded-2xl shadow-lg p-6">
                                    <h3 class="text-xl font-bold text-gray-800 mb-4">My Classes</h3>
                                    <div class="space-y-4">
                                        <?php if (empty($roleSpecificData['classes'])): ?>
                                            <p class="text-gray-500">You are not enrolled in any classes.</p>
                                        <?php else: ?>
                                            <?php foreach ($roleSpecificData['classes'] as $class): ?>
                                                <div class="p-4 border rounded-lg flex justify-between items-center">
                                                    <div>
                                                        <p class="font-bold text-gray-700"><?= htmlspecialchars($class['Class_Name'] . ' - ' . $class['Section']) ?></p>
                                                        <p class="text-sm text-gray-500">Instructor: <?= htmlspecialchars($class['TeacherName']) ?></p>
                                                    </div>
                                                    <a href="/LabFlow/pages/student/class_dashboard.php?class_id=<?= $class['ClassID'] ?>" class="px-3 py-1.5 text-xs font-bold text-orange-600 bg-orange-50 rounded-md hover:bg-orange-100">View</a>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- My Liabilities -->
                                <?php if ($roleSpecificData['liability']['has_liability']): ?>
                                <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-6 rounded-r-lg shadow-md">
                                    <h3 class="text-xl font-bold mb-4">Active Liabilities</h3>
                                    <p class="mb-4">You have unresolved damages. Please settle them to ensure clearance.</p>
                                    <ul class="space-y-2">
                                    <?php foreach ($roleSpecificData['liability']['items'] as $item): ?>
                                        <li class="font-semibold text-sm">- <?= htmlspecialchars($item['Item_Name']) ?> (<?= $item['qty_damaged'] ?> pcs)</li>
                                    <?php endforeach; ?>
                                    </ul>
                                    <div class="mt-6 flex items-center gap-4">
                                        <a href="/LabFlow/pages/student/settlement_cases.php" class="inline-block px-4 py-2 bg-red-600 text-white font-bold text-sm rounded-lg hover:bg-red-700 shadow-lg shadow-red-500/20">Settle Now</a>
                                        <a href="/LabFlow/pages/student/generate_clearance_receipt.php" target="_blank" class="inline-block px-4 py-2 bg-gray-700 text-white font-bold text-sm rounded-lg hover:bg-gray-800 shadow-lg shadow-gray-500/20">Print Liability Slip</a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Teacher View -->
                            <?php if ($userRole === 'Teacher'): ?>
                                <div class="bg-white rounded-2xl shadow-lg p-6">
                                    <h3 class="text-xl font-bold text-gray-800 mb-4">My Classes</h3>
                                    <div class="space-y-4">
                                        <?php foreach ($roleSpecificData['classes'] as $class): ?>
                                            <div class="p-4 border rounded-lg flex justify-between items-center">
                                                <div>
                                                    <p class="font-bold text-gray-700"><?= htmlspecialchars($class['Class_Name'] . ' - ' . $class['Section']) ?></p>
                                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($class['Semester']) ?></p>
                                                </div>
                                                <a href="/LabFlow/pages/teacher/manage_class.php?class_id=<?= $class['ClassID'] ?>" class="px-3 py-1.5 text-xs font-bold text-orange-600 bg-orange-50 rounded-md hover:bg-orange-100">Manage</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <a href="/LabFlow/pages/teacher/create_class.php" class="inline-block mt-6 px-5 py-2.5 bg-gray-800 text-white font-bold text-sm rounded-lg hover:bg-gray-900">Create New Class</a>
                                </div>
                            <?php endif; ?>

                            <!-- Admin View -->
                            <?php if ($userRole === 'Admin'): ?>
                                <div class="bg-white rounded-2xl shadow-lg p-6">
                                    <h3 class="text-xl font-bold text-gray-800 mb-4">Management Dashboard</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <a href="/LabFlow/pages/admin/manage_inventory.php" class="block p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border">
                                            <p class="font-bold text-gray-700">Manage Inventory</p>
                                            <p class="text-sm text-gray-500">Add, edit, and import items.</p>
                                        </a>
                                        <a href="/LabFlow/pages/admin/manage_users.php" class="block p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border">
                                            <p class="font-bold text-gray-700">Manage Users & Roles</p>
                                            <p class="text-sm text-gray-500">Oversee all system users.</p>
                                        </a>
                                        <a href="/LabFlow/pages/common/settlement_hub.php" class="block p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border">
                                            <p class="font-bold text-gray-700">Settlement Hub</p>
                                            <p class="text-sm text-gray-500">Review and resolve damages.</p>
                                        </a>
                                        <a href="/LabFlow/HTML_Demo/stock_room.php" class="block p-4 bg-gray-50 hover:bg-gray-100 rounded-lg border">
                                            <p class="font-bold text-gray-700">Stock Room Layout</p>
                                            <p class="text-sm text-gray-500">Visualize and manage shelves.</p>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Column: Account Settings -->
                        <div class="space-y-8">
                            <div class="bg-white rounded-2xl shadow-lg p-6" x-data="{ success: false, error: '' }">
                                <h3 class="text-xl font-bold text-gray-800 mb-4">Change Password</h3>
                                <form @submit.prevent="
                                    let formData = new FormData($event.target);
                                    fetch('update_password_handler.php', { method: 'POST', body: formData })
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data.success) {
                                                success = true; error = ''; $event.target.reset();
                                                setTimeout(() => success = false, 3000);
                                            } else {
                                                error = data.message || 'An error occurred.'; success = false;
                                            }
                                        })
                                ">
                                    <div class="space-y-4">
                                        <div>
                                            <label for="current_password" class="text-sm font-bold text-gray-600 block mb-1">Current Password</label>
                                            <input type="password" name="current_password" id="current_password" required class="w-full p-2.5 border text-sm border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500">
                                        </div>
                                        <div>
                                            <label for="new_password" class="text-sm font-bold text-gray-600 block mb-1">New Password</label>
                                            <input type="password" name="new_password" id="new_password" required class="w-full p-2.5 border text-sm border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500">
                                        </div>
                                        <div>
                                            <label for="confirm_password" class="text-sm font-bold text-gray-600 block mb-1">Confirm New Password</label>
                                            <input type="password" name="confirm_password" id="confirm_password" required class="w-full p-2.5 border text-sm border-gray-300 rounded-lg focus:ring-orange-500 focus:border-orange-500">
                                        </div>
                                    </div>
                                    <button type="submit" class="w-full mt-6 px-5 py-3 bg-orange-600 text-white font-bold text-sm rounded-lg hover:bg-orange-700 transition-colors">Update Password</button>
                                    
                                    <div x-show="success" x-transition class="mt-4 p-3 bg-green-50 text-green-700 text-sm font-semibold rounded-lg">Password updated successfully!</div>
                                    <div x-show="error" x-transition class="mt-4 p-3 bg-red-50 text-red-700 text-sm font-semibold rounded-lg" x-text="error"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>