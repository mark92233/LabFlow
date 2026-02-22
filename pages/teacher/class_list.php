<?php
session_start();
require_once '../../dbRelated/operation.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$class_id = $_GET['id'] ?? null;

// Fetch details via DataManager
$class_info = $db->getClassDetails($class_id);

if (!$class_info || ($class_info['TeacherID'] != $_SESSION['user_id'] && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: manage_classes.php");
    exit();
}

// Fetching updated population (MasterID-based)
$students = $db->getEnrolledStudents($class_id);

// UI Variable for Header
$page_title = $class_info['Class_Name'] . " Enrollment";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class List | <?= htmlspecialchars($class_info['Class_Name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>

            <main class="p-8 animate-reveal">
                <header class="mb-12 flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                             <span class="bg-blue-500/10 text-blue-600 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-tighter">
                                <?= htmlspecialchars($class_info['Semester']) ?>
                            </span>
                            <span class="text-slate-300">•</span>
                            <span class="text-xs text-slate-400 font-bold uppercase tracking-widest"><?= htmlspecialchars($class_info['Section']) ?></span>
                        </div>
                        <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter">
                            Student <span class="text-blue-600">Roster.</span>
                        </h2>
                    </div>
                    
                    <div class="relative w-full md:w-80">
                        <input type="text" id="studentSearch" onkeyup="filterStudents()" 
                               placeholder="Search name or ID..." 
                               class="w-full pl-12 pr-4 py-4 bg-white border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 shadow-sm transition-all font-medium">
                        <svg class="w-6 h-6 absolute left-4 top-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </header>

                <div class="glass-card overflow-hidden">
                    <div class="p-6 border-b border-slate-50 bg-slate-50/30 flex justify-between items-center">
                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest italic">Enrollment Data</h3>
                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded-lg"><?= count($students) ?> Records Found</span>
                    </div>

                    <?php if (empty($students)): ?>
                        <div class="p-20 text-center flex flex-col items-center">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                                <svg class="w-10 h-10 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </div>
                            <h3 class="text-[#0f172a] font-black text-xl italic uppercase tracking-tight">No Students Enrolled</h3>
                            <p class="text-slate-400 text-sm mt-2 max-w-xs">Use the Admission terminal in Manage Classes to add students to this section.</p>
                            <a href="manage_classes.php" class="mt-8 bg-[#0f172a] text-white px-8 py-3 rounded-2xl font-bold text-xs hover:bg-blue-600 transition-all">Go to Admission</a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse" id="studentTable">
                                <thead class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-50">
                                    <tr>
                                        <th class="px-8 py-5">ID Number</th>
                                        <th class="px-8 py-5">Full Identity</th>
                                        <th class="px-8 py-5">Communication</th>
                                        <th class="px-8 py-5 text-center">Cloud Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php foreach ($students as $student): ?>
                                        <tr class="student-row hover:bg-blue-50/30 transition-all group">
                                            <td class="px-8 py-5 font-mono text-xs text-slate-400 student-id"><?= htmlspecialchars($student['ID_Number']) ?></td>
                                            <td class="px-8 py-5 font-black text-[#0f172a] student-name leading-tight">
                                                <?= htmlspecialchars($student['Full_Name']) ?>
                                            </td>
                                            <td class="px-8 py-5 text-slate-500 text-xs font-medium"><?= htmlspecialchars($student['Official_Email']) ?></td>
                                            <td class="px-8 py-5 text-center">
                                                <?php if ($student['Is_Verified'] == 1): ?>
                                                    <span class="px-3 py-1 bg-blue-500 text-white text-[9px] font-black rounded-lg uppercase tracking-tighter shadow-lg shadow-blue-500/20 italic">Verified</span>
                                                <?php else: ?>
                                                    <span class="px-3 py-1 bg-slate-100 text-slate-400 text-[9px] font-black rounded-lg uppercase tracking-tighter italic">Pending Auth</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr id="noResults" class="hidden">
                                        <td colspan="4" class="py-20 text-center">
                                            <p class="text-slate-400 text-sm italic font-medium">No students match your query.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        function filterStudents() {
            const input = document.getElementById('studentSearch').value.toLowerCase();
            const rows = document.getElementsByClassName('student-row');
            const noResults = document.getElementById('noResults');
            let visibleCount = 0;

            for (let row of rows) {
                const name = row.querySelector('.student-name').innerText.toLowerCase();
                const id = row.querySelector('.student-id').innerText.toLowerCase();
                
                if (name.includes(input) || id.includes(input)) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            }

            if (visibleCount === 0 && input !== "") {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }
    </script>

    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>