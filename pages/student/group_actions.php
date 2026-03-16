<?php
session_start();
require_once '../../dbRelated/operation.php';

// Force JSON response for all AJAX calls
header('Content-Type: application/json');

// 1. Access Control
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$db = new DataManager();
$studentID = $_SESSION['user_id'];
$masterID = $db->getMasterID($studentID);

// 2. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = $_POST['action'] ?? '';

    try {
        // ---------------------------------------------------------
        // ➤ ACTION: BULK ASSIGN LOGISTICS
        // ---------------------------------------------------------
        if ($action === 'bulk_assign_logistics') {
            $activityID = $_POST['activity_id'] ?? null;
            $groupID = $_POST['group_id'] ?? null;
            $assignmentsJSON = $_POST['assignments'] ?? '[]';
            $assignments = json_decode($assignmentsJSON, true);

            if (!$groupID || !$activityID || !is_array($assignments)) {
                throw new Exception("Invalid bulk distribution data.");
            }

            // Call the new function in operation.php
            $success = $db->bulkDistributeItems($activityID, $groupID, $assignments);

            if ($success) {
                echo json_encode(['status' => 'success']);
            } else {
                throw new Exception("Database failed to save assignments.");
            }
            exit();
        }

        // ---------------------------------------------------------
        // ➤ ACTION 2: CREATE GROUP (Refactored for Fetch)
        // ---------------------------------------------------------
        if ($action === 'create_group') {
            $activityID = $_POST['activity_id'];
            $groupName = trim($_POST['group_name']);

            if (empty($groupName)) {
                throw new Exception("Group name cannot be empty.");
            }

            $db->db->beginTransaction();

            try {
                // A. Create Group
                $stmt = $db->db->prepare("INSERT INTO activity_groups (ActivityID, GroupName, Created_By) VALUES (?, ?, ?)");
                $stmt->execute([$activityID, $groupName, $masterID]);
                $groupID = $db->db->lastInsertId();

                // B. Add Leader
                $stmtMember = $db->db->prepare("INSERT INTO group_members (GroupID, MasterID, Is_Leader) VALUES (?, ?, 1)");
                $stmtMember->execute([$groupID, $masterID]);

                // C. Generate Workspace Sections
                $sections = ['Introduction', 'Methodology', 'Results & Discussion', 'Conclusion'];
                $stmtSec = $db->db->prepare("INSERT INTO report_sections (ActivityID, GroupID, Title, Status, Content) VALUES (?, ?, ?, 'Pending', '')");
                
                foreach ($sections as $title) {
                    $stmtSec->execute([$activityID, $groupID, $title]);
                }

                // D. Add Members
                if (!empty($_POST['members']) && is_array($_POST['members'])) {
                    $stmtMember = $db->db->prepare("INSERT INTO group_members (GroupID, MasterID, Is_Leader) VALUES (?, ?, 0)");
                    foreach ($_POST['members'] as $memberMasterID) {
                        $stmtMember->execute([$groupID, (int) $memberMasterID]);
                    }
                }

                $db->db->commit();
                echo json_encode(['status' => 'success']);

            } catch (Exception $e) {
                $db->db->rollBack();
                throw new Exception("Creation failed: " . $e->getMessage());
            }
            exit();
        }

        // ---------------------------------------------------------
        // ➤ ACTION 3: JOIN GROUP
        // ---------------------------------------------------------
        if ($action === 'join_group') {
            // (Keeping simpler logic here as this usually comes from a different UI)
            // ... implementation if needed ...
            throw new Exception("Join logic not yet implemented in JSON mode.");
        }

        throw new Exception("Unknown action request: " . htmlspecialchars($action));

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}
?>