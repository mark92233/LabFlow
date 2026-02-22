<?php
session_start();
require_once 'operation.php';

header('Content-Type: application/json');

$db = new DataManager();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$masterID = $db->getMasterID($_SESSION['user_id']);

// Support both POST and GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$sectionID = $_POST['section_id'] ?? $_GET['section_id'] ?? null;
$content = $_POST['content'] ?? '';

// =================================================================================
// 🟢 GLOBAL ACTIONS (Do NOT require SectionID)
// These actions operate on the Activity, Group, or Submission level.
// =================================================================================

// 1. Fetch history for Forensic Modal
if ($action === 'get_history_diff') {
    $historyID = $_GET['history_id'] ?? null;
    if (!$historyID) {
        echo json_encode(['status' => 'error', 'message' => 'Missing History ID']);
        exit();
    }
    $diff = $db->getHistoryComparison($historyID);
    echo json_encode(['status' => 'success', 'data' => $diff]);
    exit();
}

// 2. Final Project Submission (Student Leader Action)
elseif ($action === 'submit_final_project') {
    $activityID = $_POST['activity_id'] ?? null;
    $groupID = $_POST['group_id'] ?? null;
    $studentID = $_SESSION['user_id'];

    if (!$activityID || !$groupID) {
        echo json_encode(['status' => 'error', 'message' => 'Missing IDs']);
        exit();
    }

    $result = $db->submitFinalReport($activityID, $groupID, $studentID);
    echo json_encode($result);
    exit();
}

// 3. Teacher Grading Action (Teacher Action)
elseif ($action === 'submit_grade') {
    // Basic Role Check
    if ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized Grading Access']);
        exit();
    }
    
    $submissionID = $_POST['submission_id'] ?? null;
    $status = $_POST['status'] ?? null; 
    $baseGrade = $_POST['grade'] ?? null;
    $feedback = $_POST['feedback'] ?? '';
    
    // Decode JSON string if individual grades are passed
    $individualGrades = isset($_POST['individual_grades']) ? json_decode($_POST['individual_grades'], true) : null;

    if (!$submissionID || !$status) {
        echo json_encode(['status' => 'error', 'message' => 'Missing submission data']);
        exit();
    }

    $result = $db->saveGrades($submissionID, $status, $baseGrade, $feedback, $individualGrades);
    echo json_encode($result);
    exit();
}

// =================================================================================
// 🔴 SECURITY CHECK: Context Enforcement
// All actions below this line REQUIRE a valid sectionID.
// =================================================================================
if (!$sectionID || !$masterID) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request: Context Missing']);
    exit();
}

// =================================================================================
// 🔵 SECTION ACTIONS (Require SectionID)
// These actions operate on specific report cards (Intro, Methods, etc.)
// =================================================================================

// Checkout: Lock a section for editing
if ($action === 'checkout') {
    $secCheck = $db->db->prepare("SELECT ActivityID, GroupID FROM report_sections WHERE SectionID = ?");
    $secCheck->execute([$sectionID]);
    $secRef = $secCheck->fetch(PDO::FETCH_ASSOC);

   if ($secRef) {
        $access = $db->getGroupActivityStatus($secRef['ActivityID'], $secRef['GroupID']);
        
        // This check was blocking you. 
        // With Step 2 fixed, 'Returned' status will make $access['is_locked'] FALSE.
        if ($access['is_locked']) {
            echo json_encode(['status' => 'error', 'message' => 'Access Denied: This activity is now read-only.']);
            exit();
        }
   }

    $sql = "UPDATE report_sections 
            SET Locked_By = :mid, 
                Locked_At = NOW(), 
                Status = 'In Progress'
            WHERE SectionID = :sid 
            AND (Locked_By IS NULL OR Locked_By = :mid OR Locked_At < NOW() - INTERVAL 30 MINUTE)";
    
    $stmt = $db->db->prepare($sql);
    $stmt->execute(['mid' => $masterID, 'sid' => $sectionID]);

    if ($stmt->rowCount() > 0) {
        $sec = $db->db->prepare("SELECT Title, Content, Draft_Content FROM report_sections WHERE SectionID = ?");
        $sec->execute([$sectionID]);
        $data = $sec->fetch(PDO::FETCH_ASSOC);
        $data['comments'] = $db->getSectionComments($sectionID);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Section is currently locked by another user."]);
    }
}

// Heartbeat: Maintain lock and save draft
elseif ($action === 'heartbeat') {
    $sql = "UPDATE report_sections 
            SET Draft_Content = :content, 
                Locked_At = NOW(),
                Last_Heartbeat = NOW() 
            WHERE SectionID = :sid AND Locked_By = :mid";
    
    $stmt = $db->db->prepare($sql);
    $stmt->execute(['content' => $content, 'sid' => $sectionID, 'mid' => $masterID]);
    echo json_encode(['status' => 'success']);
}

// Finalize: Commit changes and log Forensic history
elseif ($action === 'finalize') {
    $check = $db->db->prepare("SELECT Content FROM report_sections WHERE SectionID = ?");
    $check->execute([$sectionID]);
    $oldContent = $check->fetchColumn();

    $hasChanged = (trim($oldContent) !== trim($content));

    $sql = "UPDATE report_sections 
            SET Content = :content, 
                Draft_Content = NULL,
                Status = 'Completed',
                Locked_By = NULL,
                Locked_At = NULL,
                Last_Updated_By = :mid
            WHERE SectionID = :sid AND Locked_By = :mid";
            
    $stmt = $db->db->prepare($sql);
    $success = $stmt->execute(['content' => $content, 'sid' => $sectionID, 'mid' => $masterID]);

    if ($success) {
        if ($hasChanged) {
            $log = $db->db->prepare("INSERT INTO section_history (SectionID, MasterID, Content_Snapshot, Timestamp) VALUES (?, ?, ?, NOW())");
            $log->execute([$sectionID, $masterID, $content]);
            echo json_encode(['status' => 'success', 'contribution_logged' => true]);
        } else {
            echo json_encode(['status' => 'success', 'contribution_logged' => false]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to finalize section.']);
    }
}

// Commenting logic: Get Comments
elseif ($action === 'get_comments') {
    $sec = $db->db->prepare("SELECT Content, Draft_Content FROM report_sections WHERE SectionID = ?");
    $sec->execute([$sectionID]);
    $contentData = $sec->fetch(PDO::FETCH_ASSOC);
    $comments = $db->getSectionComments($sectionID);
    echo json_encode([
        'status' => 'success', 
        'data' => [
            'comments' => $comments,
            'Content' => $contentData['Content'],
            'Draft_Content' => $contentData['Draft_Content']
        ]
    ]);
}

// Commenting logic: Add Comment
elseif ($action === 'add_comment') {
    $index = $_POST['index'] ?? 0;
    $length = $_POST['length'] ?? 0;
    $commentText = $_POST['comment_text'] ?? '';
    $success = $db->addSectionComment($sectionID, $masterID, $index, $length, $commentText);
    echo json_encode(['status' => $success ? 'success' : 'error']);
}

// Commenting logic: Resolve Comment
elseif ($action === 'resolve_comment') {
    $commentID = $_POST['comment_id'] ?? null;
    $success = $db->resolveComment($commentID, $sectionID);
    echo json_encode(['status' => $success ? 'success' : 'error']);
}
?>