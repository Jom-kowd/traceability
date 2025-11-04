<?php
// Use our header. This checks for login and shows the nav bar.
include 'header_app.php'; 
// $conn and $role are now available

// --- Security Check ---
// Only Manufacturers, Distributors, and Retailers can browse products to buy
if ($role != 'Manufacturer' && $role != 'Distributor' && $role != 'Retailer') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to view this page.</p>";
    echo "</div></body></html>"; 
    exit;
}

// --- Handle Search ---
$search_term = '';
if (isset($_GET['search'])) {
    $search_term = $_GET['search'];
}
?>

<title>Browse Farmer Products - Organic Traceability</title

<div class="page-header">
    <h1>Browse Farmer Products</h1>
</div>

<div class="search-bar" style="margin-bottom: 2rem;">
    <form action="browse_products.php" method="GET">
        <input type="text" name="search" placeholder="Search by Product Name..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
</div>

<style>
/* These styles should already be in your app_style.css, 
   but are here just in case */
.product-card-details {
    padding: 1rem;
    background: #f9f9f9;
    border-top: 1px solid #eee;
    font-size: 0.9rem;
}
.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}
.detail-row strong {
    color: #333;
}
.detail-row span {
    color: #555;
}
/* Re-using the styles from your manage_products.php buttons */
.product-card-actions {
    padding: 1rem;
    display: flex;
    justify-content: center;
    gap: 10px;
    background-color: #f9f9f9;
    border-top: 1px solid #eee;
}
.product-card-actions a {
    display: inline-block;
    padding: 10px;
    text-decoration: none;
    border-radius: 5px;
    color: white;
    font-size: 1.1rem;
}
/* These classes should be in app_style.css */
.btn-view-details { background-color: #34495e; } /* Dark */
.btn-order-action { background-color: #0d6efd; } /* Blue */

</style>

<div class="product-grid">
    <?php
    // --- Fetch Products from Database ---
    $sql = "SELECT 
                p.*, 
                u.Username as FarmerName 
            FROM Products p
            JOIN Users u ON p.CreatedByUserID = u.UserID
            JOIN UserRoles ur ON u.RoleID = ur.RoleID
            WHERE ur.RoleName = 'Farmer'";
    
    if (!empty($search_term)) {
        $sql .= " AND p.ProductName LIKE ?";
        $search_like = "%" . $search_term . "%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $search_like);
    } else {
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($product = $result->fetch_assoc()) {
    ?>
            <div class="product-card">
                <h3><?php echo htmlspecialchars($product['ProductName']); ?></h3>
                
                <?php
                if (!empty($product['ProductImage']) && file_exists($product['ProductImage'])) {
                    echo '<img src="' . htmlspecialchars($product['ProductImage']) . '" alt="' . htmlspecialchars($product['ProductName']) . '" class="product-card-image">';
                } else {
                    echo '<img src="https://via.placeholder.com/300x200.png?text=No+Image" alt="No Image" class="product-card-image">';
                }
                ?>
                
                <div class="product-card-details">
                    <div class="detail-row">
                        <strong>Farmer:</strong>
                        <span><?php echo htmlspecialchars($product['FarmerName']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Price (per unit):</strong>
                        <span>$<?php echo number_format($product['Price'] ?? 0, 2); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Shelf Life:</strong>
                        <span><?php echo htmlspecialchars($product['ShelfLifeDays'] ?? 0); ?> days</span>
                    </div>
                </div>
                
                <div class="product-card-actions">
                    <a href="view_details.php?id=<?php echo $product['ProductID']; ?>" class="btn-view-details" title="View Product Details">
                        <i class="fas fa-eye"></i>
                    </a>
                    
                    <a href="place_order.php?product_id=<?php echo $product['ProductID']; ?>" class="btn-order-action" title="Order Now">
                        <i class="fas fa-shopping-cart"></i>
                    </a>
                </div>
            </div>
            <?php
        } // End while loop
    } else {
        echo "<p>No products found from any farmers.</p>";
    }
    $stmt->close();
    $conn->close();
    ?>
</div>

<?php 
// Close the HTML tags opened by header_app.php
?>
</div> </body>
</html>