<?php
session_start();
require_once '../../dbRelated/operation.php';

$db = new DataManager();
$sectionID = $_GET['section_id'] ?? null;
$activityID = $_GET['activity_id'] ?? null;
$masterID = $db->getMasterID($_SESSION['user_id']);

// 1. Security: Verify the user actually has the lock
$check = $db->db->prepare("SELECT rs.*, la.Title as ActivityTitle 
                           FROM report_sections rs 
                           JOIN lab_activities la ON rs.ActivityID = la.ActivityID
                           WHERE rs.SectionID = ? AND rs.Locked_By = ?");
$check->execute([$sectionID, $masterID]);
$section = $check->fetch(PDO::FETCH_ASSOC);

if (!$section) {
    header("Location: workspace.php?activity_id=$activityID&error=lock_lost");
    exit();
}

// 2. Fetch Active Comments for this section
$comments = $db->getSectionComments($sectionID);

// 3. Fetch Group Status
$myGroup = $db->getStudentGroupStatus($activityID, $masterID);
$isLeader = $myGroup && $myGroup['Is_Leader'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Editing <?= htmlspecialchars($section['Title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>window.Quill = Quill;</script>
    <script src="https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.js"></script>

    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .editor-paper { background: white; border-radius: 2.5rem; border: 1px solid #f1f5f9; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.03); overflow: hidden; display: flex; flex-direction: column; min-height: 80vh; }
        .editor-status-bar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
        .ql-toolbar.ql-snow { border: none !important; border-bottom: 1px solid #f1f5f9 !important; background: white; padding: 1rem 1.5rem !important; position: sticky; top: 0; z-index: 30; }
        .ql-container.ql-snow { border: none !important; flex: 1; display: flex; flex-direction: column; }
        .ql-editor { flex: 1; padding: 4rem !important; font-size: 1.1rem; line-height: 1.8; color: #334155; }
        .ql-editor img { cursor: pointer; transition: border 0.2s; border: 2px solid transparent; }
        .ql-editor img:hover { border: 2px solid #4f46e5; }
        
        /* Comment Sidebar Animation */
        .comment-panel { transition: transform 0.3s ease-in-out; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen" x-data="editorManager()">

    <nav class="fixed top-0 inset-x-0 bg-white/80 backdrop-blur-md border-b border-slate-100 z-50 p-4">
        <div class="max-w-[1800px] mx-auto flex justify-between items-center">
            <div class="flex items-center gap-6">
                <button @click="saveAndExit()" class="p-3 hover:bg-slate-50 rounded-2xl text-slate-400 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div>
                    <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest leading-none mb-1"><?= htmlspecialchars($section['ActivityTitle']) ?></p>
                    <h1 class="text-xl font-black text-slate-800 uppercase italic">Editing: <?= htmlspecialchars($section['Title']) ?></h1>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="hidden md:flex items-center gap-2 px-4 py-2 bg-slate-100 rounded-xl border border-slate-200" 
                     :class="timeRemaining < 300 ? 'bg-red-50 border-red-100 text-red-600' : 'text-slate-500'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-[10px] font-black uppercase tracking-widest">Lock Expires: <span x-text="formatTime(timeRemaining)">30:00</span></span>
                </div>

                <div class="flex items-center gap-2 px-4 py-2 bg-slate-100 rounded-xl">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" :class="{'bg-red-500': statusMsg === 'Error'}"></span>
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest" x-text="statusMsg"></span>
                </div>

                <button @click="finalize()" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-blue-600 shadow-lg transition-all">
                    Finish & Submit
                </button>
            </div>
        </div>
    </nav>

    <main class="pt-28 pb-10 px-6 max-w-[1800px] mx-auto flex gap-6 animate-reveal">
        
        <div class="flex-1 transition-all duration-300">
            <div class="editor-paper">
                <div id="quill-editor"></div>
            </div>
        </div>

        <?php if (!empty($comments)): ?>
        <aside class="w-80 shrink-0 space-y-4">
            <div class="bg-amber-50 border border-amber-100 rounded-[2rem] p-6 sticky top-32">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-amber-100 text-amber-600 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-800 uppercase italic">Revisions</h3>
                        <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest"><?= count($comments) ?> Items Pending</p>
                    </div>
                </div>

                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    <?php foreach ($comments as $c): ?>
                        <div class="bg-white p-4 rounded-xl border border-amber-100 shadow-sm relative group">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-[10px] font-black text-slate-400 uppercase"><?= htmlspecialchars($c['Full_Name']) ?></span>
                                <span class="text-[9px] font-bold text-slate-300"><?= date('H:i', strtotime($c['CreatedAt'])) ?></span>
                            </div>
                            <p class="text-xs text-slate-700 font-medium leading-relaxed italic">
                                "<?= nl2br(htmlspecialchars($c['Comment_Text'])) ?>"
                            </p>
                            <button @click="resolveComment(<?= $c['CommentID'] ?>)" 
                                    class="mt-3 w-full py-2 bg-slate-50 hover:bg-emerald-50 text-slate-400 hover:text-emerald-600 rounded-lg text-[9px] font-black uppercase tracking-widest transition-colors flex items-center justify-center gap-2">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Mark Resolved
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
        <?php endif; ?>

    </main>

    <script>
        function editorManager() {
            return {
                sectionID: <?= $sectionID ?>,
                wordCount: 0,
                quill: null,
                content: `<?= addslashes($section['Draft_Content'] ?? $section['Content'] ?? '') ?>`,
                statusMsg: 'Initializing...',
                timeRemaining: 1800, // 30 Minutes in seconds

                init() {
                    this.$nextTick(() => {
                        try {
                            Quill.register('modules/imageResize', ImageResize.default);
                            this.quill = new Quill('#quill-editor', {
                                theme: 'snow',
                                placeholder: 'Start typing your report...',
                                modules: {
                                    imageResize: { modules: [ 'Resize', 'DisplaySize', 'Toolbar' ] },
                                    toolbar: [
                                        [{'header': [1, 2, 3, false]}],
                                        ['bold', 'italic', 'underline', 'strike'],
                                        [{'list': 'ordered'}, {'list': 'bullet'}],
                                        [{'align': []}],
                                        ['link', 'image', 'code-block'],
                                        ['clean']
                                    ]
                                }
                            });

                            if (this.content) {
                                this.quill.root.innerHTML = this.content;
                            }

                            this.statusMsg = 'Connected';
                            
                            // Auto-save every 60s
                            setInterval(() => this.sendHeartbeat(), 60000);

                            // Countdown Timer (Every 1s)
                            setInterval(() => {
                                if (this.timeRemaining > 0) {
                                    this.timeRemaining--;
                                } else {
                                    this.statusMsg = 'Expired';
                                }
                            }, 1000);

                        } catch (e) {
                            console.error("Quill Init Error:", e);
                            this.statusMsg = 'Error';
                        }
                    });
                },

                formatTime(seconds) {
                    const m = Math.floor(seconds / 60);
                    const s = seconds % 60;
                    return `${m}:${s < 10 ? '0' : ''}${s}`;
                },

                async sendHeartbeat() {
                    this.statusMsg = 'Syncing...';
                    const formData = new FormData();
                    formData.append('action', 'heartbeat');
                    formData.append('section_id', this.sectionID);
                    formData.append('content', this.quill.root.innerHTML);
                    
                    try {
                        await fetch('../../dbRelated/lock_manager.php', { method: 'POST', body: formData });
                        this.statusMsg = 'Saved';
                        this.timeRemaining = 1800; // Reset timer on successful save
                    } catch (e) {
                        this.statusMsg = 'Offline'; // Don't reset timer if offline
                    }
                },

                async finalize() {
                    if(!confirm("Are you sure you want to finish editing? This will save your changes and release the lock.")) return;
                    
                    const formData = new FormData();
                    formData.append('action', 'finalize');
                    formData.append('section_id', this.sectionID);
                    formData.append('content', this.quill.root.innerHTML);
                    
                    const r = await fetch('../../dbRelated/lock_manager.php', { method: 'POST', body: formData });
                    const d = await r.json();
                    
                    if(d.status === 'success') {
                        window.location.href = `workspace.php?activity_id=<?= $activityID ?>`;
                    } else {
                        alert("Error: " + d.message);
                    }
                },

                async resolveComment(id) {
                    if(!confirm("Mark this comment as resolved? It will be hidden from the list.")) return;
                    
                    const formData = new FormData();
                    formData.append('action', 'resolve_comment');
                    formData.append('comment_id', id);
                    formData.append('section_id', this.sectionID);

                    const r = await fetch('../../dbRelated/lock_manager.php', { method: 'POST', body: formData });
                    const d = await r.json();

                    if(d.status === 'success') {
                        window.location.reload(); 
                    } else {
                        alert("Could not resolve comment.");
                    }
                },

                saveAndExit() {
                    this.sendHeartbeat().then(() => {
                        window.location.href = `workspace.php?activity_id=<?= $activityID ?>`;
                    });
                }
            }
        }
    </script>
    <?php include '../../includes/layout_footer.php'; ?>
</body>
</html>