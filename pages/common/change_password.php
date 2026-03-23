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
$page_title = "Change Password";
$message = null;
$message_type = 'error';

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'All fields are required.';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'The new passwords do not match.';
    } else {
        try {
            // Verify current password
            $user = $db->checkExistingAccount($_SESSION['master_id']);
            if (!$user || !password_verify($currentPassword, $user['Password_Hash'])) {
                $message = 'The current password you entered is incorrect.';
            } else {
                // Update to new password
                if ($db->updatePasswordByMasterId($_SESSION['master_id'], $newPassword)) {
                    $message = 'Password updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to update password. Please try again.';
                }
            }
        } catch (Exception $e) {
            error_log("Password Update Error: " . $e->getMessage());
            $message = 'A server error occurred. Please contact an administrator.';
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { background-image: radial-gradient(circle at top left, rgba(249, 115, 22, 0.04), transparent 35%); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-slate-800" x-data="passwordStrengthChecker()">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <div class="max-w-xl mx-auto">
                    <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/50 p-8 md:p-12 border border-slate-100">
                        <header class="mb-10 text-center">
                            <div class="w-20 h-20 bg-gradient-to-br from-orange-400 to-orange-600 text-white flex items-center justify-center rounded-full mx-auto mb-6 shadow-xl shadow-orange-500/20 ring-8 ring-white/10">
                                <i class="fas fa-shield-alt text-3xl"></i>
                            </div>
                            <h2 class="text-4xl font-black text-slate-800 tracking-tighter">Security Center</h2>
                            <p class="text-slate-500 mt-2 max-w-sm mx-auto">Update your password regularly to keep your account secure.</p>
                        </header>

                        <?php if ($message): ?>
                            <div class="mb-8 p-4 rounded-xl text-sm font-bold flex items-center gap-3 <?= $message_type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?> border">
                                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="change_password.php" class="space-y-6">
                            <div class="relative"><label for="current_password" class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-2">Current Password</label><i class="fas fa-lock absolute left-4 top-11 text-slate-400"></i><input type="password" name="current_password" id="current_password" required class="w-full p-3 pl-12 border text-sm border-slate-200 bg-slate-50 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all shadow-inner"></div>
                            
                            <div class="relative">
                                <label for="new_password" class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-2">New Password</label>
                                <i class="fas fa-key absolute left-4 top-11 text-slate-400"></i>
                                <input type="password" name="new_password" id="new_password" required x-model="password" @input="check()" class="w-full p-3 pl-12 border text-sm border-slate-200 bg-slate-50 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all shadow-inner">
                            </div>

                            <!-- Password Strength Meter -->
                            <div x-show="password.length > 0" x-transition x-cloak class="space-y-2">
                                <div class="grid grid-cols-4 gap-2">
                                    <div class="h-1.5 rounded-full" :class="strength.score >= 1 ? strength.color : 'bg-slate-200'"></div>
                                    <div class="h-1.5 rounded-full" :class="strength.score >= 2 ? strength.color : 'bg-slate-200'"></div>
                                    <div class="h-1.5 rounded-full" :class="strength.score >= 3 ? strength.color : 'bg-slate-200'"></div>
                                    <div class="h-1.5 rounded-full" :class="strength.score >= 4 ? strength.color : 'bg-slate-200'"></div>
                                </div>
                                <p class="text-xs font-bold" :class="{ 'text-red-500': strength.score <= 1, 'text-orange-500': strength.score === 2, 'text-yellow-500': strength.score === 3, 'text-green-500': strength.score === 4 }" x-text="strength.text"></p>
                            </div>

                            <div class="relative"><label for="confirm_password" class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-2">Confirm New Password</label><i class="fas fa-check-circle absolute left-4 top-11 text-slate-400"></i><input type="password" name="confirm_password" id="confirm_password" required class="w-full p-3 pl-12 border text-sm border-slate-200 bg-slate-50 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all shadow-inner"></div>
                            
                            <button type="submit" class="w-full mt-4 px-5 py-4 bg-orange-600 text-white font-bold text-sm rounded-xl hover:bg-orange-700 transition-all shadow-lg shadow-orange-600/20 hover:-translate-y-1 active:translate-y-0">Update Password</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <?php include '../../includes/layout_footer.php'; ?>
    <script>
        function passwordStrengthChecker() {
            return {
                password: '',
                strength: { score: 0, text: '', color: 'bg-slate-200' },
                check() {
                    let score = 0;
                    if (!this.password) {
                        this.strength = { score: 0, text: '', color: 'bg-slate-200' };
                        return;
                    }
                    if (this.password.length >= 8) score++;
                    if (this.password.match(/[a-z]/) && this.password.match(/[A-Z]/)) score++;
                    if (this.password.match(/[0-9]/)) score++;
                    if (this.password.match(/[^a-zA-Z0-9]/)) score++;

                    if (score <= 1) this.strength = { score: 1, text: 'Weak', color: 'bg-red-500' };
                    else if (score === 2) this.strength = { score: 2, text: 'Medium', color: 'bg-orange-500' };
                    else if (score === 3) this.strength = { score: 3, text: 'Good', color: 'bg-yellow-500' };
                    else this.strength = { score: 4, text: 'Strong', color: 'bg-green-500' };
                }
            }
        }
    </script>
</body>
</html>