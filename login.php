<?php
// Start session only if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once 'db.php'; // Use include_once

// If user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
$username_value = ''; // To repopulate form

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $username_value = $username; // Store for repopulation

    if (empty($username) || empty($password)) {
        $error_message = "Username and password required.";
    } else {
        // Correct SQL Query
        $sql = "SELECT Users.*, UserRoles.RoleName
                FROM Users
                JOIN UserRoles ON Users.RoleID = UserRoles.RoleID
                WHERE Users.Username = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Check password
                if (password_verify($password, $user['PasswordHash'])) {
                    
                    // Check status
                    if ($user['VerificationStatus'] == 'Approved') {
                        session_regenerate_id(true); // Security first
                        // Set session variables
                        $_SESSION['user_id'] = $user['UserID'];
                        $_SESSION['username'] = $user['Username'];
                        $_SESSION['role_name'] = $user['RoleName'];
                        
                        // **REDIRECT LOGIC**
                        if ($user['RoleName'] == 'Admin') {
                            header("Location: admin/index.php"); // Admin redirect
                        } else {
                            header("Location: dashboard.php"); // ALL other roles go to dashboard
                        }
                        exit; // Stop script
                        
                    } elseif ($user['VerificationStatus'] == 'Pending') {
                        $error_message = "Account pending verification.";
                    } elseif ($user['VerificationStatus'] == 'Rejected') {
                        $error_message = "Account rejected.";
                    } else {
                        $error_message = "Invalid account status.";
                    }
                } else {
                    $error_message = "Invalid username or password."; // Password FAILED
                }
            } else {
                $error_message = "Invalid username or password."; // User NOT found
            }
            $stmt->close();
        } else {
            $error_message = "Database error.";
            error_log("Login prep fail: ".$conn->error);
        }
    }

    // If we are here, login failed. Redirect back to login.php with an error.
    header("Location: login.php?error=" . urlencode($error_message));
    exit;
}

// Check for success message (e.g., from registration)
$success_msg = '';
if(isset($_GET['success'])) { $success_msg = htmlspecialchars(urldecode($_GET['success'])); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Organic Food Traceability</title>
    <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style> body { margin: 0; } </style>
</head>
<body>
    <header class="public-navbar">
        <a href="index.php" class="brand">Organic Food Traceability</a>
        <div class="links">
            <a href="track_food.php">Track Food</a>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </div>
    </header>

    <div class="public-content-wrapper">
        <div class="form-container">
            <h2>Login</h2>
            <p>Enter your credentials to access the system.</p>
            <?php
            // Display messages
            if (!empty($error_message)) { echo '<p class="message error">Error: '.htmlspecialchars($error_message).'</p>'; }
            if (isset($_GET['error'])) { echo '<p class="message error">Error: '.htmlspecialchars(urldecode($_GET['error'])).'</p>'; } // Show errors from redirect
            if (!empty($success_msg)) { echo '<p class="message success">'.htmlspecialchars($success_msg).'</p>'; }
            ?>
            <form action="login.php" method="post">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username_value); ?>">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <button type="submit" name="login">Login</button>
                <p>No account? <a href="register.php">Register here</a>.</p>
            </form>
        </div>
        <footer class="public-footer"><p>&copy; <?php echo date("Y"); ?> Organic Food Traceability System.</p></footer>
    </div></body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>