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

// --- 2. Fetch Full User Data (including profile and expiry) ---
// !! UPDATED: Added CertificateExpiryDate and ValidIDExpiryDate
$sql_sec = "SELECT VerificationStatus, RoleID, FullName, Address, 
                   CertificateExpiryDate, ValidIDExpiryDate 
            FROM Users WHERE UserID = ?";
$stmt_sec = $conn->prepare($sql_sec);
$user_sec = null; // Initialize

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
$profile_incomplete = (empty($user_sec['FullName']) || empty($user_sec['Address']));
$current_page = basename($_SERVER['PHP_SELF']);
$allowed_pages = ['profile.php', 'change_password.php', 'logout.php', 'dashboard.php'];
$roles_that_need_profile = ['Farmer', 'Manufacturer', 'Distributor', 'Retailer'];

if (in_array($role, $roles_that_need_profile) && $profile_incomplete && !in_array($current_page, $allowed_pages)) {
    header("Location: profile.php?error=complete_profile");
    exit;
}

// ==================================================================
// --- 5. NEW: Expiry Date Warning Check ---
// ==================================================================
$expiry_warning_message = '';
$is_business_role = in_array($role, $roles_that_need_profile);

if ($is_business_role && $current_page != 'profile.php') {
    $today = new DateTime();
    $warning_date = (new DateTime())->modify('+30 days'); // 30 days from now

    $cert_expiry_str = $user_sec['CertificateExpiryDate'];
    $id_expiry_str = $user_sec['ValidIDExpiryDate'];

    $expired_docs = [];
    $expiring_docs = [];

    // Check Certificate (Roles 1 & 2)
    if (($role == 'Farmer' || $role == 'Manufacturer') && !empty($cert_expiry_str)) {
        $cert_expiry_date = new DateTime($cert_expiry_str);
        if ($cert_expiry_date < $today) {
            $expired_docs[] = 'Organic Certificate';
        } elseif ($cert_expiry_date < $warning_date) {
            $expiring_docs[] = 'Organic Certificate';
        }
    }
    
    // Check Valid ID (Roles 1, 2, 3, 4)
    if (!empty($id_expiry_str)) {
        $id_expiry_date = new DateTime($id_expiry_str);
        if ($id_expiry_date < $today) {
            $expired_docs[] = 'Valid ID';
        } elseif ($id_expiry_date < $warning_date) {
            $expiring_docs[] = 'Valid ID';
        }
    }

    // Build the warning message
    if (!empty($expired_docs)) {
        $expiry_warning_message = "<strong>Action Required:</strong> Your " . implode(' and ', $expired_docs) . " has expired. Please <a href='profile.php'>update your profile</a> to ensure compliance.";
    } elseif (!empty($expiring_docs)) {
        $expiry_warning_message = "<strong>Warning:</strong> Your " . implode(' and ', $expiring_docs) . " will expire soon. Please <a href='profile.php'>update your profile</a>.";
    }
}
// --- End of Expiry Check ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="app_style.css"> 
    
    <style>
        .expiry-warning-banner {
            background-color: #fff3cd;
            color: #664d03;
            padding: 1rem 1.5rem;
            text-align: center;
            font-weight: 500;
            border-bottom: 1px solid #ffecb5;
        }
        .expiry-warning-banner a {
            color: #0056b3;
            font-weight: 700;
            text-decoration: underline;
        }
        .expiry-warning-banner.expired {
            background-color: #f8d7da;
            color: #721c24;
            border-bottom: 1px solid #f5c6cb;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <header class="top-navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-brand">Organic Food Traceability</a>
            <nav class="navbar-links">
                <?php
                // Role-Based Navigation
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
    
    <?php
    // --- !! NEW: Display the warning banner !! ---
    if (!empty($expiry_warning_message)) {
        $banner_class = (strpos($expiry_warning_message, 'expired') !== false) ? 'expired' : '';
        echo "<div class='expiry-warning-banner " . $banner_class . "'>" . $expiry_warning_message . "</div>";
    }
    ?>
    
<div class="main-content">