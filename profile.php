<?php
// Include header first. It handles security and connection.
// $conn, $user_id, $role are available
include_once 'header_app.php';

$message = '';
$error_message = '';

// Check for redirect messages
if(isset($_GET['error'])) {
    if($_GET['error'] == 'complete_profile') $error_message = "Please complete your profile (Full Name and Address) before proceeding.";
    if($_GET['error'] == 'mismatch') $error_message = "New passwords did not match.";
    if($_GET['error'] == 'incorrect') $error_message = "Incorrect old password.";
    if($_GET['error'] == 'db_error_pass') $error_message = "Could not update password due to a database error.";
    if($_GET['error'] == 'db_error_profile') $error_message = "Could not update profile due to a database error.";
}
if(isset($_GET['success'])) {
    if($_GET['success'] == 'password_changed') $message = "Password updated successfully!";
    if($_GET['success'] == 'profile_updated') $message = "Profile updated successfully!";
}


// --- Handle Profile Update Form Submission ---
if (isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);

    // Validation
    if (empty($fullname) || empty($address) || empty($email)) {
        $error_message = "Full Name, Email, and Address are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $error_message = "Invalid email format.";
    } else {
        // Update user data in database
        $sql_update = "UPDATE Users SET FullName = ?, Email = ?, ContactNumber = ?, Address = ? WHERE UserID = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("ssssi", $fullname, $email, $contact, $address, $user_id);
            if ($stmt_update->execute()) {
                // Success! Redirect to clear POST data and show success
                header("Location: profile.php?success=profile_updated"); exit;
            } else {
                if($conn->errno == 1062) { // Duplicate email
                     $error_message = "That email address is already in use by another account.";
                } else {
                     $error_message = "Failed to update profile. " . $stmt_update->error;
                     error_log("Profile update failed: ".$stmt_update->error);
                }
            }
            $stmt_update->close();
        } else { $error_message = "Database error. Please try again."; error_log("Profile update prepare fail: ".$conn->error); }
    }
}


// --- Fetch Current User Data for Form ---
// We use the $user_id from header_app.php
$sql_user = "SELECT Username, FullName, Email, ContactNumber, Address FROM Users WHERE UserID = ?";
$stmt_user = $conn->prepare($sql_user);
$user_data = null;
if($stmt_user){
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user_data = $result_user->fetch_assoc();
    $stmt_user->close();
}
if (!$user_data) {
    // This should not happen if header_app.php worked
    echo "<h1>Error</h1><p>Could not retrieve user data.</p></div></body></html>";
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
    exit;
}

?>
<title>My Profile - Organic Traceability</title>
<style>
/* Add/reuse form styles from app_style.css if not already present */
.form-card { max-width: 700px; margin: 1rem auto; background-color: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.form-card label { display: block; margin-bottom: .5rem; font-weight: bold; }
.form-card input[type="text"], .form-card input[type="email"], .form-card input[type="password"], .form-card textarea { width: 100%; padding: .75rem; margin-bottom: 1.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
.form-card input[disabled] { background-color: #e9ecef; cursor: not-allowed; }
.form-card textarea { min-height: 100px; resize: vertical; }
.btn-submit { background-color: #28a745; color: white; padding: .75rem 1.5rem; border: none; border-radius: 4px; font-size: 1rem; font-weight: bold; cursor: pointer; }
.btn-submit:hover { background-color: #218838; }
.profile-section { margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid #eee; }
.profile-section:last-child { border-bottom: none; margin-bottom: 0; }
.message { padding: 1rem; border-radius: 5px; margin: 1rem 0; font-weight: bold; text-align: center; }
.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
</style>

<div class="page-header"><h1>My Profile</h1></div>

<div class="form-card">
    
    <?php
    if (!empty($error_message)) { echo '<p class="message error">'.htmlspecialchars($error_message).'</p>'; }
    if (!empty($message)) { echo '<p class="message success">'.htmlspecialchars($message).'</p>'; }
    ?>

    <div class="profile-section">
        <h2>Account Details</h2>
        <form action="profile.php" method="POST">
            <label for="username">Username (read-only):</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['Username'] ?? ''); ?>" disabled>

            <label for="role">Role (read-only):</label>
            <input type="text" id="role" name="role" value="<?php echo htmlspecialchars($role); ?>" disabled>

            <label for="fullname">Full Name (Required):</label>
            <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user_data['FullName'] ?? ''); ?>" required>

            <label for="email">Email (Required):</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>" required>

            <label for="contact">Contact Number (Optional):</label>
            <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($user_data['ContactNumber'] ?? ''); ?>">

            <label for="address">Address / Location (Required for <?php echo htmlspecialchars($role); ?>):</label>
            <textarea id="address" name="address" required placeholder="Enter your full address"><?php echo htmlspecialchars($user_data['Address'] ?? ''); ?></textarea>

            <button type="submit" name="update_profile" class="btn-submit">Update Profile</button>
        </form>
    </div>

    <div class="profile-section">
        <h2>Change Password</h2>
        <form action="change_password.php" method="POST">
            <label for="old_pass">Old Password:</label>
            <input type="password" id="old_pass" name="old_pass" required>

            <label for="new_pass">New Password (min. 6):</label>
            <input type="password" id="new_pass" name="new_pass" required minlength="6">

            <label for="confirm_pass">Confirm New Password:</label>
            <input type="password" id="confirm_pass" name="confirm_pass" required minlength="6">

            <button type="submit" name="change_pwd" class="btn-submit" style="background-color: #0d6efd;">Change Password</button>
        </form>
    </div>

</div>

</div></body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>