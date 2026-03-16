<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();

// 2. Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Action: Add a new user
    if (isset($_POST['add_user'])) {
        $idNum = trim($_POST['user_id']);
        $name = trim($_POST['user_name']);
        $email = trim($_POST['user_email']);
        $userRole = $_POST['user_role'];

        if ($db->uploadUserToMasterlist($idNum, $name, $email, $userRole)) {
            $_SESSION['toast_message'] = ['text' => "$userRole '$name' has been added to the masterlist.", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => "Error: Failed to register user. The ID number might already exist with a different role.", 'type' => 'error'];
        }
    }
    // Action: Update an existing user's role
    elseif (isset($_POST['update_role'])) {
        $masterId = $_POST['master_id'];
        $newRole = $_POST['new_role'];

        if ($db->updateUserRole($masterId, $newRole)) {
            $_SESSION['toast_message'] = ['text' => "User role updated successfully.", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => $db->getLastError() ?: "Failed to update user role.", 'type' => 'error'];
        }
    }

    header("Location: manage_users.php");
    exit();
}

// 3. Fetch data for display
$users = $db->getManageableUsers();
$page_title = "User Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .side-panel {
            transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div>
                        <h2 class="text-4xl font-extrabold text-gray-800 tracking-tighter">
                            User <span class="text-orange-500">Management.</span>
                        </h2>
                        <p class="text-slate-400 font-medium text-xs">Add, view, and manage Teacher and Admin accounts.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="openPanel('addUserPanel')" class="px-5 py-3 bg-orange-500 text-white rounded-xl font-bold text-xs shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-all">
                            Add New User
                        </button>
                    </div>
                </header>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-200/50 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Contact</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Account Status</th>
                                    <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Role</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-orange-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($user['Full_Name']) ?></p>
                                            <p class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($user['ID_Number']) ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-600 font-medium"><?= htmlspecialchars($user['Official_Email']) ?></p>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($user['is_verified'] == 1): ?>
                                                <span class="px-3 py-1 bg-blue-500 text-white text-[9px] font-black rounded-lg uppercase tracking-tighter shadow-lg shadow-blue-500/20 italic">Verified</span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 bg-slate-100 text-slate-400 text-[9px] font-black rounded-lg uppercase tracking-tighter italic">Pending Auth</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <form method="POST" class="flex items-center gap-2">
                                                <input type="hidden" name="master_id" value="<?= $user['MasterID'] ?>">
                                                <select name="new_role" class="bg-gray-50 border-gray-200 rounded-md text-xs font-bold p-2 focus:ring-orange-500 focus:border-orange-500">
                                                    <option value="Teacher" <?= $user['Role'] === 'Teacher' ? 'selected' : '' ?>>Teacher</option>
                                                    <option value="LabTech" <?= $user['Role'] === 'LabTech' ? 'selected' : '' ?>>LabTech</option>
                                                    <option value="Admin" <?= $user['Role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                                </select>
                                                <button type="submit" name="update_role" class="px-3 py-2 bg-white border border-gray-200 text-gray-600 rounded-lg text-[10px] font-bold hover:bg-blue-50 hover:text-blue-600">
                                                    Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Panel Backdrop -->
    <div id="panelBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden" onclick="closeAllPanels()"></div>

    <!-- Add User Panel -->
    <aside id="addUserPanel" class="side-panel fixed top-0 right-0 h-full w-full max-w-lg bg-white shadow-2xl transform translate-x-full z-50 flex flex-col">
        <header class="p-8 border-b border-slate-100 flex justify-between items-center flex-shrink-0">
            <h3 class="font-black text-slate-800 text-xl tracking-tight uppercase italic">Register New User</h3>
            <button onclick="closeAllPanels()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-100 transition-colors text-slate-400">&times;</button>
        </header>
        <div class="p-8 overflow-y-auto flex-1">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_user" value="1">
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Role</label>
                    <select name="user_role" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium">
                        <option value="Teacher">Teacher</option>
                        <option value="LabTech">LabTech</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="space-y-1"><label class="text-[10px] font-black text-slate-400 ml-2 uppercase">ID Number</label><input type="text" name="user_id" placeholder="e.g. 2024-001" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required></div>
                <div class="space-y-1"><label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Full Name</label><input type="text" name="user_name" placeholder="e.g. Juan Dela Cruz" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required></div>
                <div class="space-y-1"><label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Official Email</label><input type="email" name="user_email" placeholder="e.g. juan@wmsu.edu.ph" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required></div>
                <button type="submit" class="w-full bg-slate-800 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs shadow-xl shadow-slate-900/20 hover:bg-orange-600 transition-all transform active:scale-95">Add to Masterlist</button>
            </form>
        </div>
    </aside>

    <?php include '../../includes/toast.php'; ?>
    <script>
        function openPanel(panelId) {
            const panel = document.getElementById(panelId);
            const backdrop = document.getElementById('panelBackdrop');
            if (panel && backdrop) {
                backdrop.classList.remove('hidden');
                panel.classList.remove('translate-x-full');
            }
        }

        function closeAllPanels() {
            const backdrop = document.getElementById('panelBackdrop');
            backdrop.classList.add('hidden');
            document.querySelectorAll('.side-panel').forEach(p => {
                p.classList.add('translate-x-full');
            });
        }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>