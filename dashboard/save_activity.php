<?php
session_start();
require_once '../dbRelated/operation.php';

// Security Check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dm = new DataManager();
    
    // --- 1. HANDLE PDF UPLOAD ---
    $manual_path = null;
    if (isset($_FILES['manual']) && $_FILES['manual']['error'] === 0) {
        $uploadDir = '../uploads/manuals/'; 
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['manual']['name']));
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['manual']['tmp_name'], $targetPath)) {
            $manual_path = 'uploads/manuals/' . $fileName; 
        }
    }

    // --- 2. COLLECT DATA ---
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $deadline = str_replace('T', ' ', $_POST['deadline']);
    $type = $_POST['activity_type']; 
    $sub_mode = $_POST['submission_mode']; 
    $grp_mode = $_POST['grouping_mode'] ?? 'None';
    $limit = !empty($_POST['group_limit']) ? $_POST['group_limit'] : null;

    // --- 3. SAVE CORE ACTIVITY ---
    $activityID = $dm->createActivity($title, $desc, $deadline, $manual_path, $type, $sub_mode, $grp_mode, $limit);

    if ($activityID) {
        // 4. Assign Classes
        if (!empty($_POST['target_classes'])) {
            foreach ($_POST['target_classes'] as $classID) {
                $dm->assignActivityToClass($activityID, $classID);

                // --- AUTO GROUPING LOGIC ---
                // NOTE: Ensure your generateSmartGroups function in operation.php calls createDefaultSections internaly!
                if ($type === 'Group' && $grp_mode === 'Auto' && $limit > 0) {
                    $dm->generateSmartGroups($activityID, $classID, $limit);
                }
            }
        }

        // --- MANUAL GROUPING LOGIC ---
        if ($type === 'Group' && $grp_mode === 'Manual' && !empty($_POST['manual_groups_json'])) {
            $manualGroups = json_decode($_POST['manual_groups_json'], true);
            
            if (is_array($manualGroups)) {
                foreach ($manualGroups as $groupData) {
                    $groupName = $groupData['name'] ?? 'New Group';
                    
                    // 1. Create Group in DB
                    $dm->manualCreateGroup($activityID, $groupName);
                    $groupID = $dm->db->lastInsertId(); 
                    
                    // [CRITICAL FIX] 2.1: Initialize Workspace Cards if mode is Builder
                    // This uses the "createDefaultSections" function we added to operation.php
                    if ($sub_mode === 'Builder' && $groupID) {
                        $dm->createDefaultSections($activityID, $groupID);
                    }
                    
                    // 2. Add Members
                    if (!empty($groupData['members']) && $groupID) {
                        foreach ($groupData['members'] as $member) {
                            $masterID = $member['MasterID'];
                            $dm->manualAddMember($groupID, $masterID);
                            
                            // 3. Set Leader if flagged
                            if (isset($member['isLeader']) && $member['isLeader'] === true) {
                                $dm->manualSetLeader($groupID, $masterID);
                            }
                        }
                    }
                }
            }
        }

        // --- 5. Save Inventory ---
        if (!empty($_POST['items'])) {
            $items = $_POST['items'];
            $qtys = $_POST['qtys'];
            foreach ($items as $index => $itemID) {
                $qty = intval($qtys[$index]);
                if ($itemID && $qty > 0) {
                    $dm->addActivityRequirement($activityID, $itemID, $qty);
                }
            }
        }

        echo json_encode(['status' => 'success', 'redirect' => 'activity_hub.php?activity_id=' . $activityID]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database Creation Failed']);
    }
}
?>