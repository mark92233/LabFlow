<?php
require_once __DIR__ . '/db_connect.php';

if (!class_exists('DataManager')) {
class DataManager {
    public $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // --- AUTHENTICATION CORE ---

    // Check if the ID Number exists in the school masterlist
    public function verifyIdentity($id_number) {
    // Modified query to check ONLY the ID_Number
    $query = "SELECT * FROM lookup_masterlist WHERE ID_Number = :id LIMIT 1";
    
    $stmt = $this->db->prepare($query);
    $stmt->bindParam(':id', $id_number);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

    // Check if a user account has already been created for this MasterID
    public function checkExistingAccount($masterId) {
        try {
            $query = "SELECT u.*, m.Role, m.Full_Name 
                      FROM users u
                      INNER JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                      WHERE u.MasterID = :mid LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':mid', $masterId);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    // Complete the registration process by saving the password and email
    public function finalizeRegistration($master_id, $email, $password) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (MasterID, Confirmed_Email, Password_Hash, Is_Verified) 
                      VALUES (:mid, :email, :pass, 1)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':mid', $master_id);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':pass', $hashed_password);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            return false;
        }
    }

    // Fetch all classes a specific student is currently enrolled in
    public function getStudentEnrolledClasses($studentUserID) {
        try {
            $query = "SELECT c.*, m.Full_Name as TeacherName 
                      FROM class_enrollment ce
                      JOIN classes c ON ce.ClassID = c.ClassID
                      JOIN users u_teacher ON c.TeacherID = u_teacher.UserID
                      JOIN lookup_masterlist m ON u_teacher.MasterID = m.MasterID
                      JOIN users u_student ON ce.MasterID = u_student.MasterID
                      WHERE u_student.UserID = :sid";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['sid' => $studentUserID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Student Dash Error: " . $e->getMessage());
            return [];
        }
    }

    // --- ADMIN INVENTORY MANAGEMENT ---

    // Create a new category for inventory items
    public function addCategory($name) {
        try {
            $query = "INSERT INTO categories (Category_Name) VALUES (:name)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([':name' => $name]);
        } catch (PDOException $e) { return false; }
    }

    // Fetch all available categories
    public function getCategories() {
        $query = "SELECT * FROM categories ORDER BY Category_Name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add a new item to the inventory
    public function addItem($catId, $name, $qty, $location, $desc) {
        $query = "INSERT INTO inventory (CategoryID, Item_Name, Total_Qty, Available_Qty, Location, Description) 
                  VALUES (:cid, :name, :tqty, :aqty, :loc, :desc)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'cid' => $catId,
            'name' => $name,
            'tqty' => $qty,
            'aqty' => $qty, 
            'loc' => $location,
            'desc' => $desc
        ]);
        return $this->db->lastInsertId();
    }

    // --- TEACHER CLASS & MASTERLIST MANAGEMENT ---

    // Create a new class section
    public function createClass($teacherUserID, $className, $section, $semester) {
        $query = "INSERT INTO classes (TeacherID, Class_Name, Section, Semester) 
                  VALUES (:tid, :cname, :sec, :sem)";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['tid' => $teacherUserID, 'cname' => $className, 'sec' => $section, 'sem' => $semester]);
        return $this->db->lastInsertId();
    }

   // Get all classes assigned to a specific teacher
   public function getTeacherClasses($teacherUserID) {
    // FIXED: Ensure we select Class_Name here too
    $query = "SELECT * FROM classes WHERE TeacherID = :tid ORDER BY Class_Name, Section";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['tid' => $teacherUserID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // Get details for a single class
    public function getClassDetails($classID) {
        $query = "SELECT * FROM classes WHERE ClassID = :cid LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['cid' => $classID]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Add a student to the global masterlist (used during CSV upload)
    public function uploadStudentToMasterlist($idNum, $fullName, $email) {
        try {
            $query = "INSERT INTO lookup_masterlist (ID_Number, Full_Name, Official_Email, Role) 
                      VALUES (:id, :name, :email, 'Student')
                      ON DUPLICATE KEY UPDATE Full_Name = VALUES(Full_Name), Official_Email = VALUES(Official_Email)";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $idNum, 'name' => $fullName, 'email' => $email]);
            $check = "SELECT MasterID FROM lookup_masterlist WHERE ID_Number = :id";
            $cStmt = $this->db->prepare($check);
            $cStmt->execute(['id' => $idNum]);
            return $cStmt->fetchColumn();
        } catch (Exception $e) { return false; }
    }

    // Enroll a student into a class using their MasterID
    public function enrollByMasterID($masterID, $classID) {
        try {
            $query = "INSERT IGNORE INTO class_enrollment (ClassID, MasterID) VALUES (:cid, :mid)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['cid' => $classID, 'mid' => $masterID]);
        } catch (PDOException $e) { return false; }
    }

    // Get classes for a student based on their MasterID
    public function getStudentEnrolledClassesByMaster($masterID) {
        $query = "SELECT c.*, m.Full_Name as TeacherName 
                  FROM class_enrollment ce
                  JOIN classes c ON ce.ClassID = c.ClassID
                  JOIN users u ON c.TeacherID = u.UserID
                  JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                  WHERE ce.MasterID = :mid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['mid' => $masterID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Link an activity to a specific class
    public function assignActivityToClass($activityID, $classID) {
        try {
            $query = "INSERT IGNORE INTO activity_assignments (ActivityID, ClassID) VALUES (:aid, :cid)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['aid' => $activityID, 'cid' => $classID]);
        } catch (PDOException $e) {
            error_log("Assignment Error: " . $e->getMessage());
            return false;
        }
    }

    // Define required items for an activity
    public function addActivityRequirement($activityID, $itemID, $qty) {
        try {
            // FIX: Uses Required_Qty to match student view fetching
            $query = "INSERT INTO activity_requirements (ActivityID, ItemID, Required_Qty) 
                      VALUES (:aid, :iid, :qty)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['aid' => $activityID, 'iid' => $itemID, 'qty' => $qty]);
        } catch (PDOException $e) {
            error_log("Requirement Error: " . $e->getMessage());
            return false;
        }
    }

    // Get the list of items required for an activity
    public function getActivityRequirements($activityID) {
        $query = "SELECT i.ItemID, i.Item_Name, ar.Required_Qty 
                  FROM activity_requirements ar
                  JOIN inventory i ON ar.ItemID = i.ItemID
                  WHERE ar.ActivityID = :aid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['aid' => $activityID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- PHASE 8: BORROWING SYSTEM ---

    // Initialize a borrowing session (Pending status)
    public function createBorrowingSession($studentID, $activityID, $qrData, $reason = null) {
        try {
            // Ensure ActivityID is NULL if empty/zero to allow independent borrowing
            if (empty($activityID) || $activityID === 0 || $activityID === '0') {
                $activityID = null;
            }

            $query = "INSERT INTO borrowing_sessions (StudentID, ActivityID, Status, QR_Code_Data, Request_Reason) 
                      VALUES (:sid, :aid, 'Pending', :qr, :reason)";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['sid' => $studentID, 'aid' => $activityID, 'qr' => $qrData, 'reason' => $reason]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Session Error: " . $e->getMessage());
            return false;
        }
    }

    // Add specific items to a borrowing session
    public function addItemToSlip($sessionID, $itemID, $qty) {
        $query = "INSERT INTO borrowed_items (SessionID, ItemID, Quantity) VALUES (:sid, :iid, :qty)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['sid' => $sessionID, 'iid' => $itemID, 'qty' => $qty]);
    }

    // Update the status of a borrowing session (e.g., Pending -> Approved)
    public function updateSessionStatus($sessionID, $status) {
        try {
            $query = "UPDATE borrowing_sessions SET Status = :status WHERE SessionID = :sid";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['status' => $status, 'sid' => $sessionID]);
        } catch (PDOException $e) { return false; }
    }

    // Get all inventory items for selection
    public function getInventoryItems() {
        $query = "SELECT ItemID, Item_Name, Available_Qty, Location FROM inventory ORDER BY Item_Name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Find a session using the QR code string
    public function getSessionByQR($qrData) {
        $query = "SELECT bs.*, m.Full_Name 
                  FROM borrowing_sessions bs
                  JOIN users u ON bs.StudentID = u.UserID
                  JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                  WHERE bs.QR_Code_Data = :qr LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['qr' => $qrData]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Count sessions for a student with a specific status
    public function countStudentSessions($studentID, $status) {
        $query = "SELECT COUNT(*) FROM borrowing_sessions WHERE StudentID = :sid AND Status = :status";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['sid' => $studentID, 'status' => $status]);
        return $stmt->fetchColumn();
    }

    // Count pending requests for a teacher's classes
    public function countPendingRequests($teacherID) {
        try {
            $query = "SELECT COUNT(bs.SessionID) 
                      FROM borrowing_sessions bs
                      LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
                      LEFT JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                      LEFT JOIN classes c ON aa.ClassID = c.ClassID
                      WHERE bs.Status = 'Pending' 
                      AND (c.TeacherID = :tid OR bs.ActivityID IS NULL)";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['tid' => $teacherID]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) { return 0; }
    }

    // --- ADMIN DASHBOARD ANALYTICS ---
    public function getAdminKPIs() {
        try {
            $stats = [];
            
            // 1. Inventory Stats
            $inv = $this->db->query("SELECT COUNT(*) as unique_items, SUM(Total_Qty) as total_stock FROM inventory")->fetch(PDO::FETCH_ASSOC);
            $stats['unique_items'] = $inv['unique_items'] ?? 0;
            $stats['total_stock'] = $inv['total_stock'] ?? 0;

            // 2. User & Request Stats
            $stats['total_users'] = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $stats['pending_reqs'] = $this->db->query("SELECT COUNT(*) FROM borrowing_sessions WHERE Status = 'Pending'")->fetchColumn();
            $stats['open_damages'] = $this->db->query("SELECT COUNT(*) FROM damaged_returns WHERE status = 'Unresolved'")->fetchColumn();

            // 3. Population Stats
            $stats['student_pop'] = $this->db->query("SELECT COUNT(*) FROM lookup_masterlist WHERE Role = 'Student'")->fetchColumn();
            $stats['teacher_pop'] = $this->db->query("SELECT COUNT(*) FROM lookup_masterlist WHERE Role = 'Teacher'")->fetchColumn();
            $stats['total_classes'] = $this->db->query("SELECT COUNT(*) FROM classes")->fetchColumn();

            // 3. Graph Data: Inventory by Category
            $catSql = "SELECT c.Category_Name, COUNT(i.ItemID) as count 
                       FROM inventory i 
                       JOIN categories c ON i.CategoryID = c.CategoryID 
                       GROUP BY c.Category_Name";
            $stats['categories'] = $this->db->query($catSql)->fetchAll(PDO::FETCH_ASSOC);

            // 4. Graph Data: Session Status
            $statusSql = "SELECT Status, COUNT(*) as count FROM borrowing_sessions GROUP BY Status";
            $stats['session_stats'] = $this->db->query($statusSql)->fetchAll(PDO::FETCH_ASSOC);

            // 5. Trend Data: Borrowing (Last 7 Days)
            $trendSql = "SELECT DATE(CreatedAt) as date, COUNT(*) as count 
                         FROM borrowing_sessions 
                         WHERE CreatedAt >= DATE(NOW()) - INTERVAL 7 DAY 
                         GROUP BY DATE(CreatedAt) 
                         ORDER BY date ASC";
            $stats['borrowing_trend'] = $this->db->query($trendSql)->fetchAll(PDO::FETCH_ASSOC);

            // 6. Trend Data: Damages (Last 7 Days)
            $dmgTrendSql = "SELECT DATE(logged_at) as date, COUNT(*) as count 
                            FROM damaged_returns 
                            WHERE logged_at >= DATE(NOW()) - INTERVAL 7 DAY 
                            GROUP BY DATE(logged_at) 
                            ORDER BY date ASC";
            $stats['damage_trend'] = $this->db->query($dmgTrendSql)->fetchAll(PDO::FETCH_ASSOC);

            return $stats;
        } catch (PDOException $e) { return []; }
    }

    // Generate a unique hash for QR codes
    private function generateQRHash($studentId) {
        return bin2hex(random_bytes(4)) . "-" . $studentId . "-" . time();
    }

    // Approve a borrowing request and generate a new QR code
    public function approveRequest($sessionId, $teacherId) {
        $newHash = $this->generateQRHash($teacherId);
        $query = "UPDATE borrowing_sessions SET 
                  Status = 'Approved', 
                  ApproverName = (SELECT Full_Name FROM lookup_masterlist m 
                                 JOIN users u ON m.MasterID = u.MasterID 
                                 WHERE u.UserID = :tid),
                  QR_Code_Data = :hash
                  WHERE SessionID = :sid AND Status = 'Pending'";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['sid' => $sessionId, 'tid' => $teacherId, 'hash' => $newHash]);
    }


    // Get inventory items with category names
    public function getInventoryShop() {
        $query = "SELECT i.*, c.Category_Name FROM inventory i LEFT JOIN categories c ON i.CategoryID = c.CategoryID ORDER BY i.Item_Name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get details of a specific item
    public function getItemDetails($itemId) {
        $query = "SELECT i.*, c.Category_Name FROM inventory i JOIN categories c ON i.CategoryID = c.CategoryID WHERE i.ItemID = :iid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['iid' => $itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
 // Process the student's cart and create a borrowing request
 public function submitRequisition($studentId, $activityId, $items) {
    try {
        $this->db->beginTransaction();

        // 🟢 FIX: Force activityId to NULL if it's empty, 0, or false
        // This ensures the database treats it as an "Independent Borrow"
        if (empty($activityId) || $activityId === 0 || $activityId === '0') {
            $activityId = null;
        }

        // 1. Insert the session record
        $sql = "INSERT INTO borrowing_sessions (StudentID, ActivityID, Status, QR_Code_Data) 
                VALUES (:sid, :aid, 'Pending', :qr)";
        $stmt = $this->db->prepare($sql);
        
        $qrData = "SNHS-REF-" . strtoupper(uniqid()); 
        
        $stmt->execute([
            'sid' => $studentId,
            'aid' => $activityId, // Now this is safely NULL if needed
            'qr'  => $qrData
        ]);
        
        $sessionId = $this->db->lastInsertId();

        // 2. Map items to the session
        $itemSql = "INSERT INTO borrowed_items (SessionID, ItemID, Quantity) VALUES (:sid, :iid, :qty)";
        $itemStmt = $this->db->prepare($itemSql);

        foreach ($items as $item) {
            $iid = $item['id'] ?? $item['ItemID'];
            $qty = $item['qty'] ?? $item['Quantity'];

            $itemStmt->execute([
                'sid' => $sessionId,
                'iid' => $iid,
                'qty' => $qty
            ]);
        }

        $this->db->commit();
        return $sessionId;
    } catch (Exception $e) {
        $this->db->rollBack();
        // Log error safely
        $this->lastError = $e->getMessage(); 
        return false;
    }
}

// Get full details of an activity, including class info
public function getActivityDetails($activityID, $classID = null) {
    try {
        // REVISED QUERY:
        // We fetch basic info from 'la' (lab_activities).
        // We use 'la.type AS Type' to match the capitalization your PHP frontend expects.
        $sql = "SELECT la.*, 
                       la.type AS Type, 
                       aa.ClassID, 
                       c.Class_Name, 
                       c.Section 
                FROM lab_activities la
                LEFT JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID";
        
        // If a specific Class ID is requested, filter the JOIN immediately
        if ($classID) {
            $sql .= " AND aa.ClassID = :cid";
        }
        
        $sql .= " LEFT JOIN classes c ON aa.ClassID = c.ClassID
                  WHERE la.ActivityID = :aid 
                  ORDER BY aa.AssignmentID DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        
        $params = [':aid' => $activityID];
        if ($classID) {
            $params[':cid'] = $classID;
        }

        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Helpful debugging line (comment out in production)
        // die("SQL Error: " . $e->getMessage());
        return false;
    }
}

// Get all groups and their members for a specific activity
public function getGroupsWithSubmissions($activityID, $classID) {
    try {
        // 1. MAIN QUERY: Fetch Groups (Unchanged)
        $sql = "SELECT g.GroupID, g.GroupName, 
                       NULL as SubmissionID, 
                       NULL as SubmissionDate, 
                       NULL as Grade, 
                       'Pending' AS Status, 
                       NULL as Report_URL, 
                       NULL as Feedback
                FROM activity_groups g
                WHERE g.ActivityID = :aid";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':aid' => $activityID]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. MEMBER LOOP: Fixed to include Is_Leader
        if ($groups) {
            foreach ($groups as &$group) {
                // 🟢 REVISED JOIN: We add 'AS name' and 'AS role' here
                // This forces the keys to be lowercase and simple for JavaScript
                $sqlMembers = "SELECT lm.Full_Name AS name, gm.Is_Leader AS role 
                               FROM group_members gm
                               JOIN lookup_masterlist lm ON gm.MasterID = lm.MasterID
                               WHERE gm.GroupID = :gid
                               ORDER BY gm.Is_Leader DESC, lm.Full_Name ASC"; 
                               
                $stmtMembers = $this->db->prepare($sqlMembers);
                $stmtMembers->execute([':gid' => $group['GroupID']]);
                
                // Fetch as Associative Array
                $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);
                
                // Add null avatar to prevent frontend warnings
                foreach ($members as &$m) { $m['Avatar'] = null; } 
                
                $group['Members'] = $members;
            }
        }

        return $groups;

    } catch (PDOException $e) {
        die("<h3>Database Error:</h3> " . $e->getMessage());
    }
}

 // Get all activities assigned to a student
 public function getStudentActivities($studentUserID) {
    try {
        // This query bridges the logged-in UserID to the enrolled MasterID
        $query = "SELECT la.*, m_teacher.Full_Name as Instructor 
                  FROM lab_activities la
                  JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                  JOIN class_enrollment ce ON aa.ClassID = ce.ClassID
                  JOIN users u_student ON ce.MasterID = u_student.MasterID
                  JOIN classes c ON aa.ClassID = c.ClassID
                  JOIN users u_teacher ON c.TeacherID = u_teacher.UserID
                  JOIN lookup_masterlist m_teacher ON u_teacher.MasterID = m_teacher.MasterID
                  WHERE u_student.UserID = :sid
                  ORDER BY la.Deadline ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute(['sid' => $studentUserID]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debugging: If list is empty, log exactly why [cite: 2025-12-06]
        if (empty($results)) {
            error_log("No assigned activities found for UserID: $studentUserID. Check class_enrollment for corresponding MasterID.");
        }

        return $results;
    } catch (PDOException $e) {
        error_log("Database Error in getStudentActivities: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch full activity details including class context for the student view.
 *
 */

// Check if a student has an active (not returned/cancelled) session for an activity
public function getActiveSessionForActivity($studentID, $activityID) {
    try {
        if (empty($activityID) || $activityID === 0 || $activityID === '0') {
            $query = "SELECT SessionID FROM borrowing_sessions 
                      WHERE StudentID = :sid AND ActivityID IS NULL 
                      AND Status NOT IN ('Returned', 'Cancelled') 
                      LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['sid' => $studentID]);
        } else {
            $query = "SELECT SessionID FROM borrowing_sessions 
                      WHERE StudentID = :sid AND ActivityID = :aid 
                      AND Status NOT IN ('Returned', 'Cancelled') 
                      LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['sid' => $studentID, 'aid' => $activityID]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

// Get the most recent session for an activity (regardless of status)
public function getSessionForActivity($studentID, $activityID) {
    try {
        // We MUST fetch the session regardless of status. 
        // If we don't find the 'Returned' session, the page loops back to 'Requisition Bag'.
        if (empty($activityID) || $activityID === 0 || $activityID === '0') {
            $sql = "SELECT * FROM borrowing_sessions 
                    WHERE StudentID = :sid 
                    AND ActivityID IS NULL 
                    ORDER BY SessionID DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['sid' => $studentID]);
        } else {
            $sql = "SELECT * FROM borrowing_sessions 
                    WHERE StudentID = :sid 
                    AND ActivityID = :aid 
                    ORDER BY SessionID DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['sid' => $studentID, 'aid' => $activityID]);
        }
        
        return $stmt->fetch(PDO::FETCH_ASSOC); // Returns array or false
    } catch (PDOException $e) {
        return false;
    }
}

// Inside DataManager class
private $lastError = null;

public function getLastError() {
    return $this->lastError;
}

/**
 * Finalizes the handover by updating session status and subtracting inventory.
 *
 * This is called when the student physically receives the items.
 */
public function finalizeHandover($sid) {
    try {
        $this->db->beginTransaction();

        // 1. STATUS GUARD: Check if already issued [cite: 2025-12-06]
        $check = "SELECT Status FROM borrowing_sessions WHERE SessionID = :sid FOR UPDATE";
        $cStmt = $this->db->prepare($check);
        $cStmt->execute(['sid' => $sid]);
        $currentStatus = $cStmt->fetchColumn();

        if ($currentStatus === 'Issued') {
            $this->db->rollBack();
            return true; // Already processed, don't subtract again
        }

        // 2. Get items
        $query = "SELECT ItemID, Quantity FROM borrowed_items WHERE SessionID = :sid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['sid' => $sid]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            // 3. Subtract Stock
            $updateInv = "UPDATE inventory 
                          SET Available_Qty = Available_Qty - :qty 
                          WHERE ItemID = :iid AND Available_Qty >= :qty";
            $uStmt = $this->db->prepare($updateInv);
            $uStmt->execute(['qty' => $item['Quantity'], 'iid' => $item['ItemID']]);
        }

        // 4. Update Status [cite: 2025-12-06]
        $updateStatus = "UPDATE borrowing_sessions SET Status = 'Issued' WHERE SessionID = :sid";
        $sStmt = $this->db->prepare($updateStatus);
        $sStmt->execute(['sid' => $sid]);

        $this->db->commit();
        return true;
    } catch (Exception $e) {
        $this->db->rollBack();
        $this->lastError = $e->getMessage();
        return false;
    }
}

/**
 * Processes the return of apparatus, restoring stock to the inventory.
 *
 * This is called when items are returned in good condition.
 */
public function processReturn($sid, $remarks) {
    try {
        // 1. Get items for this session
        $query = "SELECT ItemID, Quantity FROM borrowed_items WHERE SessionID = :sid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['sid' => $sid]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updatedCount = 0;
        foreach ($items as $item) {
            // 2. Perform Addition
            $restoreInv = "UPDATE inventory SET Available_Qty = Available_Qty + :qty WHERE ItemID = :iid";
            $rStmt = $this->db->prepare($restoreInv);
            $rStmt->execute(['qty' => $item['Quantity'], 'iid' => $item['ItemID']]);
            
            // Check if any row was actually changed
            if ($rStmt->rowCount() > 0) {
                $updatedCount++;
            }
        }

        // 3. Finalize Session [cite: 2025-12-06]
        $updateStatus = "UPDATE borrowing_sessions SET Status = 'Returned', Remarks = :rem WHERE SessionID = :sid";
        $sStmt = $this->db->prepare($updateStatus);
        $sStmt->execute(['sid' => $sid, 'rem' => $remarks]);

        if ($updatedCount === 0 && !empty($items)) {
            $this->lastError = "Inventory was not updated. Please check if ItemIDs still exist.";
            return false;
        }

        return true;
    } catch (Exception $e) {
        $this->lastError = "Database Error: " . $e->getMessage();
        return false;
    }
}

// Get all activities for a specific class (Student View)
public function getActivitiesByClassForStudent($classID, $studentID) {
    try {
        $sql = "SELECT a.*, NULL AS SubmissionStatus, NULL AS Grade 
                FROM lab_activities a
                JOIN activity_assignments aa ON a.ActivityID = aa.ActivityID
                WHERE aa.ClassID = :cid 
                ORDER BY a.CreatedAt DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cid' => $classID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Get all activities created by a teacher
public function getTeacherActivities($teacherID) {
    try {
        $sql = "SELECT la.*, c.Class_Name, c.Section 
                FROM lab_activities la
                JOIN classes c ON la.ClassID = c.ClassID
                WHERE c.TeacherID = :tid
                ORDER BY la.CreatedAt DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tid' => $teacherID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DataManager::getTeacherActivities Error: " . $e->getMessage());
        return [];
    }
}

// Get activities assigned to a specific class
public function getActivitiesByClass($classID) {
    try {
        // We JOIN the bridge table to find activities assigned to this specific ClassID
        $sql = "SELECT a.* FROM lab_activities a
                JOIN activity_assignments aa ON a.ActivityID = aa.ActivityID
                WHERE aa.ClassID = :cid 
                ORDER BY a.CreatedAt DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cid' => $classID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DataManager::getActivitiesByClass Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches all students enrolled in a class and their submission status for a specific activity.
 * This powers the right-hand sidebar in the Teacher's Activity Hub.
 */
public function getEnrollmentWithSubmissions($activityID, $classID) {
    try {
        // QUERY EXPLANATION:
        // 1. SELECT from 'class_enrollment' (ce) to get the roster.
        // 2. JOIN 'lookup_masterlist' (lm) to get the Name.
        // 3. LEFT JOIN 'users' (u) to find the UserID (if they have signed up).
        // 4. LEFT JOIN 'lab_submissions' (ls) to find their work.
        
        $sql = "SELECT lm.Full_Name, 
                       NULL as Avatar, 
                       NULL as SubmissionID, 
                       NULL as SubmissionDate, 
                       NULL as Grade, 
                       'Pending' AS Status, 
                       NULL as Report_URL, 
                       NULL as Feedback,
                       0 as Is_Late
                FROM class_enrollment ce
                JOIN lookup_masterlist lm ON ce.MasterID = lm.MasterID
                WHERE ce.ClassID = :cid
                ORDER BY lm.Full_Name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':cid' => $classID
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // FINAL SAFETY CHECK: If empty, verify class_enrollment isn't empty
        if (empty($results)) {
            // Uncomment the line below to see if the query ran but found 0 people
            // die("Query ran successfully but found 0 students in Class ID: " . $classID);
        }

        return $results;

    } catch (PDOException $e) {
        // PRINT THE ERROR ON SCREEN
        die("<h3>Database Error (Individual List):</h3> " . $e->getMessage());
    }
}

// Process a return that includes damaged items (Restocks good items, logs damages)
public function processReturnWithDamage($session_id, $damage_data) {
    if (!$this->db) { return false; }

    try {
        $this->db->beginTransaction();

        // A. SECURITY: Fetch the real StudentID and borrowed items from Database
        // We do NOT trust the student_id coming from the frontend JSON.
        $stmt_session = $this->db->prepare("SELECT StudentID FROM borrowing_sessions WHERE SessionID = ?");
        $stmt_session->execute([$session_id]);
        $session_info = $stmt_session->fetch(PDO::FETCH_ASSOC);

        if (!$session_info) { throw new Exception("Session not found."); }
        $real_student_id = $session_info['StudentID'];

        // B. Fetch borrowed items [ItemID => Quantity]
        $stmt_b = $this->db->prepare("SELECT ItemID, Quantity FROM borrowed_items WHERE SessionID = ?");
        $stmt_b->execute([$session_id]);
        $rows = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

        $borrowed_items = [];
        foreach ($rows as $r) {
            $borrowed_items[$r['ItemID']] = $r['Quantity'];
        }

        // C. Prepare Statements
        $stmt_restock = $this->db->prepare("UPDATE inventory SET Available_Qty = Available_Qty + ? WHERE ItemID = ?");
        $stmt_damage_log = $this->db->prepare("INSERT INTO damaged_returns (session_id, item_id, student_id, qty_damaged, damage_type, status, notes, evidence_image) VALUES (?, ?, ?, ?, ?, 'Unresolved', ?, ?)");

        $reported_damage_map = [];
        
        // Ensure Directory Exists
        $target_dir = __DIR__ . "/../uploads/evidence/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

        // D. Log Damages & Upload Files
        foreach ($damage_data as $report) {
            $iID = $report['item_id'];
            $qty_bad = (int)$report['qty'];
            
            // Track how many were damaged for the restocking calculation later
            $reported_damage_map[$iID] = $qty_bad;

            if ($qty_bad > 0) {
                // --- FILE UPLOAD LOGIC ---
                $evidence_filename = null;
                // CRITICAL: This reconstructs the key to find the file in $_FILES
                $file_key = 'evidence_' . $iID; 

                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES[$file_key]['tmp_name'];
                    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp']; // Added webp just in case
                    
                    if (in_array($ext, $allowed)) {
                        // Unique Name: dmg_SessionID_ItemID_Timestamp
                        $new_name = "dmg_" . $session_id . "_" . $iID . "_" . time() . "." . $ext;
                        if (move_uploaded_file($tmp_name, $target_dir . $new_name)) {
                            $evidence_filename = $new_name;
                        }
                    }
                }

                // Execute Log (Using $real_student_id)
                $stmt_damage_log->execute([
                    $session_id,
                    $iID,
                    $real_student_id, // Secure ID
                    $qty_bad,
                    $report['type'],
                    $report['notes'],
                    $evidence_filename
                ]);
            }
        }

        // E. Restock Good Items (The "Difference" Calculation)
        foreach ($borrowed_items as $itemID => $total_borrowed) {
            $qty_bad = $reported_damage_map[$itemID] ?? 0;
            
            // Logic: If I borrowed 5 and broke 2, I am returning 3 good ones.
            $qty_good = $total_borrowed - $qty_bad;

            // Safety check: prevent negative restocking if frontend sends bad math
            if ($qty_good > 0) {
                $stmt_restock->execute([$qty_good, $itemID]);
            }
        }

        // F. Close Session
        // We flag it as 'has_damage' so it shows up in your Settlement Page
        $sql_update = "UPDATE borrowing_sessions SET Status = 'Returned', has_damage = 1, Remarks = 'Returned with damages' WHERE SessionID = ?";
        $this->db->prepare($sql_update)->execute([$session_id]);

        $this->db->commit();
        return true;

    } catch (Exception $e) {
        $this->db->rollBack();
        // error_log($e->getMessage()); // Useful for debugging
        return false;
    }
}

// Process a return where all items are in good condition
public function processCleanReturn($session_id) {
        if (!$this->db) { return false; }

        try {
            $this->db->beginTransaction();

            // A. Fetch borrowed items
            $sql_borrowed = "SELECT ItemID, Quantity FROM borrowed_items WHERE SessionID = ?";
            $stmt_b = $this->db->prepare($sql_borrowed);
            $stmt_b->execute([$session_id]);
            $items = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

            // B. Restock Loop
            // FIX: Changed 'Quantity' to 'Available_Qty'
            $stmt_restock = $this->db->prepare("UPDATE inventory SET Available_Qty = Available_Qty + ? WHERE ItemID = ?");
            
            foreach ($items as $item) {
                $stmt_restock->execute([$item['Quantity'], $item['ItemID']]);
            }

            // C. Close Session
            $sql_update = "UPDATE borrowing_sessions SET Status = 'Returned', has_damage = 0, Remarks = 'Returned in good condition' WHERE SessionID = ?";
            $this->db->prepare($sql_update)->execute([$session_id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // Place this inside DataManager class

    // Check if a student has any unresolved liabilities (damages)
    public function checkLiability($student_id) {
        // Returns an array with status (bool) and details (array of items)
        
        $stmt = $this->conn->prepare("
            SELECT dr.*, i.Item_Name, bs.SessionID, bs.Date_Returned
            FROM damaged_returns dr
            JOIN inventory i ON dr.item_id = i.ItemID
            JOIN borrowing_sessions bs ON dr.session_id = bs.SessionID
            WHERE dr.student_id = ? AND dr.status = 'Unresolved'
        ");
        
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $liabilities = [];
        while ($row = $result->fetch_assoc()) {
            $liabilities[] = $row;
        }
        
        return [
            'has_liability' => count($liabilities) > 0,
            'items' => $liabilities
        ];
    }

// Inside class DataManager
// Get all students enrolled in a class
public function getEnrolledStudents($class_id) {
        if (!$this->db) { return []; }

        try {
            $sql = "SELECT 
                        ce.EnrollmentID, 
                        ce.ClearanceStatus, 
                        m.MasterID,      /* <--- ADDED THIS */
                        m.Full_Name, 
                        m.ID_Number 
                    FROM class_enrollment ce
                    JOIN lookup_masterlist m ON ce.MasterID = m.MasterID
                    WHERE ce.ClassID = ?
                    ORDER BY m.Full_Name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$class_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Get a list of damages for a specific student
    public function getStudentDamages($master_id) {
        if (!$this->db) { return []; }

        try {
            $sql = "SELECT 
                        dr.damage_id,
                        i.ItemName,
                        dr.damage_type,
                        dr.qty_damaged,
                        dr.logged_at as ReturnDate, /* Alias for JS compatibility */
                        dr.notes as Remarks         /* Alias for JS compatibility */
                    FROM damaged_returns dr
                    JOIN users u ON dr.student_id = u.UserID
                    JOIN inventory i ON dr.item_id = i.ItemID
                    WHERE u.MasterID = ? 
                    AND dr.status = 'Unresolved'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$master_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Update the clearance status of a student
    public function updateClearanceStatus($enrollment_id, $new_status) {
        if (!$this->db) { return false; }
        
        try {
            $sql = "UPDATE class_enrollment SET ClearanceStatus = ? WHERE EnrollmentID = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$new_status, $enrollment_id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // Check if a student has any unresolved damages based on enrollment ID
    public function hasUnresolvedDamages($enrollment_id) {
        if (!$this->db) { return false; }

        try {
            $sql = "SELECT COUNT(*) 
                    FROM damaged_returns dr
                    JOIN users u ON dr.student_id = u.UserID
                    JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                    JOIN class_enrollment ce ON ce.MasterID = m.MasterID
                    WHERE ce.EnrollmentID = ? 
                    AND dr.status = 'Unresolved'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$enrollment_id]);
            
            // If count > 0, they have damages
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

   // Get full borrowing history for a student, including damage details
   public function getStudentHistoryWithDetails($student_id) {
        if (!$this->db) { return []; }

        // A. Get all sessions
        $sql = "SELECT bs.*, COALESCE(la.Title, 'Independent Research') as Title 
                FROM borrowing_sessions bs
                LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
                WHERE bs.StudentID = ? 
                ORDER BY bs.CreatedAt DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$student_id]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // B. Loop through and attach Items & Liability Status
        foreach ($sessions as &$session) {
            $sid = $session['SessionID'];

            // Fetch Items
            $iStmt = $this->db->prepare("SELECT i.Item_Name, bi.Quantity 
                                         FROM borrowed_items bi 
                                         JOIN inventory i ON bi.ItemID = i.ItemID 
                                         WHERE bi.SessionID = ?");
            $iStmt->execute([$sid]);
            $session['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);

            // NEW: Added 'evidence_image' to SELECT list
            $dStmt = $this->db->prepare("SELECT damage_id, status, evidence_image FROM damaged_returns 
                                         WHERE session_id = ? AND status != 'Resolved'");
            $dStmt->execute([$sid]);
            $damage = $dStmt->fetch(PDO::FETCH_ASSOC);

            // Attach to session array
            $session['liability_status'] = $damage ? 'HasLiability' : 'Clean';
            $session['damage_id'] = $damage ? $damage['damage_id'] : null;
            $session['damage_db_status'] = $damage ? $damage['status'] : null;
            // NEW: Attach evidence image for student view
            $session['evidence_image'] = $damage ? $damage['evidence_image'] : null;
        }

        return $sessions;
    }

    // Submit proof of payment/replacement for a damaged item
    public function submitDamageProof($damage_id, $file) {
        if (!$this->db) { return "Database Error"; }

        $target_dir = __DIR__ . "/../uploads/settlements/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $new_name = "proof_" . $damage_id . "_" . time() . "." . $ext;
        $target_file = $target_dir . $new_name;

        // Validation
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) { return "Invalid file type."; }
        if ($file["size"] > 5000000) { return "File too large (Max 5MB)."; }

        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            try {
                $sql = "UPDATE damaged_returns SET proof_image = ?, status = 'Under Review' WHERE damage_id = ?";
                $this->db->prepare($sql)->execute([$new_name, $damage_id]);
                return true;
            } catch (PDOException $e) { return "Database error."; }
        }
        return "Upload failed.";
    }

// Get settlement cases (damages) for admin/teacher view
public function getSettlementCases($view = 'pending', $search = '', $class_id = '') {
        if (!$this->db) { die("Database connection missing."); }

        // 1. Status Filter
        $statusCondition = ($view === 'history') 
            ? "dr.status = 'Resolved'" 
            : "dr.status IN ('Unresolved', 'Under Review')";

        $params = [];
        $searchLogic = "";
        $classLogic = "";

        // 2. Search Filter
        if (!empty($search)) {
            $searchLogic = "AND (m.ID_Number LIKE ? OR m.Full_Name LIKE ? OR i.Item_Name LIKE ?)";
            $term = "%$search%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        // 3. Class Filter
        if (!empty($class_id)) {
            if ($class_id === 'general') {
                $classLogic = "AND aa.ClassID IS NULL";
            } else {
                $classLogic = "AND aa.ClassID = ?";
                $params[] = $class_id;
            }
        }

        // 4. Main Query
        $sql = "SELECT 
                    dr.*, 
                    i.Item_Name, 
                    m.Full_Name, 
                    m.ID_Number,
                    bs.CreatedAt as SlipDate,
                    COALESCE(la.Title, 'General Laboratory Use') as ActivityTitle,
                    bs.QR_Code_Data,
                    bs.Status as SlipStatus,
                    c.Class_Name, 
                    c.Section
                FROM damaged_returns dr
                JOIN inventory i ON dr.item_id = i.ItemID
                JOIN users u ON dr.student_id = u.UserID
                JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                JOIN borrowing_sessions bs ON dr.session_id = bs.SessionID
                LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
                LEFT JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                LEFT JOIN classes c ON aa.ClassID = c.ClassID
                WHERE $statusCondition 
                $searchLogic 
                $classLogic
                ORDER BY dr.logged_at DESC"; // Changed back to logged_at to be safe

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $raw_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // --- DEBUG OUTPUT ---
            echo "<div style='background:red; color:white; padding:20px; z-index:9999; position:relative;'>";
            echo "<strong>SQL ERROR:</strong> " . $e->getMessage() . "<br><br>";
            echo "<strong>Query:</strong> <pre>" . $sql . "</pre>";
            echo "<strong>Params:</strong> "; print_r($params);
            echo "</div>";
            die(); 
            // --------------------
        }

        // 5. PHP Aggregation
        $cases = [];
        foreach ($raw_rows as $row) {
            // Using 'damage_id' as key. 
            // IF THIS KEY DOES NOT EXIST IN DB, THIS WILL CAUSE AN ISSUE.
            // Check if your table uses 'id' or 'DamageID' instead.
            $id = $row['damage_id']; 
            
            if (!isset($cases[$id])) {
                $cases[$id] = $row;
                $cases[$id]['ClassInfo'] = [];
            }

            if (!empty($row['Class_Name'])) {
                $className = $row['Class_Name'] . ' - ' . $row['Section'];
                if (!in_array($className, $cases[$id]['ClassInfo'])) {
                    $cases[$id]['ClassInfo'][] = $className;
                }
            }
        }

        // 6. Final Formatting
        foreach ($cases as &$case) {
            $case['ClassInfo'] = !empty($case['ClassInfo']) ? implode(', ', $case['ClassInfo']) : '';

            $sid = $case['session_id'];
            $iStmt = $this->db->prepare("SELECT i.Item_Name, bi.Quantity 
                                         FROM borrowed_items bi 
                                         JOIN inventory i ON bi.ItemID = i.ItemID 
                                         WHERE bi.SessionID = ?");
            $iStmt->execute([$sid]);
            $case['slip_items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return array_values($cases);
    }

    // Mark a damage case as resolved
    public function resolveDamage($damage_id) {
        if (!$this->db) { return false; }
        try {
            $sql = "UPDATE damaged_returns SET status = 'Resolved' WHERE damage_id = ?";
            return $this->db->prepare($sql)->execute([$damage_id]);
        } catch (PDOException $e) { return false; }
    }

    // Reject proof of settlement (Student must re-upload)
    public function rejectDamage($damage_id) {
        if (!$this->db) { return false; }
        try {
            // Reset status to Unresolved and clear the image proof
            $sql = "UPDATE damaged_returns SET status = 'Unresolved', proof_image = NULL WHERE damage_id = ?";
            return $this->db->prepare($sql)->execute([$damage_id]);
        } catch (PDOException $e) { return false; }
    }

 // Create a new lab activity
public function createActivity($title, $description, $deadline, $manualURL, $type, $sub_mode, $group_mode, $limit) {
    try {
        // 'Manual_URL' is the column that stores the PDF path
        $sql = "INSERT INTO lab_activities 
                (Title, Description, Deadline, Manual_URL, type, submission_mode, grouping_mode, group_limit) 
                VALUES 
                (:title, :desc, :deadline, :manual, :type, :sub_mode, :grp_mode, :limit)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title'    => $title,
            ':desc'     => $description,
            ':deadline' => $deadline,
            ':manual'   => $manualURL, // Stores 'uploads/manuals/filename.pdf'
            ':type'     => $type,
            ':sub_mode' => $sub_mode,
            ':grp_mode' => $group_mode,
            ':limit'    => $limit 
        ]);
        
        return $this->db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Create Activity Error: " . $e->getMessage());
        return false;
    }
}
    
    // Check if a student is already in a group for an activity
    public function getStudentGroupStatus($activityID, $masterID) {
        $sql = "SELECT g.GroupName, g.GroupID, gm.Is_Leader 
                FROM group_members gm
                JOIN activity_groups g ON gm.GroupID = g.GroupID
                WHERE g.ActivityID = :aid AND gm.MasterID = :mid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':aid' => $activityID, ':mid' => $masterID]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get members of a specific group
   public function getGroupMembers($groupID) {
    // We explicitly select 'm.MasterID' to ensure the key exists in the array
    $sql = "SELECT m.MasterID, m.Is_Leader, u.Full_Name 
            FROM group_members m
            JOIN lookup_masterlist u ON m.MasterID = u.MasterID
            WHERE m.GroupID = ?";
            
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$groupID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // Get classmates who are available to join a group
    public function getAvailableClassmates($activityID, $classID, $myMasterID) {
        $sql = "SELECT m.MasterID, m.Full_Name 
                FROM class_enrollment e
                JOIN lookup_masterlist m ON e.MasterID = m.MasterID
                WHERE e.ClassID = :cid 
                AND m.MasterID != :myid
                AND m.MasterID NOT IN (
                    SELECT gm.MasterID 
                    FROM group_members gm 
                    JOIN activity_groups ag ON gm.GroupID = ag.GroupID 
                    WHERE ag.ActivityID = :aid
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':cid' => $classID, 
            ':myid' => $myMasterID,
            ':aid' => $activityID
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create a new group (Student Initiated)
    public function createStudentGroup($activityID, $groupName, $leaderMasterID, $memberMasterIDs) {
        try {
            $this->db->beginTransaction();

            // 1. Create Group Container
            $gSql = "INSERT INTO activity_groups (ActivityID, GroupName) VALUES (:aid, :name)";
            $stmt = $this->db->prepare($gSql);
            $stmt->execute([':aid' => $activityID, ':name' => $groupName]);
            $groupID = $this->db->lastInsertId();

            // 2. Add Leader (The Creator)
            $mSql = "INSERT INTO group_members (GroupID, MasterID, Is_Leader) VALUES (:gid, :mid, 1)";
            $stmt = $this->db->prepare($mSql);
            $stmt->execute([':gid' => $groupID, ':mid' => $leaderMasterID]);

            // 3. Add Selected Members
            $mSqlMembers = "INSERT INTO group_members (GroupID, MasterID, Is_Leader) VALUES (:gid, :mid, 0)";
            $stmtMembers = $this->db->prepare($mSqlMembers);
            
            foreach ($memberMasterIDs as $mid) {
                $stmtMembers->execute([':gid' => $groupID, ':mid' => $mid]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Student Group Create Error: " . $e->getMessage());
            return false;
        }
    }
    // --- GROUPING LOGIC FUNCTIONS ---

    // Helper: Get MasterID from UserID
    public function getMasterID($userID) {
        $stmt = $this->db->prepare("SELECT MasterID FROM users WHERE UserID = :uid");
        $stmt->execute([':uid' => $userID]);
        return $stmt->fetchColumn();
    }

    // 2. Helper: Get Class ID for a logged-in Student
    public function getStudentClassID($userID) {
        try {
            $masterID = $this->getMasterID($userID);
            if (!$masterID) return false;
            $sql = "SELECT ClassID FROM class_enrollment WHERE MasterID = :mid LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':mid' => $masterID]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) { return false; }
    }

    // Automatically generate groups based on student stats (Smart Grouping)
  public function generateSmartGroups($activityID, $classID, $limit) {
        try {
            // Fetch Students with Stats
            $sql = "SELECT e.MasterID, 
                           COALESCE(s.Total_Points, 0) as Points, 
                           COALESCE(s.Avg_Contribution, 0) as Contrib 
                    FROM class_enrollment e
                    LEFT JOIN student_stats s ON e.MasterID = s.MasterID
                    WHERE e.ClassID = :cid
                    ORDER BY Contrib DESC, Points DESC"; 
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':cid' => $classID]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalStudents = count($students);
            if ($totalStudents === 0) return false;

            $numGroups = ceil($totalStudents / $limit);
            
            // Create Group Containers
            $groupIds = [];
            for ($i = 1; $i <= $numGroups; $i++) {
                $groupName = "Group " . $i;
                $this->manualCreateGroup($activityID, $groupName);
                
                // Capture the ID of the new group
                $newGroupID = $this->db->lastInsertId();
                $groupIds[] = $newGroupID;
            }

            // Distribute Students (Snake Pattern)
            $currentGroupIndex = 0;
            $direction = 1; 

            foreach ($students as $student) {
                $targetGroupID = $groupIds[$currentGroupIndex];
                $this->manualAddMember($targetGroupID, $student['MasterID']);

                $currentGroupIndex += $direction;
                if ($currentGroupIndex >= $numGroups) {
                    $currentGroupIndex = $numGroups - 1;
                    $direction = -1; 
                } elseif ($currentGroupIndex < 0) {
                    $currentGroupIndex = 0;
                    $direction = 1; 
                }
            }
            
            // Trigger Leader Nomination
            if (!empty($groupIds)) {
                $this->autoNominateLeaders($groupIds); 
            }
            return true;
        } catch (PDOException $e) { return false; }
    }

    // Automatically select the best leader for each group
    public function autoNominateLeaders($groupIds) {
        try {
            foreach ($groupIds as $gid) {
                $sql = "SELECT gm.MemberID 
                        FROM group_members gm
                        LEFT JOIN student_stats ss ON gm.MasterID = ss.MasterID
                        WHERE gm.GroupID = :gid
                        ORDER BY COALESCE(ss.Leader_Count, 0) ASC, COALESCE(ss.Avg_Contribution, 0) DESC
                        LIMIT 1";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':gid' => $gid]);
                $bestCandidate = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($bestCandidate) {
                    $updateSql = "UPDATE group_members SET Is_Leader = 1 WHERE MemberID = :mid";
                    $this->db->prepare($updateSql)->execute([':mid' => $bestCandidate['MemberID']]);
                }
            }
            return true;
        } catch (PDOException $e) { return false; }
    }

    // --- MANUAL GROUP MANAGEMENT FUNCTIONS ---

    // Manually create a group (Teacher)
    public function manualCreateGroup($activityID, $name) {
        $stmt = $this->db->prepare("INSERT INTO activity_groups (ActivityID, GroupName) VALUES (:aid, :name)");
        return $stmt->execute([':aid' => $activityID, ':name' => $name]);
    }

    // Manually add a member to a group
    public function manualAddMember($groupID, $masterID) {
        $stmt = $this->db->prepare("INSERT INTO group_members (GroupID, MasterID) VALUES (:gid, :mid)");
        return $stmt->execute([':gid' => $groupID, ':mid' => $masterID]);
    }

    // Manually remove a member from a group
    public function manualRemoveMember($memberID) {
        $stmt = $this->db->prepare("DELETE FROM group_members WHERE MemberID = :mid");
        return $stmt->execute([':mid' => $memberID]);
    }

    // Manually set the leader of a group
    public function manualSetLeader($groupID, $memberID) {
        // Ensures ONLY ONE leader per group
        $this->db->beginTransaction();
        try {
            // 1. Reset everyone in this group to 0
            $s1 = $this->db->prepare("UPDATE group_members SET Is_Leader = 0 WHERE GroupID = :gid");
            $s1->execute([':gid' => $groupID]);
            
            // 2. Set the specific member to 1 using subquery to find MemberID
            $s2 = $this->db->prepare("UPDATE group_members SET Is_Leader = 1 
                                      WHERE GroupID = :gid AND MasterID = :mid");
            $s2->execute([':gid' => $groupID, ':mid' => $memberID]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // Delete a group entirely
    public function manualDeleteGroup($groupID) {
        $stmt = $this->db->prepare("DELETE FROM activity_groups WHERE GroupID = :gid");
        return $stmt->execute([':gid' => $groupID]);
    }

    // Get all groups and their members for an activity
    public function getFullGroupStructure($activityID) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM activity_groups WHERE ActivityID = :aid ORDER BY GroupName");
            $stmt->execute([':aid' => $activityID]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($groups as &$group) {
                $mStmt = $this->db->prepare("
                    SELECT gm.*, m.Full_Name, m.MasterID
                    FROM group_members gm
                    JOIN lookup_masterlist m ON gm.MasterID = m.MasterID
                    WHERE gm.GroupID = :gid
                    ORDER BY gm.Is_Leader DESC, m.Full_Name ASC
                ");
                $mStmt->execute([':gid' => $group['GroupID']]);
                $group['members'] = $mStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return $groups;
        } catch (PDOException $e) { return []; }
    }

    // Get students who are not yet assigned to any group
    public function getUngroupedStudents($activityID, $classID) {
        try {
            $sql = "SELECT e.MasterID, m.Full_Name 
                    FROM class_enrollment e
                    JOIN lookup_masterlist m ON e.MasterID = m.MasterID
                    WHERE e.ClassID = :cid 
                    AND e.MasterID NOT IN (
                        SELECT gm.MasterID 
                        FROM group_members gm 
                        JOIN activity_groups ag ON gm.GroupID = ag.GroupID 
                        WHERE ag.ActivityID = :aid
                    )
                    ORDER BY m.Full_Name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':cid' => $classID, ':aid' => $activityID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }
    // Add this INSIDE class DataManager { ... }

// Get a list of students from multiple classes (for Roster generation)
public function getStudentsByClassList($classIdArray) {
    if (empty($classIdArray)) return [];

    // 1. Auto-detect connection
    $conn = null;
    if (isset($this->conn)) { $conn = $this->conn; }
    elseif (isset($this->connection)) { $conn = $this->connection; }
    elseif (isset($this->pdo)) { $conn = $this->pdo; }
    elseif (isset($this->db)) { $conn = $this->db; }

    if (!$conn) {
        die(json_encode(['error' => "Database connection not found."]));
    }

    $ids = array_map('intval', $classIdArray);
    $placeholders = implode(',', $ids);

    // 2. CORRECT SQL (Based on your snhs_inventory.sql schema)
    // We join 'class_enrollment' (ce) with 'lookup_masterlist' (m)
    $sql = "SELECT 
                m.MasterID, 
                m.Full_Name,
                c.Class_Name
            FROM class_enrollment ce
            JOIN lookup_masterlist m ON ce.MasterID = m.MasterID
            JOIN classes c ON ce.ClassID = c.ClassID
            WHERE ce.ClassID IN ($placeholders)
            AND m.Role = 'Student'
            ORDER BY c.Class_Name, m.Full_Name";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    // --- STUDENT STATUS ENGINE ---

    // Check a student's status for a specific activity (Group, Leader, etc.)
    public function getStudentActivityStatus($activityID, $studentID) {
        // 1. Check Group Membership
        $groupSql = "SELECT GroupID, Is_Leader FROM group_members 
                     WHERE MasterID = (SELECT MasterID FROM users WHERE UserID = ?) 
                     AND GroupID IN (SELECT GroupID FROM activity_groups WHERE ActivityID = ?)";
        $stmt = $this->db->prepare($groupSql);
        $stmt->execute([$studentID, $activityID]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);

        $submission = null;
        $groupID = $group['GroupID'] ?? null;

        return [
            'has_group' => !empty($groupID), // <--- FIXED: Replaced '?' with '!empty()'
            'group_id' => $groupID,
            'is_leader' => $group['Is_Leader'] ?? 0,
            'submission' => null,
            'status' => 'Pending',
            'grade' => null,
            'feedback' => null,
            'is_locked' => false 
        ];
    }

    // --- LOGISTICS HUB LOGIC ---

    // Get the list of required items and how many have been distributed
    public function getLogisticsOverview($activityID, $groupID) {
        // Fetch Teacher's Requirements
        $reqs = $this->getActivityRequirements($activityID);
        
        // Fetch what has already been assigned
        $sql = "SELECT ItemID, SUM(Quantity) as Assigned_Qty FROM group_logistics 
                WHERE ActivityID = ? AND GroupID = ? GROUP BY ItemID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$activityID, $groupID]);
        $assigned = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [ItemID => Qty]

        // Merge Data
        foreach ($reqs as &$item) {
            $item['Distributed'] = $assigned[$item['ItemID']] ?? 0;
            $item['Remaining'] = max(0, $item['Required_Qty'] - $item['Distributed']);
        }
        return $reqs;
    }

    // Assign an item to a specific group member (Leader Action)
    public function distributeItem($activityID, $groupID, $itemID, $targetMasterID, $qty) {
        $sql = "INSERT INTO group_logistics (ActivityID, GroupID, ItemID, AssignedToMasterID, Quantity) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$activityID, $groupID, $itemID, $targetMasterID, $qty]);
    }

    // Get the items assigned to the logged-in student
    public function getMyAssignedItems($activityID, $groupID, $myMasterID) {
        $sql = "SELECT gl.ItemID, gl.Quantity as Required_Qty, i.Item_Name 
                FROM group_logistics gl
                JOIN inventory i ON gl.ItemID = i.ItemID
                WHERE gl.ActivityID = ? AND gl.GroupID = ? AND gl.AssignedToMasterID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$activityID, $groupID, $myMasterID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Check if all items have been distributed to members
    public function getDistributionStats($activityID, $groupID) {
        // 1. Check Remaining Items (We keep this)
        $reqs = $this->getLogisticsOverview($activityID, $groupID);
        $remainingItems = 0;
        foreach($reqs as $r) { $remainingItems += $r['Remaining']; }

        // 2. Check Unassigned Members (DISABLED for flexibility)
        // We set this to empty so it doesn't block the button
        $freeloaders = []; 

        return [
            // Logic Change: Only check if remaining items are 0
            'is_complete' => ($remainingItems == 0), 
            'remaining_items_count' => $remainingItems,
            'freeloaders' => $freeloaders
        ];
    }
    
}
}
