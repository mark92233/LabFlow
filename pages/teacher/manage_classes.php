<?php
session_start();
require_once '../../dbRelated/operation.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$user_id = $_SESSION['user_id'];
$status_msg = "";
$status_type = "success";

// 1. Handle Single Student Admission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['single_admission'])) {
    $idNum = trim($_POST['student_id']);
    $classID = $_POST['target_class_id'];
    $name = trim($_POST['student_name']);
    $email = trim($_POST['student_email']);
    
    $masterID = $db->uploadStudentToMasterlist($idNum, $name, $email);
    
    if ($masterID) {
        if ($db->enrollByMasterID($masterID, $classID)) {
            $status_msg = "Student $name enrolled successfully.";
        } else {
            $status_msg = "Note: Student info updated, but already enrolled in this class.";
            $status_type = "info";
        }
    } else {
        $status_msg = "Error: Failed to process student identity.";
        $status_type = "error";
    }
}

// 2. Handle Bulk Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $classID = $_POST['target_class_id'];
    try {
        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();
        $count = 0;
        for ($i = 1; $i < count($rows); $i++) {
            if (!empty($rows[$i][0])) {
                $masterID = $db->uploadStudentToMasterlist($rows[$i][0], $rows[$i][1], $rows[$i][2]);
                if ($masterID) {
                    $db->enrollByMasterID($masterID, $classID);
                    $count++;
                }
            }
        }
        $status_msg = "Bulk upload completed. $count students processed.";
    } catch (Exception $e) { 
        $status_msg = "Error: " . $e->getMessage(); 
        $status_type = "error";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_class'])) {
    $className = trim($_POST['class_name']);
    $section = trim($_POST['section']);
    $semester = $_POST['semester'];

    if ($db->createClass($user_id, $className, $section, $semester)) {
        $status_msg = "Class '$className' created successfully.";
        // Refresh class list
        $myClasses = $db->getTeacherClasses($user_id);
    } else {
        $status_msg = "Error: Could not create class.";
        $status_type = "error";
    }
}

$myClasses = $db->getTeacherClasses($user_id);
$page_title = "Class Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Classes | SNHS</title>
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
                        <h2 class="text-5xl font-extrabold text-[#0f172a] tracking-tighter mb-2">
                            Class <span class="text-blue-600">Registry.</span>
                        </h2>
                        <p class="text-slate-400 font-medium">Manage student enrollment and section parameters.</p>
                    </div>
                    <div class="relative w-full md:w-80">
                        <input type="text" id="classSearch" onkeyup="filterClasses()" 
                               placeholder="Search classes..." 
                               class="w-full pl-12 pr-4 py-4 bg-white border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 shadow-sm transition-all font-medium">
                        <svg class="w-6 h-6 absolute left-4 top-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </header>

                <?php if ($status_msg): ?>
                    <div class="mb-8 p-6 glass-card border-blue-100 flex justify-between items-center animate-reveal active">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $status_type == 'error' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600' ?>">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span class="font-bold text-slate-700"><?= $status_msg ?></span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 font-black text-xl">&times;</button>
                    </div>
                <?php endif; ?>

                <div id="classGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <button onclick="document.getElementById('createClassModal').classList.remove('hidden')" 
                            class="glass-card p-8 flex flex-col items-center justify-center border-2 border-dashed border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 transition-all group min-h-[250px]">
                        <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-[1.5rem] flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-blue-600 group-hover:text-white transition-all duration-500">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <span class="font-black text-slate-600 uppercase tracking-widest text-xs">Create New Class</span>
                    </button>

                    <?php foreach ($myClasses as $class): ?>
                        <div class="class-card glass-card p-8 hover:shadow-2xl hover:shadow-blue-500/10 transition-all duration-500 group">
                            <div class="flex justify-between items-start mb-6">
                                <span class="bg-blue-500/10 text-blue-600 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-tighter">
                                    <?= htmlspecialchars($class['Semester']) ?>
                                </span>
                            </div>
                            <h3 class="text-2xl font-black text-[#0f172a] class-title group-hover:text-blue-600 transition-colors mb-1"><?= htmlspecialchars($class['Class_Name']) ?></h3>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mb-8"><?= htmlspecialchars($class['Section']) ?></p>
                            
                            <div class="pt-6 border-t border-slate-50 space-y-3">
                                <button onclick="openAdmissionModal(<?= $class['ClassID'] ?>, '<?= addslashes($class['Class_Name']) ?>')" 
                                        class="w-full flex items-center justify-between p-4 bg-slate-50 rounded-2xl hover:bg-[#0f172a] hover:text-white transition-all group/btn">
                                    <span class="text-xs font-bold">Add Students</span>
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                </button>
                                <a href="class_list.php?id=<?= $class['ClassID'] ?>" 
                                   class="w-full flex items-center justify-between p-4 bg-white border border-slate-100 rounded-2xl hover:border-blue-500 transition-all">
                                    <span class="text-xs font-bold text-slate-600">View Enrollment</span>
                                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>

    <div id="admissionModal" class="hidden fixed inset-0 bg-[#0f172a]/80 flex items-center justify-center z-50 p-4 backdrop-blur-md">
        <div class="bg-white max-w-lg w-full rounded-[2.5rem] shadow-2xl overflow-hidden animate-reveal active">
            <div class="bg-slate-50 p-8 border-b border-slate-100 flex justify-between items-center">
                <div>
                    <h3 id="modalClassTitle" class="font-black text-[#0f172a] text-xl tracking-tight italic uppercase">Admission</h3>
                    <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase">Student Enrollment Terminal</p>
                </div>
                <button onclick="closeAdmissionModal()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-200 transition-colors text-slate-400">&times;</button>
            </div>
            <div class="p-8">
                <div class="flex gap-4 mb-8 bg-slate-100 p-1.5 rounded-2xl">
                    <button id="tabSingle" onclick="switchTab('single')" class="flex-1 py-3 rounded-xl font-bold text-xs transition-all bg-white text-[#0f172a] shadow-sm">SINGLE ENTRY</button>
                    <button id="tabBulk" onclick="switchTab('bulk')" class="flex-1 py-3 rounded-xl font-bold text-xs text-slate-400 transition-all">BULK UPLOAD</button>
                </div>

                <form id="formSingle" method="POST" class="space-y-4">
                    <input type="hidden" name="target_class_id" id="single_class_id">
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Student ID Number</label>
                        <input type="text" name="student_id" placeholder="e.g. 2024-001" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-medium" required>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Full Name</label>
                        <input type="text" name="student_name" placeholder="Juan Dela Cruz" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-medium" required>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Official Email</label>
                        <input type="email" name="student_email" placeholder="juan@wmsu.edu.ph" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 outline-none font-medium" required>
                    </div>
                    <button type="submit" name="single_admission" class="w-full bg-[#0f172a] text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs shadow-xl shadow-slate-900/20 hover:bg-blue-600 transition-all transform active:scale-95">Enroll Student</button>
                </form>

                <form id="formBulk" method="POST" enctype="multipart/form-data" class="hidden space-y-6">
                    <input type="hidden" name="target_class_id" id="bulk_class_id">
                    <div class="border-2 border-dashed border-slate-200 p-12 text-center rounded-[2rem] hover:border-blue-400 transition-all bg-slate-50/50">
                        <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <input type="file" name="excel_file" accept=".csv, .xlsx" class="mb-4 block mx-auto text-xs text-slate-400">
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Excel Template: ID, Name, Email</p>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs shadow-xl shadow-blue-500/20 hover:bg-[#0f172a] transition-all transform active:scale-95">Start Bulk Import</button>
                </form>
            </div>
        </div>
    </div>

    <div id="createClassModal" class="hidden fixed inset-0 bg-[#0f172a]/80 flex items-center justify-center z-50 p-4 backdrop-blur-md">
    <div class="bg-white max-w-lg w-full rounded-[2.5rem] shadow-2xl overflow-hidden">
        <div class="bg-slate-50 p-8 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-black text-[#0f172a] text-xl tracking-tight uppercase italic">Create Class</h3>
            <button onclick="document.getElementById('createClassModal').classList.add('hidden')" class="text-slate-400">&times;</button>
        </div>
        <form method="POST" class="p-8 space-y-4">
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Class Name</label>
                <input type="text" name="class_name" placeholder="e.g., General Chemistry 1" class="w-full bg-slate-50 p-4 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Section</label>
                <input type="text" name="section" placeholder="e.g., STEM-12A" class="w-full bg-slate-50 p-4 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Semester</label>
                <select name="semester" class="w-full bg-slate-50 p-4 rounded-2xl outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="Summer">Summer</option>
                </select>
            </div>
            <button type="submit" name="create_class" class="w-full bg-blue-600 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-[#0f172a] transition-all">
                Finalize Class Creation
            </button>
        </form>
    </div>
</div>

    <script>
        function filterClasses() {
            let input = document.getElementById('classSearch').value.toLowerCase();
            let cards = document.getElementsByClassName('class-card');
            for (let card of cards) {
                let title = card.querySelector('.class-title').innerText.toLowerCase();
                card.style.display = title.includes(input) ? "" : "none";
            }
        }

        function openAdmissionModal(id, name) {
            document.getElementById('single_class_id').value = id;
            document.getElementById('bulk_class_id').value = id;
            document.getElementById('modalClassTitle').innerText = name;
            document.getElementById('admissionModal').classList.remove('hidden');
        }

        function switchTab(type) {
            const single = document.getElementById('formSingle'), bulk = document.getElementById('formBulk');
            const tabS = document.getElementById('tabSingle'), tabB = document.getElementById('tabBulk');
            if(type === 'single') {
                single.classList.remove('hidden'); bulk.classList.add('hidden');
                tabS.className = "flex-1 py-3 rounded-xl font-bold text-xs transition-all bg-white text-[#0f172a] shadow-sm";
                tabB.className = "flex-1 py-3 rounded-xl font-bold text-xs text-slate-400 transition-all";
            } else {
                bulk.classList.remove('hidden'); single.classList.add('hidden');
                tabB.className = "flex-1 py-3 rounded-xl font-bold text-xs transition-all bg-white text-[#0f172a] shadow-sm";
                tabS.className = "flex-1 py-3 rounded-xl font-bold text-xs text-slate-400 transition-all";
            }
        }

        function closeAdmissionModal() { document.getElementById('admissionModal').classList.add('hidden'); }
    </script>

    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>