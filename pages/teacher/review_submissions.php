<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control [cite: 2025-12-06]
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Teacher') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();
$activity_id = $_GET['activity_id'] ?? null;

// Fetch Activity Details
$activity = $db->getActivityDetails($activity_id);
if (!$activity) {
    header("Location: dashboard.php?error=not_found");
    exit();
}

// Handle Grading Form Submission
$status_msg = "";
if (isset($_POST['save_grade'])) {
    if ($db->gradeSubmission($_POST['submission_id'], $_POST['grade'], $_POST['feedback'])) {
        $status_msg = "Grade saved successfully!";
    }
}

/** * Query to get all enrolled students and their submission status for THIS activity
 *
 */
$query = "SELECT lm.Full_Name, u.UserID, ls.SubmissionID, ls.Report_URL, ls.Grade, ls.Feedback, ls.Status, ls.Submitted_At
          FROM class_enrollment ce
          JOIN lookup_masterlist lm ON ce.MasterID = lm.MasterID
          JOIN users u ON u.MasterID = lm.MasterID
          LEFT JOIN lab_submissions ls ON ls.StudentID = u.UserID AND ls.ActivityID = :aid
          WHERE ce.ClassID = :cid
          ORDER BY lm.Full_Name ASC";

$stmt = $db->db->prepare($query);
$stmt->execute(['aid' => $activity_id, 'cid' => $activity['ClassID']]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review: <?= htmlspecialchars($activity['Title']) ?> | SNHS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] min-h-screen">
    <div class="flex min-h-screen">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col">
            <?php include '../../includes/glass_header.php'; ?>
            
            <main class="p-8 animate-reveal">
                <header class="mb-10 flex justify-between items-center">
                    <div>
                        <h2 class="text-4xl font-black text-[#0f172a] uppercase italic tracking-tighter">Review <span class="text-blue-600">Submissions.</span></h2>
                        <p class="text-slate-400 font-bold uppercase text-[10px] tracking-widest mt-2">Activity: <?= htmlspecialchars($activity['Title']) ?></p>
                    </div>
                    <a href="dashboard.php" class="px-6 py-3 bg-white border border-slate-100 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 transition-all">Back to Dashboard</a>
                </header>

                <?php if ($status_msg): ?>
                    <div class="mb-6 p-4 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100 font-bold text-sm">
                        <?= $status_msg ?>
                    </div>
                <?php endif; ?>

                <div class="glass-card overflow-hidden shadow-xl border-t-8 border-blue-600">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase">Student Name</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase">Status</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase">Submitted File</th>
                                <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase">Grade / Feedback</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($submissions as $sub): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-8 py-6">
                                        <p class="font-black text-slate-800 uppercase italic text-sm"><?= htmlspecialchars($sub['Full_Name']) ?></p>
                                        <p class="text-[10px] text-slate-400 font-bold"><?= $sub['Submitted_At'] ? 'Received: ' . date('M d, H:i', strtotime($sub['Submitted_At'])) : 'No submission yet' ?></p>
                                    </td>
                                    <td class="px-8 py-6">
                                        <?php 
                                            $status_class = "text-slate-400 bg-slate-100";
                                            if ($sub['Status'] == 'Submitted') $status_class = "text-amber-600 bg-amber-50";
                                            if ($sub['Status'] == 'Graded') $status_class = "text-emerald-600 bg-emerald-50";
                                        ?>
                                        <span class="px-4 py-2 rounded-xl text-[9px] font-black uppercase italic <?= $status_class ?>">
                                            <?= $sub['Status'] ?? 'Pending' ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-6">
                                        <?php if ($sub['Report_URL']): ?>
                                            <a href="../../<?= htmlspecialchars($sub['Report_URL']) ?>" target="_blank" class="flex items-center gap-2 text-blue-600 hover:text-blue-800 transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                <span class="text-xs font-bold uppercase italic">View Report</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 italic text-xs">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-8 py-6">
                                        <?php if ($sub['SubmissionID']): ?>
                                            <form method="POST" class="flex flex-col gap-2">
                                                <input type="hidden" name="submission_id" value="<?= $sub['SubmissionID'] ?>">
                                                <div class="flex gap-2">
                                                    <input type="text" name="grade" placeholder="Grade" value="<?= $sub['Grade'] ?>" class="w-20 p-2 bg-slate-50 border border-slate-100 rounded-lg text-xs font-bold outline-none focus:ring-2 focus:ring-blue-500">
                                                    <button type="submit" name="save_grade" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all">Save</button>
                                                </div>
                                                <textarea name="feedback" placeholder="Feedback..." class="w-full p-2 bg-slate-50 border border-slate-100 rounded-lg text-xs font-medium outline-none focus:ring-2 focus:ring-blue-500" rows="1"><?= htmlspecialchars($sub['Feedback'] ?? '') ?></textarea>
                                            </form>
                                        <?php else: ?>
                                            <p class="text-slate-300 italic text-[10px]">Cannot grade until submitted</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</body>
</html>