<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$student_id = $_SESSION['user_id'];

// Fetch classes for the student
$classes = $db->getStudentEnrolledClasses($student_id);

$page_title = "My Classes";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> | LabFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            <main class="p-8 animate-reveal flex-1 flex gap-8" x-data="myClassesApp(<?= htmlspecialchars(json_encode($classes), ENT_QUOTES, 'UTF-8') ?>)">
                
                <div class="flex-1">
                    <header class="mb-12">
                        <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter">
                            My <span class="text-orange-600">Classes.</span>
                        </h2>
                        <p class="text-slate-400 font-medium text-xs mt-2">Select a class to view its activities and materials.</p>
                    </header>

                    <?php if (empty($classes)): ?>
                        <div class="text-center p-20 bg-white rounded-2xl border border-slate-100 shadow-sm">
                            <h3 class="text-lg font-bold text-slate-700">You are not enrolled in any classes yet.</h3>
                            <p class="text-sm text-slate-400 mt-2">Please contact your instructor to be added to a class.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            <template x-for="classData in allClasses" :key="classData.ClassID">
                                <button @click="selectedClass = classData" 
                                        class="block text-left bg-white p-8 rounded-3xl border shadow-lg hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 group"
                                        :class="selectedClass && selectedClass.ClassID == classData.ClassID ? 'border-orange-500 ring-2 ring-orange-200' : 'border-slate-100 hover:border-orange-500'">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="w-16 h-16 bg-orange-50 text-orange-500 rounded-2xl flex items-center justify-center">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                        </div>
                                        <span class="bg-slate-100 text-slate-500 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-tighter group-hover:bg-orange-100 group-hover:text-orange-600 transition-colors" x-text="classData.Semester"></span>
                                    </div>
                                    <h3 class="text-xl font-black text-slate-800 leading-tight mb-2 group-hover:text-orange-600 transition-colors" x-text="classData.Class_Name"></h3>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest" x-text="classData.TeacherName"></p>
                                    
                                    <div class="mt-6 pt-6 border-t border-dashed border-slate-100 flex justify-between items-center">
                                        <div class="text-center">
                                            <p class="text-2xl font-black text-slate-700 group-hover:text-orange-600 transition-colors" x-text="classData.ActivityCount"></p>
                                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Activities</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-2xl font-black text-slate-700" x-text="classData.Section"></p>
                                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Section</p>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="w-96 flex-shrink-0">
                    <div class="sticky top-28">
                        <div x-show="!selectedClass" x-transition class="h-full flex flex-col items-center justify-center text-center p-8 text-slate-400 border-2 border-dashed border-slate-200 rounded-3xl">
                            <div class="w-16 h-16 bg-slate-100 text-orange-500 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"></path></svg>
                            </div>
                            <h3 class="font-bold text-slate-600">Select a Class</h3>
                            <p class="text-sm mt-1">Click a class card to see a quick peek of its details.</p>
                        </div>

                        <div x-show="selectedClass" x-transition class="bg-white rounded-3xl border-t-4 border-orange-500 shadow-lg flex flex-col" x-cloak>
                            <div class="p-8 flex-1">
                                <div class="text-center mb-6">
                                    <h3 class="text-2xl font-black text-slate-800 leading-tight" x-text="selectedClass.Class_Name"></h3>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1" x-text="selectedClass.TeacherName"></p>
                                </div>

                                <!-- Class Overview -->
                                <div class="grid grid-cols-3 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100 mb-6">
                                    <div class="text-center">
                                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Semester</p>
                                        <p class="text-sm font-bold text-slate-700" x-text="selectedClass.Semester"></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Section</p>
                                        <p class="text-sm font-bold text-slate-700" x-text="selectedClass.Section"></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Activities</p>
                                        <p class="text-sm font-bold text-slate-700" x-text="selectedClass.ActivityCount"></p>
                                    </div>
                                </div>

                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Clearance Status</h4>
                                <!-- Clearance Status -->
                                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 mb-6">
                                    <template x-if="selectedClass.ClearanceStatus === 'Cleared'">
                                        <div class="flex items-center gap-3 text-emerald-600">
                                            <div class="p-2 bg-emerald-100 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                                            <div><p class="text-sm font-bold">Cleared</p><p class="text-xs">No pending liabilities for this class.</p></div>
                                        </div>
                                    </template>
                                    <template x-if="selectedClass.ClearanceStatus !== 'Cleared'">
                                        <div class="flex items-center gap-3 text-amber-600">
                                            <div class="p-2 bg-amber-100 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                                            <div><p class="text-sm font-bold">Pending Clearance</p><p class="text-xs">You may have unresolved items.</p></div>
                                        </div>
                                    </template>
                                </div>

                                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Upcoming Deadlines</h4>
                                <div class="space-y-3">
                                    <template x-if="!selectedClass.upcoming_deadlines || selectedClass.upcoming_deadlines.length === 0">
                                        <p class="text-center text-xs text-slate-400 italic py-4">No upcoming deadlines.</p>
                                    </template>
                                    <template x-for="deadline in selectedClass.upcoming_deadlines" :key="deadline.ActivityID">
                                        <a :href="`activity_view.php?activity_id=${deadline.ActivityID}&class_id=${selectedClass.ClassID}`" class="block p-4 rounded-xl bg-white border border-slate-100 hover:border-orange-300 hover:bg-orange-50 transition-colors">
                                            <div class="flex justify-between items-center">
                                                <p class="text-sm font-bold text-slate-700" x-text="deadline.Title"></p>
                                                <p class="text-[10px] font-black text-red-500 uppercase" x-text="new Date(deadline.Deadline).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })"></p>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                            <div class="p-6 bg-slate-50/70 border-t border-slate-100">
                                <a :href="`lab_list.php?class_id=${selectedClass.ClassID}`" class="block text-center w-full py-4 bg-orange-500 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20">
                                    View All Activities
                                </a>
                            </div>
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>
    <script>
        function myClassesApp(classesData) {
            return {
                allClasses: classesData,
                selectedClass: null,
                init() {
                    // If there are classes, automatically select the first one on page load.
                    if (this.allClasses.length > 0) {
                        this.selectedClass = this.allClasses[0];
                    }
                }
            }
        }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>