<?php
// Includes admin security check, session_start(), $conn, $user_id (admin's ID)
include_once 'header.php';

// Check if required GET parameters are set
if (isset($_GET['id']) && isset($_GET['action'])) {
    // Sanitize input: Ensure ID is an integer, action is a string
    $user_id_to_verify = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $action = trim($_GET['action']); // Allowed: 'approve' or 'reject'
    $admin_id = $_SESSION['user_id']; // ID of the admin performing the action

    $new_status = '';
    $error_redirect_code = ''; // To store specific error code for redirection

    // Determine the new status based on the action
    if ($action === 'approve') {
        $new_status = 'Approved';
    } elseif ($action === 'reject') {
        $new_status = 'Rejected';
    } else {
        // Invalid action provided in URL
        $error_redirect_code = 'invalidaction';
    }

    // Validate the user ID
    if ($user_id_to_verify === false || $user_id_to_verify <= 0) {
         $error_redirect_code = 'invaliduserid';
    }

    // Proceed only if inputs are valid so far
    if (empty($error_redirect_code)) {
        // Prepare the SQL update statement
        // Update only if the user is currently 'Pending' to prevent accidental re-verification
        $sql = "UPDATE Users
                SET VerificationStatus = ?, VerifiedByAdminID = ?, VerificationDate = NOW()
                WHERE UserID = ? AND VerificationStatus = 'Pending'";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // Bind parameters: s=string (status), i=integer (adminID), i=integer (userID)
            $stmt->bind_param("sii", $new_status, $admin_id, $user_id_to_verify);

            // Execute the update
            if ($stmt->execute()) {
                // Check if any row was actually updated
                if ($stmt->affected_rows > 0) {
                    header("Location: index.php?success=updated"); // Success redirect
                    exit;
                } else {
                    // No rows updated - likely means user wasn't pending or ID was wrong
                    $error_redirect_code = 'notpending_or_notfound';
                }
            } else {
                // Execute failed - Database error during update
                error_log("Admin verify execute failed: UserID=" . $user_id_to_verify . ", Error: " . $stmt->error);
                $error_redirect_code = 'updatefailed_db';
            }
            $stmt->close(); // Close the statement
        } else {
            // Prepare statement failed
            error_log("Admin verify prepare failed: " . $conn->error);
            $error_redirect_code = 'preparefailed';
        }
    }

    // If any error occurred, redirect back to index with the error code
    header("Location: index.php?error=" . $error_redirect_code);

} else {
    // If 'id' or 'action' GET parameters are missing, just redirect back to the index
    header("Location: index.php");
}

// Close connection if open
if (isset($conn)) {
    $conn->close();
}
exit; // Ensure script stops execution after redirection
?>