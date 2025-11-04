<?php
// Includes header_app.php: Security check, session_start(), $conn, $role, $user_id
include_once 'header_app.php';

// --- Security Check (Farmer & Mfr access this page) ---
if ($role != 'Farmer' && $role != 'Manufacturer') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to view this page.</p></div></body></html>";
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
    exit;
}

// ==================================================================
// --- NEW: Profile Completion Check for this specific page ---
// ==================================================================
// Fetch FullName and Address just for this check
$sql_profile_check = "SELECT FullName, Address FROM Users WHERE UserID = ?";
$stmt_profile_check = $conn->prepare($sql_profile_check);
$profile_incomplete = true; // Assume incomplete until proven otherwise

if ($stmt_profile_check) {
    $stmt_profile_check->bind_param("i", $user_id);
    $stmt_profile_check->execute();
    $result_profile = $stmt_profile_check->get_result();
    if($user_profile = $result_profile->fetch_assoc()) {
        // Profile is complete ONLY if both FullName and Address are NOT empty
        if (!empty($user_profile['FullName']) && !empty($user_profile['Address'])) {
            $profile_incomplete = false; // Profile is complete!
        }
    }
    $stmt_profile_check->close();
} else {
    // DB error, block access just in case
    error_log("Profile check prepare failed: " . $conn->error);
    echo "<h1>Error</h1><p>Could not verify user profile. Please try again later.</p></div></body></html>";
    if (isset($conn)) $conn->close();
    exit;
}

// If profile is incomplete, redirect them to profile.php
if ($profile_incomplete) {
    // We close the connection before redirecting
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
    header("Location: profile.php?error=complete_profile");
    exit;
}
// --- End of Profile Completion Check ---


$search_term = $_GET['search'] ?? ''; // Handle search
?>
<title>Manage Products - Organic Traceability</title>

<div class="page-header">
    <h1>Manage <?php echo ($role == 'Farmer' ? 'Raw' : 'Processed'); ?> Products</h1>
    <a href="add_product.php" class="btn-add">+ Add New Product</a>
</div>

<div class="search-bar" style="margin-bottom: 2rem;">
    <form action="manage_products.php" method="GET">
        <input type="text" name="search" placeholder="Search by Product Name..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
</div>

<style>
.product-card-details{padding:1rem;background:#f9f9f9;border-top:1px solid #eee;font-size:.9rem}
.detail-row{display:flex;justify-content:space-between;margin-bottom:.5rem}
.detail-row strong{color:#333} .detail-row span{color:#555}
</style>

<div class="product-grid">
    <?php
    $current_user_id = $_SESSION['user_id'];
    // Determine product type filter based on role
    $product_type_filter = ($role == 'Farmer' ? 'Raw' : 'Processed');

    $sql = "SELECT * FROM Products WHERE CreatedByUserID = ? AND ProductType = ?"; // Fetch user's products of correct type
    $params = [$current_user_id, $product_type_filter];
    $types = "is"; // integer, string

    if (!empty($search_term)) {
        $sql .= " AND ProductName LIKE ?";
        $search_like = "%" . $search_term . "%";
        $params[] = $search_like;
        $types .= "s";
    }
    $sql .= " ORDER BY ProductName ASC"; // Order alphabetically

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Loop through results and display product cards
            while ($product = $result->fetch_assoc()) {
                ?>
                <div class="product-card">
                    <h3><?php echo htmlspecialchars($product['ProductName']); ?></h3>
                    <?php // Display image
                    if (!empty($product['ProductImage']) && file_exists($product['ProductImage'])) {
                        echo '<img src="'.htmlspecialchars($product['ProductImage']).'" alt="'.htmlspecialchars($product['ProductName']).'" class="product-card-image">';
                    } else {
                        echo '<img src="https://via.placeholder.com/300x200.png?text=No+Image" alt="No Image" class="product-card-image">';
                    } ?>
                    <div class="product-card-details">
                        <div class="detail-row"><strong>Quantity:</strong><span><?php echo htmlspecialchars($product['Quantity'] ?? 0); ?></span></div>
                        <div class="detail-row"><strong>Price:</strong><span>₱<?php echo number_format($product['Price'] ?? 0, 2); ?></span></div>
                        <div class="detail-row"><strong>Shelf Life:</strong><span><?php echo htmlspecialchars($product['ShelfLifeDays'] ?? 0); ?> days</span></div>
                    </div>
                    <div class="product-card-actions">
                        <a href="edit_product.php?id=<?php echo $product['ProductID']; ?>" class="btn-edit" title="Edit"><i class="fas fa-pencil-alt"></i></a>
                        <a href="delete_product.php?id=<?php echo $product['ProductID']; ?>" class="btn-delete" title="Delete" onclick="return confirm('Are you sure? Deleting may fail if batches or orders are linked.');"><i class="fas fa-trash-alt"></i></a>
                        
                        <?php
                        // --- Button visibility logic ---
                        // Only show the "Add Batch Details" (+) button if the logged-in user is a 'Farmer'
                        if ($role == 'Farmer'):
                        ?>
                            <a href="add_batch.php?product_id=<?php echo $product['ProductID']; ?>" class="btn-add-details" title="Add Batch Details"><i class="fas fa-plus-circle"></i></a>
                        <?php endif; ?>

                        <a href="view_details.php?id=<?php echo $product['ProductID']; ?>" class="btn-view-details" title="View Details"><i class="fas fa-eye"></i></a>
                    </div>
                </div>
                <?php
            } // end while
        } else {
            // No products found message
            echo "<p>No products found".(!empty($search_term)?' matching search':'').". Click '+ Add New Product' to get started.</p>";
        }
        $stmt->close(); // Close the prepared statement
    } else {
        // Error preparing the statement
        echo "<p style='color:red'>Error preparing database query: ".htmlspecialchars($conn->error)."</p>";
        error_log("Manage products prepare failed: ".$conn->error); // Log error for debugging
    }
    ?>
</div> </div></body>
</html>
<?php
// Close the database connection *only* if it exists and is open, at the VERY END.
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>