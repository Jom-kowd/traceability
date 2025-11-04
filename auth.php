<?php
// Start the session to access session variables
session_start();

// We need our database connection
include 'db.php';

// Check if the user is logged in (user_id is in the session)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login page
    header("Location: login.html?error=notloggedin");
    exit;
}

// We can also re-check the user's status just in case (optional but good security)
// This prevents a user who was 'Rejected' *after* logging in from continuing.
$user_id = $_SESSION['user_id'];
$sql = "SELECT VerificationStatus FROM Users WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || $user['VerificationStatus'] != 'Approved') {
    // User not found or not approved, destroy session and redirect
    session_unset();
    session_destroy();
    header("Location: login.html?error=notapproved");
    exit;
}

// If we are here, the user is logged in and approved.
// We can now use $_SESSION['user_id'], $_SESSION['username'], and $_SESSION['role_name']
?>