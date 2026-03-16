<?php
session_start();
require_once '../../dbRelated/operation.php';

$db = new DataManager();
$activity_id = $_GET['activity_id'] ?? null;
$student_id = $_SESSION['user_id'];
$masterID = $db->getMasterID($student_id);

$activity = $db->getActivityDetails($activity_id);
$myGroup = $db->getStudentGroupStatus($activity_id, $masterID);

// 🛠️ FETCH ACCESS STATUS: Check if project is already finalized
$accessStatus = $db->getGroupActivityStatus($activity_id, $myGroup['GroupID']);
$isSubmitted = $accessStatus['is_submitted'] ?? false;

// Access Control: Only the Leader should see the Final Compiler (Active phase)
// OR all members can see it if it's already submitted (Read-only phase)
if (!$myGroup || (!$myGroup['Is_Leader'] && !$isSubmitted)) {
    header("Location: workspace.php?activity_id=$activity_id&error=unauthorized");
    exit();
}

// 1. Fetch the raw data packet (Contains 'info' and 'sections')
$previewData = $db->getPreviewWithMetadata($activity_id, $myGroup['GroupID']);

// 2. Unpack the data safely
$meta = $previewData['info'] ?? [];       // Metadata (Title, Group Name)
$sections = $previewData['sections'] ?? []; // The actual list of report sections
$history = $db->getCommitHistory($activity_id, $myGroup['GroupID']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Final Review | <?= htmlspecialchars($activity['Title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        [x-cloak] { display: none !important; }
        .paper-preview { background: white; min-height: 29.7cm; padding: 2cm; box-shadow: 0 0 50px rgba(0,0,0,0.05); width: 21cm; margin-bottom: 2rem; }
        .comment-bubble { position: absolute; z-index: 100; background: white; border: 1px solid #e2e8f0; border-radius: 1rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 1rem; width: 280px; }
        .ql-container.ql-snow { border: none !important; }
        .ql-editor { padding: 0 !important; overflow: visible !important; font-family: 'Inter', sans-serif; }
        
        @media print { 
            .no-print { display: none !important; } 
            .paper-preview { box-shadow: none; padding: 0; margin: 0; border: none; width: 100%; } 
            body { background: white; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex" x-data="previewManager()">

    <aside class="no-print w-80 bg-white border-r border-slate-200 h-screen sticky top-0 overflow-y-auto p-6 hidden lg:block">
        <h3 class="text-[10px] font-black text-indigo-600 uppercase tracking-[0.2em] mb-6">Forensic Audit Trail</h3>
        <div class="space-y-6">
            <?php if(empty($history)): ?>
                <p class="text-[10px] font-bold text-slate-300 uppercase">No history found</p>
            <?php endif; ?>
            <?php foreach($history as $log): ?>
                <div class="relative pl-6 border-l-2 border-slate-100">
                    <div class="absolute -left-[9px] top-0 w-4 h-4 bg-white border-2 border-indigo-500 rounded-full"></div>
                    <p class="text-[10px] font-black text-slate-800 uppercase"><?= htmlspecialchars($log['Full_Name']) ?></p>
                    <p class="text-[9px] font-bold text-slate-400 mb-1">Updated <?= $log['SectionTitle'] ?></p>
                    <p class="text-[8px] font-medium text-slate-300"><?= date('M d, h:i A', strtotime($log['Timestamp'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <div class="flex-1 flex flex-col items-center">
        <nav class="no-print sticky top-0 w-full bg-white/90 backdrop-blur-md border-b border-slate-200 z-50 p-4 flex justify-between items-center px-12">
            <div class="flex items-center gap-4">
                <a href="workspace.php?activity_id=<?= $activity_id ?>" class="p-2 hover:bg-slate-100 rounded-lg text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="text-xs font-black uppercase italic tracking-tighter">Reviewing: <?= htmlspecialchars($activity['Title']) ?></h2>
            </div>
            
            <div class="flex gap-3">
                <button @click="generatePDF()" class="px-5 py-2.5 bg-emerald-600 text-white rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-emerald-700 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1.0 01.707.293l5.414 5.414a1 1.0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export PDF
                </button>

                <?php if (!$isSubmitted): ?>
                    <button @click="officialSubmit()" 
                            class="px-8 py-2.5 bg-indigo-600 text-white rounded-xl font-black uppercase text-[10px] tracking-widest shadow-lg hover:bg-indigo-700 transition-all">
                        Official Submit
                    </button>
                <?php else: ?>
                    <div class="px-6 py-2.5 bg-emerald-100 text-emerald-600 rounded-xl font-black uppercase text-[10px] tracking-widest flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        Finalized
                    </div>
                <?php endif; ?>
            </div>
        </nav>

        <div x-show="showCommentBox" class="comment-bubble" :style="`top: ${commentPos.top}px; left: ${commentPos.left}px;`" @click.away="showCommentBox = false" x-cloak>
            <p class="text-[9px] font-black text-indigo-600 uppercase mb-2 tracking-widest">Mark as Mistake</p>
            <textarea x-model="commentText" class="w-full border border-slate-100 p-2 text-xs rounded-lg h-20 mb-2" placeholder="Tell the student what to fix..."></textarea>
            <button @click="submitComment()" class="w-full bg-indigo-600 text-white py-2 rounded-lg text-[9px] font-black uppercase tracking-widest">Send to Workspace</button>
        </div>

        <main class="py-12 relative w-full flex justify-center">
            <div class="paper-preview rounded-sm border border-slate-200" id="document-content">
                <header class="text-center mb-16 border-b-2 border-slate-900 pb-10">
                    <h1 class="text-2xl font-black uppercase tracking-tight"><?= htmlspecialchars($activity['Title']) ?></h1>
                    <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest mt-2">Team <?= htmlspecialchars($myGroup['GroupName']) ?> • SNHS Laboratory Department</p>
                </header>

                <div class="report-content">
                    <?php foreach ($sections as $s): ?>
                        <div class="mb-14 relative">
                            <h2 class="uppercase italic tracking-tighter text-indigo-600 border-b border-indigo-50 pb-2 mb-6 flex justify-between items-center">
                                <?= $s['Title'] ?>
                                
                                <?php if($s['CommentCount'] > 0): ?>
                                    <?php if($s['Status'] === 'Completed'): ?>
                                        <span class="ml-2 bg-emerald-100 text-emerald-600 text-[8px] px-2 py-1 rounded-full not-italic font-black border border-emerald-200 uppercase tracking-widest">Edited & Ready</span>
                                    <?php else: ?>
                                        <span class="ml-2 bg-amber-100 text-amber-600 text-[8px] px-2 py-1 rounded-full not-italic font-black border border-amber-200 uppercase tracking-widest">Pending Revision</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h2>
                            <div id="editor-<?= $s['SectionID'] ?>" class="preview-editor-instance" data-section="<?= $s['SectionID'] ?>">
                                <?= $s['Content'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <footer class="mt-20 pt-10 border-t border-slate-200">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Submission Date: <?= date('F j, Y') ?></p>
                </footer>
            </div>
        </main>
    </div>

<script>
        function previewManager() {
            return {
                showCommentBox: false,
                commentText: '',
                commentPos: { top: 0, left: 0 },
                currentSection: null,
                currentSelection: null,
                quillInstances: {},
                
                // DATA: IDs required for actions
                activityId: '<?= $activity_id ?>',
                groupId: '<?= $myGroup['GroupID'] ?>', 

                init() {
                    document.querySelectorAll('.preview-editor-instance').forEach(el => {
                        const sid = el.dataset.section;
                        const q = new Quill(el, { theme: 'snow', readOnly: true, modules: { toolbar: false } });
                        this.quillInstances[sid] = q;

                        q.on('selection-change', (range) => {
                            // Only allow commenting if project is NOT submitted
                            if (range && range.length > 0 && !<?= json_encode($isSubmitted) ?>) {
                                this.currentSection = sid;
                                this.currentSelection = range;
                                const bounds = q.getBounds(range.index, range.length);
                                const elBounds = el.getBoundingClientRect();
                                this.commentPos = { 
                                    top: elBounds.top + window.scrollY + bounds.bottom + 5, 
                                    left: elBounds.left + bounds.left 
                                };
                                this.showCommentBox = true;
                            }
                        });
                    });
                },
// 🛠️ FIXED: Uses lock_manager.php (No new files needed)
                async officialSubmit() {
                    if (!confirm("⚠️ FINAL SUBMISSION WARNING ⚠️\n\nOnce you submit:\n1. The project will be LOCKED.\n2. No members can edit anymore.\n3. The teacher will be notified.\n\nAre you sure you are ready?")) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'submit_final_project'); // Matches the case in PHP
                    formData.append('activity_id', this.activityId);
                    formData.append('group_id', this.groupId);

                    try {
                        
                        const response = await fetch('../../dbRelated/lock_manager.php', { method: 'POST', body: formData });
                        
                        // Check if the response is valid
                        if (!response.ok) {
                            throw new Error(`Server Error: ${response.status}`);
                        }
                        
                        const result = await response.json();

                        if (result.status === 'success') {
                            alert("✅ Submission Successful!\n\nRedirecting to locked workspace...");
                            window.location.href = `workspace.php?activity_id=${this.activityId}`;
                        } else {
                            alert("❌ Error: " + (result.message || "Failed to submit project."));
                        }
                    } catch (error) {
                        console.error(error);
                        alert("Connection error. Check console (F12) for details.");
                    }
                },

                generatePDF() {
                    const element = document.getElementById('document-content');
                    const opt = {
                        margin: 0.5,
                        filename: '<?= htmlspecialchars($meta['Title'] ?? 'Report') ?>_Final_Report.pdf',
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true },
                        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                    };
                    
                    const btn = event.currentTarget;
                    const originalText = btn.innerHTML;
                    btn.innerText = 'Converting...';
                    
                    html2pdf().set(opt).from(element).save().then(() => {
                        btn.innerHTML = originalText;
                    });
                },

                async submitComment() {
                    if(!this.commentText.trim()) return;
                    const formData = new FormData();
                    formData.append('action', 'add_comment');
                    formData.append('section_id', this.currentSection);
                    formData.append('index', this.currentSelection.index);
                    formData.append('length', this.currentSelection.length);
                    formData.append('comment_text', this.commentText);

                    // Note: Ensure lock_manager.php has the 'add_comment' case handled!
                    const r = await fetch('../../dbRelated/lock_manager.php', { method: 'POST', body: formData });
                    const res = await r.json();
                    if(res.status === 'success') {
                        this.quillInstances[this.currentSection].formatText(this.currentSelection.index, this.currentSelection.length, 'background', '#fef3c7');
                        this.showCommentBox = false;
                        this.commentText = '';
                        location.reload();
                    }
                }
            }
        }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>