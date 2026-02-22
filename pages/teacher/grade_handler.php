<?php
session_start();
require_once '../../dbRelated/operation.php';

// 1. Security Check: Ensure only Teachers can process grades [cite: 2025-12-06]
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Teacher') {
    header("Location: ../../index.php");
    exit();
}

$db = new DataManager();

// 2. Data Sanitization & Extraction
$submissionID = $_POST['submission_id'] ?? null;
$activityID   = $_POST['activity_id']   ?? null;
$grade        = $_POST['grade']         ?? 0;
$feedback     = $_POST['feedback']      ?? '';

// 3. Validation: Ensure we have the critical IDs
if (!$submissionID || !$activityID) {
    header("Location: activity_hub.php?status=missing_data");
    exit();
}

// 4. Execution: Call the Model to update the database
// This triggers the 'Graded' status and saves the numeric score [cite: 2025-12-06]
if ($db->gradeSubmission($submissionID, $grade, $feedback)) {
    // Success: Redirect back with success flag
    header("Location: activity_hub.php?activity_id=$activityID&status=graded_success");
} else {
    // Failure: Redirect back with error flag
    header("Location: activity_hub.php?activity_id=$activityID&status=db_error");
}
exit();