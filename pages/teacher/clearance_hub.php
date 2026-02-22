<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Security & Role Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Teacher') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$class_id = $_GET['class_id'] ?? null;

// 2. Handle Status Update (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_clearance'])) {
    $enrollment_id = $_POST['enrollment_id'];
    $current_status = $_POST['current_status'];
    
    // Logic: Determine target status
    $new_status = ($current_status === 'Cleared') ? 'Pending' : 'Cleared';

    // --- SECURITY GATEKEEPER ---
    // If trying to mark as 'Cleared', we MUST check the database for damages first.
    // This prevents bypassing the UI modal.
    if ($new_status === 'Cleared' && $db->hasUnresolvedDamages($enrollment_id)) {
        $error = "Action Blocked: This student has unresolved damages. Please resolve them in the Handover Terminal first.";
    } else {
        // Safe to proceed
        $success = $db->updateClearanceStatus($enrollment_id, $new_status);
        
        if ($success) {
            header("Location: clearance_hub.php?class_id=" . $class_id);
            exit();
        } else {
            $error = "System Error: Could not update status.";
        }
    }
}

// 3. Fetch Data
$students = $db->getEnrolledStudents($class_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Hub | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 animate-reveal">
                
                <header class="mb-10 flex justify-between items-end">
                    <div>
                        <a href="class_activities.php?class_id=<?= htmlspecialchars($class_id) ?>" 
                           class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hover:text-blue-600 transition-colors mb-2 inline-block">
                           ← Back to Activities
                        </a>
                        <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter">
                            Clearance <span class="text-blue-600">Hub</span>
                        </h2>
                        <p class="text-slate-500 text-xs mt-2 font-medium">Manage student clearance and check for damages.</p>
                    </div>
                    
                    <div class="hidden md:flex gap-4">
                        <div class="bg-white px-5 py-3 rounded-xl border border-slate-100 shadow-sm text-center">
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Total</span>
                            <span class="text-xl font-black text-slate-700"><?= count($students) ?></span>
                        </div>
                        <div class="bg-white px-5 py-3 rounded-xl border border-slate-100 shadow-sm text-center">
                            <span class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Cleared</span>
                            <?php 
                                $cleared_count = count(array_filter($students, fn($s) => $s['ClearanceStatus'] === 'Cleared'));
                            ?>
                            <span class="text-xl font-black text-emerald-500"><?= $cleared_count ?></span>
                        </div>
                    </div>
                </header>

                <div class="bg-white rounded-[2rem] border border-slate-100 shadow-xl overflow-hidden">
                    
                    <?php if (isset($error)): ?>
                        <div class="bg-red-50 text-red-600 px-6 py-4 text-xs font-bold uppercase tracking-wide border-b border-red-100 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($students)): ?>
                        <div class="p-20 text-center flex flex-col items-center justify-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                            <p class="text-slate-400 italic text-sm">No students enrolled yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50/80 border-b border-slate-100 backdrop-blur-sm sticky top-0 z-10">
                                    <tr>
                                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase tracking-widest w-1/3">Student Name</th>
                                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase tracking-widest w-1/3">Status</th>
                                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase tracking-widest w-1/3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($students as $student): 
                                        // CHECK FOR DAMAGES FOR THIS STUDENT
                                        $damages = $db->getStudentDamages($student['MasterID']);
                                        $has_damages = !empty($damages);
                                        // Encode damages to JSON for the JavaScript modal
                                        $damages_json = htmlspecialchars(json_encode($damages), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <tr class="group hover:bg-blue-50/30 transition-all duration-200">
                                            
                                            <td class="p-6">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-black text-slate-400 group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                                                        <?= substr($student['Full_Name'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-slate-700 text-sm group-hover:text-blue-700 transition-colors">
                                                            <?= htmlspecialchars($student['Full_Name']) ?>
                                                        </div>
                                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                            ID: <?= htmlspecialchars($student['ID_Number']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="p-6">
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

                                            <td class="p-6 text-right">
                                                <form method="POST" id="form-<?= $student['EnrollmentID'] ?>">
                                                    <input type="hidden" name="enrollment_id" value="<?= $student['EnrollmentID'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $student['ClearanceStatus'] ?>">
                                                    
                                                    <input type="hidden" name="toggle_clearance" value="1">
                                                    
                                                    <button type="button" 
                                                            onclick="handleClearanceClick(
                                                                '<?= $student['EnrollmentID'] ?>', 
                                                                '<?= $student['ClearanceStatus'] ?>', 
                                                                '<?= $damages_json ?>',
                                                                '<?= htmlspecialchars($student['Full_Name']) ?>'
                                                            )"
                                                            class="relative inline-flex items-center justify-center px-4 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all duration-200 border
                                                            <?= $student['ClearanceStatus'] === 'Cleared' 
                                                                ? 'bg-white border-red-100 text-red-500 hover:bg-red-50 hover:border-red-200' 
                                                                : 'bg-white border-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white hover:border-blue-600' 
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
                    <p class="text-sm text-slate-600 mb-6 font-medium">
                        This student has <span class="font-bold text-red-500 underline decoration-red-200">unresolved damages</span>. Please resolve these items in the Handover Terminal before granting clearance.
                    </p>
                    
                    <div class="bg-slate-50 rounded-xl border border-slate-200 overflow-hidden shadow-inner">
                        <table class="w-full text-left">
                            <thead class="bg-slate-100 border-b border-slate-200">
                                <tr>
                                    <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-widest">Item / Qty</th>
                                    <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-widest">Issue</th>
                                    <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-widest text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody id="modalDamageList" class="divide-y divide-slate-100">
                                </tbody>
                        </table>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                    <button onclick="closeModal()" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all shadow-lg shadow-slate-200">
                        Okay, I understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function handleClearanceClick(enrollmentId, currentStatus, damagesJson, studentName) {
            
            // Case 1: Revoking Clearance (Always Allowed)
            if (currentStatus === 'Cleared') {
                document.getElementById('form-' + enrollmentId).submit();
                return;
            }

            // Case 2: Granting Clearance (Must Check Damages)
            const damages = JSON.parse(damagesJson);

            if (damages.length > 0) {
                // Found damages! Show Modal.
                showDamageModal(studentName, damages);
            } else {
                // No damages. Proceed.
                document.getElementById('form-' + enrollmentId).submit();
            }
        }

        function showDamageModal(name, damages) {
            const modal = document.getElementById('damageModal');
            const list = document.getElementById('modalDamageList');
            const nameLabel = document.getElementById('modalStudentName');
            
            nameLabel.textContent = name;
            
            list.innerHTML = damages.map(item => `
                <tr class="hover:bg-red-50/50 transition-colors">
                    <td class="p-4">
                        <div class="text-xs font-bold text-slate-800">${item.ItemName || 'Unknown Item'}</div>
                        <div class="text-[9px] text-slate-400 font-mono mt-0.5">Qty: ${item.qty_damaged}</div>
                    </td>
                    <td class="p-4">
                        <span class="inline-block px-2 py-0.5 rounded bg-red-100 text-red-600 text-[9px] font-bold uppercase italic border border-red-200">
                            ${item.damage_type || 'Broken'}
                        </span>
                        <div class="text-[9px] text-slate-500 mt-1 italic">"${item.notes || 'No notes'}"</div>
                    </td>
                    <td class="p-4 text-right">
                        <div class="text-[10px] text-slate-500 font-mono font-medium">
                            ${item.logged_at ? item.logged_at.split(' ')[0] : '-'}
                        </div>
                    </td>
                </tr>
            `).join('');

            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('damageModal').classList.add('hidden');
        }
    </script>
</body>
</html>