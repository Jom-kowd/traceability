<?php
// Include DB connection
include_once 'db.php';
// Start session to check login status for nav bar
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$error_message = '';
// Check if redirected from track_food.php with an error
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars(urldecode($_GET['error']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Organic Food Traceability</title>
    <link rel="stylesheet" href="public_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style> body { margin: 0; } </style>
</head>
<body>
    <header class="public-navbar">
        <a href="index.php" class="brand">Organic Food Traceability</a>
        <div class="links">
            <a href="track_food.php">Track Food</a>
            <?php
            // Show Dashboard/Logout if logged in, else Login/Register
            if (isset($_SESSION['user_id']) && isset($_SESSION['role_name'])) {
                echo '<a href="dashboard.php">Dashboard</a>';
                echo '<a href="logout.php">Logout</a>';
            } else {
                echo '<a href="login.php">Login</a>';
                echo '<a href="register.php">Register</a>';
            }
            ?>
        </div>
    </header>

    <div class="public-content-wrapper">

        <section class="intro-container">
            <h1>Welcome to the Organic Food Traceability System</h1>
            <p>
                Ensuring <strong>transparency</strong> and <strong>trust</strong> in the organic food supply chain.
                Follow the journey of your food from the farm, through processing and distribution,
                right to your table using the tracking tool below.
            </p>
            <p>
                Supply chain participants can <strong>Login</strong> or <strong>Register</strong> via the links in the header.
            </p>
        </section>

        <footer class="public-footer">
            <p>&copy; <?php echo date("Y"); ?> Organic Food Traceability System. All rights reserved.</p>
        </footer>

    </div></body> </html> <?php
// Close the database connection *only* if it exists and is open
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>