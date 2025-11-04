<?php
// Include header first. It handles security and connection.
// $conn, $user_id are available
include_once 'header_app.php';

// Check if the form was submitted
if (isset($_POST['change_pwd'])) {

    $old_pass = $_POST['old_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    // --- Validation ---
    if ($new_pass !== $confirm_pass) {
        header("Location: profile.php?error=mismatch"); // Redirect back
        exit;
    }
    if (strlen($new_pass) < 6) {
         header("Location: profile.php?error=short"); // Redirect back
         exit;
    }
    if (empty($old_pass)) {
         header("Location: profile.php?error=incorrect");
         exit;
    }

    // Check old password
    $sql_check = "SELECT PasswordHash FROM Users WHERE UserID = ?";
    $stmt_check = $conn->prepare($sql_check);
    if ($stmt_check) {
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        $user = $result->fetch_assoc();
        $stmt_check->close();

        if ($user && password_verify($old_pass, $user['PasswordHash'])) {
            // Old password IS correct, proceed to update
            $new_password_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $sql_update = "UPDATE Users SET PasswordHash = ? WHERE UserID = ?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("si", $new_password_hash, $user_id);
                if ($stmt_update->execute()) {
                    header("Location: profile.php?success=password_changed");
                    exit;
                } else {
                    error_log("Pwd update exec failed: ".$stmt_update->error);
                    header("Location: profile.php?error=db_error_pass");
                    exit;
                }
                 $stmt_update->close();
            } else {
                 error_log("Pwd update prep failed: ".$conn->error);
                 header("Location: profile.php?error=db_error_pass");
                 exit;
            }
        } else {
            header("Location: profile.php?error=incorrect");
            exit;
        }
    } else {
         error_log("Pwd check prep failed: ".$conn->error);
         header("Location: profile.php?error=db_error_pass");
         exit;
    }
} else {
    // If accessed directly without POST, redirect to profile
    header("Location: profile.php");
    exit;
}

// Close connection if it's still open (though redirects should exit first)
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>