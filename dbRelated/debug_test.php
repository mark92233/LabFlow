<?php
// dbRelated/debug_test.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Report</h1>";

// 1. CHECK FILE INCLUSION
echo "<h3>1. Checking operation.php...</h3>";
if (!file_exists('operation.php')) {
    die("<span style='color:red'>❌ operation.php NOT FOUND. Check your file path!</span>");
}
require_once __DIR__ . '/operation.php';
echo "<span style='color:green'>✅ operation.php loaded.</span><br>";
__DIR__ . '/operation.php';
echo "<span style='color:green'>✅ operation.php loaded.</span><br>";
__DIR__ . '/operation.php';
echo "<span style='color:green'>✅ operation.php loaded.</span><br>";

// 2. CHECK CLASS INSTANTIATION
echo "<h3>2. Testing DataManager Class...</h3>";
if (!class_exists('DataManager')) {
    die("<span style='color:red'>❌ Class 'DataManager' not found. Check class name in operation.php</span>");
}

try {
    $db = new DataManager();
    echo "<span style='color:green'>✅ DataManager instantiated.</span><br>";
} catch (Exception $e) {
    die("<span style='color:red'>❌ Failed to create DataManager: " . $e->getMessage() . "</span>");
}

// 3. CHECK CONNECTION VARIABLE
echo "<h3>3. Finding Database Connection...</h3>";
$conn = null;
if (isset($db->pdo)) {
    echo "Found property: <strong>\$db->pdo</strong><br>";
    $conn = $db->pdo;
} elseif (isset($db->conn)) {
    echo "Found property: <strong>\$db->conn</strong><br>";
    $conn = $db->conn;
} elseif (isset($db->connection)) {
    echo "Found property: <strong>\$db->connection</strong><br>";
    $conn = $db->connection;
} else {
    echo "<span style='color:orange'>⚠️ Could not find public connection property. Checking if 'getStudentsByClassList' exists...</span><br>";
}

// 4. CHECK IF NEW FUNCTION EXISTS
echo "<h3>4. Checking New Function...</h3>";
if (method_exists($db, 'getStudentsByClassList')) {
    echo "<span style='color:green'>✅ method 'getStudentsByClassList' EXISTS in DataManager.</span><br>";
    
    // TRY RUNNING IT
    echo "<h3>5. Test Run Query...</h3>";
    try {
        // We use a dummy ID (e.g., 1). If your DB is empty, it returns [], which is fine. 
        // If it crashes, we know the SQL is wrong.
        $result = $db->getStudentsByClassList([1, 2, 3]); 
        echo "<span style='color:green'>✅ Query Executed Successfully!</span><br>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } catch (Exception $e) {
        echo "<span style='color:red'>❌ Query Failed: " . $e->getMessage() . "</span><br>";
        echo "Check your table names in operation.php (student_list vs students?)";
    }
} else {
    echo "<span style='color:red'>❌ Method 'getStudentsByClassList' DOES NOT EXIST.</span><br>";
    echo "You likely didn't paste the function into operation.php correctly.<br>";
}

echo "<h3>End of Report</h3>";
?>