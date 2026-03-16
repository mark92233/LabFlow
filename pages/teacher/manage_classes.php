<?php
session_start();
// NOTE: Ensure this path is correct.
require_once '../../dbRelated/operation.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Teacher', 'Admin'])) {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Register a new user to the masterlist (Admin only)
    if (isset($_POST['register_user']) && $role === 'Admin') {
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
    // 2. Handle Single Student Admission to a class
    elseif (isset($_POST['single_admission'])) {    
        $idNum = trim($_POST['student_id']);
        $classID = $_POST['target_class_id'];
        $name = trim($_POST['student_name']);
        $email = trim($_POST['student_email']);
        
        // The updated function handles both masterlist and enrollment in one transaction.
        if ($db->uploadUserToMasterlist($idNum, $name, $email, 'Student', $classID)) {
            $_SESSION['toast_message'] = ['text' => "Student '$name' processed successfully.", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => "Error: Failed to process student. They may already be registered with a different role.", 'type' => 'error'];
        }
    }
    // 3. Handle Confirmed Bulk Student Upload from Preview
    elseif (isset($_POST['confirm_bulk_import'])) {
        $classID = $_POST['confirm_import_class_id'];
        $studentsJSON = $_POST['confirmed_students_json'];
        $students = json_decode($studentsJSON, true);
        $successCount = 0;
        $errorMessages = [];

        if (is_array($students)) {
            foreach ($students as $student) {
                $idNum = $student['id'] ?? '';
                $name = $student['name'] ?? '';
                $email = $student['email'] ?? '';
                
                try {
                    if ($db->uploadUserToMasterlist($idNum, $name, $email, 'Student', $classID)) {
                        $successCount++;
                    }
                } catch (Exception $e) {
                    $errorMessages[] = $e->getMessage();
                }
            }
            $totalCount = count($students);
            $errorCount = count($errorMessages);
            $message = "Import finished. {$successCount}/{$totalCount} students processed.";
            $_SESSION['toast_message'] = ['text' => $message, 'type' => $errorCount > 0 ? 'error' : 'success'];
            if ($errorCount > 0) { $_SESSION['bulk_import_errors'] = $errorMessages; }
        } else {
            $_SESSION['toast_message'] = ['text' => "Error: Invalid student data received.", 'type' => 'error'];
        }
    }
    // 4. Handle Class Creation
    elseif (isset($_POST['create_class'])) {
        $className = trim($_POST['class_name']);
        $section = trim($_POST['section']);
        $semester = $_POST['semester'];
        
        // If Admin is creating, use the selected teacher ID. Otherwise, use their own ID.
        $teacherForClass = ($role === 'Admin' && isset($_POST['teacher_id'])) ? $_POST['teacher_id'] : $user_id;

        $result = $db->createClass($teacherForClass, $className, $section, $semester);
        if ($result) {
            $_SESSION['toast_message'] = ['text' => "Class '$className' created successfully.", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => $db->getLastError() ?: "Error: Could not create class.", 'type' => 'error'];
        }
    }
    // 5. Handle Class Deletion
    elseif (isset($_POST['delete_class']) && $role === 'Admin') {
        $classID = $_POST['class_id_to_delete'];
        if ($db->deleteClass($classID)) {
            $_SESSION['toast_message'] = ['text' => "Class deleted successfully.", 'type' => 'success'];
        } else {
            $_SESSION['toast_message'] = ['text' => $db->getLastError() ?: "Error: Could not delete class.", 'type' => 'error'];
        }
    }
    header("Location: manage_classes.php");
    exit();
}

// Fetch class data for display
$myClasses = ($role === 'Admin') ? $db->getAllClasses() : $db->getTeacherClasses($user_id);

// NEW: Fetch teachers for Admin
$teachers = [];
if ($role === 'Admin') {
    $teachers = $db->getTeachers();
}

$page_title = "Class Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <style>
        .side-panel {
            transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        /* Minor fix for search input focus ring */
        input:focus { outline: none; }
        [x-cloak] { display: none !important; }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen" x-data="admissionManager()">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            <main class="p-8 animate-reveal">
                <header class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div>
                        <h2 class="text-4xl font-extrabold text-gray-800 tracking-tighter">
                            User & Class <span class="text-orange-500">Registry.</span>
                        </h2>
                        <p class="text-slate-400 font-medium text-xs">Manage users, classes, and student enrollment.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if ($role === 'Admin'): ?>
                            <button onclick="openUserRegistryModal()" class="flex items-center gap-2 px-5 py-3 bg-white border border-slate-200 rounded-xl font-bold text-xs text-slate-600 hover:bg-slate-50 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                <span>Register User</span>
                            </button>
                        <?php endif; ?>
                        <button onclick="openPanel('createClassPanel')" class="px-5 py-3 bg-orange-500 text-white rounded-xl font-bold text-xs shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-all">Create New Class</button>
                    </div>
                    <div class="relative w-full md:w-80">
                        <input type="text" id="classSearch" onkeyup="filterClasses()" 
                               placeholder="Search classes..." 
                               class="w-full pl-12 pr-4 py-4 bg-white border border-slate-200 rounded-2xl outline-none focus:ring-2 focus:ring-orange-500 shadow-sm transition-all font-medium">
                        <svg class="w-6 h-6 absolute left-4 top-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </header>

                <div id="classGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <button onclick="openPanel('createClassPanel')" class="bg-slate-50/50 p-8 flex flex-col items-center justify-center border-2 border-dashed border-slate-200 hover:border-orange-500 hover:bg-orange-50 transition-all group min-h-[250px] rounded-3xl">
                        <div class="w-16 h-16 bg-orange-50 text-orange-600 rounded-[1.5rem] flex items-center justify-center mb-4 group-hover:scale-110 group-hover:bg-orange-600 group-hover:text-white transition-all duration-500">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <span class="font-black text-slate-600 uppercase tracking-widest text-xs">Create New Class</span>
                    </button>

                    <?php foreach ($myClasses as $class): ?>
                        <div class="class-card bg-white p-8 hover:shadow-2xl hover:shadow-orange-500/10 transition-all duration-500 group rounded-3xl border border-slate-100">
                            <div class="flex justify-between items-start mb-6">
                                <span class="bg-orange-500/10 text-orange-600 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-tighter">
                                    <?= htmlspecialchars($class['Semester']) ?>
                                </span>
                            </div>
                            <h3 class="text-2xl font-black text-[#0f172a] class-title group-hover:text-orange-600 transition-colors mb-1"><?= htmlspecialchars($class['Class_Name']) ?></h3>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mb-8"><?= htmlspecialchars($class['Section']) ?></p>
                            
                            <div class="pt-6 border-t border-slate-50 space-y-3">
                                <button @click="open(<?= $class['ClassID'] ?>, '<?= addslashes(htmlspecialchars($class['Class_Name'])) ?>')"
                                        class="w-full flex items-center justify-between p-4 bg-orange-50 rounded-2xl hover:bg-orange-100 transition-all group/btn">
                                    <span class="text-xs font-bold">Add Students</span>
                                    <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                </button>
                                <a href="class_activities.php?class_id=<?= $class['ClassID'] ?>"
                                   class="w-full flex items-center justify-between p-4 bg-white border border-slate-100 rounded-2xl hover:border-orange-500 hover:bg-orange-50 transition-all">
                                    <span class="text-xs font-bold text-slate-600">View Activities</span>
                                    <svg class="w-4 h-4 text-slate-300 group-hover:text-orange-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                                </a>
                                <a href="class_list.php?id=<?= $class['ClassID'] ?>" 
                                   class="w-full flex items-center justify-between p-4 bg-white border border-slate-100 rounded-2xl hover:border-orange-500 hover:bg-orange-50 transition-all">
                                    <span class="text-xs font-bold text-slate-600">View Enrollment</span>
                                    <svg class="w-4 h-4 text-slate-300 group-hover:text-orange-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </a>
                                <?php if ($role === 'Admin'): ?>
                                    <button @click="openDeleteModal(<?= $class['ClassID'] ?>, '<?= addslashes(htmlspecialchars($class['Class_Name'])) ?>')" class="w-full flex items-center justify-between p-4 bg-red-50 rounded-2xl hover:bg-red-100 transition-all group/btn">
                                        <span class="text-xs font-bold text-red-600">Delete Class</span>
                                        <svg class="w-4 h-4 text-red-400 group-hover/btn:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Panel Backdrop -->
    <div id="panelBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden" onclick="closeAllPanels()"></div>

    <!-- Admission Panel -->
    <aside id="admissionPanel" class="side-panel fixed top-0 right-0 h-full w-full max-w-lg bg-white shadow-2xl transform translate-x-full z-50 flex flex-col">
        <header class="p-8 border-b border-slate-100 flex justify-between items-center flex-shrink-0">
            <div>
                <h3 id="admissionPanelTitle" class="font-black text-slate-800 text-xl tracking-tight italic uppercase">Admission</h3>
                <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase">Student Enrollment Terminal</p>
            </div>
            <button onclick="closeAllPanels()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-100 transition-colors text-slate-400">&times;</button>
        </header>
        <div class="p-8 overflow-y-auto flex-1">
            <div class="flex gap-2 mb-8 bg-slate-100 p-1.5 rounded-2xl" x-show="activeTab !== 'preview'">
                <button @click="switchTab('single')" class="flex-1 py-3 rounded-xl font-bold text-xs transition-all" :class="activeTab === 'single' ? 'bg-white text-orange-600 shadow-sm' : 'text-slate-500'">SINGLE ENTRY</button>
                <button @click="switchTab('bulk')" class="flex-1 py-3 rounded-xl font-bold text-xs transition-all" :class="activeTab === 'bulk' ? 'bg-white text-orange-600 shadow-sm' : 'text-slate-500'">BULK UPLOAD</button>
            </div>

            <!-- Single Entry Form -->
            <form x-show="activeTab === 'single'" method="POST" class="space-y-4">
                <input type="hidden" name="target_class_id" id="single_class_id">
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Student ID Number</label>
                    <input type="text" name="student_id" placeholder="e.g. 2024-001" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Full Name</label>
                    <input type="text" name="student_name" placeholder="Juan Dela Cruz" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Official Email</label>
                    <input type="email" name="student_email" placeholder="juan@wmsu.edu.ph" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required>
                </div>
                <button type="submit" name="single_admission" class="w-full bg-orange-500 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs shadow-xl shadow-orange-500/20 hover:bg-orange-600 transition-all transform active:scale-95">Enroll Student</button>
            </form>

            <!-- Bulk Upload Form -->
            <div x-show="activeTab === 'bulk'" class="space-y-6">
                <div class="border-2 border-dashed border-slate-200 p-12 text-center rounded-3xl hover:border-orange-400 transition-all bg-slate-50/50" :class="{ 'animate-pulse': isLoading }">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <input type="file" id="bulk_file_input" @change="handleFileSelect" accept=".csv, .xlsx" class="mb-4 block mx-auto text-xs text-slate-400">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Excel Template: ID, Name, Email</p>
                </div>
                <p class="text-center text-xs text-slate-400 italic" x-show="isLoading">Processing file...</p>
            </div>

            <!-- Preview List -->
            <div x-show="activeTab === 'preview'" x-cloak class="space-y-4">
                <div class="flex justify-between items-center">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Review & Confirm (<span x-text="previewData.length"></span>)</h4>
                    <button @click="activeTab = 'bulk'; file = null; document.getElementById('bulk_file_input').value = '';" class="text-xs font-bold text-slate-500 hover:text-red-500 transition-colors">Cancel</button>
                </div>
                <div class="max-h-[30rem] overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                    <template x-for="(student, index) in previewData" :key="index">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-2 relative group">
                            <button @click="removeStudentFromPreview(index)" title="Remove Student" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs font-black flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity transform hover:scale-110 active:scale-95">
                                &times;
                            </button>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-1">
                                    <label class="text-[9px] font-bold text-slate-400">ID Number</label>
                                    <input type="text" x-model="student.id" class="w-full bg-white border border-slate-200 rounded p-2 text-xs font-mono focus:ring-1 focus:ring-orange-500 focus:border-orange-500 outline-none">
                                </div>
                                <div class="col-span-2">
                                    <label class="text-[9px] font-bold text-slate-400">Full Name</label>
                                    <input type="text" x-model="student.name" class="w-full bg-white border border-slate-200 rounded p-2 text-xs focus:ring-1 focus:ring-orange-500 focus:border-orange-500 outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-slate-400">Email</label>
                                <input type="email" x-model="student.email" class="w-full bg-white border border-slate-200 rounded p-2 text-xs focus:ring-1 focus:ring-orange-500 focus:border-orange-500 outline-none">
                            </div>
                        </div>
                    </template>
                </div>
                <button @click="confirmImport()" class="w-full bg-orange-500 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs shadow-xl shadow-orange-500/20 hover:bg-orange-600 transition-all">Confirm & Enroll All</button>
            </div>
        </div>
    </aside>

    <!-- User Registry Panel -->
    <?php if ($role === 'Admin'): ?>
    <aside id="userRegistryPanel" class="side-panel fixed top-0 right-0 h-full w-full max-w-lg bg-white shadow-2xl transform translate-x-full z-50 flex flex-col">
        <header class="p-8 border-b border-slate-100 flex justify-between items-center flex-shrink-0">
            <div>
                <h3 class="font-black text-slate-800 text-xl tracking-tight uppercase italic">User Registry</h3>
                <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase">Add to Masterlist</p>
            </div>
            <button onclick="closeAllPanels()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-100 transition-colors text-slate-400">&times;</button>
        </header>
        <div class="p-8 overflow-y-auto flex-1">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="register_user" value="1">
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Role</label>
                    <select name="user_role" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium">
                        <option value="Student">Student</option>
                        <option value="Teacher">Teacher</option>
                        <option value="LabTech">LabTech</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="space-y-1"><label class="text-[10px] font-black text-slate-400 ml-2 uppercase">ID Number</label><input type="text" name="user_id" placeholder="e.g. 2024-001" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required></div>
                <div class="space-y-1"><label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Full Name</label><input type="text" name="user_name" placeholder="e.g. Juan Dela Cruz" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required></div>
                <div class="space-y-1"><label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Official Email</label><input type="email" name="user_email" placeholder="e.g. juan@wmsu.edu.ph" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-orange-500 outline-none font-medium" required></div>
                <button type="submit" class="w-full bg-orange-500 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs shadow-xl shadow-orange-500/20 hover:bg-orange-600 transition-all transform active:scale-95">Add to Masterlist</button>
            </form>
        </div>
    </aside>
    <?php endif; ?>

    <!-- Hidden form for confirmed bulk import -->
    <form id="confirmImportForm" method="POST" class="hidden">
        <input type="hidden" name="confirm_bulk_import" value="1">
        <input type="hidden" name="confirm_import_class_id" id="confirm_import_class_id">
        <input type="hidden" name="confirmed_students_json" id="confirm_import_data">
    </form>

    <!-- Create Class Panel -->
    <aside id="createClassPanel" class="side-panel fixed top-0 right-0 h-full w-full max-w-lg bg-white shadow-2xl transform translate-x-full z-50 flex flex-col">
        <header class="p-8 border-b border-slate-100 flex justify-between items-center flex-shrink-0">
            <h3 class="font-black text-[#0f172a] text-xl tracking-tight uppercase italic">Create Class</h3>
            <button onclick="closeAllPanels()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-100 transition-colors text-slate-400">&times;</button>
        </header>
        <div class="p-8 overflow-y-auto flex-1">
            <form method="POST" class="space-y-4">
                <?php if ($role === 'Admin'): ?>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Assign Teacher</label>
                        <select name="teacher_id" class="w-full bg-slate-50 p-4 rounded-2xl outline-none focus:ring-2 focus:ring-orange-500" required>
                            <option value="" disabled selected>Select a teacher...</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['UserID'] ?>"><?= htmlspecialchars($teacher['Full_Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Class Name</label>
                    <input type="text" name="class_name" placeholder="e.g., General Chemistry 1" class="w-full bg-slate-50 p-4 rounded-2xl outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Section</label>
                    <input type="text" name="section" placeholder="e.g., STEM-12A" class="w-full bg-slate-50 p-4 rounded-2xl outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 ml-2 uppercase">Semester</label>
                    <select name="semester" class="w-full bg-slate-50 p-4 rounded-2xl outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="1st Semester">1st Semester</option>
                        <option value="2nd Semester">2nd Semester</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
                <button type="submit" name="create_class" class="w-full bg-orange-500 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-orange-600 transition-all">
                    Finalize Class Creation
                </button>
            </form>
        </div>
    </aside>

    <!-- Delete Confirmation Modal -->
    <div id="deleteClassModal" x-show="showDeleteModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showDeleteModal = false"></div>

        <div class="relative transform overflow-hidden rounded-2xl bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-8 border border-slate-100 animate-reveal active">
            <form method="POST">
                <input type="hidden" name="delete_class" value="1">
                <input type="hidden" name="class_id_to_delete" x-model="classIdToDelete">
                
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 mb-6">
                        <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-2">Delete Class</h3>
                    <p class="text-slate-500 mb-8 text-sm">Are you sure you want to delete the class <strong x-text="classNameToDelete" class="text-slate-700"></strong>? This action cannot be undone.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <button type="button" @click="showDeleteModal = false" class="w-full rounded-lg bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="w-full text-center rounded-lg bg-red-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-red-500/20 hover:bg-red-700 transition-colors">
                        Confirm Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../includes/toast.php'; ?>
    <script>
        function filterClasses() {
            let input = document.getElementById('classSearch').value.toLowerCase();
            let cards = document.getElementsByClassName('class-card');
            for (let card of cards) {
                let title = card.querySelector('.class-title').innerText.toLowerCase();
                card.style.display = title.includes(input) ? "" : "none";
            }
        }

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

        function openUserRegistryModal() { openPanel('userRegistryPanel'); }

        document.addEventListener('alpine:init', () => {
            Alpine.data('admissionManager', (initialPreviewData = []) => ({
                classId: null,
                className: '',
                activeTab: 'single', // 'single', 'bulk', 'preview'
                previewData: [],
                isLoading: false,
                showDeleteModal: false,
                classIdToDelete: null,
                classNameToDelete: '',

                openDeleteModal(id, name) {
                    this.classIdToDelete = id;
                    this.classNameToDelete = name;
                    this.showDeleteModal = true;
                },
                
                init() {
                    if (initialPreviewData.length > 0) {
                        this.previewData = initialPreviewData;
                        this.activeTab = 'preview';
                        <?php
                        if (isset($_SESSION['bulk_preview_data']) && isset($_GET['show_preview'])) {
                            echo "this.classId = " . json_encode($_SESSION['bulk_preview_class_id']) . ";\n";
                            echo "this.className = " . json_encode($_SESSION['bulk_preview_class_name']) . ";\n";
                            echo "document.getElementById('admissionPanelTitle').innerText = this.className;\n";
                            echo "openPanel('admissionPanel');\n";
                            // Clean up session data after use
                            unset($_SESSION['bulk_preview_data']);
                            unset($_SESSION['bulk_preview_class_id']);
                            unset($_SESSION['bulk_preview_class_name']);
                        }
                        ?>
                    }
                },

                open(id, name) {
                    this.classId = id;
                    this.className = name;
                    document.getElementById('admissionPanelTitle').innerText = name;
                    document.getElementById('single_class_id').value = id; // This was the missing line
                    this.activeTab = 'single'; // Reset to single entry tab
                    this.previewData = [];
                    const fileInput = document.getElementById('bulk_file_input');
                    if(fileInput) fileInput.value = '';
                    openPanel('admissionPanel');
                },

                switchTab(tab) {
                    // Don't switch to preview tab manually, it's handled by page reload
                    if (tab !== 'preview') {
                        this.activeTab = tab;
                    }
                },

                handleFileSelect(event) {
                    this.file = event.target.files[0];
                    if (this.file) {
                        this.generatePreview();
                    }
                },

                async generatePreview() {
                    if (!this.file) {
                        showToast('Please select a file first.', 'error');
                        return;
                    }
                    this.isLoading = true;
                    this.previewData = [];

                    const formData = new FormData();
                    formData.append('excel_file', this.file);

                    try {
                        const response = await fetch('ajax_preview_import.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.error) { throw new Error(result.error); }
                        this.previewData = result.data;
                        this.activeTab = 'preview';
                    } catch (e) {
                        showToast('Error processing file: ' + e.message, 'error');
                        this.file = null;
                        document.getElementById('bulk_file_input').value = '';
                    } finally {
                        this.isLoading = false;
                    }
                },

                removeStudentFromPreview(index) {
                    this.previewData.splice(index, 1);
                },

                confirmImport() {
                    document.getElementById('confirm_import_data').value = JSON.stringify(this.previewData);
                    document.getElementById('confirm_import_class_id').value = this.classId;
                    document.getElementById('confirmImportForm').submit();
                }
            }))
        });
    </script>

    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>