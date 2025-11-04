<?php
// Includes header_app.php: Security check, session_start(), $conn, $role, $user_id
// The header already checks if the profile is complete for 'Retailer' role.
include_once 'header_app.php';

// --- Security Check: Role ---
// This page is for Retailers (and Consumers, though they are blocked by profile check if they register as one)
if ($role != 'Retailer' && $role != 'Consumer') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to view this page.</p></div></body></html>";
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
    exit;
}

// ==================================================================
// --- Profile Completion Check (Backup for this specific page) ---
// ==================================================================
// This check is *also* in header_app.php, but we add it here for extra security.
// Consumers are exempt from this check.
if ($role == 'Retailer') {
    $sql_profile_check = "SELECT FullName, Address FROM Users WHERE UserID = ?";
    $stmt_profile_check = $conn->prepare($sql_profile_check);
    $profile_incomplete = true; // Assume incomplete

    if ($stmt_profile_check) {
        $stmt_profile_check->bind_param("i", $user_id);
        $stmt_profile_check->execute();
        $result_profile = $stmt_profile_check->get_result();
        if($user_profile = $result_profile->fetch_assoc()) {
            if (!empty($user_profile['FullName']) && !empty($user_profile['Address'])) {
                $profile_incomplete = false; // Profile is complete!
            }
        }
        $stmt_profile_check->close();
    } else {
        echo "<h1>Error</h1><p>Could not verify user profile. Please try again later.</p></div></body></html>";
        if (isset($conn)) $conn->close(); exit;
    }

    if ($profile_incomplete) {
        if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
        header("Location: profile.php?error=complete_profile");
        exit;
    }
}
// --- End of Profile Completion Check ---


$search_term = $_GET['search'] ?? '';
?>

<title>Browse Manufacturer Products - Organic Traceability</title>
<div class="page-header"><h1>Browse Manufacturer Products</h1></div>

<div class="search-bar" style="margin-bottom: 2rem;">
    <form action="browse_manufacturer_products.php" method="GET">
        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="product-grid">
    <?php
    // Select Processed products listed by Manufacturers
    $sql = "SELECT p.*, u.Username as ManufacturerName FROM Products p JOIN Users u ON p.CreatedByUserID = u.UserID JOIN UserRoles ur ON u.RoleID = ur.RoleID WHERE ur.RoleName = 'Manufacturer' AND p.ProductType = 'Processed'";
    $params = []; $types = "";
    if (!empty($search_term)) { $sql .= " AND p.ProductName LIKE ?"; $params[] = "%".$search_term."%"; $types .= "s"; }
    $sql .= " ORDER BY p.ProductName ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute(); $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($product = $result->fetch_assoc()) { ?>
            <div class="product-card">
                <h3><?php echo htmlspecialchars($product['ProductName']); ?></h3>
                <?php if(!empty($product['ProductImage'])&&file_exists($product['ProductImage'])){echo'<img src="'.htmlspecialchars($product['ProductImage']).'" alt="'.htmlspecialchars($product['ProductName']).'" class="product-card-image">';}else{echo'<img src="https://via.placeholder.com/300x200?text=No+Image" alt="No Image" class="product-card-image">';} ?>
                <div class="product-card-details">
                    <div class="detail-row"><strong>Manufacturer:</strong><span><?php echo htmlspecialchars($product['ManufacturerName']); ?></span></div>
                    <div class="detail-row"><strong>Price:</strong><span>₱<?php echo number_format($product['Price']??0,2); ?></span></div>
                    <div class="detail-row"><strong>Shelf Life:</strong><span><?php echo htmlspecialchars($product['ShelfLifeDays']??0);?> days</span></div>
                </div>
                <div class="product-card-actions">
                    <a href="view_details.php?id=<?php echo $product['ProductID']; ?>" class="btn-view-details" title="View"><i class="fas fa-eye"></i></a>
                    <a href="place_retailer_order.php?product_id=<?php echo $product['ProductID']; ?>" class="btn-order-action" title="Order"><i class="fas fa-shopping-cart"></i></a>
                </div>
            </div>
            <?php }
        } else { echo "<p>No products found".(!empty($search_term)?' matching search':'').".</p>"; }
        $stmt->close();
    } else { echo "<p style='color:red'>Error: ".$conn->error."</p>"; error_log("Browse Mfr prep fail: ".$conn->error); }
    ?>
</div>
</div></body></html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>