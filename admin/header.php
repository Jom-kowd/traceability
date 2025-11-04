<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CHECK POINT 1: Session Check ---
// Are 'user_id' and 'role_name' actually set in the session after login?
// Is 'role_name' EXACTLY 'Admin'? (Case-sensitive!)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_name']) || $_SESSION['role_name'] != 'Admin') {

    // --- CHECK POINT 2: Redirect ---
    // If the session check fails, destroy session and redirect back to login.
    // Is the path '../login.html' correct (goes up one level)?
    $_SESSION = array(); // Clear session data
    if (ini_get("session.use_cookies")) { /* Delete session cookie */
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: ../login.html?error=unauthorized_admin"); // Redirect UP to parent login
    exit;
}

// --- CHECK POINT 3: Database Connection ---
// Include database connection (path goes UP one level then to db.php)
include_once __DIR__ . '/../db.php';

// Check if DB connection was successful
if (!isset($conn) || $conn->connect_error) {
     error_log("Admin DB Connection failed: " . ($conn->connect_error ?? 'Unknown error'));
     die("Database connection failed within admin area."); // Stop execution
}

// If script reaches here, user is logged in AND is an Admin.
?>