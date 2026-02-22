<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control [cite: 2025-12-06]
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$error = "";
$success = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_category'])) {
    $catName = trim($_POST['category_name']);
    if (!empty($catName)) {
        if ($db->addCategory($catName)) {
            $success = "Category '$catName' added successfully.";
        } else {
            $error = "Category already exists or a database error occurred.";
        }
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
    <link rel="stylesheet" href="../../assets/css/style.css">
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
                            
                            <?php if ($success): ?>
                                <div class="mb-6 p-4 bg-green-50 text-green-600 rounded-2xl text-[10px] font-black uppercase italic border border-green-100 italic animate-reveal">
                                    <?= $success ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($error): ?>
                                <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-[10px] font-black uppercase italic border border-red-100 italic animate-reveal">
                                    <?= $error ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="space-y-4">
                                <input type="text" name="category_name" placeholder="e.g. Glassware" 
                                       class="w-full bg-slate-50 border-none p-4 rounded-2xl font-bold text-sm outline-none focus:ring-2 focus:ring-blue-500" required>
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
</body>
</html>