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

// This file is deprecated as lab_submissions table involvement is removed.
// Returning an empty array to prevent errors.
?>