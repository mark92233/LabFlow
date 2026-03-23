<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control & Initial Setup - Admin, Teacher, or LabTech
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Teacher', 'LabTech'])) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$sid = $_GET['sid'] ?? null;

if (!$sid) {
    header("Location: handover.php");
    exit();
}

// 2. Handle Form Submission (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $session_id_post = $_POST['session_id'];
    $return_type = $_POST['process_return'];
    $handlerId = $_SESSION['user_id']; // The current admin/teacher is the one processing the return

    if ($return_type === 'clean') {
        if ($db->processCleanReturn($session_id_post, $handlerId)) {
            $_SESSION['toast_message'] = ['text' => "Session #{$session_id_post} marked as returned.", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => "Failed to process clean return for #{$session_id_post}.", 'type' => 'error'];
        }
    } elseif ($return_type === 'with_damage') {
        $damage_data = $_POST['damages'] ?? [];
        $filtered_damage_data = array_filter($damage_data, fn($report) => !empty($report['qty']) && $report['qty'] > 0);

        if (empty($filtered_damage_data)) {
            if ($db->processCleanReturn($session_id_post, $handlerId)) {
                $_SESSION['toast_message'] = ['text' => "Session #{$session_id_post} marked as returned (no damages logged).", 'type' => 'success'];
            } else {
                $_SESSION['toast_message'] = ['text' => "Failed to process clean return for #{$session_id_post}.", 'type' => 'error'];
            }
        } else {
            if ($db->processReturnWithDamage($session_id_post, $filtered_damage_data, $handlerId)) {
                $_SESSION['toast_message'] = ['text' => "Return with damages for #{$session_id_post} has been logged.", 'type' => 'success'];
            } else {
                $_SESSION['toast_message'] = ['text' => "Failed to process return with damages for #{$session_id_post}.", 'type' => 'error'];
            }
        }
    }

    header("Location: handover.php");
    exit();
}

// 3. Fetch Data for Display (GET Request)
$query = "SELECT bs.SessionID, bs.Status, bs.CreatedAt, m.Full_Name as StudentName, m.ID_Number as studentId, COALESCE(c.Class_Name, 'General') as Class_Name, COALESCE(la.Title, 'Independent Research') as Title, bs.Remarks
          FROM borrowing_sessions bs
          JOIN users u ON bs.StudentID = u.UserID
          JOIN lookup_masterlist m ON u.MasterID = m.MasterID
          LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
          LEFT JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
          LEFT JOIN classes c ON aa.ClassID = c.ClassID
          WHERE bs.SessionID = :sid
          GROUP BY bs.SessionID";
$stmt = $db->db->prepare($query);
$stmt->execute(['sid' => $sid]);
$sessionData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sessionData || $sessionData['Status'] !== 'Issued') {
    $_SESSION['toast_message'] = ['text' => "This session is not ready for return processing.", 'type' => 'error'];
    header("Location: handover.php");
    exit();
}

$itemsQuery = "SELECT bi.ItemID as id, 
                    i.Item_Name as name, 
                    bi.Quantity as qty,
                    i.is_consumable,
                    iv.Size_Value,
                    iv.Unit
               FROM borrowed_items bi 
               JOIN inventory i ON bi.ItemID = i.ItemID 
               LEFT JOIN item_variants iv ON bi.VariantID = iv.VariantID
               WHERE bi.SessionID = ?";
$iStmt = $db->db->prepare($itemsQuery);
$iStmt->execute([$sid]);
$borrowedItems = $iStmt->fetchAll(PDO::FETCH_ASSOC);

// Separate items
$consumables = array_filter($borrowedItems, fn($item) => $item['is_consumable'] == 1);
$nonConsumables = array_filter($borrowedItems, fn($item) => $item['is_consumable'] == 0);

$page_title = "Process Return #" . $sid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap');
        .thermal-font { font-family: 'Courier Prime', 'Courier New', Courier, monospace; }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
        .shake-error {
            animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both;
        }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 flex-1">
                <form method="POST" action="process_return.php?sid=<?= $sid ?>" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-8 h-full">
                    <input type="hidden" name="session_id" value="<?= $sid ?>">

                    <!-- Middle Column: Item List & Damage Forms -->
                    <div class="lg:col-span-8 space-y-4">
                        <header class="flex justify-between items-center">
                            <div>
                                <h2 class="text-2xl font-extrabold text-gray-800">Borrowed Items</h2>
                                <p class="text-sm text-slate-500 font-medium">For: <span class="font-bold text-blue-600"><?= htmlspecialchars($sessionData['StudentName']) ?></span></p>
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" name="process_return" value="clean" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Confirm Clean Return</button>
                                <button type="submit" name="process_return" value="with_damage" class="px-4 py-2 bg-red-500 text-white rounded-lg text-xs font-bold shadow-lg shadow-red-500/20 hover:bg-red-600">Submit with Damages</button>
                            </div>
                        </header>

                        <?php if (!empty($nonConsumables)): ?>
                            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-widest pt-4">Non-Consumables to Return</h3>
                            <?php foreach ($nonConsumables as $item): ?>
                            <div class="bg-white border border-slate-200 rounded-xl p-4 transition-all duration-300">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-slate-800">
                                            <?= htmlspecialchars($item['name']) ?>
                                            <?php if (!empty($item['Size_Value'])): ?>
                                                <span class="text-xs font-medium text-slate-500">(<?= htmlspecialchars($item['Size_Value']) . htmlspecialchars($item['Unit']) ?>)</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-slate-500">Borrowed: <?= $item['qty'] ?></p>
                                    </div>
                                    <button type="button" onclick="toggleDamageForm(this, '<?= $item['id'] ?>')" class="text-xs font-bold text-red-500 hover:bg-red-50 rounded-md px-3 py-2 transition-colors">Report Damage</button>
                                </div>
                                <div id="damage-form-<?= $item['id'] ?>" class="hidden mt-4 pt-4 border-t border-dashed border-slate-200 space-y-3">
                                    <input type="hidden" name="damages[<?= $item['id'] ?>][item_id]" value="<?= $item['id'] ?>">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-500 uppercase">Quantity Damaged</label>
                                            <input type="number" name="damages[<?= $item['id'] ?>][qty]" min="1" max="<?= $item['qty'] ?>" class="w-full bg-slate-100 border border-slate-200 p-2 rounded-lg font-medium text-sm shadow-sm transition-all duration-300" placeholder="0" oninput="validateDamageQty(this, <?= $item['qty'] ?>); updatePreview('<?= $item['id'] ?>', '<?= htmlspecialchars(addslashes($item['name'])) ?>')">
                                            <p class="damage-qty-error text-red-500 text-xs mt-1 hidden h-4"></p>
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-slate-500 uppercase">Damage Type</label>
                                            <select name="damages[<?= $item['id'] ?>][type]" class="w-full bg-slate-100 border-slate-200 p-2 rounded-lg font-medium text-sm shadow-sm" onchange="updatePreview('<?= $item['id'] ?>', '<?= htmlspecialchars(addslashes($item['name'])) ?>')">
                                                <option value="Broken">Broken</option>
                                                <option value="Lost">Lost</option>
                                                <option value="Dirty">Dirty</option>
                                                <option value="Malfunction">Malfunction</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-500 uppercase">Notes</label>
                                        <textarea name="damages[<?= $item['id'] ?>][notes]" class="w-full bg-slate-100 border-slate-200 p-2 rounded-lg font-medium text-sm shadow-sm" rows="2" placeholder="e.g., Cracked during experiment..." oninput="updatePreview('<?= $item['id'] ?>', '<?= htmlspecialchars(addslashes($item['name'])) ?>')"></textarea>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-slate-500 uppercase">Evidence Photo</label>
                                        <div id="dropzone-<?= $item['id'] ?>" ondrop="dropHandler(event, '<?= $item['id'] ?>');" ondragover="dragOverHandler(event);" ondragleave="dragLeaveHandler(event);" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-xl hover:border-indigo-500 hover:bg-indigo-50 transition-colors duration-300">
                                            <div class="space-y-1 text-center">
                                                <svg class="mx-auto h-10 w-10 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                                <div class="flex text-xs text-slate-600">
                                                    <label for="evidence_<?= $item['id'] ?>" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none">
                                                        <span>Upload a file</span>
                                                        <input id="evidence_<?= $item['id'] ?>" name="evidence_<?= $item['id'] ?>" type="file" class="sr-only" accept="image/*" onchange="fileChangeHandler(event, '<?= $item['id'] ?>', '<?= htmlspecialchars(addslashes($item['name'])) ?>')">
                                                    </label>
                                                    <p class="pl-1">or drag and drop</p>
                                                </div>
                                                <p class="text-[10px] text-slate-500">PNG, JPG up to 5MB</p>
                                                <p id="filename-<?= $item['id'] ?>" class="text-xs text-emerald-600 font-bold pt-2"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($consumables)): ?>
                            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-widest pt-4">Consumables Used</h3>
                            <?php foreach ($consumables as $item): ?>
                            <div class="bg-white border border-slate-200 rounded-xl p-4 opacity-70">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-slate-800">
                                            <?= htmlspecialchars($item['name']) ?>
                                            <?php if (!empty($item['Size_Value'])): ?>
                                                <span class="text-xs font-medium text-slate-500">(<?= htmlspecialchars($item['Size_Value']) . htmlspecialchars($item['Unit']) ?>)</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-slate-500">Used: <?= $item['qty'] ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Photo Previews -->
                    <div class="lg:col-span-4">
                        <div class="bg-white rounded-2xl shadow-lg border border-slate-200/50 h-full p-6 sticky top-28">
                            <h3 class="text-sm font-bold text-slate-800 mb-4 border-b pb-3">Damage Report Preview</h3>
                            <div id="photo-preview-container" class="space-y-4 overflow-y-auto h-[calc(100%-52px)] custom-scrollbar pr-2">
                                <div id="photo-empty-state" class="text-center text-slate-400 pt-10">
                                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <p class="text-xs font-medium">Previews will appear here.</p>
                                </div>
                                <!-- JS will inject previews here -->
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

<script>
    function validateDamageQty(input, maxQty) {
        const value = parseInt(input.value, 10) || 0;
        const errorEl = input.nextElementSibling;

        // Always clear previous error state on new input
        input.classList.remove('border-red-500', 'shake-error');
        if (errorEl) {
            errorEl.classList.add('hidden');
            errorEl.textContent = '';
        }

        if (value > maxQty) {
            // Set the value to the max allowed
            input.value = maxQty;

            // Show error message and styles
            if (errorEl) {
                errorEl.textContent = `Max quantity is ${maxQty}.`;
                errorEl.classList.remove('hidden');
            }
            input.classList.add('border-red-500', 'shake-error');
            
            setTimeout(() => input.classList.remove('shake-error'), 820);
        } 
    }

    function dragOverHandler(ev) {
        ev.preventDefault();
        ev.currentTarget.classList.add('border-indigo-500', 'bg-indigo-50');
    }

    function dragLeaveHandler(ev) {
        ev.currentTarget.classList.remove('border-indigo-500', 'bg-indigo-50');
    }

    function dropHandler(ev, itemId) {
        ev.preventDefault();
        ev.currentTarget.classList.remove('border-indigo-500', 'bg-indigo-50');
        
        const fileInput = document.getElementById(`evidence_${itemId}`);
        let file;

        if (ev.dataTransfer.items && ev.dataTransfer.items[0].kind === 'file') {
            file = ev.dataTransfer.items[0].getAsFile();
        } else {
            file = ev.dataTransfer.files[0];
        }

        if (file) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
            
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }
    }

    function fileChangeHandler(ev, itemId, itemName) {
        const fileInput = ev.target;
        const fileNameDisplay = document.getElementById(`filename-${itemId}`);
        if (fileInput.files.length > 0) {
            fileNameDisplay.textContent = fileInput.files[0].name;
        } else {
            fileNameDisplay.textContent = '';
        }
        updatePreview(itemId, itemName);
    }

    function toggleDamageForm(button, itemId) {
        const form = document.getElementById(`damage-form-${itemId}`);
        const parentCard = button.closest('.bg-white');
        form.classList.toggle('hidden');
        if (form.classList.contains('hidden')) {
            button.innerText = 'Report Damage';
            button.classList.remove('bg-red-100');
            parentCard.classList.remove('ring-2', 'ring-red-300', 'shadow-lg');
            // Clear inputs when hiding
            form.querySelector('input[type="number"]').value = '';
            form.querySelector('textarea').value = '';
            const fileInput = form.querySelector('input[type="file"]');
            fileInput.value = '';
        
        // This will trigger the removal of the preview card
        updatePreview(itemId, ''); 
        } else {
            button.innerText = 'Cancel Report';
            button.classList.add('bg-red-100');
            parentCard.classList.add('ring-2', 'ring-red-300', 'shadow-lg');
        }
    }
function updatePreview(itemId, itemName) {
    const container = document.getElementById('photo-preview-container');
    const emptyState = document.getElementById('photo-empty-state');
    let previewCard = document.getElementById(`preview-${itemId}`);

    // Data from form
    const qtyInput = document.querySelector(`input[name="damages[${itemId}][qty]"]`);
    const typeInput = document.querySelector(`select[name="damages[${itemId}][type]"]`);
    const notesInput = document.querySelector(`textarea[name="damages[${itemId}][notes]"]`);
    const fileInput = document.querySelector(`input[name="evidence_${itemId}"]`);

    const qty = qtyInput ? qtyInput.value : '';
    const type = typeInput ? typeInput.value : '';
    const notes = notesInput ? notesInput.value : '';
    const file = fileInput ? fileInput.files[0] : null;

    // If form is hidden (no qty, notes, file), remove card
    if (!qty && !notes && !file) {
        if (previewCard) {
            previewCard.remove();
        }
        if (container.childElementCount <= 1) { // only empty state left
            emptyState.classList.remove('hidden');
        }
        return;
    }

    // If card doesn't exist, create it
    if (!previewCard) {
        const previewHtml = `
            <div id="preview-${itemId}" class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3 animate-reveal">
                <h4 class="text-sm font-bold text-slate-800">${itemName}</h4>
                <div id="preview-img-container-${itemId}" class="hidden w-full aspect-video bg-slate-200 rounded-lg overflow-hidden">
                    <img id="preview-img-${itemId}" class="w-full h-full object-cover">
                </div>
                <div class="text-xs space-y-2 text-slate-600 pt-2 border-t border-slate-200">
                    <div>
                        <strong class="text-slate-400 text-[9px] uppercase block">Qty Damaged:</strong>
                        <span id="preview-qty-${itemId}" class="font-medium"></span>
                    </div>
                    <div>
                        <strong class="text-slate-400 text-[9px] uppercase block">Type:</strong>
                        <span id="preview-type-${itemId}" class="font-medium"></span>
                    </div>
                    <div>
                        <strong class="text-slate-400 text-[9px] uppercase block">Notes:</strong>
                        <p id="preview-notes-${itemId}" class="font-medium italic"></p>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', previewHtml);
        previewCard = document.getElementById(`preview-${itemId}`);
        emptyState.classList.add('hidden');
        // Add a small delay to allow the element to be in the DOM, then add 'active' to trigger the animation.
        setTimeout(() => {
            if(previewCard) previewCard.classList.add('active');
        }, 10);
    }

    // Update content
    if(previewCard) {
        document.getElementById(`preview-qty-${itemId}`).textContent = qty || '...';
        document.getElementById(`preview-type-${itemId}`).textContent = type;
        document.getElementById(`preview-notes-${itemId}`).textContent = notes ? `"${notes}"` : '...';

        // Handle image
        const imgContainer = document.getElementById(`preview-img-container-${itemId}`);
        const imgEl = document.getElementById(`preview-img-${itemId}`);
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imgEl.src = e.target.result;
                imgContainer.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            imgContainer.classList.add('hidden');
        }
    }
}
</script>
<?php include '../../includes/layout_footer.php'; ?>
</body>
</html>