<?php
// Start the session to gain access to it
session_start();

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will completely log out the user from the browser's perspective.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect back to the gateway
header("Location: index.php");
exit();
?>