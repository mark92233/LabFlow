<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$activity_id = $_GET['activity_id'] ?? null;
$student_id = $_SESSION['user_id'];
$masterID = $db->getMasterID($student_id);

// 2. Fetch Activity & Group Details
$activity = $db->getActivityDetails($activity_id);
$myGroup = $db->getStudentGroupStatus($activity_id, $masterID);

if (!$activity || !$myGroup) {
    header("Location: lab_list.php?error=access_denied");
    exit();
}

$groupID = $myGroup['GroupID'];
// 🛠️ FETCH SMART STATUS (Fixes Unlock & Feedback issues)
$statusData = $db->getStudentActivityStatus($activity_id, $student_id);
$isLocked = $statusData['is_locked']; // Smart Lock: False if 'Returned', True if 'Submitted'
$myStatus = $statusData['status'];    // 'Returned', 'Graded', 'Submitted', etc.
$teacherFeedback = $statusData['feedback'];

// Maintain compatibility with existing deadline logic
$accessStatus = $db->getGroupActivityStatus($activity_id, $groupID);
// 3. Fetch Data (Anonymized sections for cards + History for sidebar)
$sections = $db->getAnonymizedSections($activity_id, $groupID);
$history = $db->getCommitHistory($activity_id, $groupID);

// 4. Calculate Completion Progress
$completedCount = 0;
foreach($sections as $sec) { 
    if($sec['Status'] === 'Completed') $completedCount++; 
}
$progress = (count($sections) > 0) ? ($completedCount / count($sections)) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workspace | <?= htmlspecialchars($activity['Title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        [x-cloak] { display: none !important; }
        .audit-scroll::-webkit-scrollbar { width: 4px; }
        .audit-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .audit-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .audit-scroll::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
        
        .diff-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem; height: 450px; overflow-y: auto; }
        .prose img { max-width: 100%; height: auto; border-radius: 0.5rem; }
    </style>
</head>
<body class="bg-[#f8fafc] min-h-screen" x-data="workspaceManager()">

    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 max-w-[1600px] mx-auto w-full animate-reveal">
                
                <?php if ($accessStatus['is_past_deadline']): ?>
                    <div class="mb-8 p-4 bg-red-50 border border-red-100 rounded-[2rem] flex items-center justify-center gap-3 text-red-600 shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em]">Editing Closed: Deadline reached on <?= $accessStatus['deadline_formatted'] ?></p>
                    </div>
               <?php elseif ($myStatus === 'Returned'): ?>
    <div class="mb-8 p-6 bg-amber-50 border-l-8 border-amber-500 rounded-r-[2rem] shadow-sm animate-reveal flex items-start gap-5">
        <div class="p-3 bg-amber-100 text-amber-600 rounded-full shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </div>
        <div class="flex-1">
            <h3 class="text-xl font-black text-amber-800 uppercase italic tracking-tighter">Action Required: Revision Needed</h3>
            <div class="mt-3 bg-white/60 p-4 rounded-xl border border-amber-200 text-slate-700 italic text-sm leading-relaxed">
                <span class="text-[10px] font-black text-amber-600 uppercase not-italic block mb-1">Instructor Feedback:</span>
                "<?= htmlspecialchars($teacherFeedback) ?>"
            </div>
        </div>
    </div>

<?php elseif ($accessStatus['is_submitted'] && $myStatus !== 'Returned'): ?>
    <div class="mb-8 p-4 bg-emerald-50 border border-emerald-100 rounded-[2rem] flex items-center justify-center gap-3 text-emerald-600 shadow-sm">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M5 13l4 4L19 7"/></svg>
        <p class="text-[10px] font-black uppercase tracking-[0.2em]">Project Finalized: Work has been submitted</p>
    </div>
<?php endif; ?>
                <header class="mb-12 flex flex-col lg:flex-row lg:items-end justify-between gap-6">
                    <div>
                        <a href="activity_view.php?activity_id=<?= $activity_id ?>&class_id=<?= $activity['ClassID'] ?>" class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-2 flex items-center gap-2 hover:gap-3 transition-all">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
                            Back to Lab Manual
                        </a>
                        <h2 class="text-5xl font-black text-[#0f172a] uppercase italic tracking-tighter">Team <span class="text-indigo-600">Workspace.</span></h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.3em] mt-2">
                            Project: <?= htmlspecialchars($activity['Title']) ?> • Group: <span class="text-slate-600"><?= htmlspecialchars($myGroup['GroupName']) ?></span>
                        </p>
                    </div>

                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm min-w-[320px]">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Overall Progress</span>
                            <span class="text-xs font-black text-indigo-600"><?= round($progress) ?>%</span>
                        </div>
                        <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-600 transition-all duration-1000" style="width: <?= $progress ?>%"></div>
                        </div>
                    </div>
                </header>

                <div class="flex flex-col lg:flex-row gap-8">
                    <div class="flex-1 grid grid-cols-1 xl:grid-cols-2 gap-8 self-start">
                        <?php foreach ($sections as $s): 
                            $showRevisionBadge = ($s['Open_Comments_Count'] > 0 && $s['Status'] === 'Needs Revision');
                        ?>
                            <div class="glass-card p-10 border-t-8 transition-all duration-500 relative overflow-hidden group"
                                 :class="getCardClass('<?= $s['Status'] ?>', <?= $s['Locked_By'] ?? 'null' ?>)">
                                
                                <div class="flex justify-between items-start mb-8">
                                    <div>
                                        <h3 class="text-2xl font-black text-slate-800 uppercase italic tracking-tighter mb-1"><?= $s['Title'] ?></h3>
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full" :class="getStatusDot('<?= $s['Status'] ?>')"></span>
                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest"><?= $s['Status'] ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if($showRevisionBadge && !$isLocked): ?>
                                        <div class="bg-amber-500 text-white px-4 py-2 rounded-2xl flex items-center gap-2 shadow-lg animate-pulse">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            <span class="text-[9px] font-black uppercase tracking-tight">Leader Requests Edit</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($s['Locked_By'] && $s['Locked_By'] != $masterID && !$isLocked): ?>
                                    <div class="mb-8 flex items-center gap-4 p-5 bg-red-50 border border-red-100 rounded-[1.5rem]">
                                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center animate-pulse text-xs">🔒</div>
                                        <div>
                                            <p class="text-[9px] font-black text-red-400 uppercase tracking-widest leading-none mb-1">Status</p>
                                            <p class="text-xs font-bold text-red-700 italic">Being edited by another team member...</p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="flex gap-3">
                                    <?php if($isLocked): ?>
                                        <button @click="viewFullSection(<?= $s['SectionID'] ?>, '<?= addslashes($s['Title']) ?>')"
                                                class="flex-1 py-5 rounded-[2rem] font-black uppercase text-[10px] tracking-widest transition-all shadow-lg bg-slate-800 text-white hover:bg-indigo-600 italic">
                                            View Work Done
                                        </button>
                                    <?php else: ?>
                                        <button @click="startEditing(<?= $s['SectionID'] ?>)"
                                                <?= ($s['Locked_By'] && $s['Locked_By'] != $masterID) ? 'disabled' : '' ?>
                                                class="flex-1 py-5 rounded-[2rem] font-black uppercase text-[10px] tracking-widest transition-all shadow-lg
                                                <?= ($s['Locked_By'] == $masterID) ? 'bg-indigo-600 text-white' : 'bg-[#0f172a] text-white hover:bg-blue-600 disabled:opacity-30' ?>">
                                            <?= ($s['Locked_By'] == $masterID) ? 'Resume Drafting' : 'Checkout Section' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <aside class="w-full lg:w-[380px] space-y-6">
                        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm">
                            <h3 class="text-xs font-black uppercase italic tracking-tighter mb-6 flex items-center gap-3">
                                <span class="w-2 h-2 bg-indigo-600 rounded-full"></span>
                                Forensic Audit Trail
                            </h3>
                            
                            <div class="audit-scroll pr-2 space-y-6 relative max-h-[450px] overflow-y-auto before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-px before:bg-slate-100">
                                <?php if(empty($history)): ?>
                                    <p class="text-center py-10 text-[10px] font-bold text-slate-300 uppercase tracking-widest">No activity logged yet</p>
                                <?php endif; ?>

                                <?php foreach($history as $log): ?>
                                    <div @click="viewDiff(<?= $log['HistoryID'] ?>, '<?= addslashes($log['SectionTitle']) ?>')" 
                                         class="relative pl-10 cursor-pointer group hover:bg-slate-50 p-2 rounded-2xl transition-all">
                                        <div class="absolute left-0 top-2 w-8 h-8 rounded-full bg-slate-50 border-2 border-white shadow-sm flex items-center justify-center text-[10px] font-black text-indigo-600 z-10 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                            <?= substr($log['Full_Name'], 0, 1) ?>
                                        </div>
                                        
                                        <div>
                                            <p class="text-[11px] font-black text-slate-800 leading-tight"><?= htmlspecialchars($log['Full_Name']) ?></p>
                                            <p class="text-[10px] font-bold text-slate-400">Updated <span class="text-indigo-500 italic"><?= $log['SectionTitle'] ?></span></p>
                                            <p class="text-[8px] font-black text-slate-300 uppercase mt-1 flex items-center gap-2">
                                                <?= date('h:i A • M d', strtotime($log['Timestamp'])) ?>
                                                <span class="text-indigo-400 opacity-0 group-hover:opacity-100 transition-all underline">View Changes</span>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <?php if($isLocked): ?>
                                <div class="p-8 bg-slate-800 rounded-[2.5rem] text-white shadow-xl border border-slate-700 animate-reveal">
                                    <h4 class="text-lg font-black italic uppercase tracking-tighter mb-2">Compiled Report</h4>
                                    <p class="text-[10px] font-medium text-slate-400 mb-6 leading-relaxed">
                                        The activity is locked. You can still view the full compiled version of your team's work.
                                    </p>
                                    <a href="preview_compiler.php?activity_id=<?= $activity_id ?>" 
                                       class="block text-center w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-500 transition-all">
                                        Open Full Preview
                                    </a>
                                </div>
                            <?php elseif($myGroup['Is_Leader'] && $progress >= 100): ?>
                                <div class="p-8 bg-indigo-900 rounded-[2.5rem] text-white shadow-xl border border-indigo-800 animate-reveal">
                                    <h4 class="text-lg font-black italic uppercase tracking-tighter mb-2">Final Review</h4>
                                    <p class="text-[10px] font-medium text-indigo-300 mb-6 leading-relaxed">
                                        All sections are complete. You can now compile the report and perform the final submission.
                                    </p>
                                    <a href="preview_compiler.php?activity_id=<?= $activity_id ?>" 
                                       class="block text-center w-full py-4 bg-white text-indigo-900 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-50 transition-all">
                                        Final Compiler
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>

    <div x-show="showDiffModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-900/60 backdrop-blur-sm" 
         x-cloak>
        <div class="bg-white w-full max-w-6xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[90vh]" @click.away="showDiffModal = false">
            <header class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <div>
                    <h3 class="text-2xl font-black text-slate-800 uppercase italic tracking-tighter">Content Viewer.</h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Snapshot for: <span class="text-indigo-600" x-text="diffData.sectionTitle"></span></p>
                </div>
                <button @click="showDiffModal = false" class="w-12 h-12 bg-white border border-slate-200 rounded-2xl flex items-center justify-center hover:bg-red-50 hover:text-red-500 transition-all text-xl font-bold">&times;</button>
            </header>

            <div class="flex-1 overflow-hidden flex flex-col lg:flex-row divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-widest text-center" x-text="diffData.before === '(Final Version)' ? 'Status' : 'Version Prior to Edit'"></div>
                    <div class="p-10 overflow-y-auto prose prose-slate max-w-none text-sm leading-relaxed text-slate-400 italic bg-slate-50/20" x-html="diffData.before"></div>
                </div>
                <div class="flex-1 flex flex-col min-h-0">
                    <div class="p-4 bg-indigo-50 border-b border-indigo-100 text-[9px] font-black text-indigo-600 uppercase tracking-widest text-center" x-text="diffData.before === '(Final Version)' ? 'Complete Content' : 'Contribution'"></div>
                    <div class="p-10 overflow-y-auto prose prose-slate max-w-none text-sm leading-relaxed text-slate-800 bg-white" x-html="diffData.after"></div>
                </div>
            </div>
            
            <footer class="p-6 bg-slate-50 border-t border-slate-100 text-center">
                <p class="text-[9px] font-bold text-slate-400 uppercase italic">Workspace is currently in Read-Only mode.</p>
            </footer>
        </div>
    </div>

 <script>
        function workspaceManager() {
            return {
                currentMasterID: <?= $masterID ?>,
                showDiffModal: false,
                diffData: { before: '', after: '', sectionTitle: '' },

                // 🛠️ FIXED: These functions are now INSIDE the return object
                getCardClass(status, lockedBy) {
                    // 1. If Activity is globally locked (Submitted/Graded), grey it out
                    if (<?= json_encode($isLocked) ?>) return 'border-slate-300 bg-slate-50/50';
                    
                    // 2. If Section is locked by SOMEONE ELSE, show red/grey
                    if (lockedBy && lockedBy != this.currentMasterID) return 'border-red-400 bg-red-50/30 grayscale-[0.5]';
                    
                    // 3. Status Colors
                    if (status === 'Completed') return 'border-emerald-500 bg-emerald-50/20';
                    if (status === 'Needs Revision') return 'border-amber-400 bg-amber-50/40'; // Amber for revisions
                    
                    // 4. Default Open State
                    return 'border-slate-100 bg-white hover:border-indigo-300';
                },

                getStatusDot(status) {
                    if (status === 'Completed') return 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]';
                    if (status === 'Needs Revision') return 'bg-amber-500 animate-pulse';
                    if (status === 'In Progress') return 'bg-blue-500';
                    return 'bg-slate-300';
                },

                async viewDiff(historyID, sectionTitle) {
                    try {
                        const response = await fetch(`../../dbRelated/lock_manager.php?action=get_history_diff&history_id=${historyID}`);
                        const result = await response.json();
                        if (result.status === 'success') {
                            this.diffData = {
                                before: result.data.before,
                                after: result.data.after,
                                sectionTitle: sectionTitle
                            };
                            this.showDiffModal = true;
                        }
                    } catch (error) { alert("Could not retrieve Forensic data."); }
                },

                async viewFullSection(sectionID, sectionTitle) {
                    try {
                        const response = await fetch(`../../dbRelated/lock_manager.php?action=get_comments&section_id=${sectionID}`);
                        const result = await response.json();

                        if (result.status === 'success') {
                            this.diffData = {
                                before: '(Final Version)',
                                after: result.data.Content || result.data.Draft_Content || 'No content finalized yet.',
                                sectionTitle: sectionTitle
                            };
                            this.showDiffModal = true;
                        } else {
                            alert("Unable to load preview: " + result.message);
                        }
                    } catch (error) {
                        console.error("Preview Error:", error);
                        alert("Could not connect to the server.");
                    }
                },

                async startEditing(sectionID) {
                    const formData = new FormData();
                    formData.append('action', 'checkout');
                    formData.append('section_id', sectionID);
                    try {
                        const response = await fetch('../../dbRelated/lock_manager.php', { method: 'POST', body: formData });
                        const result = await response.json();
                        if (result.status === 'success') {
                            window.location.href = `editor.php?section_id=${sectionID}&activity_id=<?= $activity_id ?>`;
                        } else { 
                            alert(result.message); 
                        }
                    } catch (error) { alert("Could not connect to the lock manager."); }
                }
            }
        }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>