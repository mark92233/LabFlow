<?php
session_start();
require_once __DIR__ . '/../../dbRelated/operation.php';

// Access Control: Any logged-in user can view their own liabilities.
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();

// Handle Actions (only proof submission for students)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['damage_id']) && $_POST['action'] === 'submit_proof' && isset($_FILES['proof_image'])) {
        $id = $_POST['damage_id'];
        $settlement_mode = $_POST['settlement_mode'] ?? null;
        $result = $db->submitDamageProof($id, $settlement_mode, $_FILES['proof_image']);
        if ($result === true) {
            $_SESSION['toast_message'] = ['text' => "Proof for Case #{$id} submitted successfully.", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => "Proof submission failed: " . $result, 'type' => 'error'];
        }
        header("Location: settlement_cases.php"); // Redirect back to this page
        exit();
    }
}

// Always fetch personal cases for the logged-in student
$cases = $db->getSettlementCases('personal_all', '', '', $_SESSION['user_id']);
// Sort to show pending cases first, then resolved ones.
usort($cases, function ($a, $b) {
    $statusOrder = ['Unresolved' => 1, 'Under Review' => 2, 'Resolved' => 3];
    $a_status = $statusOrder[$a['status']] ?? 99;
    $b_status = $statusOrder[$b['status']] ?? 99;
    if ($a_status == $b_status) {
        return strtotime($b['logged_at']) - strtotime($a['logged_at']); // Newest first within the same status
    }
    return $a_status <=> $b_status;
});

$casesForJs = [];
foreach ($cases as $case) {
    $casesForJs[$case['damage_id']] = $case;
}
$page_title = "My Liabilities";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .sticky-sidebar { height: calc(100vh - 120px); position: sticky; top: 100px; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 flex gap-8 animate-reveal">
                <div class="flex-1 flex flex-col gap-6">
                    <header class="mb-2">
                        <h2 class="text-4xl font-extrabold text-gray-800 tracking-tighter">My <span class="text-orange-500">Liabilities.</span></h2>
                        <p class="text-slate-400 font-medium text-xs">A record of your unresolved and settled damage reports.</p>
                    </header>

                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200/50 flex-1 flex flex-col overflow-hidden">
                        <?php if (empty($cases)): ?>
                            <div class="flex-1 flex flex-col items-center justify-center text-center p-10">
                                <div class="w-16 h-16 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <h3 class="text-slate-800 font-bold text-lg">No Liabilities</h3>
                                <p class="text-slate-400 text-sm">You have no personal damage reports, pending or resolved.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-y-auto flex-1">
                                <table class="w-full text-left">
                                    <thead class="bg-gray-50 border-b border-gray-100 sticky top-0">
                                        <tr>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Item</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Damage</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Date Logged</th>
                                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100" id="cases-table">
                                        <?php foreach ($cases as $case):
                                            $statusClass = match($case['status']) {
                                                'Under Review' => 'bg-blue-100 text-blue-600',
                                                'Unresolved' => 'bg-orange-100 text-orange-600',
                                                'Resolved' => 'bg-green-100 text-green-600',
                                                default => 'bg-slate-100 text-slate-500'
                                            };
                                            $caseJSON = htmlspecialchars(json_encode($casesForJs[$case['damage_id']]), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr id="row-<?= $case['damage_id'] ?>" onclick='showCaseDetails(<?= $caseJSON ?>)' class="hover:bg-orange-50/50 transition-colors cursor-pointer">
                                                <td class="px-6 py-4">
                                                    <p class="font-bold text-gray-800 text-sm">
                                                        <?= htmlspecialchars($case['Item_Name']) ?>
                                                        <?php if ($case['is_scalable'] == 1 && !empty($case['Size_Value'])): ?>
                                                            <span class="font-medium text-slate-500 text-xs">(<?= htmlspecialchars($case['Size_Value']) . htmlspecialchars($case['Unit'] ?? '') ?>)</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">Slip #<?= htmlspecialchars($case['session_id']) ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($case['damage_type']) ?></p>
                                                    <p class="text-xs text-gray-500">Qty: <?= htmlspecialchars($case['qty_damaged']) ?></p>
                                                </td>
                                                <td class="px-6 py-4 text-xs text-gray-500 font-medium">
                                                    <?= date('M d, Y', strtotime($case['logged_at'])) ?>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase <?= $statusClass ?>">
                                                        <?= $case['status'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="w-96 sticky-sidebar">
                    <div id="details-panel-container" class="h-full">
                        <div id="details-empty-state" class="h-full flex flex-col items-center justify-center text-center p-8 text-slate-400 border-2 border-dashed border-slate-200 rounded-3xl">
                            <svg class="w-16 h-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <h3 class="font-bold text-slate-500">Select a Case</h3>
                            <p class="text-sm mt-1">Click on a row to view its details here.</p>
                        </div>
                        <div id="details-content-wrapper" class="hidden h-full">
                            <!-- JS will inject content here -->
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl"></div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <script>
        // This JS is mostly copied from the teacher's page, but simplified for the student view.
        function showCaseDetails(caseData) {
            document.querySelectorAll('#cases-table tr').forEach(row => row.classList.remove('bg-orange-100'));
            document.getElementById(`row-${caseData.damage_id}`).classList.add('bg-orange-100');

            const wrapper = document.getElementById('details-content-wrapper');
            const emptyState = document.getElementById('details-empty-state');

            let imagesHtml = '';
            const hasEvidence = caseData.evidence_image;
            const hasProof = caseData.proof_image;
            const caseDataJSON = JSON.stringify(caseData);

            if (hasEvidence) {
                imagesHtml += `<div class="flex-1"><label class="text-[10px] font-bold text-slate-500 uppercase">Damage Evidence</label><div class="relative group mt-1"><img src="../../uploads/evidence/${caseData.evidence_image}" class="w-full h-40 object-cover rounded-xl bg-slate-100 cursor-pointer" alt="Evidence of Damage" onclick='showFullImageView("../../uploads/evidence/${caseData.evidence_image}", ${caseDataJSON})'><div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none rounded-xl"><span class="text-white text-xs font-bold uppercase tracking-widest">View</span></div></div></div>`;
            }
            if (hasProof) {
                imagesHtml += `<div class="flex-1"><label class="text-[10px] font-bold text-slate-500 uppercase">My Settlement Proof</label><div class="relative group mt-1"><img src="../../uploads/settlements/${caseData.proof_image}" class="w-full h-40 object-cover rounded-xl bg-slate-100 cursor-pointer" alt="Proof of Settlement" onclick='showFullImageView("../../uploads/settlements/${caseData.proof_image}", ${caseDataJSON})'><div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none rounded-xl"><span class="text-white text-xs font-bold uppercase tracking-widest">View</span></div></div></div>`;
            }
            if (!hasEvidence && !hasProof) { imagesHtml = `<div class="w-full text-center p-4 bg-slate-100 rounded-lg text-xs text-slate-400 font-medium">No images submitted for this case.</div>`; }
            
            const isResolved = caseData.status === 'Resolved';
            const isRejected = caseData.status === 'Unresolved' && caseData.notes && caseData.proof_image === null; // A case is considered rejected if it's unresolved, has notes, and the proof image has been cleared.

            let buttonsHtml = '';
            if (isResolved) {
                buttonsHtml = `<div class="w-full py-3 bg-green-100 text-green-600 rounded-xl text-xs font-black uppercase tracking-widest text-center">Case Resolved</div>`;
            } else if (caseData.status === 'Unresolved') {
                buttonsHtml = `<form action="settlement_cases.php" method="POST" enctype="multipart/form-data" class="w-full space-y-3"><input type="hidden" name="damage_id" value="${caseData.damage_id}"><input type="hidden" name="action" value="submit_proof"><div><label class="block text-xs font-bold text-gray-500 mb-2">Settlement Method:</label><div class="flex gap-2"><label class="flex-1 p-3 border-2 border-gray-200 rounded-xl flex items-center gap-3 cursor-pointer hover:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 transition-all"><input type="radio" name="settlement_mode" value="payment" class="w-4 h-4 text-blue-600 focus:ring-blue-500" checked><span class="text-xs font-bold text-gray-700">Pay Fine</span></label><label class="flex-1 p-3 border-2 border-gray-200 rounded-xl flex items-center gap-3 cursor-pointer hover:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 transition-all"><input type="radio" name="settlement_mode" value="replacement" class="w-4 h-4 text-blue-600 focus:ring-blue-500"><span class="text-xs font-bold text-gray-700">Replace Item</span></label></div></div><label class="block text-xs font-bold text-gray-500 pt-2">Upload Proof:</label><div id="dropzone-${caseData.damage_id}" ondrop="dropHandler(event, ${caseData.damage_id});" ondragover="dragOverHandler(event);" ondragleave="dragLeaveHandler(event);" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-colors duration-300"><div class="space-y-1 text-center"><svg class="mx-auto h-10 w-10 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg><div class="flex text-xs text-gray-600"><label for="proof_image_${caseData.damage_id}" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none"><span>Upload a file</span><input id="proof_image_${caseData.damage_id}" name="proof_image" type="file" class="sr-only" accept="image/*,application/pdf" onchange="fileChangeHandler(event, ${caseData.damage_id})" required></label><p class="pl-1">or drag and drop</p></div><p id="filename-${caseData.damage_id}" class="text-xs text-emerald-600 font-bold pt-2"></p></div></div><button type="submit" class="w-full py-3 bg-blue-600 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:bg-blue-700 transition-all">Submit Proof</button></form>`;
            } else { buttonsHtml = `<div class="w-full py-3 bg-blue-100 text-blue-600 rounded-xl text-xs font-black uppercase tracking-widest text-center">Proof Submitted, Awaiting Review</div>`; }

            const contentHtml = `<div class="bg-white rounded-2xl shadow-xl border border-slate-200/50 h-full flex flex-col">
                <div class="p-6 flex-1 overflow-y-auto custom-scrollbar">
                    <div class="flex gap-4 mb-4">${imagesHtml}</div>
                    <h4 class="text-lg font-black text-slate-800 uppercase italic">${caseData.Item_Name}</h4>
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-widest">Reported By: <span class="text-blue-600">${caseData.HandlerName || '(Not Recorded)'}</span></p>
                    
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 my-4 relative">
                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div><span class="block text-slate-400 font-bold uppercase text-[9px]">Reported By</span><span class="font-bold text-slate-700">${caseData.HandlerName || '(Not Recorded)'}</span></div>
                            <div><span class="block text-slate-400 font-bold uppercase text-[9px]">Date Logged</span><span class="font-bold text-slate-700">${new Date(caseData.logged_at).toLocaleDateString()}</span></div>
                            <div><span class="block text-slate-400 font-bold uppercase text-[9px]">Damage Type</span><span class="font-bold text-slate-700">${caseData.damage_type}</span></div>
                            <div><span class="block text-slate-400 font-bold uppercase text-[9px]">Quantity Damaged</span><span class="font-bold text-slate-700">${caseData.qty_damaged} of ${caseData.qty_borrowed || '?'}</span></div>
                            
                            ${isRejected ? `
                                <div class="col-span-2 mt-2 pt-4 border-t border-dashed border-slate-200">
                                    <div class="bg-red-50 border-l-4 border-red-400 p-3 rounded-r-lg">
                                        <div class="flex justify-between items-center mb-1">
                                            <h4 class="text-[10px] font-black text-red-600 uppercase tracking-widest">Faculty Feedback (Proof Rejected)</h4>
                                            ${caseData.ResolverName ? `<span class="text-[9px] font-bold text-red-500">by ${caseData.ResolverName}</span>` : ''}
                                        </div>
                                        <p class="italic text-red-700 text-sm">"${caseData.notes}"</p>
                                    </div>
                                </div>` : `<div class="col-span-2"><span class="block text-slate-400 font-bold uppercase text-[9px]">Notes</span><p class="italic text-slate-600">${caseData.notes || 'No remarks provided.'}</p></div>`}
                            
                            ${caseData.settlement_mode ? `<div class="col-span-2 pt-2 mt-2 border-t border-slate-200"><span class="block text-slate-400 font-bold uppercase text-[9px]">Settlement Method</span><span class="font-bold text-slate-700 capitalize">${caseData.settlement_mode}</span></div>` : ''}

                            ${caseData.status === 'Resolved' ?
                                `<div class="col-span-2 pt-2 mt-2 border-t border-slate-200"><span class="block text-slate-400 font-bold uppercase text-[9px]">Resolved By</span><span class="font-bold text-slate-700">${caseData.ResolverName || '(Not Recorded)'}</span></div>` :
                                (caseData.status === 'Under Review' ?
                                    `<div class="col-span-2 pt-2 mt-2 border-t border-slate-200"><span class="block text-slate-400 font-bold uppercase text-[9px]">Reviewed By</span><span class="font-bold text-slate-700">(Pending Faculty Review)</span></div>` : '')
                            }
                        </div>
                    </div>
                </div>
                <div class="p-4 border-t border-slate-100 bg-slate-50/50"><div class="flex gap-3">${buttonsHtml}</div></div>
            </div>`;
            wrapper.innerHTML = contentHtml;
            emptyState.classList.add('hidden');
            wrapper.classList.remove('hidden');
        }
        function showFullImageView(imgSrc, caseData) { const wrapper = document.getElementById('details-content-wrapper'); if (!wrapper) return; const fullViewHtml = `<div class="bg-white rounded-2xl shadow-xl border border-slate-200/50 h-full flex flex-col animate-reveal active"><div class="p-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3"><button id="back-to-details-btn" class="flex items-center gap-2 px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-300 transition-colors"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>Back to Details</button></div><div class="p-6 flex-1 overflow-y-auto custom-scrollbar flex items-center justify-center bg-slate-100"><img src="${imgSrc}" class="w-full h-auto rounded-lg shadow-md max-w-full max-h-full object-contain" alt="Full preview"></div></div>`; wrapper.innerHTML = fullViewHtml; document.getElementById('back-to-details-btn').addEventListener('click', () => showCaseDetails(caseData)); }
        function dragOverHandler(ev) { ev.preventDefault(); ev.currentTarget.classList.add('border-blue-500', 'bg-blue-50'); }
        function dragLeaveHandler(ev) { ev.currentTarget.classList.remove('border-blue-500', 'bg-blue-50'); }
        function dropHandler(ev, damageId) { ev.preventDefault(); ev.currentTarget.classList.remove('border-blue-500', 'bg-blue-50'); const fileInput = document.getElementById(`proof_image_${damageId}`); let file = (ev.dataTransfer.items && ev.dataTransfer.items[0].kind === 'file') ? ev.dataTransfer.items[0].getAsFile() : ev.dataTransfer.files[0]; if (file) { const dataTransfer = new DataTransfer(); dataTransfer.items.add(file); fileInput.files = dataTransfer.files; fileInput.dispatchEvent(new Event('change', { bubbles: true })); } }
        function fileChangeHandler(ev, damageId) { const fileNameDisplay = document.getElementById(`filename-${damageId}`); fileNameDisplay.textContent = ev.target.files.length > 0 ? ev.target.files[0].name : ''; }
        function showToast(message, type = 'success') { const toast = document.getElementById('toast-container'); if (!toast) return; const iconContainer = document.getElementById('toast-icon-container'); const messageContainer = document.getElementById('toast-message'); toast.className = 'fixed bottom-10 right-10 z-[200] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal'; iconContainer.className = 'inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl'; messageContainer.textContent = message; if (type === 'success') { toast.classList.add('bg-emerald-600'); iconContainer.classList.add('bg-emerald-100'); iconContainer.innerHTML = `<svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>`; } else { toast.classList.add('bg-red-600'); iconContainer.classList.add('bg-red-100'); iconContainer.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`; } toast.classList.remove('hidden'); toast.style.opacity = '1'; toast.style.transform = 'translateY(0)'; setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000); }
        <?php
        if (isset($_SESSION['toast_message'])) {
            $toast = $_SESSION['toast_message'];
            unset($_SESSION['toast_message']);
            echo "document.addEventListener('DOMContentLoaded', () => showToast('" . addslashes($toast['text']) . "', '" . $toast['type'] . "'));";
        }
        ?>
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>