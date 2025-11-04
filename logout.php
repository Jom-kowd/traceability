<?php
session_start();
$_SESSION = array(); // Unset all session variables
if (ini_get("session.use_cookies")) { // Delete session cookie
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy(); // Destroy the session
header("Location: login.php?status=loggedout"); // Redirect to login page
exit;
?>