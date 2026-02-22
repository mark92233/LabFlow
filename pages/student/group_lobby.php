<?php
session_start();
require_once '../../dbRelated/operation.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../index.php");
    exit();
}

$activityID = $_GET['id'];
$userID = $_SESSION['user_id'];
$dm = new DataManager();

// Get IDs
$masterID = $dm->getMasterID($userID);
$classID = $dm->getStudentClassID($userID); // Make sure this exists in operation.php, or fetch via user query

// 1. Get Activity Details & Limits
// (You might need to add a quick fetch function for this or reuse getActivities)
// For now, let's assume we fetch basic info:
$stmt = $dm->db->prepare("SELECT * FROM lab_activities WHERE ActivityID = ?");
$stmt->execute([$activityID]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

$limit = $activity['group_limit'] ?? 4; // Default to 4 if null

// 2. Check My Status
$myGroup = $dm->getStudentGroupStatus($activityID, $masterID);

// 3. If no group, get available classmates
$classmates = [];
if (!$myGroup) {
    $classmates = $dm->getAvailableClassmates($activityID, $classID, $masterID);
} else {
    $groupMembers = $dm->getGroupMembers($myGroup['GroupID']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Group Lobby | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen p-6 flex items-center justify-center">

    <div class="max-w-2xl w-full bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-100"
         x-data="{ 
            selected: [], 
            limit: <?= $limit - 1 ?>, // Limit minus self (leader)
            createTeam() {
                if (this.selected.length > this.limit) {
                    alert('Too many members selected!');
                    return;
                }
                const formData = new FormData(document.getElementById('lobbyForm'));
                fetch('group_actions.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(d => {
                        if(d.status === 'success') window.location.reload();
                        else alert(d.message);
                    });
            }
         }">

        <div class="bg-indigo-900 p-8 text-white relative overflow-hidden">
            <div class="relative z-10">
                <a href="activity_view.php?activity_id=<?= $activityID ?>" class="text-indigo-200 text-xs font-bold uppercase hover:text-white mb-2 block">&larr; Back to Activity</a>
                <h1 class="text-3xl font-black italic tracking-tighter">Team <span class="text-indigo-400">Lobby.</span></h1>
                <p class="text-sm text-indigo-200 mt-1"><?= htmlspecialchars($activity['Title']) ?></p>
            </div>
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500 rounded-full blur-3xl opacity-20"></div>
        </div>

        <div class="p-8">
            
            <?php if ($myGroup): ?>
                <div class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 text-green-600 rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800">You are in <?= htmlspecialchars($myGroup['GroupName']) ?></h2>
                    <p class="text-slate-500 mb-8">Wait for your leader to start the work.</p>

                    <div class="bg-slate-50 rounded-2xl p-6 text-left max-w-sm mx-auto border border-slate-200">
                        <h3 class="text-xs font-bold uppercase text-slate-400 mb-4 tracking-widest">Roster</h3>
                        <ul class="space-y-3">
                            <?php foreach ($groupMembers as $m): ?>
                                <li class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold 
                                        <?= $m['Is_Leader'] ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-200 text-slate-600' ?>">
                                        <?= $m['Is_Leader'] ? '👑' : substr($m['Full_Name'], 0, 1) ?>
                                    </div>
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($m['Full_Name']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            <?php else: ?>
                <form id="lobbyForm" @submit.prevent="createTeam">
                    <input type="hidden" name="activity_id" value="<?= $activityID ?>">
                    
                    <div class="mb-6">
                        <label class="block text-xs font-bold uppercase text-slate-400 mb-2">Team Name</label>
                        <input type="text" name="group_name" required placeholder="e.g. The Avengers" 
                               class="w-full bg-slate-50 border-none p-4 rounded-xl font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="mb-8">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-xs font-bold uppercase text-slate-400">Invite Classmates</label>
                            <span class="text-xs font-bold text-indigo-600" x-text="selected.length + '/' + limit"></span>
                        </div>
                        
                        <div class="h-64 overflow-y-auto bg-slate-50 rounded-xl p-4 border border-slate-100 custom-scrollbar">
                            <?php if (empty($classmates)): ?>
                                <p class="text-center text-slate-400 text-sm py-10">Everyone is already in a group!</p>
                            <?php else: ?>
                                <div class="grid grid-cols-1 gap-2">
                                    <?php foreach ($classmates as $cm): ?>
                                        <label class="flex items-center gap-3 p-3 bg-white border border-slate-200 rounded-lg cursor-pointer hover:border-indigo-400 transition-all">
                                            <input type="checkbox" name="members[]" value="<?= $cm['MasterID'] ?>" 
                                                   x-model="selected" 
                                                   :disabled="selected.length >= limit && !selected.includes('<?= $cm['MasterID'] ?>')"
                                                   class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                                            <span class="text-sm font-bold text-slate-700"><?= htmlspecialchars($cm['Full_Name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">* You are automatically the Leader/Host.</p>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all hover:-translate-y-1">
                        Create Team & Start &rarr;
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>