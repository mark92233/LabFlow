<?php
session_start();
require_once 'dbRelated/operation.php';

// Security: If they didn't come from the index.php identity check, send them back
if (!isset($_SESSION['login_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pass = $_POST['password'];
    $dataMgr = new DataManager();
    
    // Fetch the user by their MasterID (which we stored in session at index.php)
    // Fetch the user by their MasterID (which we stored in session at index.php)
$user = $dataMgr->checkExistingAccount($_SESSION['login_id']);

if ($user && password_verify($pass, $user['Password_Hash'])) {
    // FIX: Assign the primary key from the 'users' table, not the 'lookup_masterlist'
    $_SESSION['user_id'] = $user['UserID']; 
    
    $_SESSION['user_role'] = $user['Role']; 
    $_SESSION['user_name'] = $user['Full_Name']; // Use the name joined from the masterlist
        // Clean up temp variables
        unset($_SESSION['login_id']);
        if (empty($_SESSION['user_role'])) {
        die("Error: User role not found in database. Check your Users table.");
    }
        header("Location: dashboard/router.php");
        exit();
    } else {
        $error = "Incorrect password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded shadow-md w-96">
        <h2 class="text-2xl font-bold mb-4">Login</h2>
        <p class="mb-4 text-sm text-gray-600">Enter password for <b><?php echo $_SESSION['temp_name']; ?></b></p>
        
        <?php if($error): ?> <p class="text-red-500 text-sm mb-4"><?php echo $error; ?></p> <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <input type="password" name="password" placeholder="Password" class="w-full p-2 border rounded" required autofocus>
            <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700">Login to Dashboard</button>
        </form>
        <a href="session_kill.php" class="block mt-4 text-center text-xs text-gray-400 hover:underline">Not you? Switch account</a>
    </div>
</body>
</html>