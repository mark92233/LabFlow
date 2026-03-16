<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control [cite: 2025-12-06]
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_category'])) {
    $catName = trim($_POST['category_name']);

    $isConsumable = isset($_POST['is_consumable']) ? (int)$_POST['is_consumable'] : 0;
    if (!empty($catName)) {
        if ($db->addCategory($catName, $isConsumable)) {
            $_SESSION['toast_message'] = ['text' => "Category '$catName' added successfully.", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => "Category already exists or a database error occurred.", 'type' => 'error'];
        }
        header("Location: category_mgr.php");
        exit();
    }
}

$categories = $db->getCategories();
$page_title = "Category Manager";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Category Manager | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel=  "stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-10">
                    <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter mb-2">Categories<span class="text-blue-600">.</span></h2>
                    <p class="text-slate-400 font-medium italic">Define the "Types" for your apparatus cards.</p>
                </header>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <section class="lg:col-span-1">
                        <div class="glass-card p-8 sticky top-24">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6 italic">New Classification</h3>

                            <form method="POST" class="space-y-4">
                                <input type="text" name="category_name" placeholder="e.g. Glassware" 
                                       class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm outline-none focus:ring-2 focus:ring-blue-500" required>
                                <div>
                                    <label for="is_consumable" class="block text-xs font-bold text-slate-500 mb-2">Category Type</label>
                                    <select name="is_consumable" id="is_consumable" class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="0">Non-Consumable</option>
                                        <option value="1">Consumable</option>
                                    </select>
                                </div>
                                <button type="submit" name="save_category" 
                                        class="w-full bg-[#0f172a] text-white py-4 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-blue-600 transition-all shadow-xl">
                                    Save Category
                                </button>
                            </form>
                        </div>
                    </section>

                    <section class="lg:col-span-2">
                        <div class="glass-card p-8">
                            <div class="flex items-center justify-between mb-8">
                                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest italic">Active Classifications</h3>
                                <span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-[10px] font-black italic"><?= count($categories) ?> TOTAL</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php if (empty($categories)): ?>
                                    <div class="col-span-full py-20 text-center opacity-20 italic font-black uppercase tracking-widest">No Categories Found</div>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <div class="group p-5 bg-slate-50 rounded-[1.5rem] border border-transparent hover:border-blue-200 hover:bg-white transition-all flex justify-between items-center">
                                            <span class="text-sm font-black text-slate-800 uppercase italic"><?= $cat['Category_Name'] ?></span>
                                            <div class="w-2 h-2 rounded-full bg-blue-500 shadow-lg shadow-blue-500/50"></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <?php
    $toast_message = null;
    $toast_type = 'success'; // Default type

    if (isset($_SESSION['toast_message'])) {
        $toast_message = $_SESSION['toast_message']['text'];
        $toast_type = $_SESSION['toast_message']['type'];
        unset($_SESSION['toast_message']);
    }
    ?>

    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl">
            <!-- Icon will be inserted by JS -->
        </div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <script>
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast-container');
        if (!toast) return;

        const iconContainer = document.getElementById('toast-icon-container');
        const messageContainer = document.getElementById('toast-message');

        // Reset classes
        toast.className = 'fixed bottom-10 right-10 z-[200] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal';
        iconContainer.className = 'inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl';

        messageContainer.textContent = message;

        if (type === 'success') {
            toast.classList.add('bg-emerald-600');
            iconContainer.classList.add('bg-emerald-100');
            iconContainer.innerHTML = `<svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>`;
        } else { // error
            toast.classList.add('bg-red-600');
            iconContainer.classList.add('bg-red-100');
            iconContainer.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`;
        }

        toast.classList.remove('hidden');
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
        toast.style.transition = 'all 0.5s ease';

        setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
    }

    <?php if ($toast_message): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('<?php echo addslashes($toast_message); ?>', '<?php echo $toast_type; ?>');
    });
    <?php endif; ?>
    </script>
</body>
</html>