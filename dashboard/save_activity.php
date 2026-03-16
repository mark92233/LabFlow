<?php
session_start();
require_once __DIR__ . '/../dbRelated/operation.php';

// Security Check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'Teacher' && $_SESSION['user_role'] !== 'Admin')) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

header('Content-Type: application/json');
$dm = new DataManager();

try {
    $dm->db->beginTransaction(); // START THE TRANSACTION

    // --- 1. DETERMINE MODE (CREATE vs UPDATE) ---
    $activity_id = $_POST['activity_id'] ?? null;
    $edit_mode = !empty($activity_id);

    // --- 2. COLLECT FORM DATA ---
    $title = $_POST['title'] ?? '';
    $desc = $_POST['description'] ?? '';
    $deadline = isset($_POST['deadline']) ? str_replace('T', ' ', $_POST['deadline']) : null;
    $type = $_POST['activity_type'] ?? 'Individual';
    $grp_mode = $_POST['grouping_mode'] ?? 'Auto';
    $limit = !empty($_POST['group_limit']) ? $_POST['group_limit'] : null;
    $target_classes = $_POST['target_classes'] ?? [];
    $requirements = $_POST['requirements'] ?? [];

    // --- 3. HANDLE PDF UPLOAD ---
    $manual_path = null;
    if ($edit_mode) {
        $oldActivity = $dm->getActivityDetails($activity_id);
        // Preserve old path if no new file is uploaded
        $manual_path = $oldActivity['Manual_URL'] ?? null;
    }
    if (isset($_FILES['manual']) && $_FILES['manual']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/manuals/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // If editing and an old file exists, delete it
        if ($edit_mode && !empty($manual_path) && file_exists('../' . $manual_path)) {
            @unlink('../' . $manual_path);
        }

        $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['manual']['name']));
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['manual']['tmp_name'], $targetPath)) {
            $manual_path = 'uploads/manuals/' . $fileName;
        } else {
            throw new Exception('Failed to move uploaded file.');
        }
    }

    // --- 4. DATABASE OPERATION ---
    $success = false;
    $new_activity_id = null;
    $toast_message = '';

    if ($edit_mode) {
        // UPDATE existing activity
        $success = $dm->updateActivity($activity_id, $title, $desc, $deadline, $manual_path, $type, $grp_mode, $limit, $target_classes, $requirements);
        $new_activity_id = $activity_id; // For redirect and grouping logic
        $toast_message = 'Activity updated successfully!';
    } else {
        // CREATE new activity
        $new_activity_id = $dm->createActivity($title, $desc, $deadline, $manual_path, $type, $grp_mode, $limit);
        if ($new_activity_id) {
            // Assign classes
            foreach ($target_classes as $classID) {
                $dm->assignActivityToClass($new_activity_id, $classID);
            }
            // Add requirements
            foreach ($requirements as $req) {
                $dm->addActivityRequirement($new_activity_id, $req['id'], $req['qty'], $req['selectedVariantId'] ?? null);
            }
            $success = true;
            $toast_message = 'Activity created successfully!';
        }
    }

    if (!$success || !$new_activity_id) {
        throw new Exception('Core activity database operation failed.');
    }

    // --- 5. HANDLE GROUPING (for both create and update) ---
    if ($type === 'Group') {
        // On update, always clear old groups first to handle mode changes (e.g., Manual -> Auto)
        if ($edit_mode) {
            $stmt = $dm->db->prepare("SELECT GroupID FROM activity_groups WHERE ActivityID = ?");
            $stmt->execute([$new_activity_id]);
            $groupIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($groupIds)) {
                $placeholders = str_repeat('?,', count($groupIds) - 1) . '?';
                // Delete from child table first to avoid FK constraint errors
                $dm->db->prepare("DELETE FROM group_members WHERE GroupID IN ($placeholders)")->execute($groupIds);
                // Then delete from parent table
                $dm->db->prepare("DELETE FROM activity_groups WHERE GroupID IN ($placeholders)")->execute($groupIds);
            }
        }

        // --- 5.1 Handle Grouping Modes ---
        if ($grp_mode === 'Auto' && !empty($_POST['auto_groups_json'])) {
            // A. Use the pre-generated groups from the preview
            $autoGroups = json_decode($_POST['auto_groups_json'], true);
            if (is_array($autoGroups)) {
                foreach ($autoGroups as $groupData) {
                    $groupName = $groupData['name'];
                    $dm->manualCreateGroup($new_activity_id, $groupName);
                    $groupID = $dm->db->lastInsertId();
                    if (!empty($groupData['members']) && $groupID) {
                        foreach ($groupData['members'] as $member) {
                            $masterID = $member['MasterID'];
                            $dm->manualAddMember($groupID, $masterID);
                            if (isset($member['isLeader']) && $member['isLeader'] === true) {
                                $dm->manualSetLeader($groupID, $masterID);
                            }
                        }
                    }
                }
            }
        } elseif ($grp_mode === 'Manual' && !empty($_POST['manual_groups_json'])) {
            // B. Use the manually configured groups
            $manualGroups = json_decode($_POST['manual_groups_json'], true);
            if (is_array($manualGroups)) {
                foreach ($manualGroups as $groupData) {
                    if (empty($groupData['members'])) continue;
                    $groupName = $groupData['name'] ?? 'New Group';
                    $dm->manualCreateGroup($new_activity_id, $groupName);
                    $groupID = $dm->db->lastInsertId();
                    if (!empty($groupData['members']) && $groupID) {
                        foreach ($groupData['members'] as $member) {
                            $masterID = $member['MasterID'];
                            $dm->manualAddMember($groupID, $masterID);
                            if (isset($member['isLeader']) && $member['isLeader'] === true) {
                                $dm->manualSetLeader($groupID, $masterID);
                            }
                        }
                    }
                }
            }
        } elseif ($grp_mode === 'Auto' && empty($_POST['auto_groups_json']) && $limit > 0) {
            // C. Fallback for Auto mode if JS fails or preview data isn't sent
            // This now runs for both create and edit mode.
            foreach ($target_classes as $classID) {
                $dm->generateSmartGroups($new_activity_id, $classID, $limit);
            }
        }
    }

    $dm->db->commit(); // COMMIT THE TRANSACTION

    // --- 7. SUCCESS RESPONSE ---
    $_SESSION['toast_message'] = ['text' => $toast_message, 'type' => 'success'];
    $redirect_class_id = $_POST['class_id'] ?? ($target_classes[0] ?? '');
    echo json_encode(['status' => 'success', 'redirect' => "class_activities.php?class_id=$redirect_class_id"]);

} catch (Exception $e) {
    // --- 8. ERROR RESPONSE ---
    if ($dm->db->inTransaction()) {
        $dm->db->rollBack(); // ROLLBACK ON ERROR
    }
    http_response_code(500);
    error_log("save_activity.php error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'A server-side error occurred. Please check the logs.']);
}

exit();
?>