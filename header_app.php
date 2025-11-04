<?php
// Start session only if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'db.php'; // Use include_once for DB connection

// --- 1. Basic Security Check (Is user logged in?) ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=notloggedin"); // Use login.php
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role_name'] ?? ''; // Safely get role

// --- 2. Fetch Full User Data (including profile fields) ---
$sql_sec = "SELECT VerificationStatus, RoleID, FullName, Address FROM Users WHERE UserID = ?";
$stmt_sec = $conn->prepare($sql_sec);

if ($stmt_sec) {
    $stmt_sec->bind_param("i", $user_id);
    $stmt_sec->execute();
    $result_sec = $stmt_sec->get_result();
    $user_sec = $result_sec->fetch_assoc();
    $stmt_sec->close();

    // --- 3. Verification Status Check ---
    if (!$user_sec || $user_sec['VerificationStatus'] != 'Approved') {
        session_unset(); session_destroy(); // Log out
        header("Location: login.php?error=notapproved");
        exit;
    }
} else {
    // Critical DB Error
    error_log("Failed security check prepare: " . $conn->error);
    session_unset(); session_destroy();
    header("Location: login.php?error=db_error");
    exit;
}

// ==================================================================
// --- 4. Profile Completion Check ---
// ==================================================================
// Check if essential profile fields are empty
$profile_incomplete = (empty($user_sec['FullName']) || empty($user_sec['Address']));

// Get the name of the current script (e.g., "manage_products.php")
$current_page = basename($_SERVER['PHP_SELF']);

// Define pages that are ALWAYS allowed, even with an incomplete profile
$allowed_pages = ['profile.php', 'change_password.php', 'logout.php', 'dashboard.php'];

// Define roles that MUST have a complete profile to operate
$roles_that_need_profile = ['Farmer', 'Manufacturer', 'Distributor', 'Retailer'];

// If the user's role needs a profile, AND their profile is incomplete,
// AND they are NOT trying to access an allowed page...
if (in_array($role, $roles_that_need_profile) && $profile_incomplete && !in_array($current_page, $allowed_pages)) {
    // ...force redirect them to profile.php.
    header("Location: profile.php?error=complete_profile");
    exit;
}
// If we are here, user is logged in, approved, and has a complete profile (if required).
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="app_style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
<body>
    <header class="top-navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-brand">Organic Food Traceability</a>
            <nav class="navbar-links">
                <?php
                // Role-Based Navigation (Latest version)
                if ($role == 'Farmer') {
                    echo '<a href="manage_products.php">My Raw Products</a> ';
                    echo '<a href="farmer_orders.php">Incoming Orders</a>';
                } elseif ($role == 'Manufacturer') {
                    echo '<a href="manage_products.php">My Processed Products</a> ';
                    echo '<a href="browse_farmer_products.php">Order Raw Materials</a> ';
                    echo '<a href="manufacturer_orders.php">Manage Orders</a>';
                } elseif ($role == 'Distributor') {
                    echo '<a href="distributor_dashboard.php">Manage Deliveries</a>';
                } elseif ($role == 'Retailer') {
                    echo '<a href="browse_manufacturer_products.php">Browse Products</a> ';
                    echo '<a href="retailer_orders.php">My Orders</a>';
                } elseif ($role == 'Consumer') {
                     echo '<a href="track_food.php">Track Food</a>';
                }
                ?>
            </nav>
            <div class="navbar-user-actions">
                <a href="profile.php" title="My Profile (<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>)"><i class="fas fa-user-circle"></i></a>
                <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </header>
<div class="main-content">