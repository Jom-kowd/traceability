<?php
// Include the header first - it provides $conn and checks login
include_once 'header_app.php';
// $conn, $role, $user_id are available if login was successful
?>
<title>Dashboard - Organic Traceability</title>

<h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h1>
<p>Your current role is: <strong><?php echo htmlspecialchars($role); ?></strong>.</p>

<?php
// --- NEW FARMER-FRIENDLY DASHBOARD ---
// We check the $role variable provided by header_app.php
if ($role == 'Farmer') {
    echo '<p>Use the quick links below to manage your farm.</p>';
    
    echo '<div class="icon-dashboard">';
    
    // Link to Manage Products
    echo '<a href="manage_products.php" class="dashboard-icon">';
    echo '  <i class="fas fa-seedling"></i>'; // Font Awesome icon
    echo '  Manage My Products';
    echo '</a>';

    // Link to View Incoming Orders
    echo '<a href="farmer_orders.php" class="dashboard-icon">';
    echo '  <i class="fas fa-inbox"></i>'; // Font Awesome icon
    echo '  Incoming Orders';
    echo '</a>';

    // Link to Profile
    echo '<a href="profile.php" class="dashboard-icon">';
    echo '  <i class="fas fa-user-cog"></i>'; // Font Awesome icon
    echo '  Update Profile';
    echo '</a>';

    echo '</div>';
} 
// --- You can add "else if" blocks here for other roles like Manufacturer or Retailer ---
/*
else if ($role == 'Manufacturer') {
    // ... Add Manufacturer icons here ...
}
*/
else {
    // Default message for other roles (Admin, Distributor, Consumer, etc.)
    echo '<p>Please use the links in the navigation bar above to access features.</p>';
}

// --- End of specific page content ---
?>

</div></body>
</html>
<?php
// CRITICAL: Close the connection *only* if it exists and is open, at the VERY END.
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>