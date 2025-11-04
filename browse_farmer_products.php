<?php
include_once 'header_app.php'; // Security, $conn, $role

if ($role != 'Manufacturer') {
    echo "<h1 style='color:red;'>Access Denied</h1></div></body></html>"; exit;
}
$search_term = $_GET['search'] ?? '';
?>
<title>Browse Farmer Products - Organic Traceability</title>
<div class="page-header"><h1>Browse Farmer Raw Products</h1></div>
<div class="search-bar" style="margin-bottom: 2rem;">
    <form action="browse_farmer_products.php" method="GET">
        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
</div>
<style> /* Styles */
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:2rem} .product-card{background-color:#fff;border-radius:8px;box-shadow:0 4px 8px rgba(0,0,0,.05);overflow:hidden;border:1px solid #ddd} .product-card h3{text-align:center;padding:1rem;margin:0;background-color:#f9f9f9;color:#333;border-bottom:1px solid #eee} .product-card-image{width:100%;height:200px;object-fit:cover} .product-card-details{padding:1rem;background:#f9f9f9;border-top:1px solid #eee;font-size:.9rem} .detail-row{display:flex;justify-content:space-between;margin-bottom:.5rem} .detail-row strong{color:#333} .detail-row span{color:#555} .product-card-actions{padding:1rem;display:flex;justify-content:center;gap:10px;background-color:#f0f0f0;border-top:1px solid #ddd} .product-card-actions a{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;text-decoration:none;border-radius:50%;color:#fff;font-size:1.1rem;transition:transform .1s ease} .product-card-actions a:hover{transform:scale(1.1)} .btn-view-details{background-color:#34495e} .btn-order-action{background-color:#0d6efd}
</style>
<div class="product-grid">
    <?php
    $sql = "SELECT p.*, u.Username as FarmerName FROM Products p JOIN Users u ON p.CreatedByUserID = u.UserID JOIN UserRoles ur ON u.RoleID = ur.RoleID WHERE ur.RoleName = 'Farmer' AND p.ProductType = 'Raw'";
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
                <?php if(!empty($product['ProductImage'])&&file_exists($product['ProductImage'])){echo '<img src="'.htmlspecialchars($product['ProductImage']).'" alt="" class="product-card-image">';}else{echo '<img src="https://via.placeholder.com/300x200?text=No+Image" alt="" class="product-card-image">';} ?>
                <div class="product-card-details">
                    <div class="detail-row"><strong>Farmer:</strong><span><?php echo htmlspecialchars($product['FarmerName']); ?></span></div>
                    <div class="detail-row"><strong>Price:</strong><span>₱<?php echo number_format($product['Price']??0,2); ?></span></div>
                    <div class="detail-row"><strong>Shelf Life:</strong><span><?php echo htmlspecialchars($product['ShelfLifeDays']??0);?> days</span></div>
                </div>
                <div class="product-card-actions">
                    <a href="view_details.php?id=<?php echo $product['ProductID']; ?>" class="btn-view-details" title="View"><i class="fas fa-eye"></i></a>
                    <a href="place_manufacturer_order.php?product_id=<?php echo $product['ProductID']; ?>" class="btn-order-action" title="Order"><i class="fas fa-shopping-cart"></i></a>
                </div>
            </div>
            <?php }
        } else { echo "<p>No raw products found".(!empty($search_term)?' matching search':'').".</p>"; }
        $stmt->close();
    } else { echo "<p style='color:red'>Error: ".$conn->error."</p>"; error_log("Browse farmer prep fail: ".$conn->error); }
    ?>
</div>
</div></body></html>
<?php if (isset($conn)) $conn->close(); ?>