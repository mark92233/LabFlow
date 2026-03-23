<?php
require_once __DIR__ . '/db_connect.php';

if (!class_exists('DataManager')) {
/**
 * Custom Exception for cases requiring user confirmation.
 */
class ConfirmationRequiredException extends Exception {
    protected $data;
    public function __construct($message = "", $data = [], $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }
    public function getData() {
        return $this->data;
    }
}

class DataManager {
    public $db;
    private $lastError = null;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            // The getConnection method now throws an exception on failure, so we don't need to check for a null return.
        } catch (PDOException $e) {
            // Catch the specific PDOException from db_connect and re-throw it to be handled by the AJAX script.
            throw new Exception("Database Initialization Failed: " . $e->getMessage());
        }
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function getUserProfileData($userId) {
        try {
            $query = "SELECT u.UserID, u.MasterID, u.Confirmed_Email, m.Full_Name, m.ID_Number, m.Role
                      FROM users u
                      JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                      WHERE u.UserID = :uid LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getUserProfileData Error: " . $e->getMessage());
            return null;
        }
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
            $this->db->beginTransaction();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (MasterID, Confirmed_Email, Password_Hash, Is_Verified) 
                      VALUES (:mid, :email, :pass, 1)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':mid', $master_id);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':pass', $hashed_password);
            $stmt->execute();

            $updateQuery = "UPDATE lookup_masterlist SET is_verified = 1 WHERE MasterID = :mid";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':mid', $master_id);
            $updateStmt->execute();

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Registration Error: " . $e->getMessage());
            return false;
        }
    }

    // Update a user's password using their MasterID
    public function updatePasswordByMasterId($masterId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET Password_Hash = :password WHERE MasterID = :master_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['password' => $hashedPassword, 'master_id' => $masterId]);
    }

    // Fetch all classes a specific student is currently enrolled in
    public function getStudentEnrolledClasses($studentUserID) {
        try {
            $query = "SELECT c.*, m.Full_Name as TeacherName, ce.ClearanceStatus,
                             (SELECT COUNT(*) FROM activity_assignments aa WHERE aa.ClassID = c.ClassID) as ActivityCount
                      FROM class_enrollment ce
                      JOIN classes c ON ce.ClassID = c.ClassID
                      JOIN users u_teacher ON c.TeacherID = u_teacher.UserID
                      JOIN lookup_masterlist m ON u_teacher.MasterID = m.MasterID
                      JOIN users u_student ON ce.MasterID = u_student.MasterID
                      WHERE u_student.UserID = :sid
                      ORDER BY c.Class_Name ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['sid' => $studentUserID]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // For each class, fetch upcoming deadlines
            $deadlineStmt = $this->db->prepare(
                "SELECT la.Title, la.Deadline, la.ActivityID
                 FROM lab_activities la
                 JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                 WHERE aa.ClassID = :cid AND la.Deadline >= NOW()
                 ORDER BY la.Deadline ASC
                 LIMIT 3"
            );
            foreach ($classes as &$class) {
                $deadlineStmt->execute(['cid' => $class['ClassID']]);
                $class['upcoming_deadlines'] = $deadlineStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $classes;
        } catch (PDOException $e) {
            error_log("Student Dash Error: " . $e->getMessage());
            return [];
        }
    }

    // --- ADMIN INVENTORY MANAGEMENT ---
    // Create a new category for inventory items
    public function addCategory($name, $isConsumable = 0)
    {
        try {
            $query = "INSERT INTO categories (Category_Name, is_consumable) VALUES (:name, :is_consumable)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':name', $name, \PDO::PARAM_STR);
            $stmt->bindValue(':is_consumable', (int) $isConsumable, \PDO::PARAM_INT);

            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }

            return false;
        } catch (\PDOException $e) {
            // error_log($e->getMessage()); // Optional: for server-side logging
            return false;
        }
    }

    // Fetch all available categories
    public function getCategories() {
        $query = "SELECT * FROM categories ORDER BY Category_Name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch categories filtered by their nature (consumable or not)
    public function getCategoriesByType($isConsumable) {
        $query = "SELECT * FROM categories WHERE is_consumable = :is_consumable ORDER BY Category_Name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':is_consumable' => $isConsumable]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add a new item to the inventory
    public function addItem($catId, $name, $desc, $isConsumable, $isScalable, $qty = 0, $location = null, $unit = null) {
        // Sanitize location: if it's an empty string, it must be NULL for the foreign key.
        $shelfLocation = empty($location) ? null : $location;

        $query = "INSERT INTO inventory (CategoryID, Item_Name, Description, is_consumable, is_scalable, Total_Qty, Available_Qty, shelf_id, Unit) 
                  VALUES (:cid, :name, :desc, :consumable, :scalable, :tqty, :aqty, :loc, :unit)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'cid' => $catId,
            'name' => $name,
            'desc' => $desc,
            'consumable' => $isConsumable,
            'scalable' => $isScalable,
            'tqty' => $qty,
            'aqty' => $qty, 
            'loc' => $shelfLocation,
            'unit' => $unit
        ]);
        return $this->db->lastInsertId();
    }

    // Add a size variant for a scalable item
    public function addVariant($itemId, $size, $unit, $qty) {
        $query = "INSERT INTO item_variants (ItemID, Size_Value, Unit, Variant_Total_Qty, Variant_Available_Qty)
                  VALUES (:itemId, :size, :unit, :qty, :qty)";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'itemId' => $itemId,
            'size' => $size,
            'unit' => $unit,
            'qty' => $qty
        ]);
    }

    // Update the parent inventory item's total quantity based on its variants
    public function updateInventoryTotalFromVariants($itemId) {
        $query = "UPDATE inventory i SET
                    i.Total_Qty = (SELECT SUM(iv.Variant_Total_Qty) FROM item_variants iv WHERE iv.ItemID = :itemId),
                    i.Available_Qty = (SELECT SUM(iv.Variant_Available_Qty) FROM item_variants iv WHERE iv.ItemID = :itemId)
                  WHERE i.ItemID = :itemId";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['itemId' => $itemId]);
    }

    /**
     * Update an existing inventory item's details.
     * This handles changes to name, description, location, and total quantity,
     * while safely adjusting the available quantity.
     * @param int $itemId The ID of the item to update.
     * @param string $itemName The new name for the item.
     * @param string $description The new description.
     * @param string $location The new location.
     * @param int $totalQty The new total quantity.
     * @return bool True on success, false on failure.
     */
    public function updateInventoryItem($itemId, $itemName, $description, $location, $totalQty) {
        try {
            $this->db->beginTransaction();

            // 1. Get current item state to calculate quantity difference
            $stmt = $this->db->prepare("SELECT Total_Qty, Available_Qty FROM inventory WHERE ItemID = :id FOR UPDATE");
            $stmt->execute([':id' => $itemId]);
            $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentItem) {
                $this->db->rollBack();
                return false; // Item not found
            }

            // 2. Calculate the difference and the new available quantity
            $qtyDifference = $totalQty - (int)$currentItem['Total_Qty'];
            $newAvailableQty = (int)$currentItem['Available_Qty'] + $qtyDifference;

            // 3. Safety check: prevent available quantity from going negative
            if ($newAvailableQty < 0) {
                $this->lastError = "Update failed: New total quantity is less than the number of items currently borrowed.";
                $this->db->rollBack();
                return false;
            }

            // Sanitize location: if it's an empty string, it must be NULL for the foreign key.
            $shelfLocation = empty($location) ? null : $location;

            // 4. Prepare and execute the UPDATE statement
            $query = "UPDATE inventory SET Item_Name = :name, Description = :desc, shelf_id = :loc, Total_Qty = :tqty, Available_Qty = :aqty WHERE ItemID = :id";
            $updateStmt = $this->db->prepare($query);
            $updateStmt->execute([':name' => $itemName, ':desc' => $description, ':loc' => $shelfLocation, ':tqty' => $totalQty, ':aqty' => $newAvailableQty, ':id' => $itemId]);

            $this->db->commit();
            return $updateStmt->rowCount() > 0;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Inventory Update Error: " . $e->getMessage());
            $this->lastError = "A database error occurred during the update.";
            return false;
        }
    }

    /**
     * Updates a scalable inventory item and its variants.
     * @param int $itemId The ID of the parent item.
     * @param string $itemName The new name for the item.
     * @param string $description The new description.
     * @param array $variantsData Data from the form about variants.
     * @return bool True on success, false on failure.
     */
    public function updateScalableInventoryItem($itemId, $itemName, $description, $variantsData) {
        try {
            $this->db->beginTransaction();

            // 1. Update parent item's text fields
            $parentStmt = $this->db->prepare("UPDATE inventory SET Item_Name = :name, Description = :desc WHERE ItemID = :id");
            $parentStmt->execute([':name' => $itemName, ':desc' => $description, ':id' => $itemId]);

            // 2. Get all current variant IDs for this item from the DB
            $stmt = $this->db->prepare("SELECT VariantID FROM item_variants WHERE ItemID = :id");
            $stmt->execute([':id' => $itemId]);
            $existingVariantIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $submittedVariantIds = [];

            // 3. Process submitted variants (update existing, insert new)
            $updateStmt = $this->db->prepare("UPDATE item_variants SET Size_Value = :size, Unit = :unit, Variant_Total_Qty = :qty, Variant_Available_Qty = Variant_Available_Qty + (:qty - Variant_Total_Qty) WHERE VariantID = :vid");
            $insertStmt = $this->db->prepare("INSERT INTO item_variants (ItemID, Size_Value, Unit, Variant_Total_Qty, Variant_Available_Qty) VALUES (:itemId, :size, :unit, :qty, :qty)");

            foreach ($variantsData as $key => $variant) {
                $size = trim($variant['size']);
                $unit = trim($variant['unit']);
                $qty = (int)$variant['qty'];

                if (empty($size) && $qty == 0) continue; // Skip empty new rows

                if (is_numeric($key)) { // Existing variant
                    $variantId = (int)$key;
                    $submittedVariantIds[] = $variantId;

                    $checkStmt = $this->db->prepare("SELECT (Variant_Available_Qty + (:qty - Variant_Total_Qty)) as new_avail FROM item_variants WHERE VariantID = :vid FOR UPDATE");
                    $checkStmt->execute([':qty' => $qty, ':vid' => $variantId]);
                    if ($checkStmt->fetchColumn() < 0) {
                        $this->lastError = "Update for size '{$size}{$unit}' failed: New total quantity is less than items currently borrowed.";
                        $this->db->rollBack();
                        return false;
                    }
                    $updateStmt->execute([':size' => $size, ':unit' => $unit, ':qty' => $qty, ':vid' => $variantId]);
                } else { // New variant
                    $insertStmt->execute([':itemId' => $itemId, ':size' => $size, ':unit' => $unit, ':qty' => $qty]);
                }
            }

            // 4. Delete variants that were removed in the UI
            $variantsToDelete = array_diff($existingVariantIds, $submittedVariantIds);
            if (!empty($variantsToDelete)) {
                $qMarks = str_repeat('?,', count($variantsToDelete) - 1) . '?';
                $checkBorrowedStmt = $this->db->prepare("SELECT COUNT(*) FROM item_variants WHERE VariantID IN ($qMarks) AND Variant_Available_Qty < Variant_Total_Qty");
                $checkBorrowedStmt->execute($variantsToDelete);
                if ($checkBorrowedStmt->fetchColumn() > 0) {
                     $this->lastError = "Cannot delete a size that has items currently borrowed.";
                     $this->db->rollBack();
                     return false;
                }
                $deleteStmt = $this->db->prepare("DELETE FROM item_variants WHERE VariantID IN ($qMarks)");
                $deleteStmt->execute($variantsToDelete);
            }

            // 5. Recalculate and update parent inventory totals
            $this->updateInventoryTotalFromVariants($itemId);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Scalable Inventory Update Error: " . $e->getMessage());
            $this->lastError = "A database error occurred during the update.";
            return false;
        }
    }

    /**
     * Deletes an inventory item and its variants if applicable.
     * Checks for dependencies before deleting.
     * @param int $itemId The ID of the item to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteInventoryItem($itemId) {
        try {
            $this->db->beginTransaction();

            // 1. Get item details
            $stmt = $this->db->prepare("SELECT is_scalable, Available_Qty, Total_Qty FROM inventory WHERE ItemID = :id FOR UPDATE");
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $this->lastError = "Item not found.";
                $this->db->rollBack();
                return false;
            }

            // 2. Check if item is a requirement for any lab activities
            $checkActivity = $this->db->prepare("SELECT COUNT(*) FROM activity_requirements WHERE ItemID = :id");
            $checkActivity->execute([':id' => $itemId]);
            if ($checkActivity->fetchColumn() > 0) {
                 $this->lastError = "Cannot delete item. It is required by one or more lab activities. Please remove it from the activities first.";
                 $this->db->rollBack();
                 return false;
            }

            // 3. Check if item is currently borrowed
            if ($item['is_scalable'] == 1) {
                // For scalable items, check if any variant has items borrowed
                $variantCheckStmt = $this->db->prepare("SELECT SUM(Variant_Total_Qty) as total, SUM(Variant_Available_Qty) as available FROM item_variants WHERE ItemID = :id");
                $variantCheckStmt->execute([':id' => $itemId]);
                $variantQtys = $variantCheckStmt->fetch(PDO::FETCH_ASSOC);

                if ($variantQtys && $variantQtys['available'] < $variantQtys['total']) {
                    $this->lastError = "Cannot delete item. Some variants are currently borrowed.";
                    $this->db->rollBack();
                    return false;
                }
                // Delete variants first
                $deleteVariantsStmt = $this->db->prepare("DELETE FROM item_variants WHERE ItemID = :id");
                $deleteVariantsStmt->execute([':id' => $itemId]);
            } elseif ($item['Available_Qty'] < $item['Total_Qty']) {
                $this->lastError = "Cannot delete item. Some units are currently borrowed.";
                $this->db->rollBack();
                return false;
            }
            
            // 4. Delete the main item
            $deleteItemStmt = $this->db->prepare("DELETE FROM inventory WHERE ItemID = :id");
            $deleteItemStmt->execute([':id' => $itemId]);

            $this->db->commit();

            // 5. Clean up image file
            $imagePath = __DIR__ . "/../assets/img/items/{$itemId}.png";
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }

            return $deleteItemStmt->rowCount() > 0;

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Inventory Delete Error: " . $e->getMessage());
            $this->lastError = "A database error occurred. The item might be referenced in past borrowing records.";
            return false;
        }
    }

    /**
     * Imports or updates an inventory item from an array of data (e.g., from a CSV row).
     * @param array $data The associative array of item data.
     * @return bool True on success, false on failure.
     */
    public function importInventoryItem(array $data) {
        try {
            $this->db->beginTransaction();

            // 1. Find or Create Category
            $stmt = $this->db->prepare("SELECT CategoryID FROM categories WHERE Category_Name = :name");
            $stmt->execute([':name' => $data['category']]);
            $categoryId = $stmt->fetchColumn();

            if (!$categoryId) {
                $catQuery = "INSERT INTO categories (Category_Name, is_consumable) VALUES (:name, :consumable)";
                $catStmt = $this->db->prepare($catQuery);
                $catStmt->execute([':name' => $data['category'], ':consumable' => $data['is_consumable']]);
                $categoryId = $this->db->lastInsertId();
            }

            // 2. Check if item exists
            $stmt = $this->db->prepare("SELECT ItemID FROM inventory WHERE Item_Name = :name");
            $stmt->execute([':name' => $data['name']]);
            $itemId = $stmt->fetchColumn();

            if ($itemId) {
                // Item exists: Update its core details
                $updateStmt = $this->db->prepare(
                    "UPDATE inventory SET Description = :desc, Location = :loc, CategoryID = :catId, is_consumable = :consumable, is_scalable = :scalable WHERE ItemID = :id"
                );
                $updateStmt->execute([
                    ':desc' => $data['description'],
                    ':loc' => $data['location'],
                    ':catId' => $categoryId,
                    ':consumable' => $data['is_consumable'],
                    ':scalable' => $data['is_scalable'],
                    ':id' => $itemId
                ]);
            } else {
                // Item does not exist: Create it with zero quantity initially
                $insertStmt = $this->db->prepare(
                    "INSERT INTO inventory (Item_Name, CategoryID, Description, Location, is_consumable, is_scalable, Total_Qty, Available_Qty) 
                     VALUES (:name, :catId, :desc, :loc, :consumable, :scalable, 0, 0)"
                );
                $insertStmt->execute([
                    ':name' => $data['name'],
                    ':catId' => $categoryId,
                    ':desc' => $data['description'],
                    ':loc' => $data['location'],
                    ':consumable' => $data['is_consumable'],
                    ':scalable' => $data['is_scalable']
                ]);
                $itemId = $this->db->lastInsertId();
            }

            // 3. Handle quantities based on scalability
            if ($data['is_scalable'] == 0) {
                // Non-scalable: Update the main inventory table quantities.
                // This assumes an overwrite. For a safer update, use logic from updateInventoryItem().
                $qtyStmt = $this->db->prepare("UPDATE inventory SET Total_Qty = :qty, Available_Qty = :qty WHERE ItemID = :id");
                $qtyStmt->execute([':qty' => $data['total_qty'], ':id' => $itemId]);
            } else {
                // Scalable: Clear old variants and insert new ones from the 'variants' string.
                $this->db->prepare("DELETE FROM item_variants WHERE ItemID = :id")->execute([':id' => $itemId]);

                if (!empty($data['variants'])) {
                    $variantPairs = explode(',', $data['variants']);
                    $variantInsertStmt = $this->db->prepare("INSERT INTO item_variants (ItemID, Size_Value, Unit, Variant_Total_Qty, Variant_Available_Qty) VALUES (:itemId, :size, :unit, :qty, :qty)");
                    foreach ($variantPairs as $pair) {
                        list($sizeInfo, $qty) = array_pad(explode(':', trim($pair)), 2, 0);
                        preg_match('/([0-9\.]+)\s*(.*)/', $sizeInfo, $matches);
                        $variantInsertStmt->execute([':itemId' => $itemId, ':size' => $matches[1] ?? '0', ':unit' => $matches[2] ?? '', ':qty' => (int)$qty]);
                    }
                }
                $this->updateInventoryTotalFromVariants($itemId);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->lastError = $e->getMessage();
            error_log("Inventory Import DB Error: " . $e->getMessage());
            return false;
        }
    }

    // --- TEACHER CLASS & MASTERLIST MANAGEMENT ---

    // Create a new class section
    public function createClass($teacherUserID, $className, $section, $semester) {
        // First, check if a class with the same details already exists for this teacher
        $checkQuery = "SELECT ClassID FROM classes WHERE TeacherID = :tid AND Class_Name = :cname AND Section = :sec AND Semester = :sem";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->execute(['tid' => $teacherUserID, 'cname' => $className, 'sec' => $section, 'sem' => $semester]);
        if ($checkStmt->fetch()) {
            $this->lastError = "A class with the same name, section, and semester already exists.";
            return false;
        }

        // If no duplicate, proceed with insertion
        $query = "INSERT INTO classes (TeacherID, Class_Name, Section, Semester) 
                  VALUES (:tid, :cname, :sec, :sem)";
        $stmt = $this->db->prepare($query);
        if ($stmt->execute(['tid' => $teacherUserID, 'cname' => $className, 'sec' => $section, 'sem' => $semester])) {
            return $this->db->lastInsertId();
        }
        $this->lastError = "A database error occurred during class creation.";
        return false;
    }

   // Get all classes assigned to a specific teacher
   public function getTeacherClasses($teacherUserID) {
    // FIXED: Ensure we select Class_Name here too
    $query = "SELECT * FROM classes WHERE TeacherID = :tid ORDER BY Class_Name, Section";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['tid' => $teacherUserID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    /**
     * Get all classes in the system (for Admin view).
     * @return array
     */
    public function getAllClasses() {
        $query = "SELECT * FROM classes ORDER BY Class_Name, Section";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get details for a single class
    public function getClassDetails($classID) {
        $query = "SELECT * FROM classes WHERE ClassID = :cid LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['cid' => $classID]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Add a user to the global masterlist (or update if exists).
     * If a class ID is provided for a student, it also enrolls them into that class.
     * This is now a single transaction to ensure data integrity.
     *
     * @param string $idNum The user's ID number.
     * @param string $fullName The user's full name.
     * @param string $email The user's email.
     * @param string $role The user's role ('Student', 'Teacher', etc.).
     * @param int|null $classID The optional class ID to enroll a student into.
     * @return int|false The MasterID on success, or false on failure.
     */
    public function uploadUserToMasterlist($idNum, $fullName, $email, $role, $classID = null) {
        if (!in_array($role, ['Student', 'Teacher', 'Admin', 'LabTech'])) {
            return false;
        }
        try {
            $this->db->beginTransaction();

            $query = "INSERT INTO lookup_masterlist (ID_Number, Full_Name, Official_Email, Role)
                      VALUES (:id, :name, :email, :role)
                      ON DUPLICATE KEY UPDATE Full_Name = VALUES(Full_Name), Official_Email = VALUES(Official_Email), Role = VALUES(Role)";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $idNum, 'name' => $fullName, 'email' => $email, 'role' => $role]);

            $check = "SELECT MasterID FROM lookup_masterlist WHERE ID_Number = :id";
            $cStmt = $this->db->prepare($check);
            $cStmt->execute(['id' => $idNum]);
            $masterID = $cStmt->fetchColumn();

            // If a classID is provided and the user is a student, enroll them.
            // Use !empty() to guard against null, 0, or empty strings for the ClassID.
            if ($masterID && !empty($classID) && $role === 'Student') {
                // The enrollByMasterID function will now throw an exception on failure,
                // which will be caught here and trigger a transaction rollback.
                $this->enrollByMasterID($masterID, $classID);
            }

            $this->db->commit();
            return $masterID;
        } catch (Exception $e) { 
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Masterlist Upload/Enrollment Error: " . $e->getMessage());
            
            // Create a more user-friendly, specific error message to throw
            $errorMessage = "Error for student '{$fullName}' (ID: {$idNum}): ";
            if (str_contains($e->getMessage(), '1452')) { // Foreign Key constraint fails
                $errorMessage .= "Enrollment failed. The Class ID '{$classID}' may be invalid or not exist.";
            } else {
                $errorMessage .= "A database error occurred during processing. The student was not added.";
            }
            throw new Exception($errorMessage);
        }
    }

    // Enroll a student into a class using their MasterID
    public function enrollByMasterID($masterID, $classID) {
        try {
            // This query is safer than INSERT IGNORE.
            // It will silently do nothing if the student is already enrolled (due to a UNIQUE index).
            // However, it WILL throw a PDOException for other errors, like a foreign key violation
            // if the ClassID is invalid. This is what we want.
            $query = "INSERT INTO class_enrollment (ClassID, MasterID) VALUES (:cid, :mid)
                      ON DUPLICATE KEY UPDATE EnrollmentID = EnrollmentID"; // No-op for duplicates
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['cid' => $classID, 'mid' => $masterID]);
        } catch (PDOException $e) {
            error_log("Enrollment Error: " . $e->getMessage());
            // Re-throw the exception to ensure the calling transaction is rolled back.
            throw $e;
        }
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
    public function addActivityRequirement($activityID, $itemID, $qty, $variantID = null) {
        try {
            $query = "INSERT INTO activity_requirements (ActivityID, ItemID, Required_Qty, VariantID) 
                      VALUES (:aid, :iid, :qty, :vid)";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['aid' => $activityID, 'iid' => $itemID, 'qty' => $qty, 'vid' => $variantID]);
        } catch (PDOException $e) {
            error_log("Requirement Error: " . $e->getMessage());
            return false;
        }
    }

    // Get the list of items required for an activity
    public function getActivityRequirements($activityID) { // This function is correct
        $query = "SELECT i.ItemID, i.Item_Name, i.is_consumable, i.is_scalable, ar.Required_Qty, ar.VariantID,
                         iv.Size_Value, iv.Unit
                  FROM activity_requirements ar
                  JOIN inventory i ON ar.ItemID = i.ItemID
                  LEFT JOIN item_variants iv ON ar.VariantID = iv.VariantID
                  WHERE ar.ActivityID = :aid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['aid' => $activityID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get the list of classes an activity is assigned to
    public function getAssignedClassesForActivity($activityID) {
        try {
            $sql = "SELECT ClassID FROM activity_assignments WHERE ActivityID = :aid";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':aid' => $activityID]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAssignedClassesForActivity Error: " . $e->getMessage());
            return [];
        }
    }
    // --- PHASE 8: BORROWING SYSTEM ---

    // Initialize a borrowing session (Pending status)
    public function createBorrowingSession($studentID, $activityID, $qrData, $reason = null) {
        try {
            // Ensure ActivityID is NULL if empty/zero to allow independent borrowing
            if (empty($activityID) || $activityID === 0 || $activityID === '0') {
                $activityID = null;
            }

            $query = "INSERT INTO borrowing_sessions (StudentID, ActivityID, Status, QR_Code_Data, Remarks) 
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
        // --- MOCK DATA BLOCK ---
        // Set to true to use mock data, false to use the live database.
        $useMockData = true;

        if ($useMockData) {
            return [
                'total_stock' => 2450,
                'total_users' => 450,
                'pending_reqs' => 12,
                'open_damages' => 3,
                'categories' => [
                    ['Category_Name' => 'Glassware', 'count' => 45],
                    ['Category_Name' => 'Chemicals', 'count' => 60],
                    ['Category_Name' => 'Apparatus', 'count' => 23],
                    ['Category_Name' => 'Electronics', 'count' => 15],
                    ['Category_Name' => 'Safety', 'count' => 10],
                ],
                'session_stats' => [
                    ['Status' => 'Pending', 'count' => 12],
                    ['Status' => 'Approved', 'count' => 8],
                    ['Status' => 'Issued', 'count' => 25],
                    ['Status' => 'Returned', 'count' => 150],
                    ['Status' => 'Cancelled', 'count' => 5],
                ],
                'lowest_stock_items' => [
                    ['Item_Name' => 'Test Tubes (Small)', 'Available_Qty' => 5],
                    ['Item_Name' => 'Filter Paper (Round)', 'Available_Qty' => 8],
                    ['Item_Name' => 'Pipette Tips (100ul)', 'Available_Qty' => 12],
                    ['Item_Name' => 'Ethanol 95% (500ml)', 'Available_Qty' => 15],
                    ['Item_Name' => 'Beaker (50ml)', 'Available_Qty' => 18],
                    ['Item_Name' => 'Bunsen Burner', 'Available_Qty' => 20],
                    ['Item_Name' => 'Safety Goggles', 'Available_Qty' => 25],
                    ['Item_Name' => 'Stirring Rods', 'Available_Qty' => 30],
                    ['Item_Name' => 'Conical Flask (100ml)', 'Available_Qty' => 35],
                    ['Item_Name' => 'Measuring Cylinder (10ml)', 'Available_Qty' => 40],
                ],
                'user_population_by_role' => [
                    ['Role' => 'Student', 'count' => 420],
                    ['Role' => 'Teacher', 'count' => 25],
                    ['Role' => 'Admin', 'count' => 5],
                ],
                'total_classes' => 15,
                'class_demographics' => [
                    ['Class_Name' => 'GenChem 1', 'Section' => 'STEM-12A', 'student_count' => 35],
                    ['Class_Name' => 'GenChem 1', 'Section' => 'STEM-12B', 'student_count' => 38],
                    ['Class_Name' => 'Physics 2', 'Section' => 'STEM-12C', 'student_count' => 32],
                    ['Class_Name' => 'Biology Lab', 'Section' => 'BSBio-2', 'student_count' => 28],
                    ['Class_Name' => 'Adv. Chem', 'Section' => 'BSChem-3', 'student_count' => 20],
                ],
                'damage_stats' => [
                    ['status' => 'Unresolved', 'count' => 3],
                    ['status' => 'Under Review', 'count' => 2],
                    ['status' => 'Resolved', 'count' => 18],
                ],
            ];
        }
        // --- END MOCK DATA BLOCK ---

        // Original logic for fetching live data (if $useMockData is false)
        $stats = [
            'total_stock' => 0,
            'total_users' => 0,
            'pending_reqs' => 0,
            'open_damages' => 0,
            'categories' => [],
            'session_stats' => [],
            'lowest_stock_items' => [],
            'user_population_by_role' => [],
            'total_classes' => 0,
            'class_demographics' => [],
            'damage_stats' => [],
        ];

        try {
            // Total Stock
            $stock_sql = "SELECT SUM(Total_Qty) FROM inventory";
            $stats['total_stock'] = (int)$this->db->query($stock_sql)->fetchColumn();

            // Total Users
            $users_sql = "SELECT COUNT(UserID) FROM users";
            $stats['total_users'] = (int)$this->db->query($users_sql)->fetchColumn();

            // Pending Requests (Borrowing Sessions)
            $pending_reqs_sql = "SELECT COUNT(SessionID) FROM borrowing_sessions WHERE Status = 'Pending'";
            $stats['pending_reqs'] = (int)$this->db->query($pending_reqs_sql)->fetchColumn();

            // Open Damages
            $open_damages_sql = "SELECT COUNT(damage_id) FROM damaged_returns WHERE status IN ('Unresolved', 'Under Review')";
            $stats['open_damages'] = (int)$this->db->query($open_damages_sql)->fetchColumn();

            // Inventory Composition (Categories)
            $categories_sql = "SELECT c.Category_Name, COUNT(i.ItemID) as count FROM categories c LEFT JOIN inventory i ON c.CategoryID = i.CategoryID GROUP BY c.Category_Name ORDER BY c.Category_Name";
            $stats['categories'] = $this->db->query($categories_sql)->fetchAll(PDO::FETCH_ASSOC);

            // Borrowing Session Status
            $session_stats_sql = "SELECT Status, COUNT(SessionID) as count FROM borrowing_sessions GROUP BY Status ORDER BY Status";
            $stats['session_stats'] = $this->db->query($session_stats_sql)->fetchAll(PDO::FETCH_ASSOC);

            // Lowest Stock Items (Top 20)
            $lowest_stock_sql = "SELECT Item_Name, Available_Qty FROM inventory ORDER BY Available_Qty ASC LIMIT 20";
            $stats['lowest_stock_items'] = $this->db->query($lowest_stock_sql)->fetchAll(PDO::FETCH_ASSOC);

            // User Population by Role
            $user_pop_sql = "SELECT Role, COUNT(MasterID) as count FROM lookup_masterlist GROUP BY Role ORDER BY Role";
            $stats['user_population_by_role'] = $this->db->query($user_pop_sql)->fetchAll(PDO::FETCH_ASSOC);

            // Total Classes
            $total_classes_sql = "SELECT COUNT(ClassID) FROM classes";
            $stats['total_classes'] = (int)$this->db->query($total_classes_sql)->fetchColumn();

            // Class Demographics (Example: student count per class)
            $class_demographics_sql = "SELECT c.Class_Name, c.Section, COUNT(ce.MasterID) as student_count FROM classes c LEFT JOIN class_enrollment ce ON c.ClassID = ce.ClassID GROUP BY c.ClassID ORDER BY c.Class_Name, c.Section LIMIT 5";
            $stats['class_demographics'] = $this->db->query($class_demographics_sql)->fetchAll(PDO::FETCH_ASSOC);

            // Damage Status
            $damage_stats_sql = "SELECT status, COUNT(damage_id) as count FROM damaged_returns GROUP BY status ORDER BY status";
            $stats['damage_stats'] = $this->db->query($damage_stats_sql)->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("getAdminKPIs Error: " . $e->getMessage());
            // Return default empty stats on error
        }

        return $stats;
    }

    public function getLabTechDashboardData() {
        // --- MOCK DATA BLOCK ---
        // This function provides mock data for the LabTech dashboard charts.
        return [
            'pending_reqs_count' => 18,
            'approved_reqs_count' => 7,
            'issued_items_count' => 23,
            'pending_settlements_count' => 5,
            'pending_by_class' => [
                ['class_name' => 'BS Biology 2A', 'count' => 5],
                ['class_name' => 'BS Chemistry 1B', 'count' => 8],
                ['class_name' => 'BS Physics 3A', 'count' => 3],
                ['class_name' => 'General Research', 'count' => 2],
            ],
            'handover_trend' => [
                ['date' => date('Y-m-d', strtotime('-6 days')), 'count' => 5],
                ['date' => date('Y-m-d', strtotime('-5 days')), 'count' => 8],
                ['date' => date('Y-m-d', strtotime('-4 days')), 'count' => 6],
                ['date' => date('Y-m-d', strtotime('-3 days')), 'count' => 12],
                ['date' => date('Y-m-d', strtotime('-2 days')), 'count' => 9],
                ['date' => date('Y-m-d', strtotime('-1 day')), 'count' => 15],
                ['date' => date('Y-m-d'), 'count' => 7],
            ],
            'overdue_items' => [
                ['item_name' => 'Microscope', 'days_overdue' => 12],
                ['item_name' => 'Bunsen Burner', 'days_overdue' => 9],
                ['item_name' => 'Volumetric Flask (500ml)', 'days_overdue' => 7],
                ['item_name' => 'Digital Scale', 'days_overdue' => 5],
                ['item_name' => 'Hot Plate', 'days_overdue' => 3],
            ],
            'damage_types' => [
                ['type' => 'Broken', 'count' => 4],
                ['type' => 'Lost', 'count' => 1],
                ['type' => 'Dirty', 'count' => 3],
                ['type' => 'Malfunction', 'count' => 2],
            ],
        ];
    }

    public function getAdminDashboardData() {
        // --- MOCK DATA BLOCK ---
        // Set to true to use mock data, false to use the live database.
        $useMockData = true;

        if ($useMockData) {
            return [
                'inventory_health' => [
                    'Available' => 1250,
                    'Issued' => 320,
                    'Damaged' => 45,
                    'Lost' => 12,
                ],
                'variant_distribution' => [
                    ['VariantName' => 'Beaker (50ml)', 'Variant_Available_Qty' => 5],
                    ['VariantName' => 'Pipette (10ml)', 'Variant_Available_Qty' => 8],
                    ['VariantName' => 'Test Tube (Small)', 'Variant_Available_Qty' => 15],
                    ['VariantName' => 'Graduated Cylinder (100ml)', 'Variant_Available_Qty' => 20],
                    ['VariantName' => 'Beaker (250ml)', 'Variant_Available_Qty' => 22],
                ],
                'system_users' => [
                    [
                        'ID_Number' => '2024-001', 'Full_Name' => 'Maria Clara', 'Role' => 'Student',
                        'Confirmed_Email' => 'm.clara@wmsu.edu.ph', 'CreatedAt' => '2026-03-15 10:00:00', 'Is_Verified' => 1
                    ],
                    [
                        'ID_Number' => '2024-002', 'Full_Name' => 'Crisostomo Ibarra', 'Role' => 'Student',
                        'Confirmed_Email' => 'c.ibarra@wmsu.edu.ph', 'CreatedAt' => '2026-03-14 09:00:00', 'Is_Verified' => 1
                    ],
                    [
                        'ID_Number' => 'T-101', 'Full_Name' => 'Jose Rizal', 'Role' => 'Teacher',
                        'Confirmed_Email' => 'j.rizal@wmsu.edu.ph', 'CreatedAt' => '2026-02-20 08:00:00', 'Is_Verified' => 1
                    ],
                    [
                        'ID_Number' => '2024-003', 'Full_Name' => 'Elias Salvador', 'Role' => 'Student',
                        'Confirmed_Email' => null, 'CreatedAt' => null, 'Is_Verified' => 0
                    ],
                ]
            ];
        }
        // --- END MOCK DATA BLOCK ---

        $data = [
            'inventory_health' => [],
            'variant_distribution' => [],
            'system_users' => [],
        ];
    
        try {
            // 1. Master Inventory Health
            $available_sql = "SELECT SUM(Available_Qty) FROM inventory";
            $available = (int)$this->db->query($available_sql)->fetchColumn();
    
            $issued_sql = "SELECT SUM(bi.Quantity) FROM borrowed_items bi JOIN borrowing_sessions bs ON bi.SessionID = bs.SessionID WHERE bs.Status = 'Issued'";
            $issued = (int)$this->db->query($issued_sql)->fetchColumn();
    
            $damages_sql = "SELECT damage_type, SUM(qty_damaged) as total FROM damaged_returns WHERE status IN ('Unresolved', 'Under Review') GROUP BY damage_type";
            $stmt = $this->db->query($damages_sql);
            $damages = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $damaged = (int)($damages['Damaged'] ?? 0);
            $lost = (int)($damages['Lost'] ?? 0);
    
            $data['inventory_health'] = [
                'Available' => $available,
                'Issued' => $issued,
                'Damaged' => $damaged,
                'Lost' => $lost,
            ];
    
            // 2. Variant Distribution
            $variant_sql = "SELECT CONCAT(i.Item_Name, ' (', iv.Size_Value, iv.Unit, ')') as VariantName, iv.Variant_Available_Qty 
                            FROM item_variants iv 
                            JOIN inventory i ON iv.ItemID = i.ItemID 
                            WHERE i.is_scalable = 1 AND iv.Variant_Available_Qty > 0
                            ORDER BY iv.Variant_Available_Qty ASC
                            LIMIT 10"; // Limit to 10 lowest stock variants for a clean chart
            $stmt = $this->db->query($variant_sql);
            $data['variant_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // 3. System Activity & Security
            $users_sql = "SELECT m.ID_Number, m.Full_Name, m.Role, u.Confirmed_Email, u.CreatedAt, m.is_verified as Is_Verified
                          FROM lookup_masterlist m 
                          LEFT JOIN users u ON m.MasterID = u.MasterID 
                          ORDER BY u.CreatedAt DESC, m.Full_Name ASC
                          LIMIT 20"; // Limit for summary view
            $stmt = $this->db->query($users_sql);
            $data['system_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        } catch (PDOException $e) {
            error_log("getAdminDashboardData Error: " . $e->getMessage());
            return [
                'inventory_health' => ['Available' => 0, 'Issued' => 0, 'Damaged' => 0, 'Lost' => 0],
                'variant_distribution' => [],
                'system_users' => [],
            ];
        }
    
        return $data;
    }

    public function getTeacherDashboardData($teacherID) {
        $data = [
            'total_students' => 0,
            'total_activities' => 0,
            'total_classes' => 0,
            'pending_requests' => 0,
            'upcoming_deadlines' => [], // For chart
            'borrowing_by_class' => [], // For chart
            'my_classes' => [], // For list
            'recent_activities' => [], // For list
        ];

        try {
            // 1. Total Enrolled Students
            $sql_students = "SELECT COUNT(DISTINCT ce.MasterID) 
                             FROM class_enrollment ce
                             JOIN classes c ON ce.ClassID = c.ClassID
                             WHERE c.TeacherID = :tid";
            $stmt_students = $this->db->prepare($sql_students);
            $stmt_students->execute(['tid' => $teacherID]);
            $data['total_students'] = (int)$stmt_students->fetchColumn();

            // 2. Total Activities
            $sql_activities = "SELECT COUNT(DISTINCT la.ActivityID)
                               FROM lab_activities la
                               JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                               JOIN classes c ON aa.ClassID = c.ClassID
                               WHERE c.TeacherID = :tid";
            $stmt_activities = $this->db->prepare($sql_activities);
            $stmt_activities->execute(['tid' => $teacherID]);
            $data['total_activities'] = (int)$stmt_activities->fetchColumn();

            // 3. Total Classes
            $sql_classes = "SELECT COUNT(*) FROM classes WHERE TeacherID = :tid";
            $stmt_classes = $this->db->prepare($sql_classes);
            $stmt_classes->execute(['tid' => $teacherID]);
            $data['total_classes'] = (int)$stmt_classes->fetchColumn();

            // 4. Pending Requests for Approval
            $data['pending_requests'] = $this->countPendingRequests($teacherID);

            // 5. Upcoming Deadlines (for chart)
            $sql_deadlines = "SELECT la.Title, la.Deadline, c.Class_Name 
                              FROM lab_activities la 
                              JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID 
                              JOIN classes c ON aa.ClassID = c.ClassID 
                              WHERE c.TeacherID = :tid AND la.Deadline >= NOW() 
                              ORDER BY la.Deadline ASC LIMIT 5";
            $stmt_deadlines = $this->db->prepare($sql_deadlines);
            $stmt_deadlines->execute(['tid' => $teacherID]);
            $data['upcoming_deadlines'] = $stmt_deadlines->fetchAll(PDO::FETCH_ASSOC);

            // 6. Borrowing Activity by Class (for chart)
            $sql_borrowing = "SELECT c.Class_Name, c.Section, COUNT(bs.SessionID) as session_count 
                              FROM classes c
                              LEFT JOIN activity_assignments aa ON c.ClassID = aa.ClassID
                              LEFT JOIN borrowing_sessions bs ON aa.ActivityID = bs.ActivityID
                              WHERE c.TeacherID = :tid
                              GROUP BY c.ClassID 
                              ORDER BY session_count DESC";
            $stmt_borrowing = $this->db->prepare($sql_borrowing);
            $stmt_borrowing->execute(['tid' => $teacherID]);
            $data['borrowing_by_class'] = $stmt_borrowing->fetchAll(PDO::FETCH_ASSOC);

            // 7. Get Teacher's Classes for list view
            $data['my_classes'] = $this->getTeacherClasses($teacherID);

            // 8. Get Recent Activities for list view
            $sql_recent = "SELECT la.ActivityID, la.Title, la.CreatedAt, c.Class_Name, c.ClassID
                           FROM lab_activities la
                           JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                           JOIN classes c ON aa.ClassID = c.ClassID
                           WHERE c.TeacherID = :tid
                           GROUP BY la.ActivityID
                           ORDER BY la.CreatedAt DESC
                           LIMIT 5";
            $stmt_recent = $this->db->prepare($sql_recent);
            $stmt_recent->execute(['tid' => $teacherID]);
            $data['recent_activities'] = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Teacher Dashboard Data Error: " . $e->getMessage());
            return []; // Return empty array on error
        }
        return $data;
    }

    public function getStudentDashboardData($studentID) {
        $data = [
            'my_classes' => [],
            'class_activity_counts' => [], // For chart
            'upcoming_deadlines' => [],
            'pending_sessions' => 0,
            'issued_sessions' => 0,
            'unresolved_liabilities' => 0,
            'session_stats' => [], // For chart
        ];

        try {
            // 1. Get Classes
            $sql_classes = "SELECT 
                                c.ClassID, c.Class_Name, c.Section, c.Semester, m.Full_Name as TeacherName
                            FROM class_enrollment ce
                            JOIN classes c ON ce.ClassID = c.ClassID
                            JOIN users u_teacher ON c.TeacherID = u_teacher.UserID
                            JOIN lookup_masterlist m ON u_teacher.MasterID = m.MasterID
                            JOIN users u_student ON ce.MasterID = u_student.MasterID
                            WHERE u_student.UserID = :sid
                            ORDER BY c.Class_Name";
            $stmt_classes = $this->db->prepare($sql_classes);
            $stmt_classes->execute(['sid' => $studentID]);
            $data['my_classes'] = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

            // 2. Get Activity counts per class for chart
            $sql_activities = "SELECT c.Class_Name, c.Section, COUNT(aa.ActivityID) as activity_count
                               FROM class_enrollment ce
                               JOIN classes c ON ce.ClassID = c.ClassID
                               LEFT JOIN activity_assignments aa ON c.ClassID = aa.ClassID
                               JOIN users u ON ce.MasterID = u.MasterID
                               WHERE u.UserID = :sid
                               GROUP BY c.ClassID
                               ORDER BY c.Class_Name";
            $stmt_activities = $this->db->prepare($sql_activities);
            $stmt_activities->execute(['sid' => $studentID]);
            $data['class_activity_counts'] = $stmt_activities->fetchAll(PDO::FETCH_ASSOC);

            // 3. Get Upcoming Deadlines
            $sql_deadlines = "SELECT la.ActivityID, la.Title, la.Deadline, c.Class_Name, c.ClassID
                              FROM lab_activities la
                              JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                              JOIN classes c ON aa.ClassID = c.ClassID
                              JOIN class_enrollment ce ON c.ClassID = ce.ClassID
                              JOIN users u ON ce.MasterID = u.MasterID
                              WHERE u.UserID = :sid AND la.Deadline >= NOW() GROUP BY la.ActivityID ORDER BY la.Deadline ASC LIMIT 5";
            $stmt_deadlines = $this->db->prepare($sql_deadlines);
            $stmt_deadlines->execute(['sid' => $studentID]);
            $data['upcoming_deadlines'] = $stmt_deadlines->fetchAll(PDO::FETCH_ASSOC);

            // 4. Get Session Counts
            $sql_sessions = "SELECT Status, COUNT(*) as count FROM borrowing_sessions WHERE StudentID = :sid AND Status IN ('Pending', 'Issued', 'Approved', 'Returned', 'Cancelled') GROUP BY Status";
            $stmt_sessions = $this->db->prepare($sql_sessions);
            $stmt_sessions->execute(['sid' => $studentID]);
            $counts = $stmt_sessions->fetchAll(PDO::FETCH_KEY_PAIR);
            $data['pending_sessions'] = $counts['Pending'] ?? 0;
            $data['issued_sessions'] = $counts['Issued'] ?? 0;
            $data['session_stats'] = $counts;

            // 5. Get Unresolved Liabilities
            $data['unresolved_liabilities'] = $this->countUnresolvedLiabilities($studentID);

        } catch (PDOException $e) {
            error_log("Student Dashboard Data Error: " . $e->getMessage());
        }
        return $data;
    }
    // Generate a unique hash for QR codes
    private function generateQRHash($studentId) {
        return bin2hex(random_bytes(4)) . "-" . $studentId . "-" . time();
    }

    // Approve a borrowing request and generate a new QR code
    public function approveRequest($sessionId, $approverId) {
        $newHash = $this->generateQRHash($approverId);
        $query = "UPDATE borrowing_sessions SET 
                  Status = 'Approved', 
                  approver_user_id = :approverId,
                  QR_Code_Data = :hash
                  WHERE SessionID = :sid AND Status = 'Pending'";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['sid' => $sessionId, 'approverId' => $approverId, 'hash' => $newHash]);
    }


    // Get inventory items with category names
    public function getInventoryShop() {
        $query = "SELECT i.*, c.Category_Name,
                         CASE
                             WHEN i.is_consumable = 1 THEN 'consumable'
                             ELSE 'non-consumable'
                         END AS Asset_Type
                  FROM inventory i
                  LEFT JOIN categories c ON i.CategoryID = c.CategoryID
                  ORDER BY i.Item_Name ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $variantQuery = $this->db->prepare("SELECT * FROM item_variants WHERE ItemID = :itemId ORDER BY CAST(Size_Value AS UNSIGNED)");

        foreach ($inventoryItems as &$item) {
            if ($item['is_scalable'] == 1) {
                $variantQuery->execute([':itemId' => $item['ItemID']]);
                $item['variants'] = $variantQuery->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $item['variants'] = [];
            }
        }
        return $inventoryItems;
    }

    public function getPaginatedInventory($options = []) {
        $limit = $options['limit'] ?? 12;
        $page = (int)($options['page'] ?? 1);
        $offset = ($page - 1) * $limit;
        $search = $options['search'] ?? '';
        $category = $options['category'] ?? 'all';
        $assetType = $options['asset_type'] ?? 'all';
    
        $joinSql = "FROM inventory i LEFT JOIN shelves s ON i.shelf_id = s.id";
        $whereClauses = [];
        $params = [];
    
        if (!empty($search)) {
            $whereClauses[] = "(i.Item_Name LIKE :search OR s.shelf_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
    
        if ($category !== 'all' && is_numeric($category)) {
            $whereClauses[] = "i.CategoryID = :category";
            $params[':category'] = $category;
        }

        if ($assetType === 'consumable') {
            $whereClauses[] = "i.is_consumable = 1";
        } elseif ($assetType === 'non-consumable') {
            $whereClauses[] = "i.is_consumable = 0";
        }
    
        $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
        // Query for total count
        $countSql = "SELECT COUNT(i.ItemID) $joinSql $whereSql";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int) $countStmt->fetchColumn();
    
        // Query for data
        $dataSql = "SELECT i.ItemID, i.Item_Name, i.CategoryID, i.is_consumable, s.shelf_name as shelf_id 
                    $joinSql 
                    $whereSql 
                    ORDER BY i.Item_Name ASC 
                    LIMIT :limit OFFSET :offset";
        $dataStmt = $this->db->prepare($dataSql);
        
        foreach ($params as $key => $val) { $dataStmt->bindValue($key, $val); }
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $items = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
        return ['items' => $items, 'total' => $totalRecords, 'pages' => ceil($totalRecords / $limit), 'currentPage' => $page];
    }

    /**
     * Assigns an inventory item to a specific shelf by updating its shelf_id.
     * We assume shelf_id in the inventory table stores the shelf_name.
     * @param int $itemId The ID of the item to assign.
     * @param string $shelfName The name of the shelf to assign it to.
     * @return bool True on success, false on failure.
     */
    public function assignItemToShelf($itemId, $shelfName) {
        try {
            // First, check that the shelf name exists in the shelves table for data integrity.
            // Normalize all whitespace to single spaces, then trim, for robust lookup.
            $cleanedShelfName = preg_replace('/\s+/u', ' ', trim($shelfName));
            
            // Fetch the integer ID of the shelf, not just its name.
            $idStmt = $this->db->prepare("SELECT id FROM shelves WHERE shelf_name = :shelfName");
            $idStmt->execute([':shelfName' => $cleanedShelfName]);
            $shelfId = $idStmt->fetchColumn();

            if (!$shelfId) {
                 $allShelvesStmt = $this->db->prepare("SELECT shelf_name FROM shelves ORDER BY shelf_name ASC");
                $allShelvesStmt->execute();
                $existingShelfNames = $allShelvesStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $this->lastError = "Assignment failed. The selected shelf '{$cleanedShelfName}' does not exist. "
                                 . "Existing shelves: [" . implode(', ', $existingShelfNames) . "]";
                return false;
            }

            // Now, update the inventory table with the correct integer ID.
            $sql = "UPDATE inventory SET shelf_id = :shelfId WHERE ItemID = :itemId";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':shelfId' => $shelfId, ':itemId' => $itemId]);
        } catch (PDOException $e) {
            $this->lastError = "Database error during assignment.";
            error_log("assignItemToShelf Error: " . $e->getMessage());
            return false;
        }
    }

    // Get details of a specific item
    public function getItemDetails($itemId) {
        $query = "SELECT i.*, c.Category_Name FROM inventory i JOIN categories c ON i.CategoryID = c.CategoryID WHERE i.ItemID = :iid";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['iid' => $itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
 // Process the student's cart and create a borrowing request
 public function submitRequisition($studentId, $activityId, $items, $reason = null) {
    try {
        $this->db->beginTransaction();

        if (empty($activityId) || $activityId === 0 || $activityId === '0') {
            $activityId = null;
        }

        // 1. Insert the session record
        $sql = "INSERT INTO borrowing_sessions (StudentID, ActivityID, Status, QR_Code_Data, Remarks) 
                VALUES (:sid, :aid, 'Pending', :qr, :reason)";
        $stmt = $this->db->prepare($sql);
        
        $qrData = "SNHS-REF-" . strtoupper(uniqid()); 
        
        $stmt->execute([
            'sid' => $studentId,
            'aid' => $activityId,
            'qr'  => $qrData,
            'reason' => $reason
        ]);
        
        $sessionId = $this->db->lastInsertId();

        // 2. Map items to the session
        $itemSql = "INSERT INTO borrowed_items (SessionID, ItemID, VariantID, Quantity) VALUES (:sid, :iid, :vid, :qty)";
        $itemStmt = $this->db->prepare($itemSql);

        foreach ($items as $item) {
            $iid = $item['itemId'] ?? ($item['id'] ?? null);
            $vid = $item['variantId'] ?? null;
            $qty = $item['qty'] ?? $item['Quantity'];

            $itemStmt->execute([
                'sid' => $sessionId,
                'iid' => $iid,
                'vid' => $vid,
                'qty' => $qty,
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
        // 1. MAIN QUERY: Fetch Groups. Submission data is removed.
        $sql = "SELECT g.GroupID, g.GroupName FROM activity_groups g
                WHERE g.ActivityID = :aid";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':aid' => $activityID]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Re-add member fetching logic
        if ($groups) {
            foreach ($groups as &$group) {
                // This forces the keys to be lowercase and simple for JavaScript
                $sqlMembers = "SELECT lm.Full_Name AS name, gm.Is_Leader AS role 
                               FROM group_members gm
                               JOIN lookup_masterlist lm ON gm.MasterID = lm.MasterID
                               WHERE gm.GroupID = :gid
                               ORDER BY gm.Is_Leader DESC, lm.Full_Name ASC";

                $stmtMembers = $this->db->prepare($sqlMembers);
                $stmtMembers->execute([':gid' => $group['GroupID']]);

                $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);
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

/**
 * Finalizes the handover by updating session status and subtracting inventory.
 *
 * This is called when the student physically receives the items.
 */
public function finalizeHandover($sid, $handlerId) {
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

        // 4. Update Status and record the handler
        $updateStatus = "UPDATE borrowing_sessions 
                         SET Status = 'Issued', handler_user_id = :handlerId 
                         WHERE SessionID = :sid";
        $sStmt = $this->db->prepare($updateStatus);
        $sStmt->execute(['sid' => $sid, 'handlerId' => $handlerId]);

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
public function processReturn($sid, $remarks, $handlerId) {
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

        // 3. Finalize Session and record the handler
        $updateStatus = "UPDATE borrowing_sessions 
                         SET Status = 'Returned', Remarks = :rem, handler_user_id = :handlerId 
                         WHERE SessionID = :sid";
        $sStmt = $this->db->prepare($updateStatus);
        $sStmt->execute(['sid' => $sid, 'rem' => $remarks, 'handlerId' => $handlerId]);

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

/**
 * Get paginated and filtered activities for a student in a specific class.
 * Includes submission status for each activity.
 *
 * @param int $classID The ID of the class.
 * @param int $studentID The UserID of the student.
 * @param array $options Filtering, sorting, and pagination options.
 * @return array An array containing the data, total records, and page count.
 */
public function getActivitiesByClassForStudent($classID, $studentID, $options = []) {
    if (!$this->db) return ['data' => [], 'total' => 0, 'pages' => 1, 'current_page' => 1];

    $masterID = $this->getMasterID($studentID);
    if (!$masterID) return ['data' => [], 'total' => 0, 'pages' => 1, 'current_page' => 1];

    // 1. Set up options
    $search = $options['search'] ?? '';
    $status = $options['status'] ?? 'all';
    $sort = $options['sort'] ?? 'deadline_desc';
    $limit = $options['limit'] ?? 10;
    $page = $options['page'] ?? 1;
    $offset = ($page - 1) * $limit;

    // 2. Build Query
    $params = [':cid' => $classID];
    $whereClauses = "aa.ClassID = :cid";

    if (!empty($search)) {
        $whereClauses .= " AND a.Title LIKE :search";
        $params[':search'] = "%$search%";
    }

    // Sorting logic
    $orderBy = "ORDER BY Deadline DESC"; // Default
    if ($sort === 'deadline_asc') $orderBy = "ORDER BY Deadline ASC";
    elseif ($sort === 'title_asc') $orderBy = "ORDER BY Title ASC";
    elseif ($sort === 'created_asc') $orderBy = "ORDER BY CreatedAt ASC";

    // The `lab_submissions` table that was previously used here does not exist, causing a fatal error.
    // This has been removed. The submission status is now hardcoded to 'Open' as the underlying
    // submission feature appears to be deprecated or removed.

    // We build the main query and then wrap it to filter by the calculated status.
    $baseQuery = "
        FROM lab_activities a
        JOIN activity_assignments aa ON a.ActivityID = aa.ActivityID
        WHERE $whereClauses
    ";

    $wrappedQuery = "SELECT *, 'Open' as submission_status FROM (
        SELECT a.*
        $baseQuery
        GROUP BY a.ActivityID
    ) as temp_table";

    // Add status filtering
    $statusWhere = "";
    if ($status !== 'all') {
        $statusWhere = " WHERE submission_status = :status";
        $params[':status'] = $status;
    }

    // Get total records for pagination
    $countQuery = "SELECT COUNT(*) FROM ($wrappedQuery) as count_table" . $statusWhere;
    $countStmt = $this->db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = (int) $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Get the actual data
    $dataQuery = $wrappedQuery . $statusWhere . " " . $orderBy . " LIMIT :limit OFFSET :offset";
    $stmt = $this->db->prepare($dataQuery);
    foreach ($params as $key => &$val) { $stmt->bindParam($key, $val); }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $totalRecords, 'pages' => $totalPages, 'current_page' => $page];
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
        // Query no longer joins lab_submissions
        $sql = "SELECT lm.Full_Name, lm.MasterID
                FROM class_enrollment ce
               JOIN lookup_masterlist lm ON ce.MasterID = lm.MasterID
               WHERE ce.ClassID = :cid
               ORDER BY lm.Full_Name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cid' => $classID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("<h3>Database Error (Individual List):</h3> " . $e->getMessage());
    }
}

// Process a return that includes damaged items (Restocks good items, logs damages)
public function processReturnWithDamage($session_id, $damage_data, $handlerId) {
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
        $stmt_damage_log = $this->db->prepare("INSERT INTO damaged_returns (session_id, item_id, student_id, reported_by_user_id, qty_damaged, damage_type, status, notes, evidence_image) VALUES (?, ?, ?, ?, ?, ?, 'Unresolved', ?, ?)");

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
                    $handlerId,       // The user reporting the damage
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
        $sql_update = "UPDATE borrowing_sessions 
                       SET Status = 'Returned', has_damage = 1, Remarks = 'Returned with damages', handler_user_id = ? 
                       WHERE SessionID = ?";
        $stmt_update = $this->db->prepare($sql_update);
        $stmt_update->execute([$handlerId, $session_id]);

        $this->db->commit();
        return true;

    } catch (Exception $e) {
        $this->db->rollBack();
        // error_log($e->getMessage()); // Useful for debugging
        return false;
    }
}

// Process a return where all items are in good condition
public function processCleanReturn($session_id, $handlerId) {
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
            $sql_update = "UPDATE borrowing_sessions 
                           SET Status = 'Returned', has_damage = 0, Remarks = 'Returned in good condition', handler_user_id = ? 
                           WHERE SessionID = ?";
            $stmt_update = $this->db->prepare($sql_update);
            $stmt_update->execute([$handlerId, $session_id]);

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
        if (!$this->db) { return ['has_liability' => false, 'items' => []]; }

        try {
            $sql = "SELECT 
                        dr.*, 
                        i.Item_Name, 
                        bs.SessionID, 
                        bs.CreatedAt as SlipDate
                    FROM damaged_returns dr
                    JOIN inventory i ON dr.item_id = i.ItemID
                    JOIN borrowing_sessions bs ON dr.session_id = bs.SessionID
                    WHERE dr.student_id = ? AND dr.status IN ('Unresolved', 'Under Review')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$student_id]);
            $liabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'has_liability' => count($liabilities) > 0,
                'items' => $liabilities
            ];
        } catch (PDOException $e) {
            error_log("checkLiability Error: " . $e->getMessage());
            return ['has_liability' => false, 'items' => []];
        }
    }

// Inside class DataManager
// Get all students enrolled in a class
public function getEnrolledStudents($class_id) {
        if (!$this->db) { return []; }

        try {
            $sql = "SELECT 
                        ce.EnrollmentID, 
                        ce.ClearanceStatus, 
                        m.MasterID,
                        m.Full_Name, 
                        m.ID_Number,
                        m.Official_Email,
                        m.is_verified AS Is_Verified
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

    // Get all students enrolled in a class with pagination and search
    public function getPaginatedEnrolledStudents($class_id, $options = []) {
        if (!$this->db) { return ['data' => [], 'total' => 0, 'pages' => 1, 'currentPage' => 1]; }

        $limit = $options['limit'] ?? 10;
        $page = $options['page'] ?? 1;
        $offset = ($page - 1) * $limit;
        $search = $options['search'] ?? '';

        $params = [':cid' => $class_id];
        $whereClauses = "ce.ClassID = :cid";

        if (!empty($search)) {
            $whereClauses .= " AND (m.Full_Name LIKE :search OR m.ID_Number LIKE :search)";
            $params[':search'] = "%$search%";
        }

        // Query for total count
        $countSql = "SELECT COUNT(ce.EnrollmentID) 
                     FROM class_enrollment ce
                     JOIN lookup_masterlist m ON ce.MasterID = m.MasterID
                     WHERE $whereClauses";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int) $countStmt->fetchColumn();

        // Query for data
        $dataSql = "SELECT 
                        ce.EnrollmentID, 
                        ce.ClearanceStatus, 
                        m.MasterID,
                        m.Full_Name, 
                        m.ID_Number,
                        m.Official_Email,
                        m.is_verified AS Is_Verified
                    FROM class_enrollment ce
                    JOIN lookup_masterlist m ON ce.MasterID = m.MasterID
                    WHERE $whereClauses
                    ORDER BY m.Full_Name ASC
                    LIMIT :limit OFFSET :offset";
        $dataStmt = $this->db->prepare($dataSql);
        foreach ($params as $key => &$val) { $dataStmt->bindValue($key, $val); }
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $students = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return ['data' => $students, 'total' => $totalRecords, 'pages' => ceil($totalRecords / $limit), 'currentPage' => $page];
    }

    // Get all users who are not students (for admin management)
    public function getManageableUsers() {
        try {
            $query = "SELECT MasterID, ID_Number, Full_Name, Official_Email, Role, is_verified
                      FROM lookup_masterlist 
                      WHERE Role != 'Student'
                      ORDER BY Role, Full_Name ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getManageableUsers Error: " . $e->getMessage());
            return [];
        }
    }

    // Get all users with the 'Teacher' role
    public function getTeachers() {
        try {
            $query = "SELECT u.UserID, m.Full_Name 
                      FROM users u
                      JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                      WHERE m.Role = 'Teacher'
                      ORDER BY m.Full_Name ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getTeachers Error: " . $e->getMessage());
            return [];
        }
    }

    // Delete a class after checking for dependencies
    public function deleteClass($classID) {
        try {
            $this->db->beginTransaction();

            // 1. Check for enrolled students
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM class_enrollment WHERE ClassID = ?");
            $stmt->execute([$classID]);
            if ($stmt->fetchColumn() > 0) {
                $this->lastError = "Cannot delete class. There are still students enrolled in it.";
                $this->db->rollBack();
                return false;
            }

            // 2. Check for assigned activities
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM activity_assignments WHERE ClassID = ?");
            $stmt->execute([$classID]);
            if ($stmt->fetchColumn() > 0) {
                $this->lastError = "Cannot delete class. It still has activities assigned to it.";
                $this->db->rollBack();
                return false;
            }

            // 3. If checks pass, delete the class
            $stmt = $this->db->prepare("DELETE FROM classes WHERE ClassID = ?");
            $stmt->execute([$classID]);

            $this->db->commit();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Class Deletion Error: " . $e->getMessage());
            $this->lastError = "A database error occurred during deletion.";
            return false;
        }
    }

    // Update a user's role in the masterlist
    public function updateUserRole($masterId, $newRole) {
        if (!in_array($newRole, ['Teacher', 'Admin', 'LabTech'])) {
            $this->lastError = "Invalid role specified.";
            return false;
        }
        try {
            $query = "UPDATE lookup_masterlist SET Role = :role WHERE MasterID = :mid";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['role' => $newRole, 'mid' => $masterId]);
        } catch (PDOException $e) {
            error_log("updateUserRole Error: " . $e->getMessage());
            $this->lastError = "Database error during role update.";
            return false;
        }
    }


    // Get a list of damages for a specific student
    public function getStudentDamages($master_id) {
        if (!$this->db) { return []; }

        try {
            $sql = "SELECT 
                        dr.damage_id,
                        i.Item_Name,
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
            $iStmt = $this->db->prepare("SELECT 
                                             i.Item_Name, 
                                             bi.Quantity,
                                             i.is_consumable,
                                             iv.Size_Value,
                                             iv.Unit
                                         FROM borrowed_items bi 
                                         JOIN inventory i ON bi.ItemID = i.ItemID 
                                         LEFT JOIN item_variants iv ON bi.VariantID = iv.VariantID
                                         WHERE bi.SessionID = ?");
            $iStmt->execute([$sid]);
            $session['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);

            // NEW: Added 'evidence_image' to SELECT list
            $dStmt = $this->db->prepare("SELECT damage_id, status, evidence_image, notes FROM damaged_returns 
                                         WHERE session_id = ? AND status != 'Resolved'");
            $dStmt->execute([$sid]);
            $damage = $dStmt->fetch(PDO::FETCH_ASSOC);

            // Attach to session array
            $session['liability_status'] = $damage ? 'HasLiability' : 'Clean';
            $session['damage_id'] = $damage ? $damage['damage_id'] : null;
            $session['damage_db_status'] = $damage ? $damage['status'] : null;
            $session['damage_notes'] = $damage ? $damage['notes'] : null;
            // NEW: Attach evidence image for student view
            $session['evidence_image'] = $damage ? $damage['evidence_image'] : null;
        }

        return $sessions;
    }

    // Submit proof of payment/replacement for a damaged item
    public function submitDamageProof($damage_id, $settlement_mode, $file) {
        if (!$this->db) { return "Database Error"; }

        // Validate settlement mode
        if (!in_array($settlement_mode, ['payment', 'replacement'])) {
            return "Invalid settlement mode specified.";
        }

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
                $sql = "UPDATE damaged_returns SET proof_image = ?, status = 'Under Review', settlement_mode = ? WHERE damage_id = ?";
                $this->db->prepare($sql)->execute([$new_name, $settlement_mode, $damage_id]);
                return true;
            } catch (PDOException $e) { return "Database error."; }
        }
        return "Upload failed.";
    }

// Get settlement cases (damages) for admin/teacher view
public function getSettlementCases($view = 'pending', $search = '', $class_id = '', $for_user_id = null) {
        if (!$this->db) { die("Database connection missing."); }

        // 1. Status Filter
        if ($view === 'history') {
            $statusCondition = "dr.status = 'Resolved'";
        } elseif ($view === 'personal_all') {
            $statusCondition = "1"; // No status filter, get all.
        } else { // 'pending' is the default
            $statusCondition = "dr.status IN ('Unresolved', 'Under Review')";
        }

        $params = [];
        $searchLogic = "";
        $classLogic = "";
        $userLogic = "";

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

        // 4. User Filter for personal view
        if ($for_user_id !== null) {
            $userLogic = "AND dr.student_id = ?";
            $params[] = $for_user_id;
        }

        // 4. Main Query
        $sql = "SELECT 
                    dr.*, 
                    i.Item_Name, 
                    m.Full_Name, 
                    m.ID_Number,
                    bs.CreatedAt as SlipDate,
                    i.is_scalable,
                    COALESCE(la.Title, 'General Laboratory Use') as ActivityTitle,
                    bs.QR_Code_Data,
                    bs.Status as SlipStatus,
                    c.Class_Name, 
                    c.Section,
                    bi.Quantity as qty_borrowed,
                    iv.Size_Value,
                    iv.Unit,
                    m_handler.Full_Name as HandlerName,
                    m_resolver.Full_Name as ResolverName
                FROM damaged_returns dr
                JOIN inventory i ON dr.item_id = i.ItemID
                JOIN users u ON dr.student_id = u.UserID
                JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                JOIN borrowing_sessions bs ON dr.session_id = bs.SessionID
                LEFT JOIN borrowed_items bi ON dr.session_id = bi.SessionID AND dr.item_id = bi.ItemID
                LEFT JOIN item_variants iv ON bi.VariantID = iv.VariantID
                LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
                LEFT JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                LEFT JOIN classes c ON aa.ClassID = c.ClassID
                LEFT JOIN users u_handler ON dr.reported_by_user_id = u_handler.UserID
                LEFT JOIN lookup_masterlist m_handler ON u_handler.MasterID = m_handler.MasterID
                LEFT JOIN users u_resolver ON dr.resolved_by_user_id = u_resolver.UserID
                LEFT JOIN lookup_masterlist m_resolver ON u_resolver.MasterID = m_resolver.MasterID
                WHERE $statusCondition 
                $searchLogic 
                $classLogic
                $userLogic
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
    public function resolveDamage($damage_id, $adminId) {
        if (!$this->db) { return false; }
        try {
            $sql = "UPDATE damaged_returns 
                    SET status = 'Resolved', resolved_by_user_id = ? 
                    WHERE damage_id = ?";
            return $this->db->prepare($sql)->execute([$adminId, $damage_id]);
        } catch (PDOException $e) { return false; }
    }

    // Reject proof of settlement (Student must re-upload)
    public function rejectDamage($damage_id, $adminId, $rejection_notes = null) {
        if (!$this->db) { return false; }
        try {
            // Reset status to Unresolved, clear the image proof, and add rejection notes
            $sql = "UPDATE damaged_returns 
                    SET status = 'Unresolved', proof_image = NULL, notes = ?, resolved_by_user_id = ? 
                    WHERE damage_id = ?";
            return $this->db->prepare($sql)->execute([$rejection_notes, $adminId, $damage_id]);
        } catch (PDOException $e) { return false; }
    }

    /**
     * Counts the number of unresolved liabilities for a specific student.
     * @param int $student_id The UserID of the student.
     * @return int The number of unresolved cases.
     */
    public function countUnresolvedLiabilities($student_id) {
        if (!$this->db) { return 0; }
        try {
            $sql = "SELECT COUNT(*) FROM damaged_returns WHERE student_id = ? AND status IN ('Unresolved', 'Under Review')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$student_id]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("countUnresolvedLiabilities Error: " . $e->getMessage());
            return 0;
        }
    }

 // Create a new lab activity
public function createActivity($title, $description, $deadline, $manualURL, $type, $group_mode, $limit) {
    try {
        // 'Manual_URL' is the column that stores the PDF path
        $sql = "INSERT INTO lab_activities 
                (Title, Description, Deadline, Manual_URL, type, grouping_mode, group_limit) 
                VALUES 
                (:title, :desc, :deadline, :manual, :type, :grp_mode, :limit)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title'    => $title,
            ':desc'     => $description,
            ':deadline' => $deadline,
            ':manual'   => $manualURL,
            ':type'     => $type,
            ':grp_mode' => $group_mode,
            ':limit'    => $limit 
        ]);
        
        return $this->db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Create Activity Error: " . $e->getMessage());
        return false;
    }
}

public function updateActivity($activityID, $title, $description, $deadline, $manualURL, $type, $group_mode, $limit, $assignedClasses, $requirements) {
    try {
        // This function is now part of a larger transaction in save_activity.php
        $sql = "UPDATE lab_activities SET
                    Title = :title,
                    Description = :desc,
                    Deadline = :deadline,
                    type = :type,
                    grouping_mode = :grp_mode,
                    group_limit = :limit";
        
        // Only update manual URL if a new one was provided. A null value means no new file was uploaded.
        if ($manualURL !== null) {
            $sql .= ", Manual_URL = :manual";
        }

        $sql .= " WHERE ActivityID = :aid";

        $stmt = $this->db->prepare($sql);
        $params = [
            ':title'    => $title,
            ':desc'     => $description,
            ':deadline' => $deadline,
            ':type'     => $type,
            ':grp_mode' => $group_mode,
            ':limit'    => $limit,
            ':aid'      => $activityID
        ];
        if ($manualURL !== null) {
            $params[':manual'] = $manualURL;
        }
        $stmt->execute($params);

        // 2. Clear and re-insert class assignments
        $this->db->prepare("DELETE FROM activity_assignments WHERE ActivityID = ?")->execute([$activityID]);
        if (!empty($assignedClasses)) {
            foreach ($assignedClasses as $classID) {
                $this->assignActivityToClass($activityID, $classID);
            }
        }

        // 3. Clear and re-insert item requirements
        $this->db->prepare("DELETE FROM activity_requirements WHERE ActivityID = ?")->execute([$activityID]);
        if (!empty($requirements)) {
            foreach ($requirements as $req) {
                $this->addActivityRequirement($activityID, $req['id'], $req['qty'], $req['selectedVariantId'] ?? null);
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Update Activity Error: " . $e->getMessage());
        throw $e; // Re-throw exception to be caught by the main transaction handler
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
            // 1. Fetch all students from the class, no stats needed.
            $sql = "SELECT e.MasterID FROM class_enrollment e WHERE e.ClassID = :cid";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':cid' => $classID]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalStudents = count($students);
            if ($totalStudents === 0) return false;

            // 2. Shuffle the students for random assignment.
            shuffle($students);

            $numGroups = ceil($totalStudents / $limit);
            
            // 3. Create Group Containers
            $groupIds = [];
            for ($i = 1; $i <= $numGroups; $i++) {
                $groupName = "Group " . $i;
                $this->manualCreateGroup($activityID, $groupName);
                $newGroupID = $this->db->lastInsertId();
                $groupIds[] = $newGroupID;
            }

            // 4. Distribute Students sequentially into groups
            $groupIndex = 0;
            foreach ($students as $student) {
                $targetGroupID = $groupIds[$groupIndex];
                $this->manualAddMember($targetGroupID, $student['MasterID']);
                
                // Move to the next group, loop back to the start if at the end
                $groupIndex = ($groupIndex + 1) % $numGroups;
            }
            
            // 5. Trigger random leader nomination
            if (!empty($groupIds)) {
                $this->autoNominateLeaders($groupIds); 
            }
            return true;
        } catch (PDOException $e) { 
            error_log("Smart Grouping Error: " . $e->getMessage()); // Log error
            return false; 
        }
    }

    // Automatically select the best leader for each group
    public function autoNominateLeaders($groupIds) {
        try {
            foreach ($groupIds as $gid) {
                // 1. Get all members of the group
                $sql = "SELECT MemberID FROM group_members WHERE GroupID = :gid";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':gid' => $gid]);
                $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // 2. If there are members, pick one at random
                if (!empty($members)) {
                    $randomMemberId = $members[array_rand($members)];
                    
                    // 3. Set the randomly chosen member as leader
                    $updateSql = "UPDATE group_members SET Is_Leader = 1 WHERE MemberID = :mid";
                    $this->db->prepare($updateSql)->execute([':mid' => $randomMemberId]);
                }
            }
            return true;
        } catch (PDOException $e) { 
            error_log("Leader Nomination Error: " . $e->getMessage()); // Log error
            return false; 
        }
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
        // This should be called within a transaction
        try {
            // 1. Reset everyone in this group to 0
            $s1 = $this->db->prepare("UPDATE group_members SET Is_Leader = 0 WHERE GroupID = :gid");
            $s1->execute([':gid' => $groupID]);
            
            // 2. Set the specific member to 1
            $s2 = $this->db->prepare("UPDATE group_members SET Is_Leader = 1 
                                      WHERE GroupID = :gid AND MasterID = :mid");
            $s2->execute([':gid' => $groupID, ':mid' => $memberID]);
            
            return true;
        } catch (Exception $e) {
            throw $e; // Re-throw to be caught by the main transaction handler
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

    public function getPaginatedActivitiesForClass($classId, $options = []) {
        if (!$this->db || !$classId) {
            return ['data' => [], 'total' => 0, 'pages' => 0];
        }

        $limit = $options['limit'] ?? 10;
        $page = $options['page'] ?? 1;
        $offset = ($page - 1) * $limit;
        $search = $options['search'] ?? '';
        $sort = $options['sort'] ?? 'newest';

        $params = [':cid' => $classId];
        $whereClauses = "aa.ClassID = :cid";

        if (!empty($search)) {
            $whereClauses .= " AND (a.Title LIKE :search OR a.Description LIKE :search)";
            $params[':search'] = "%$search%";
        }

        // Sorting logic
        $orderBy = "ORDER BY a.CreatedAt DESC"; // Default: newest first
        if ($sort === 'oldest') {
            $orderBy = "ORDER BY a.CreatedAt ASC";
        } elseif ($sort === 'deadline') {
            $orderBy = "ORDER BY a.Deadline ASC";
        }

        // Base query
        $baseQuery = "FROM lab_activities a
                    JOIN activity_assignments aa ON a.ActivityID = aa.ActivityID
                    WHERE $whereClauses";

        // Count total records
        $countQuery = "SELECT COUNT(DISTINCT a.ActivityID) " . $baseQuery;
        $countStmt = $this->db->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = (int) $countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        $dataQuery = "SELECT a.* " . $baseQuery . " GROUP BY a.ActivityID " . $orderBy . " LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($dataQuery);
        foreach ($params as $key => &$val) { $stmt->bindParam($key, $val); }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['data' => $activities, 'total' => $totalRecords, 'pages' => $totalPages];
    }

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

public function previewSmartGroups($classIdArray, $limit) {
        if (empty($classIdArray) || $limit <= 0) {
            return [];
        }

        // 1. Fetch all students from the selected classes
        $students = $this->getStudentsByClassList($classIdArray);
        if (empty($students)) {
            return [];
        }

        // 2. Shuffle for randomness
        shuffle($students);

        $totalStudents = count($students);
        $numGroups = ceil($totalStudents / $limit);
        $groups = [];

        // 3. Create logical group structures
        for ($i = 1; $i <= $numGroups; $i++) {
            $groups[$i] = [
                'name' => 'Group ' . $i,
                'members' => []
            ];
        }

        // 4. Distribute students into the logical groups
        $groupIndex = 1;
        foreach ($students as $student) {
            $groups[$groupIndex]['members'][] = ['MasterID' => $student['MasterID'], 'Full_Name' => $student['Full_Name'], 'isLeader' => false];
            $groupIndex = ($groupIndex % $numGroups) + 1;
        }

        // 5. Nominate a random leader for each group
        foreach ($groups as &$group) {
            if (!empty($group['members'])) {
                $leaderIndex = array_rand($group['members']);
                $group['members'][$leaderIndex]['isLeader'] = true;
            }
        }

        return array_values($groups);
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

        $groupID = $group['GroupID'] ?? null;

        return [
            'has_group' => !empty($groupID),
            'group_id' => $groupID,
            'is_leader' => $group['Is_Leader'] ?? 0
        ];
    }

    // --- LOGISTICS HUB LOGIC ---

    // Get the list of required items and how many have been distributed
    public function getLogisticsOverview($activityID, $groupID) {
        // Fetch Teacher's Requirements
        $reqs = $this->getActivityRequirements($activityID);

        // Fetch what has already been assigned
        $sql = "SELECT ItemID, VariantID, SUM(Quantity) as Assigned_Qty FROM group_logistics
                WHERE ActivityID = ? AND GroupID = ? GROUP BY ItemID, VariantID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$activityID, $groupID]);
        $assignedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $assignedSummary = [];
        foreach ($assignedRows as $row) {
            $key = "{$row['ItemID']}_" . ($row['VariantID'] ?? '0');
            $assignedSummary[$key] = $row['Assigned_Qty'];
        }

        // NEW: Fetch detailed assignments, including the LogisticsID for undo operations
        $detailSql = "SELECT gl.LogisticsID, gl.ItemID, gl.VariantID, gl.AssignedToMasterID, gl.Quantity, lm.Full_Name
                      FROM group_logistics gl
                      JOIN lookup_masterlist lm ON gl.AssignedToMasterID = lm.MasterID
                      WHERE gl.ActivityID = ? AND gl.GroupID = ?
                      ORDER BY lm.Full_Name";
        $detailStmt = $this->db->prepare($detailSql);
        $detailStmt->execute([$activityID, $groupID]);
        $detailedAssignments = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

        // Group detailed assignments by ItemID
        $assignmentsByItem = [];
        foreach ($detailedAssignments as $assignment) {
            $key = "{$assignment['ItemID']}_" . ($assignment['VariantID'] ?? '0');
            $assignmentsByItem[$key][] = $assignment;
        }

        // Merge Data
        foreach ($reqs as &$item) {
            $key = "{$item['ItemID']}_" . ($item['VariantID'] ?? '0');
            $item['Distributed'] = $assignedSummary[$key] ?? 0;
            $item['Remaining'] = max(0, $item['Required_Qty'] - $item['Distributed']);
            $item['Assignments'] = $assignmentsByItem[$key] ?? [];
        }
        return $reqs;
    }

    // Bulk assign items, clearing previous assignments (Leader Action)
    public function bulkDistributeItems($activityID, $groupID, $assignments) {
        try {
            $this->db->beginTransaction();

            // 1. Clear existing distributions for this group/activity
            $deleteSql = "DELETE FROM group_logistics WHERE ActivityID = ? AND GroupID = ?";
            $deleteStmt = $this->db->prepare($deleteSql);
            $deleteStmt->execute([$activityID, $groupID]);

            // 2. Insert new assignments
            $insertSql = "INSERT INTO group_logistics (ActivityID, GroupID, ItemID, AssignedToMasterID, Quantity, VariantID)
                          VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $this->db->prepare($insertSql);

            foreach ($assignments as $assignment) {
                // Basic validation
                if (isset($assignment['item_id'], $assignment['target_id'], $assignment['qty']) && $assignment['qty'] > 0) {
                    $insertStmt->execute([$activityID, $groupID, $assignment['item_id'], $assignment['target_id'], $assignment['qty'], $assignment['variant_id'] ?? null]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Bulk Distribution Error: " . $e->getMessage());
            return false;
        }
    }

    // Get the items assigned to the logged-in student
    public function getMyAssignedItems($activityID, $groupID, $myMasterID) {
        $sql = "SELECT gl.ItemID, gl.Quantity as Required_Qty, i.Item_Name, i.is_consumable, i.is_scalable, gl.VariantID, iv.Size_Value, iv.Unit
                FROM group_logistics gl
                JOIN inventory i ON gl.ItemID = i.ItemID
                LEFT JOIN item_variants iv ON gl.VariantID = iv.VariantID
                WHERE gl.ActivityID = ? AND gl.GroupID = ? AND gl.AssignedToMasterID = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$activityID, $groupID, $myMasterID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- SPATIAL INVENTORY (SHELVES) ---
    public function getShelves() {
        try {
            $query = "SELECT * FROM shelves ORDER BY created_at ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Shelves Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets a list of items for a given list of shelf names.
     * @param array $shelfNames
     * @return array An associative array of shelf_name => [item_names]
     */
    public function getItemsOnShelves(array $shelfNames): array
    {
        if (empty($shelfNames)) {
            return [];
        }
        $placeholders = rtrim(str_repeat('?,', count($shelfNames)), ',');
        $sql = "SELECT s.shelf_name, i.Item_Name 
                FROM inventory i 
                JOIN shelves s ON i.shelf_id = s.id 
                WHERE s.shelf_name IN ($placeholders)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($shelfNames);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedResults = [];
        foreach ($results as $row) {
            $groupedResults[$row['shelf_name']][] = $row['Item_Name'];
        }
        return $groupedResults;
    }

    /**
     * Sets the shelf_id to NULL for all items on the given shelves.
     * @param array $shelfNames
     * @return bool True on success
     */
    public function unassignItemsFromShelves(array $shelfNames): bool
    {
        if (empty($shelfNames)) {
            return true; // Nothing to do
        }
        try {
            $placeholders = rtrim(str_repeat('?,', count($shelfNames)), ',');
            $updateSql = "UPDATE inventory SET shelf_id = NULL WHERE shelf_id IN (SELECT id FROM shelves WHERE shelf_name IN ($placeholders))";
            $updateStmt = $this->db->prepare($updateSql);
            return $updateStmt->execute($shelfNames);
        } catch (PDOException $e) {
            error_log("unassignItemsFromShelves Error: " . $e->getMessage());
            $this->lastError = "Database error while unassigning items.";
            return false;
        }
    }

    public function saveShelves(array $shelves, bool $force = false): false|int
    {
        try {
            $this->db->beginTransaction();

            // 1. Get all shelf names from the submitted payload
            $submittedShelfNames = array_column($shelves, 'shelf_name');
            if (empty($submittedShelfNames)) {
                $submittedShelfNames = ['']; // Prevents NOT IN () SQL error if all shelves are deleted
            }

            // 2. Delete shelves that are no longer in the layout, but only if they are empty.
            $placeholders = rtrim(str_repeat('?,', count($submittedShelfNames)), ',');
            $getDeletedShelvesSQL = "SELECT id, shelf_name FROM shelves WHERE shelf_name NOT IN ($placeholders)";
            $deletedShelvesStmt = $this->db->prepare($getDeletedShelvesSQL);
            $deletedShelvesStmt->execute($submittedShelfNames);
            $shelvesToDelete = $deletedShelvesStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($shelvesToDelete)) {
                $shelfIdsToDelete = array_column($shelvesToDelete, 'id');

                // Check if these shelves have items on them
                $placeholdersForIds = rtrim(str_repeat('?,', count($shelfIdsToDelete)), ',');
                $getItemsSQL = "SELECT i.Item_Name, s.shelf_name FROM inventory i JOIN shelves s ON i.shelf_id = s.id WHERE i.shelf_id IN ($placeholdersForIds)";
                $itemsStmt = $this->db->prepare($getItemsSQL);
                $itemsStmt->execute($shelfIdsToDelete);
                $itemsOnShelves = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($itemsOnShelves) && !$force) {
                    // If items exist and we are NOT forcing deletion, throw exception with data for the modal
                    $dataForConfirmation = [];
                    foreach ($itemsOnShelves as $item) {
                        $dataForConfirmation[$item['shelf_name']][] = $item['Item_Name'];
                    }
                    throw new ConfirmationRequiredException('Shelves contain items.', $dataForConfirmation);
                }

                // If forcing deletion, first set the items' shelf_id to NULL
                if ($force && !empty($itemsOnShelves)) {
                    $updateInvStmt = $this->db->prepare("UPDATE inventory SET shelf_id = NULL WHERE shelf_id IN ($placeholdersForIds)");
                    $updateInvStmt->execute($shelfIdsToDelete);
                }

                // Now, it's safe to delete the shelves
                $deleteStmt = $this->db->prepare("DELETE FROM shelves WHERE id IN ($placeholdersForIds)");
                $deleteStmt->execute($shelfIdsToDelete);
            }

            // 3. Upsert (Update or Insert) the shelves from the payload.
            // This relies on a UNIQUE constraint on the `shelf_name` column.
            $upsertStmt = $this->db->prepare(
                "INSERT INTO shelves (shelf_name, pos_x, pos_y, width, height, rotation)
                 VALUES (:name, :px, :py, :w, :h, :r)
                 ON DUPLICATE KEY UPDATE
                    pos_x = VALUES(pos_x),
                    pos_y = VALUES(pos_y),
                    width = VALUES(width),
                    height = VALUES(height),
                    rotation = VALUES(rotation)"
            );

            foreach ($shelves as $shelf) {
                if (!isset($shelf['shelf_name'], $shelf['pos_x'], $shelf['pos_y'], $shelf['width'], $shelf['height'], $shelf['rotation'])) {
                    continue; // Skip invalid shelf data
                }
                $shelf['shelf_name'] = preg_replace('/\s+/u', ' ', trim($shelf['shelf_name'])); // Normalize whitespace before saving
                $upsertStmt->execute([
                    ':name' => $shelf['shelf_name'],
                    ':px'   => $shelf['pos_x'],
                    ':py'   => $shelf['pos_y'],
                    ':w'    => $shelf['width'],
                    ':h'    => $shelf['height'],
                    ':r'    => $shelf['rotation'] ?? 0
                ]);
            }

            $this->db->commit();
            return count($shelves);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (str_contains($e->getMessage(), 'Unknown column')) {
                $this->lastError = "Database Schema Mismatch. Please ensure the 'shelves' table has a 'rotation' column.";
            } elseif (str_contains($e->getMessage(), 'Duplicate entry')) {
                $this->lastError = "Database Error: Shelf names must be unique. Please ensure a UNIQUE constraint is on the 'shelf_name' column.";
            } else {
                $this->lastError = "Database Error: " . $e->getMessage();
            }
        }
    }

    public function getUserProfilePageData($userId, $userRole) {
        if (!$this->db) { return []; }

        $data = [
            'identity' => $this->getUserProfileData($userId),
            'role_specific' => [],
        ];

        try {
            switch ($userRole) {
                case 'Student':
                    $studentData = $this->getStudentDashboardData($userId);
                    $liabilityData = $this->checkLiability($userId);
                    $data['role_specific'] = [
                        'items_borrowed' => $studentData['issued_sessions'] ?? 0,
                        'active_requisitions' => $studentData['pending_sessions'] ?? 0,
                        'unresolved_liabilities' => count($liabilityData['items']),
                        'is_cleared' => !$liabilityData['has_liability'] && ($studentData['issued_sessions'] ?? 0) === 0,
                        'upcoming_deadlines' => $studentData['upcoming_deadlines'] ?? [],
                        'classes' => $this->getStudentEnrolledClasses($userId),
                    ];
                    break;
                case 'Teacher':
                    $data['role_specific']['classes'] = $this->getTeacherClasses($userId);
                    $data['role_specific']['recent_activities'] = array_slice($this->getTeacherActivities($userId), 0, 5);
                    $data['role_specific']['action_center']['pending_reqs'] = $this->countPendingRequests($userId);
                    
                    $stmt = $this->db->prepare("SELECT COUNT(DISTINCT dr.student_id) FROM damaged_returns dr JOIN borrowing_sessions bs ON dr.session_id = bs.SessionID JOIN activity_assignments aa ON bs.ActivityID = aa.ActivityID JOIN classes c ON aa.ClassID = c.ClassID WHERE c.TeacherID = :tid AND dr.status IN ('Unresolved', 'Under Review')");
                    $stmt->execute([':tid' => $userId]);
                    $data['role_specific']['action_center']['unresolved_liabilities'] = $stmt->fetchColumn();
                    break;
                case 'LabTech':
                    $kpiData = $this->getLabTechDashboardData(); // This is mock data
                    $handledSlipsQuery = "SELECT bs.SessionID, bs.Status, bs.CreatedAt, m.Full_Name as StudentName FROM borrowing_sessions bs JOIN users u ON bs.StudentID = u.UserID JOIN lookup_masterlist m ON u.MasterID = m.MasterID WHERE bs.approver_user_id = :uid OR bs.handler_user_id = :uid ORDER BY bs.CreatedAt DESC LIMIT 5";
                    $stmt = $this->db->prepare($handledSlipsQuery);
                    $stmt->execute([':uid' => $userId]);
                    $handledTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $data['role_specific']['kpis'] = [
                        'pending_reqs' => $kpiData['pending_reqs_count'],
                        'for_handover' => $kpiData['approved_reqs_count'],
                        'awaiting_return' => $kpiData['issued_items_count'],
                        'pending_settlements' => $kpiData['pending_settlements_count'],
                    ];
                    $data['role_specific']['handled_transactions'] = $handledTransactions;
                    $data['role_specific']['handover_trend'] = $kpiData['handover_trend']; // For chart
                    break;
                case 'Admin':
                    $kpiData = $this->getAdminKPIs(); // This is mock data
                    $actionLog = $this->getAdminActionLog($userId); // This is also mock data

                    $data['role_specific']['kpis'] = [
                        'total_stock' => $kpiData['total_stock'],
                        'total_users' => $kpiData['total_users'],
                        'open_damages' => $kpiData['open_damages'],
                        'pending_reqs' => $kpiData['pending_reqs'],
                    ];
                    $data['role_specific']['user_distribution'] = $kpiData['user_population_by_role'];
                    $data['role_specific']['inventory_composition'] = $kpiData['categories'];
                    $data['role_specific']['action_log'] = $actionLog;
                    break;
            }
            return $data;
        } catch (PDOException $e) {
            error_log("getUserProfilePageData Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves a log of actions performed by a specific admin.
     * NOTE: This is a placeholder. A real implementation requires a dedicated `admin_logs` table.
     * @param int $adminId The UserID of the admin.
     * @return array An array of log entries.
     */
    public function getAdminActionLog($adminId) {
        // In a real application, you would query a dedicated `admin_logs` table:
        // $sql = "SELECT * FROM admin_logs WHERE admin_user_id = ? ORDER BY timestamp DESC LIMIT 20";
        // For this example, we return mock data.
        return [
            [
                'type' => 'INVENTORY',
                'icon' => 'fa-boxes-stacked',
                'color' => 'blue',
                'description' => 'Registered a new item: <strong>Beaker (500ml)</strong>.',
                'timestamp' => '2026-03-20 11:45:12'
            ],
            [
                'type' => 'SETTLEMENT',
                'icon' => 'fa-gavel',
                'color' => 'green',
                'description' => 'Resolved damage case #102 for student <strong>Maria Clara</strong>.',
                'timestamp' => '2026-03-20 10:21:05'
            ],
            [
                'type' => 'USER',
                'icon' => 'fa-user-pen',
                'color' => 'orange',
                'description' => 'Updated the role of <strong>Jose Rizal</strong> to <em>Teacher</em>.',
                'timestamp' => '2026-03-19 16:30:00'
            ]
        ];
    }

    public function getStudentClearanceSummary($studentIdentifier) {
        // $studentIdentifier can be UserID, MasterID, or ID_Number
        $sql = "SELECT u.UserID, m.MasterID, m.Full_Name, m.ID_Number, m.Role
                FROM users u
                JOIN lookup_masterlist m ON u.MasterID = m.MasterID
                WHERE u.UserID = :id OR m.MasterID = :id OR m.ID_Number = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $studentIdentifier]);
        $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$studentInfo) {
            return null; // Student not found
        }

        $studentID = $studentInfo['UserID'];

        // Fetch all borrowing sessions
        $sessionSql = "SELECT SessionID, Status, bs.CreatedAt, Remarks,
                        COALESCE(la.Title, 'Independent Research') as ActivityTitle
                    FROM borrowing_sessions bs
                    LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
                    WHERE bs.StudentID = ?
                    ORDER BY bs.CreatedAt DESC";
        $sessionStmt = $this->db->prepare($sessionSql);
        $sessionStmt->execute([$studentID]);
        $borrowingSessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all damage logs
        $damageSql = "SELECT dr.*, i.Item_Name
                    FROM damaged_returns dr
                    JOIN inventory i ON dr.item_id = i.ItemID
                    WHERE dr.student_id = ?
                    ORDER BY dr.logged_at DESC";
        $damageStmt = $this->db->prepare($damageSql);
        $damageStmt->execute([$studentID]);
        $damageLogs = $damageStmt->fetchAll(PDO::FETCH_ASSOC);

        // Determine overall clearance status
        $isCleared = true;
        if (array_search('Issued', array_column($borrowingSessions, 'Status')) !== false) $isCleared = false;
        if (array_search('Approved', array_column($borrowingSessions, 'Status')) !== false) $isCleared = false;
        if (array_search('Pending', array_column($borrowingSessions, 'Status')) !== false) $isCleared = false;
        if (array_search('Unresolved', array_column($damageLogs, 'status')) !== false) $isCleared = false;
        if (array_search('Under Review', array_column($damageLogs, 'status')) !== false) $isCleared = false;

        return ['student' => $studentInfo, 'sessions' => $borrowingSessions, 'damages' => $damageLogs, 'is_cleared' => $isCleared];
    }

    public function getStudentFullTransactionHistory($studentId) {
        // Fetch all borrowing sessions
        $sessionSql = "SELECT 
                            SessionID as id,
                            'borrow' as type,
                            bs.CreatedAt as date,
                            Status as status,
                            COALESCE(la.Title, 'Independent Research') as title
                       FROM borrowing_sessions bs
                       LEFT JOIN lab_activities la ON bs.ActivityID = la.ActivityID
                       WHERE bs.StudentID = ?";
        $sessionStmt = $this->db->prepare($sessionSql);
        $sessionStmt->execute([$studentId]);
        $sessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all damage logs
        $damageSql = "SELECT 
                          dr.damage_id as id,
                          'damage' as type,
                          dr.logged_at as date,
                          dr.status as status,
                          i.Item_Name as title
                      FROM damaged_returns dr
                      JOIN inventory i ON dr.item_id = i.ItemID
                      WHERE dr.student_id = ?";
        $damageStmt = $this->db->prepare($damageSql);
        $damageStmt->execute([$studentId]);
        $damages = $damageStmt->fetchAll(PDO::FETCH_ASSOC);

        // Merge and sort
        $history = array_merge($sessions, $damages);
        usort($history, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $history;
    }
}
}
