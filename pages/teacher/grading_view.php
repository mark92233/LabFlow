<?php
session_start();
require_once '../../dbRelated/operation.php';
$db = new DataManager();

// 1. Security Gatekeeper
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header("Location: ../../index.php"); 
    exit();
}

// Get Parameters
$activityID = $_GET['activity_id'] ?? null;
$submissionID = $_GET['submission_id'] ?? null;
$groupID = $_GET['group_id'] ?? null; 

if (!$activityID) {
    die("Invalid Parameters: Activity ID missing.");
}

// 2. Resolve Context (Grading vs. Progress Check)
if ($submissionID) {
    // CASE A: Grading Mode (Accessed via "Grade Now")
    $subDetails = $db->db->prepare("SELECT * FROM lab_submissions WHERE SubmissionID = ?");
    $subDetails->execute([$submissionID]);
    $submission = $subDetails->fetch(PDO::FETCH_ASSOC);

    if (!$submission) die("Submission record not found.");
    $groupID = $submission['GroupID']; 

} elseif ($groupID) {
    // CASE B: Progress Mode (Accessed via "See Progress")
    $subCheck = $db->db->prepare("SELECT * FROM lab_submissions WHERE ActivityID = ? AND GroupID = ?");
    $subCheck->execute([$activityID, $groupID]);
    $submission = $subCheck->fetch(PDO::FETCH_ASSOC);

    // Create Virtual Object if no submission yet
    if (!$submission) {
        $submission = [
            'SubmissionID' => null, 
            'Status' => 'In Progress',
            'Grade' => '',
            'Feedback' => '',
            'GroupID' => $groupID
        ];
    }
} else {
    die("Invalid Parameters: Missing Target (Submission ID or Group ID).");
}

// 3. Fetch Content & Forensics
$report = $db->getPreviewWithMetadata($activityID, $groupID);
$forensics = $db->getContributionAnalysis($activityID, $groupID);

// 4. Setup Variables for UI
$currentGrade = $submission['Grade'] ?? '';
$currentFeedback = $submission['Feedback'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grading Console | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        
        .ql-container.ql-snow { border: none !important; }
        .ql-editor { padding: 0 !important; font-family: 'Inter', sans-serif; font-size: 1.05rem; line-height: 1.8; color: #334155; }
    </style>
</head>
<body class="bg-[#f8fafc] h-screen overflow-hidden flex flex-col font-sans" x-data="gradingConsole()">

    <header class="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6 shrink-0 z-30 shadow-sm relative">
        <div class="flex items-center gap-6">
            <a href="activity_hub.php?activity_id=<?= $activityID ?>" class="flex items-center gap-2 text-slate-400 hover:text-slate-600 font-bold text-xs uppercase transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Dashboard
            </a>
            <div class="h-6 w-px bg-slate-200"></div>
            <div>
                <h1 class="font-black text-slate-800 italic text-lg tracking-tight">Grading Console</h1>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    Target: <span class="text-indigo-600"><?= htmlspecialchars($report['info']['GroupName'] ?? 'Student') ?></span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="flex flex-col items-end">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Estimated Length</span>
                <span class="text-sm font-black text-slate-700"><?= number_format($forensics['total_chars'] / 5) ?> Words</span>
            </div>
            
            <div class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest border"
                 :class="{
                    'bg-emerald-100 text-emerald-600 border-emerald-200': status === 'Graded',
                    'bg-amber-100 text-amber-600 border-amber-200': status === 'Returned',
                    'bg-blue-50 text-blue-500 border-blue-100': status === 'Submitted',
                    'bg-slate-100 text-slate-500 border-slate-200': status === 'In Progress'
                 }">
                <span x-text="status"></span>
            </div>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
        
        <main class="flex-1 overflow-y-auto p-8 custom-scrollbar bg-slate-100/50">
            <div class="max-w-4xl mx-auto bg-white min-h-[1000px] shadow-sm border border-slate-200 p-12 rounded-[2rem]">
                
                <div class="text-center mb-16 pb-16 border-b-2 border-slate-50 relative">
                    <span class="absolute top-0 left-1/2 -translate-x-1/2 -mt-6 bg-slate-100 px-4 py-1 rounded-full text-[10px] font-bold text-slate-400 uppercase tracking-widest">Final Report Preview</span>
                    <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-6 tracking-tight leading-tight">
                        <?= htmlspecialchars($report['info']['Title']) ?>
                    </h1>
                    <div class="inline-flex items-center gap-2 bg-slate-50 px-6 py-2 rounded-full border border-slate-100">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <p class="text-sm font-bold text-slate-600"><?= htmlspecialchars($report['info']['GroupName']) ?></p>
                    </div>
                </div>

                <?php foreach ($report['sections'] as $sec): ?>
                    <div class="mb-16 last:mb-0">
                        <div class="flex items-center gap-4 mb-6">
                            <h2 class="text-2xl font-black text-slate-800 tracking-tight"><?= htmlspecialchars($sec['Title']) ?></h2>
                            <div class="h-px flex-1 bg-slate-100"></div>
                        </div>
                        <div class="q-viewer text-slate-600" data-content="<?= htmlspecialchars($sec['Content']) ?>"></div>
                    </div>
                <?php endforeach; ?>
                
            </div>
            <div class="h-20"></div>
        </main>

        <aside class="w-[420px] bg-white border-l border-slate-200 flex flex-col shadow-[rgba(0,0,15,0.05)_0px_0px_40px] z-20">
            
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Contribution Analysis
                    </h3>
                </div>
                
                <div class="space-y-4">
                    <?php if(empty($forensics['stats'])): ?>
                        <div class="bg-white p-3 rounded-lg border border-slate-200 text-center">
                            <p class="text-xs text-slate-400 font-bold italic">No commit history found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($forensics['stats'] as $stat): ?>
                            <div class="group relative">
                                <div class="flex justify-between text-xs font-bold mb-1.5">
                                    <span class="text-slate-700"><?= htmlspecialchars($stat['name']) ?></span>
                                    <span class="<?= $stat['percent'] < 10 ? 'text-red-500' : 'text-emerald-600' ?>">
                                        <?= $stat['percent'] ?>%
                                    </span>
                                </div>
                                <div class="h-2 w-full bg-slate-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500 <?= $stat['percent'] < 10 ? 'bg-red-400' : 'bg-emerald-500' ?>" 
                                         style="width: <?= $stat['percent'] ?>%"></div>
                                </div>
                                
                                <?php if($stat['suspicious_pastes'] > 0): ?>
                                    <div class="mt-1 flex items-center gap-1 text-[10px] text-red-500 font-bold animate-pulse">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span><?= $stat['suspicious_pastes'] ?> Large Pastes Detected</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-200/60">
                    <label class="flex items-center gap-3 cursor-pointer group select-none">
                        <div class="relative">
                            <input type="checkbox" x-model="autoWeighted" class="peer sr-only">
                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                        </div>
                        <span class="text-xs font-bold text-slate-500 group-hover:text-indigo-600 transition-colors">Auto-adjust grades by effort?</span>
                    </label>
                </div>
            </div>

            <div class="flex-1 p-6 overflow-y-auto custom-scrollbar">
                
                <div class="mb-8 relative group">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 group-focus-within:text-indigo-500 transition-colors">Base Group Grade</label>
                    <div class="relative">
                        <input type="number" x-model="baseGrade" min="0" max="100" 
                               class="w-full text-5xl font-black text-slate-800 bg-transparent border-b-2 border-slate-200 focus:border-indigo-600 outline-none pb-2 placeholder-slate-200 transition-colors"
                               placeholder="00">
                        <span class="absolute bottom-4 right-0 text-slate-300 font-bold text-lg">/100</span>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Teacher Feedback</label>
                    <textarea x-model="feedback" rows="4" 
                              class="w-full bg-slate-50 p-4 rounded-2xl text-sm font-medium border border-transparent focus:bg-white focus:border-indigo-200 focus:ring-4 focus:ring-indigo-50 outline-none resize-none transition-all placeholder-slate-300 leading-relaxed" 
                              placeholder="Write helpful feedback for the group here..."></textarea>
                </div>

                <div x-show="autoWeighted" x-transition.opacity.duration.300ms 
                     class="bg-indigo-50 p-5 rounded-2xl border border-indigo-100 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-indigo-400"></div>
                    <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                        Adjust Individual Scores
                    </h4>
                    <div class="space-y-3">
                        <template x-for="stu in students" :key="stu.user_id">
                            <div class="flex justify-between items-center bg-white p-3 rounded-xl shadow-sm border border-indigo-50">
                                <div>
                                    <p class="text-xs font-bold text-slate-700 truncate max-w-[150px]" x-text="stu.name"></p>
                                    <p class="text-[9px] font-mono text-slate-400" x-text="stu.percent + '% Contrib'"></p>
                                </div>
                                <div>
                                    <input type="number" x-model="stu.manual_grade" 
                                           class="w-16 bg-slate-50 border border-slate-200 rounded-lg text-center font-black text-sm text-indigo-600 focus:border-indigo-500 outline-none p-1.5"
                                           :placeholder="calculateGrade(stu.percent)">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-slate-200 bg-white space-y-3 z-30">
                <?php if (!empty($submission['SubmissionID'])): ?>
                    <button @click="submitGrade('Graded')" 
                            :disabled="isSubmitting"
                            class="w-full py-4 bg-[#0f172a] hover:bg-indigo-600 text-white rounded-xl font-black uppercase text-xs tracking-widest shadow-xl shadow-slate-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2">
                        <span x-show="!isSubmitting">Post Final Grades</span>
                        <span x-show="isSubmitting" class="animate-pulse">Saving...</span>
                    </button>
                    
                    <button @click="submitGrade('Returned')" 
                            :disabled="isSubmitting"
                            class="w-full py-3 bg-white border-2 border-amber-100 text-amber-500 hover:bg-amber-50 rounded-xl font-bold uppercase text-xs tracking-widest transition-all">
                        Return for Revision
                    </button>
                <?php else: ?>
                    <div class="text-center p-4 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Monitoring Mode</p>
                        <p class="text-xs font-bold text-slate-500">Student has not submitted yet.</p>
                    </div>
                    <button disabled class="w-full py-3 bg-slate-100 text-slate-400 rounded-xl font-black uppercase text-xs tracking-widest cursor-not-allowed">
                        Actions Unavailable
                    </button>
                <?php endif; ?>
            </div>
        </aside>

    </div>

    <script>
        const forensicData = <?= json_encode($forensics['stats']) ?>;
        const initialStatus = '<?= $submission['Status'] ?>';
        const initialGrade = '<?= $currentGrade ?>';
        const initialFeedback = <?= json_encode($currentFeedback) ?>;

        function gradingConsole() {
            return {
                baseGrade: initialGrade,
                feedback: initialFeedback,
                status: initialStatus,
                autoWeighted: false,
                // Initialize students with manual_grade property
                students: forensicData.map(s => ({ ...s, manual_grade: '' })),
                isSubmitting: false,

                calculateGrade(percent) {
                    if (!this.baseGrade) return '0';
                    const numStudents = this.students.length;
                    if (numStudents === 0) return this.baseGrade;
                    const fairShare = 100 / numStudents;
                    const ratio = percent / fairShare;
                    if (ratio >= 0.8) return this.baseGrade; 
                    let score = Math.floor(this.baseGrade * ratio);
                    if (score < 50 && percent > 5) return 50; 
                    return score;
                },

                async submitGrade(actionType) {
                    if (!this.baseGrade && actionType === 'Graded') {
                        alert("Please enter a base grade before posting.");
                        return;
                    }

                    if (actionType === 'Returned') {
                        if (!this.feedback.trim()) {
                            alert("Please provide feedback explaining why revision is needed.");
                            return;
                        }
                        if (!confirm("⚠️ This will UNLOCK the workspace for the students.\nThey will be able to edit the report again.\n\nProceed?")) return;
                    } else {
                        if (!confirm("Confirm Grading?\n\nThis will finalize the activity record.")) return;
                    }

                    this.isSubmitting = true;
                    const fd = new FormData();
                    fd.append('action', 'submit_grade');
                    fd.append('submission_id', '<?= $submission['SubmissionID'] ?? '' ?>');
                    fd.append('grade', this.baseGrade);
                    fd.append('feedback', this.feedback);
                    fd.append('status', actionType);
                    
                    // Priority: Manual Input > Calculated > Default
                    if (this.autoWeighted && actionType === 'Graded') {
                        const individualGrades = this.students.map(s => ({
                            user_id: s.user_id,
                            grade: (s.manual_grade !== '' && s.manual_grade !== null) 
                                   ? s.manual_grade 
                                   : this.calculateGrade(s.percent)
                        }));
                        fd.append('individual_grades', JSON.stringify(individualGrades));
                    }

                    try {
                        const response = await fetch('../../dbRelated/lock_manager.php', { method: 'POST', body: fd });
                        const result = await response.json();

                        if (result.status === 'success') {
                            alert("✅ " + (actionType === 'Graded' ? "Grades Posted Successfully!" : "Project Returned for Revision."));
                            // FIXED: Redirect to activity_hub.php
                            window.location.href = "activity_hub.php?activity_id=<?= $activityID ?>";
                        } else {
                            alert("❌ Error: " + result.message);
                            this.isSubmitting = false;
                        }
                    } catch (error) {
                        console.error(error);
                        alert("Connection error. Check console.");
                        this.isSubmitting = false;
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.q-viewer').forEach(el => {
                const q = new Quill(el, { 
                    theme: 'snow', 
                    readOnly: true, 
                    modules: { toolbar: false } 
                });
                const content = el.dataset.content;
                if (content) {
                    q.clipboard.dangerouslyPasteHTML(content);
                }
            });
        });
    </script>
</body>
</html>