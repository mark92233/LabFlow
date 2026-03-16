<?php
require_once __DIR__ . '/db_connect.php';

if (!class_exists('DataManager')) {
class DataManager {
    public $db;

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
        $query = "INSERT INTO inventory (CategoryID, Item_Name, Description, is_consumable, is_scalable, Total_Qty, Available_Qty, Location, Unit) 
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
            'loc' => $location,
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

            // 4. Prepare and execute the UPDATE statement
            $query = "UPDATE inventory SET Item_Name = :name, Description = :desc, Location = :loc, Total_Qty = :tqty, Available_Qty = :aqty WHERE ItemID = :id";
            $updateStmt = $this->db->prepare($query);
            $updateStmt->execute([':name' => $itemName, ':desc' => $description, ':loc' => $location, ':tqty' => $totalQty, ':aqty' => $newAvailableQty, ':id' => $itemId]);

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
        // MOCK DATA for demonstration purposes
        $stats = [];

        // 1. Inventory Stats
        $stats['unique_items'] = 128;
        $stats['total_stock'] = 2450;
        $stats['lowest_stock_items'] = [
            ['Item_Name' => 'Test Tubes', 'Available_Qty' => 5],
            ['Item_Name' => 'Filter Paper', 'Available_Qty' => 8],
            ['Item_Name' => 'Pipette Tips', 'Available_Qty' => 12],
            ['Item_Name' => 'Ethanol 95%', 'Available_Qty' => 15],
        ];

        // 2. User & Request Stats
        $stats['total_users'] = 450;
        $stats['pending_reqs'] = 12;
        $stats['open_damages'] = 3;

        // 3. Population Stats
        $stats['user_population_by_role'] = [
            ['Role' => 'Student', 'count' => 420],
            ['Role' => 'Teacher', 'count' => 25],
            ['Role' => 'Admin', 'count' => 5],
        ];
        $stats['student_pop'] = 420;
        $stats['teacher_pop'] = 25;
        $stats['total_classes'] = 15;

        // Class Demographics for chart
        $stats['class_demographics'] = [
            ['Class_Name' => 'GenChem 1', 'Section' => 'STEM-12A', 'student_count' => 35],
            ['Class_Name' => 'GenChem 1', 'Section' => 'STEM-12B', 'student_count' => 38],
            ['Class_Name' => 'Physics 2', 'Section' => 'STEM-12C', 'student_count' => 32],
            ['Class_Name' => 'Biology Lab', 'Section' => 'BSBio-2', 'student_count' => 28],
        ];

        // 4. Graph Data: Inventory by Category
        $stats['categories'] = [
            ['Category_Name' => 'Glassware', 'count' => 45],
            ['Category_Name' => 'Chemicals', 'count' => 60],
            ['Category_Name' => 'Apparatus', 'count' => 23],
        ];

        // 5. Graph Data: Session Status
        $stats['session_stats'] = [
            ['Status' => 'Pending', 'count' => 12],
            ['Status' => 'Approved', 'count' => 8],
            ['Status' => 'Issued', 'count' => 25],
            ['Status' => 'Returned', 'count' => 150],
            ['Status' => 'Cancelled', 'count' => 5],
        ];

        // 6. Graph Data: Damage Status
        $stats['damage_stats'] = [
            ['status' => 'Unresolved', 'count' => 3],
            ['status' => 'Under Review', 'count' => 2],
            ['status' => 'Resolved', 'count' => 18],
        ];

        // 7. Trend Data: Borrowing (Last 7 Days)
        $stats['borrowing_trend'] = [];
        for ($i = 6; $i >= 0; $i--) {
            $stats['borrowing_trend'][] = [
                'date' => date('Y-m-d', strtotime("-$i days")),
                'count' => rand(5, 25),
            ];
        }

        // 8. Trend Data: Damages (Last 7 Days)
        $stats['damage_trend'] = [];
        for ($i = 6; $i >= 0; $i--) {
            $stats['damage_trend'][] = [
                'date' => date('Y-m-d', strtotime("-$i days")),
                'count' => rand(0, 3),
            ];
        }

        return $stats;
    }

    public function getTeacherDashboardData($teacherID) {
        $data = [
            'total_students' => 0,
            'total_classes' => 0,
            'clearance_progress' => []
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

            // 3. Total Classes
            $sql_classes = "SELECT COUNT(*) FROM classes WHERE TeacherID = :tid";
            $stmt_classes = $this->db->prepare($sql_classes);
            $stmt_classes->execute(['tid' => $teacherID]);
            $data['total_classes'] = (int)$stmt_classes->fetchColumn();

            // 4. Class Clearance Progress
            $sql_clearance = "SELECT
                                c.ClassID, c.Class_Name, c.Section,
                                COUNT(ce.EnrollmentID) AS total_students,
                                SUM(CASE WHEN ce.ClearanceStatus = 'Cleared' THEN 1 ELSE 0 END) AS cleared_students
                              FROM classes c
                              LEFT JOIN class_enrollment ce ON c.ClassID = c.ClassID
                              WHERE c.TeacherID = :tid
                              GROUP BY c.ClassID, c.Class_Name, c.Section
                              ORDER BY c.Class_Name, c.Section";
            $stmt_clearance = $this->db->prepare($sql_clearance);
            $stmt_clearance->execute(['tid' => $teacherID]);
            $data['clearance_progress'] = $stmt_clearance->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Teacher Dashboard Data Error: " . $e->getMessage());
            return []; // Return empty array on error
        }
        return $data;
    }

    public function getStudentDashboardData($studentID) {
        $data = [
            'my_classes' => [],
            'upcoming_deadlines' => [],
            'pending_sessions' => 0,
            'issued_sessions' => 0,
        ];

        try {
            // 1. Get Classes and Activity Counts
            $sql_classes = "SELECT 
                                c.ClassID, c.Class_Name, c.Section, c.Semester, m.Full_Name as TeacherName
                            FROM class_enrollment ce
                            JOIN classes c ON ce.ClassID = c.ClassID
                            JOIN users u_teacher ON c.TeacherID = u_teacher.UserID
                            JOIN lookup_masterlist m ON u_teacher.MasterID = m.MasterID
                            JOIN users u_student ON ce.MasterID = u_student.MasterID
                            WHERE u_student.UserID = :sid";
            $stmt_classes = $this->db->prepare($sql_classes);
            $stmt_classes->execute(['sid' => $studentID]);
            $data['my_classes'] = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

            // 2. Get Upcoming Deadlines
            $sql_deadlines = "SELECT la.ActivityID, la.Title, la.Deadline, c.Class_Name, c.ClassID
                              FROM lab_activities la
                              JOIN activity_assignments aa ON la.ActivityID = aa.ActivityID
                              JOIN classes c ON aa.ClassID = c.ClassID
                              JOIN class_enrollment ce ON c.ClassID = ce.ClassID
                              JOIN users u ON ce.MasterID = u.MasterID
                              WHERE u.UserID = :sid AND la.Deadline >= NOW()
                              GROUP BY la.ActivityID
                              ORDER BY la.Deadline ASC
                              LIMIT 3";
            $stmt_deadlines = $this->db->prepare($sql_deadlines);
            $stmt_deadlines->execute(['sid' => $studentID]);
            $data['upcoming_deadlines'] = $stmt_deadlines->fetchAll(PDO::FETCH_ASSOC);

            // 3. Get Session Counts
            $sql_sessions = "SELECT Status, COUNT(*) as count FROM borrowing_sessions WHERE StudentID = :sid AND Status IN ('Pending', 'Issued') GROUP BY Status";
            $stmt_sessions = $this->db->prepare($sql_sessions);
            $stmt_sessions->execute(['sid' => $studentID]);
            $counts = $stmt_sessions->fetchAll(PDO::FETCH_KEY_PAIR);
            $data['pending_sessions'] = $counts['Pending'] ?? 0;
            $data['issued_sessions'] = $counts['Issued'] ?? 0;

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
        $page = $options['page'] ?? 1;
        $offset = ($page - 1) * $limit;
        $search = $options['search'] ?? '';
        $category = $options['category'] ?? 'all';
    
        $whereClauses = [];
        $params = [];
    
        if (!empty($search)) {
            $whereClauses[] = "i.Item_Name LIKE :search";
            $params[':search'] = "%$search%";
        }
    
        if ($category !== 'all' && is_numeric($category)) {
            $whereClauses[] = "i.CategoryID = :category";
            $params[':category'] = $category;
        }
    
        $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
        // Query for total count
        $countSql = "SELECT COUNT(i.ItemID) FROM inventory i $whereSql";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int) $countStmt->fetchColumn();
    
        // Query for data
        $dataSql = "SELECT i.ItemID, i.Item_Name, i.CategoryID FROM inventory i $whereSql ORDER BY i.Item_Name ASC LIMIT :limit OFFSET :offset";
        $dataStmt = $this->db->prepare($dataSql);
        
        foreach ($params as $key => $val) { $dataStmt->bindValue($key, $val); }
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $items = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
        return ['items' => $items, 'total' => $totalRecords, 'pages' => ceil($totalRecords / $limit), 'currentPage' => $page];
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
                    iv.Unit
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
    public function resolveDamage($damage_id) {
        if (!$this->db) { return false; }
        try {
            $sql = "UPDATE damaged_returns SET status = 'Resolved' WHERE damage_id = ?";
            return $this->db->prepare($sql)->execute([$damage_id]);
        } catch (PDOException $e) { return false; }
    }

    // Reject proof of settlement (Student must re-upload)
    public function rejectDamage($damage_id, $rejection_notes = null) {
        if (!$this->db) { return false; }
        try {
            // Reset status to Unresolved, clear the image proof, and add rejection notes
            $sql = "UPDATE damaged_returns SET status = 'Unresolved', proof_image = NULL, notes = ? WHERE damage_id = ?";
            return $this->db->prepare($sql)->execute([$rejection_notes, $damage_id]);
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
}
}
