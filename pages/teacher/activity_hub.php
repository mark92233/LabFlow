<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Teacher') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$activity_id = $_GET['activity_id'] ?? null;
$url_class_id = $_GET['class_id'] ?? null;

// 2. Fetch Basic Activity Data
$activity = $db->getActivityDetails($activity_id, $url_class_id);
if (!$activity) { die("Activity not found."); }

// 3. Context & Type Logic
$current_class_id = $url_class_id ? $url_class_id : $activity['ClassID'];

// ROBUST CHECK: Check 'Type' (alias) OR 'type' (raw column), and handle case sensitivity
$typeVal = $activity['Type'] ?? $activity['type'] ?? 'Individual';
$isGroupActivity = (strcasecmp($typeVal, 'Group') === 0); 

// 4. Fetch the correct list (The Switch)
if ($isGroupActivity) {
    // Fetches Groups (Alpha Team, Beta Team)
    $listItems = $db->getGroupsWithSubmissions($activity_id, $current_class_id); 
} else {
    // Fetches Individual Students (Jomar Jun, Kim Solis)
    $listItems = $db->getEnrollmentWithSubmissions($activity_id, $current_class_id);
}

// Helper for date comparison
$deadline = $activity['Deadline'];
?>

<?php include 'submission_list.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review: <?= $activity['Title'] ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 flex flex-col lg:flex-row gap-8">
                <div class="flex-1">
                    <header class="mb-8">
                        <a href="class_activities.php?class_id=<?= $activity['ClassID'] ?>" class="group inline-flex items-center text-[10px] font-black text-blue-600 uppercase tracking-widest mb-4">
                            <svg class="w-4 h-4 mr-2 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            Back to Class
                        </a>
                        <div class="flex justify-between items-start">
                            <h2 class="text-4xl font-black text-slate-900 uppercase italic tracking-tighter">
                                <span class="block text-xs font-medium text-blue-500 not-italic tracking-normal mb-1">
                                    <?= $isGroupActivity ? 'Group Activity' : 'Individual Activity' ?>
                                </span>
                                <?= htmlspecialchars($activity['Title']) ?>
                            </h2>
                            <div class="text-right">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Deadline</p>
                                <p class="text-xs font-bold text-slate-700"><?= date("M d, Y h:i A", strtotime($deadline)) ?></p>
                            </div>
                        </div>
                    </header>
                    
                    <div class="glass-card p-10 border-t-8 border-blue-600">
                        <h4 class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-4 italic">Lab Description & Instructions</h4>
                        <p class="text-slate-600 text-sm leading-relaxed mb-8"><?= nl2br(htmlspecialchars($activity['Description'])) ?></p>
                        
                        <?php if($activity['Manual_URL']): ?>
                            <div class="flex items-center gap-3">
                                <button onclick="toggleManualPreview()" class="inline-flex items-center bg-slate-200 text-slate-700 px-6 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-300 transition-all">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Preview
                                </button>
                                <a href="../../<?= $activity['Manual_URL'] ?>" target="_blank" class="inline-flex items-center bg-slate-100 text-slate-700 px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all shadow-sm">
                                    Download Reference
                                </a>
                            </div>
                            <div id="manual_preview_container" class="hidden mt-6 border-t border-slate-200 pt-6 animate-reveal">
                                <iframe src="../../<?= $activity['Manual_URL'] ?>" class="w-full h-[600px] rounded-2xl border border-slate-200 bg-white"></iframe>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="w-full lg:w-96">
                    <div class="glass-card p-6 border-t-8 border-slate-900 shadow-xl sticky top-8">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="font-black text-slate-800 uppercase italic text-xs tracking-widest">
                                <?= $isGroupActivity ? 'Teams' : 'Enrollment' ?>
                            </h3>
                            <span class="bg-slate-100 text-slate-500 text-[9px] font-black px-2 py-1 rounded-md">
                                <?= count($listItems) ?> <?= $isGroupActivity ? 'Groups' : 'Students' ?>
                            </span>
                        </div>

                        <div class="space-y-3">
                            <?php foreach ($listItems as $item): 
                                $status = $item['Status'] ?? 'Unsubmitted';
                                $isGraded = ($status === 'Graded');
                                $isSubmitted = ($status === 'Submitted');
                                
                                // Late Calculation
                                $isLate = false;
                                if (($isSubmitted || $isGraded) && isset($item['SubmissionDate'])) {
                                    $submitTime = strtotime($item['SubmissionDate']);
                                    $deadlineTime = strtotime($deadline);
                                    if ($submitTime > $deadlineTime) { $isLate = true; }
                                }
                                $item['isLate'] = $isLate;
                            ?>

                                <?php if ($isGroupActivity): ?>
                                    <div class="w-full p-4 rounded-2xl border transition-all bg-white border-slate-100 relative group">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <h4 class="text-sm font-black text-slate-800 uppercase italic"><?= htmlspecialchars($item['GroupName']) ?></h4>
                                                <p class="text-[9px] font-bold uppercase text-slate-400 mt-1"><?= count($item['Members'] ?? []) ?> Members</p>
                                            </div>
                                            <?php if($isGraded): ?>
                                                <span class="text-[10px] font-black bg-blue-600 text-white px-2 py-1 rounded">Avg: <?= $item['Grade'] ?></span>
                                            <?php elseif($isSubmitted): ?>
                                                <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex gap-2 mt-2">
                                           <button onclick='openRoster(<?= htmlspecialchars(json_encode($item['Members'] ?? []), ENT_QUOTES, 'UTF-8') ?>, "<?= htmlspecialchars($item['GroupName'], ENT_QUOTES) ?>")' 
    class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 py-2 rounded-lg text-[9px] font-black uppercase tracking-wider transition-colors">
    Roster
</button>
                                            <a href="grading_view.php?group_id=<?= $item['GroupID'] ?>&activity_id=<?= $activity_id ?>&mode=progress" 
   class="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 py-2 rounded-lg text-[9px] font-black uppercase tracking-wider transition-colors text-center inline-block">
   Grade
</a>
                                        </div>
                                    </div>

                                <?php else: ?>
                                    <button onclick='openGrader(<?= json_encode($item) ?>)' 
                                            class="w-full text-left p-4 rounded-2xl border transition-all flex items-center justify-between group 
                                            <?= $isGraded ? 'bg-blue-50 border-blue-100' : ($isSubmitted ? 'bg-emerald-50 border-emerald-100' : 'bg-white border-slate-100 opacity-60 hover:opacity-100') ?>">
                                            
                                        <div class="truncate mr-4 flex-1">
                                            <div class="flex items-center gap-2">
                                                <p class="text-[10px] font-black text-slate-800 uppercase italic truncate group-hover:text-blue-600"><?= htmlspecialchars($item['Full_Name']) ?></p>
                                                <?php if($isLate): ?>
                                                    <span class="text-[8px] font-black text-red-500 bg-red-100 px-1.5 py-0.5 rounded">LATE</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-[8px] font-bold uppercase <?= $isSubmitted || $isGraded ? 'text-blue-500' : 'text-slate-300' ?>">
                                                <?= $status ?>
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <?php if($isGraded): ?>
                                                <span class="text-[10px] font-black bg-blue-600 text-white px-3 py-1 rounded-lg">
                                                    <?= $item['Grade'] ?>
                                                </span>
                                            <?php elseif($isSubmitted): ?>
                                                <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                                            <?php endif; ?>
                                        </div>
                                    </button>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>
            </main>
        </div>
    </div>

    <div id="graderModal" class="fixed inset-0 bg-[#0f172a]/90 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6">
        <div class="bg-white w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-[2.5rem] p-10 relative animate-reveal active shadow-2xl">
            <button onclick="closeGrader()" class="absolute top-8 right-8 text-slate-300 hover:text-slate-900 text-2xl transition-colors">&times;</button>
            
            <div class="flex items-center gap-3 mb-1">
                <h3 id="m_name" class="text-3xl font-black text-slate-900 uppercase italic">Student Name</h3>
                <span id="m_late_badge" class="hidden text-[10px] font-black text-white bg-red-500 px-2 py-1 rounded-md uppercase tracking-wider">Late Submission</span>
            </div>
            <p class="text-[10px] text-blue-600 font-black uppercase tracking-widest mb-8">Performance Assessment</p>
            
            <form action="grade_handler.php" method="POST" class="space-y-6">
                <input type="hidden" name="submission_id" id="m_sub_id">
                <input type="hidden" name="activity_id" value="<?= $activity_id ?>">
                
                <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Evidence / Attachment</p>
                            <p id="m_file_name" class="text-xs font-bold text-slate-700 italic">Submission_File.pdf</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="toggleStudentPreview()" class="bg-slate-200 text-slate-700 font-black px-4 py-3 rounded-xl text-[10px] uppercase hover:bg-slate-300 transition-all">Preview</button>
                            <a id="m_file" href="#" target="_blank" class="bg-white border border-slate-200 text-blue-600 font-black px-6 py-3 rounded-xl text-[10px] uppercase hover:bg-blue-600 hover:text-white transition-all shadow-sm">Download</a>
                        </div>
                    </div>
                    <div id="student_preview_container" class="hidden border-t border-slate-200 pt-4 mt-4">
                        <iframe id="student_preview_frame" class="w-full h-96 rounded-xl border border-slate-200 bg-white" src=""></iframe>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Numerical Grade (0-100)</label>
                        <input type="number" name="grade" id="m_grade" min="0" max="100" required class="w-full bg-slate-50 p-5 rounded-2xl border-2 border-transparent focus:border-blue-600 transition-all font-black text-3xl text-blue-600 outline-none">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Feedback</label>
                        <textarea name="feedback" id="m_feedback" class="w-full bg-slate-50 p-5 rounded-2xl border-2 border-transparent focus:border-blue-600 transition-all text-xs font-medium outline-none" rows="4" placeholder="How did they perform?"></textarea>
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#0f172a] text-white py-6 rounded-2xl font-black uppercase text-[10px] tracking-[0.2em] hover:bg-blue-600 transition-all shadow-xl active:scale-95">Save Grade and Notify</button>
            </form>
        </div>
    </div>

    <div id="rosterModal" class="fixed inset-0 bg-[#0f172a]/90 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] p-8 relative shadow-2xl animate-reveal">
            <button onclick="closeRoster()" class="absolute top-6 right-6 text-slate-300 hover:text-slate-900 text-xl transition-colors">&times;</button>
            
            <h3 id="r_group_name" class="text-2xl font-black text-slate-900 uppercase italic mb-1">Group Name</h3>
            <p class="text-[10px] text-blue-600 font-black uppercase tracking-widest mb-6">Team Roster</p>

            <div id="r_members_list" class="space-y-3 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                </div>
            
            <div class="mt-6 pt-6 border-t border-slate-100">
                <button onclick="closeRoster()" class="w-full bg-slate-100 text-slate-600 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Close</button>
            </div>
        </div>
    </div>
<script>
        // Existing Toggles
        function toggleManualPreview() { document.getElementById('manual_preview_container').classList.toggle('hidden'); }
        function toggleStudentPreview() { document.getElementById('student_preview_container').classList.toggle('hidden'); }

        // Grader Logic (Unchanged)
        function openGrader(stu) {
            if(!stu.SubmissionID) { alert(stu.Full_Name + " has not submitted this activity yet."); return; }
            document.getElementById('student_preview_container').classList.add('hidden');
            document.getElementById('student_preview_frame').src = "";
            document.getElementById('m_name').innerText = stu.Full_Name;
            document.getElementById('m_sub_id').value = stu.SubmissionID;
            document.getElementById('m_grade').value = stu.Grade || '';
            document.getElementById('m_feedback').value = stu.Feedback || '';
            
            const lateBadge = document.getElementById('m_late_badge');
            stu.isLate ? lateBadge.classList.remove('hidden') : lateBadge.classList.add('hidden');

            const fileUrl = "../../" + stu.Report_URL;
            document.getElementById('m_file').href = fileUrl;
            document.getElementById('student_preview_frame').src = fileUrl;
            document.getElementById('m_file_name').innerText = stu.Report_URL.split('/').pop();
            document.getElementById('graderModal').classList.remove('hidden');
        }

        function closeGrader() { 
            document.getElementById('graderModal').classList.add('hidden');
            document.getElementById('student_preview_frame').src = "";
        }
// ==========================================
        // 🛠️ FIXED: DATA-TYPE AWARE ROSTER
        // ==========================================
        function openRoster(members, groupName) {
            console.log("Roster Data:", members);

            document.getElementById('r_group_name').innerText = groupName;
            const container = document.getElementById('r_members_list');
            container.innerHTML = ''; 

            if (!members || members.length === 0) {
                container.innerHTML = '<p class="text-xs text-slate-400 italic">No members found.</p>';
            } else {
                members.forEach(member => {
                    let name = "Unknown";
                    let isLeader = false;

                    // 🔍 CHECK 1: Is it just a simple Text String? (e.g., "Mark Ando")
                    if (typeof member === 'string') {
                        name = member;
                        isLeader = false; // Strings usually don't carry role info
                    } 
                    // 🔍 CHECK 2: Is it an Object? (e.g., {name: "Mark", role: 1})
                    else if (typeof member === 'object' && member !== null) {
                        // Try every possible key
                        name = member.name || member.Full_Name || member.full_name || member[0] || "Unknown";
                        
                        // Check for Leader Role
                        if (member.role == 1 || member.Role == 1 || member.is_leader == 1 || member.Is_Leader == 1) isLeader = true;
                        if (member.role === 'Leader' || member.Role === 'Leader') isLeader = true;
                    }

                    // Render HTML
                    const div = document.createElement('div');
                    div.className = `flex items-center gap-3 p-3 rounded-xl border transition-all ${isLeader ? 'bg-amber-50 border-amber-200 shadow-sm' : 'bg-slate-50 border-slate-100'}`;
                    
                    div.innerHTML = `
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg shadow-sm shrink-0 
                            ${isLeader ? 'bg-amber-400 text-white ring-2 ring-amber-200' : 'bg-white text-slate-300'}">
                            ${isLeader ? '👑' : '👤'}
                        </div>
                        <div>
                            <p class="text-xs font-black ${isLeader ? 'text-slate-800' : 'text-slate-700'}">
                                ${name}
                            </p>
                            <p class="text-[9px] font-bold uppercase tracking-widest ${isLeader ? 'text-amber-600' : 'text-slate-400'}">
                                ${isLeader ? 'Team Leader' : 'Member'}
                            </p>
                        </div>
                    `;
                    container.appendChild(div);
                });
            }
            document.getElementById('rosterModal').classList.remove('hidden');
        }
        function closeRoster() {
            document.getElementById('rosterModal').classList.add('hidden');
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('graderModal')) closeGrader();
            if (event.target == document.getElementById('rosterModal')) closeRoster();
        }
    </script>
</body>
</html>