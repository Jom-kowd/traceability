<?php
// Include the header first - it provides $conn, $role, and $user_id
include_once 'header_app.php';

// Initialize variables for counts
$pending_farmer_orders = 0;
$active_batches = 0;
$pending_mfr_orders_raw = 0;
$pending_mfr_orders_processed = 0;
$pending_distributor_deliveries = 0;

// Fetch counts based on user role
if ($role == 'Farmer') {
    // Count pending orders from Manufacturers
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ManufacturerFarmerOrders WHERE FarmerID = ? AND Status = 'Pending Confirmation'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending_farmer_orders = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Count active batches (with stock > 0)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ProductBatches WHERE UserID = ? AND RemainingQuantity > 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $active_batches = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

} elseif ($role == 'Manufacturer') {
    // Count pending raw material orders (orders they placed)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ManufacturerFarmerOrders WHERE ManufacturerID = ? AND (Status = 'Pending Confirmation' OR Status = 'Confirmed')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending_mfr_orders_raw = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // Count pending processed orders (orders they received)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Orders WHERE SellerID = ? AND (Status = 'Pending Confirmation' OR Status = 'Processing')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending_mfr_orders_processed = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
} elseif ($role == 'Distributor') {
    // Count pending deliveries
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Orders WHERE AssignedDistributorID = ? AND Status = 'Assigned'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending_distributor_deliveries = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
}

?>
<title>Dashboard - Organic Traceability</title>

<div class="page-header" style="border-bottom: none; margin-bottom: 1.5rem;">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h1>
</div>
<p style="margin-top: -1.5rem; margin-bottom: 2rem; font-size: 1.1rem; color: #555;">Your current role is: <strong><?php echo htmlspecialchars($role); ?></strong>.</p>

<div class="dashboard-grid">

    <?php
    // ==================================================================
    // --- FARMER DASHBOARD ---
    // ==================================================================
    if ($role == 'Farmer'): ?>
        
        <div class="widget-card-container">
            <div class="widget-card">
                <div class="widget-icon"><i class="fas fa-inbox"></i></div>
                <div class="widget-info">
                    <div class="widget-value"><?php echo $pending_farmer_orders; ?></div>
                    <div class="widget-title">Pending Orders</div>
                </div>
            </div>
            <div class="widget-card">
                <div class="widget-icon" style="color: #28a745;"><i class="fas fa-boxes"></i></div>
                <div class="widget-info">
                    <div class="widget-value"><?php echo $active_batches; ?></div>
                    <div class="widget-title">Active Batches (In Stock)</div>
                </div>
            </div>
        </div>

        <div class="icon-dashboard">
            <a href="manage_products.php" class="dashboard-icon">
                <i class="fas fa-seedling"></i>
                Manage My Products
            </a>
            <a href="farmer_orders.php" class="dashboard-icon">
                <i class="fas fa-inbox"></i>
                View Incoming Orders
            </a>
            <a href="add_product.php" class="dashboard-icon">
                <i class="fas fa-plus-circle"></i>
                Add New Product
            </a>
        </div>

    <?php
    // ==================================================================
    // --- MANUFACTURER DASHBOARD ---
    // ==================================================================
    elseif ($role == 'Manufacturer'): ?>

        <div class="widget-card-container">
            <div class="widget-card">
                <div class="widget-icon" style="color: #ffc107;"><i class="fas fa-truck-loading"></i></div>
                <div class="widget-info">
                    <div class="widget-value"><?php echo $pending_mfr_orders_raw; ?></div>
                    <div class="widget-title">Pending Raw Orders</div>
                </div>
            </div>
            <div class="widget-card">
                <div class="widget-icon" style="color: #6f42c1;"><i class="fas fa-receipt"></i></div>
                <div class="widget-info">
                    <div class="widget-value"><?php echo $pending_mfr_orders_processed; ?></div>
                    <div class="widget-title">Orders to Fulfill</div>
                </div>
            </div>
        </div>

        <div class="icon-dashboard">
            <a href="manage_products.php" class="dashboard-icon">
                <i class="fas fa-cogs"></i>
                Manage My Products
            </a>
            <a href="manufacturer_orders.php" class="dashboard-icon">
                <i class="fas fa-tasks"></i>
                Manage All Orders
            </a>
            <a href="browse_farmer_products.php" class="dashboard-icon">
                <i class="fas fa-leaf"></i>
                Order Raw Materials
            </a>
        </div>

    <?php
    // ==================================================================
    // --- DISTRIBUTOR DASHBOARD ---
    // ==================================================================
    elseif ($role == 'Distributor'): ?>
        
        <div class="widget-card-container">
            <div class="widget-card">
                <div class="widget-icon"><i class="fas fa-shipping-fast"></i></div>
                <div class="widget-info">
                    <div class="widget-value"><?php echo $pending_distributor_deliveries; ?></div>
                    <div class="widget-title">Deliveries Ready for Pickup</div>
                </div>
            </div>
        </div>
        
        <div class="icon-dashboard">
            <a href="distributor_dashboard.php" class="dashboard-icon">
                <i class="fas fa-truck"></i>
                Go to Delivery Dashboard
            </a>
            <a href="profile.php" class="dashboard-icon">
                <i class="fas fa-user-cog"></i>
                Update Profile
            </a>
        </div>

    <?php
    // ==================================================================
    // --- RETAILER DASHBOARD ---
    // ==================================================================
    elseif ($role == 'Retailer'): ?>

        <div class="icon-dashboard">
            <a href="browse_manufacturer_products.php" class="dashboard-icon">
                <i class="fas fa-store"></i>
                Browse Products
            </a>
            <a href="retailer_orders.php" class="dashboard-icon">
                <i class="fas fa-shopping-basket"></i>
                My Placed Orders
            </a>
            <a href="profile.php" class="dashboard-icon">
                <i class="fas fa-user-cog"></i>
                Update Profile
            </a>
        </div>

    <?php
    // ==================================================================
    // --- CONSUMER DASHBOARD ---
    // ==================================================================
    elseif ($role == 'Consumer'): ?>

        <div class="icon-dashboard">
            <a href="track_food.php" class="dashboard-icon">
                <i class="fas fa-qrcode"></i>
                Track a Product
            </a>
            <a href="profile.php" class="dashboard-icon">
                <i class="fas fa-user-cog"></i>
                My Profile
            </a>
        </div>

    <?php 
    // ==================================================================
    // --- ADMIN (Should not be here, but as a fallback) ---
    // ==================================================================
    elseif ($role == 'Admin'): ?>
        <p>You are an Administrator. Your main dashboard is in the `/admin` folder.</p>
        <div class="icon-dashboard">
            <a href="admin/index.php" class="dashboard-icon">
                <i class="fas fa-users-cog"></i>
                Go to Admin Panel
            </a>
        </div>
    <?php endif; ?>

</div> <?php
// --- End of specific page content ---
?>

</div></body>
</html>
<?php
// CRITICAL: Close the connection
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>