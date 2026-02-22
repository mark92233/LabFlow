<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control [cite: 2025-12-06]
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
// Catch the success flag from the URL
$success = isset($_GET['success']);

// Handle Apparatus Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
    $itemId = $db->addItem($_POST['cat_id'], $_POST['item_name'], $_POST['qty'], $_POST['location'], $_POST['description']);
    if ($itemId && isset($_FILES['item_image'])) {
        move_uploaded_file($_FILES["item_image"]["tmp_name"], "../../assets/img/items/" . $itemId . ".png");
        header("Location: manage_inventory.php?success=1");
        exit();
    }
}

$categories = $db->getCategories();
$page_title = "Inventory Master";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Master | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen relative">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-10">
                    <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter mb-2">Inventory<span class="text-blue-600">.</span></h2>
                    <p class="text-slate-400 font-medium italic">Register apparatus and manage categories in one place.</p>
                </header>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <section class="lg:col-span-2">
                        <form method="POST" enctype="multipart/form-data" class="glass-card p-8 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Category</label>
                                        <button type="button" onclick="toggleCategoryModal()" class="text-[9px] font-black text-blue-600 uppercase hover:underline">+ Quick Add</button>
                                    </div>
                                    <select name="cat_id" id="category_select" class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="">Select Category</option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?= $cat['CategoryID'] ?>"><?= $cat['Category_Name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Apparatus Name</label>
                                    <input type="text" name="item_name" placeholder="e.g. Florence Flask" class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Total Stock</label>
                                    <input type="number" name="qty" placeholder="0" class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Location</label>
                                    <input type="text" name="location" placeholder="Cabinet A-1" class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Pokémon Card Description</label>
                                <textarea name="description" rows="3" class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm"></textarea>
                            </div>
                            <div class="p-6 border-2 border-dashed border-slate-200 rounded-[2rem] text-center">
                                <label class="cursor-pointer">
                                    <span class="text-xs font-black text-blue-600 uppercase tracking-widest">Upload Apparatus Photo</span>
                                    <input type="file" name="item_image" class="hidden" accept="image/*" required onchange="updateFileName(this)">
                                    <p id="file-chosen" class="text-[9px] text-slate-400 mt-1 italic">No file chosen</p>
                                </label>
                            </div>
                            <button type="submit" name="add_item" class="w-full bg-[#0f172a] text-white py-5 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-blue-600 transition-all shadow-xl">
                                Add to Shop Inventory
                            </button>
                        </form>
                    </section>

                    <aside class="space-y-6">
                        <div class="glass-card p-8 border-blue-500/20">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-4 italic">Management Tip</h3>
                            <p class="text-[11px] text-slate-500 leading-relaxed italic">
                                Use the <b class="text-blue-600">+ Quick Add</b> link to create a new category (like "Optics" or "Bio-Safety") without leaving this form.
                            </p>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>

    <div id="cat-modal" class="fixed inset-0 bg-[#0f172a]/80 backdrop-blur-sm z-[100] hidden flex items-center justify-center">
        <div class="glass-card bg-white p-8 w-full max-w-sm border-t-8 border-blue-600">
            <h4 class="text-xl font-black text-slate-800 uppercase italic mb-6">New Category</h4>
            <input type="text" id="new_cat_name" placeholder="e.g. Glassware" class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm mb-4">
            <button onclick="saveNewCategory()" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black uppercase text-xs tracking-widest">Save Category</button>
            <button onclick="toggleCategoryModal()" class="w-full text-[9px] font-black text-slate-300 uppercase tracking-widest mt-4">Close</button>
        </div>
    </div>

    <?php if ($success): ?>
    <div id="toast-success" class="fixed bottom-10 right-10 flex items-center w-full max-w-xs p-4 space-x-4 text-white bg-[#0f172a] rounded-[1.5rem] shadow-2xl animate-reveal z-[200]" role="alert">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 text-blue-500 bg-blue-100 rounded-xl">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
        </div>
        <div class="text-[10px] font-black uppercase tracking-widest italic">Apparatus Saved to Shop!</div>
    </div>
    <script>
        setTimeout(() => {
            const toast = document.getElementById('toast-success');
            if(toast) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                toast.style.transition = 'all 0.5s ease';
                setTimeout(() => toast.remove(), 500);
            }
        }, 4000);
    </script>
    <?php endif; ?>

    <script>
        function updateFileName(input) {
            const fileName = input.files[0].name;
            document.getElementById('file-chosen').textContent = fileName;
        }

        function toggleCategoryModal() {
            document.getElementById('cat-modal').classList.toggle('hidden');
        }

        async function saveNewCategory() {
            const name = document.getElementById('new_cat_name').value;
            if (!name) return alert("Enter a name");

            const response = await fetch('../../dbRelated/ajax_add_category.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `category_name=${encodeURIComponent(name)}`
            });

            const data = await response.json();
            if (data.success) {
                const select = document.getElementById('category_select');
                const option = new Option(name, data.new_id);
                select.add(option);
                select.value = data.new_id;
                toggleCategoryModal();
                document.getElementById('new_cat_name').value = '';
            } else {
                alert(data.error || "Error adding category");
            }
        }
    </script>
</body>
</html>