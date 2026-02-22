<?php
// pages/teacher/submission_list.php

// 1. Dependency Check
if (!isset($db)) {
    require_once '../../dbRelated/operation.php';
    $db = new DataManager();
}

if (!isset($activity_id)) {
    $activity_id = $_GET['activity_id'] ?? null;
    if (!$activity_id) return;
}

// 2. Fetch Activity Details (to determine Group vs Individual and Deadline)
$actStmt = $db->db->prepare("SELECT type, Deadline FROM lab_activities WHERE ActivityID = ?");
$actStmt->execute([$activity_id]);
$activityInfo = $actStmt->fetch(PDO::FETCH_ASSOC);

if (!$activityInfo) return;

$isGroupActivity = ($activityInfo['type'] === 'Group');
$deadline = $activityInfo['Deadline'];
$listItems = [];

// 3. Fetch Data Based on Mode
if ($isGroupActivity) {
    // --- GROUP MODE: Fetch Groups + Submission Status ---
    $sql = "SELECT ag.GroupID, ag.GroupName, 
                   ls.Status, ls.Submitted_At as SubmissionDate, ls.Grade, ls.SubmissionID
            FROM activity_groups ag
            LEFT JOIN lab_submissions ls ON ag.GroupID = ls.GroupID AND ls.ActivityID = ?
            WHERE ag.ActivityID = ?";
    
    $stmt = $db->db->prepare($sql);
    $stmt->execute([$activity_id, $activity_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($groups as $g) {
        // Fetch Members for this group
        $memSql = "SELECT lm.Full_Name 
                   FROM group_members gm 
                   JOIN lookup_masterlist lm ON gm.MasterID = lm.MasterID 
                   WHERE gm.GroupID = ?";
        $mStmt = $db->db->prepare($memSql);
        $mStmt->execute([$g['GroupID']]);
        $members = $mStmt->fetchAll(PDO::FETCH_COLUMN);

        $listItems[] = [
            'SubmissionID' => $g['SubmissionID'], // Null if not submitted
            'GroupName' => $g['GroupName'],
            'Members' => $members,
            'Status' => $g['Status'] ?? 'Unsubmitted',
            'SubmissionDate' => $g['SubmissionDate'],
            'Grade' => $g['Grade'],
            // Extra data for the grader link
            'GroupID' => $g['GroupID'] 
        ];
    }

} else {
    // --- INDIVIDUAL MODE: Fetch All Enrolled Students + Submission Status ---
    // Links: Activity -> Class -> Enrollment -> MasterList -> Users -> Submissions
    $sql = "SELECT lm.Full_Name, 
                   ls.Status, ls.Submitted_At as SubmissionDate, ls.Grade, ls.SubmissionID
            FROM activity_assignments aa
            JOIN class_enrollment ce ON aa.ClassID = ce.ClassID
            JOIN lookup_masterlist lm ON ce.MasterID = lm.MasterID
            LEFT JOIN users u ON lm.MasterID = u.MasterID
            LEFT JOIN lab_submissions ls ON ls.ActivityID = ? AND ls.StudentID = u.UserID
            WHERE aa.ActivityID = ?
            ORDER BY lm.Full_Name ASC";

    $stmt = $db->db->prepare($sql);
    $stmt->execute([$activity_id, $activity_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $s) {
        $listItems[] = [
            'SubmissionID' => $s['SubmissionID'],
            'Full_Name' => $s['Full_Name'],
            'Status' => $s['Status'] ?? 'Unsubmitted',
            'SubmissionDate' => $s['SubmissionDate'],
            'Grade' => $s['Grade']
        ];
    }
}
?>