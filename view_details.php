<?php
// Includes header_app.php: Security check, session_start(), $conn, $role, $user_id
include_once 'header_app.php';

// Get Product ID from URL and validate
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    echo "<h1>Invalid Product ID</h1><p>No valid product ID was provided.</p></div></body></html>";
    if (isset($conn)) $conn->close(); // Close connection before exit
    exit;
}

// Fetch Product Data along with the owner's details
$sql_fetch = "SELECT
                p.*,
                u.Username as OwnerName,
                ur.RoleName as OwnerRole
              FROM Products p
              JOIN Users u ON p.CreatedByUserID = u.UserID
              JOIN UserRoles ur ON u.RoleID = ur.RoleID
              WHERE p.ProductID = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
$product = null; // Initialize product variable

if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $product_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows == 1) {
        $product = $result->fetch_assoc(); // Fetch the product data
    }
    $stmt_fetch->close();
} else {
    // Log error if prepare failed
    error_log("View details fetch product prepare failed: " . $conn->error);
}

// If product wasn't found, display error and exit
if (!$product) {
    echo "<h1>Error</h1><p>Product not found.</p></div></body></html>";
    if (isset($conn)) $conn->close(); // Close connection
    exit;
}

// Determine if the batch details should be shown
$can_view_batches = ($product['ProductType'] == 'Raw' && $role == 'Farmer' && $product['CreatedByUserID'] == $user_id);

?>
<title>View Details: <?php echo htmlspecialchars($product['ProductName']); ?></title>

<style>
/* Ensure these styles are loaded correctly, either here or in app_style.css/public_style.css */
.details-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:1200px;margin:1rem auto}
.details-header{display:flex; flex-wrap: wrap; gap:2rem;align-items:center;border-bottom:2px solid #eee;padding-bottom:2rem;margin-bottom:2rem}
.details-header img{width:200px;height:200px;object-fit:cover;border-radius:8px;border:1px solid #ddd; flex-shrink: 0;}
.details-header-info{flex-grow: 1; min-width: 300px;}
.details-header-info h1{margin-top:0;color:#333}
.details-header-info p{font-size:1.1rem;color:#555;max-width:500px}
.details-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-bottom:2rem}
.detail-item{background:#f9f9f9;padding:1rem;border-radius:5px;border:1px solid #eee}
.detail-item label{display:block;font-weight:700;color:#555;margin-bottom:.25rem; font-size: 0.85rem;}
.detail-item p{margin:0;font-size:1rem}
.list-table{width:100%;border-collapse:collapse;margin-top:2rem;table-layout:fixed}
.list-table th,.list-table td{border:1px solid #ddd;padding:12px;text-align:left;word-wrap:break-word; vertical-align: middle;}
.list-table th{background-color:#f2f2f2; font-weight: bold;}
.list-table td p{max-height:100px;overflow-y:auto;margin:0;font-size:.9rem} /* Scroll long text */
.list-table img { max-width: 60px; height: auto; display: block; } /* QR Code image size */
.btn-cancel {
    display: inline-block; background-color: #6c757d; color: white; padding: 0.6rem 1.2rem;
    border: none; border-radius: 4px; font-size: 0.9rem; font-weight: bold;
    cursor: pointer; text-decoration: none; margin-left: 10px;
}
.btn-cancel:hover { background-color: #5a6268; }
</style>

<div class="page-header">
    <h1>View Product Details</h1>
</div>

<div class="details-card">
    <div class="details-header">
        <?php
        // Display product image or placeholder
        if (!empty($product['ProductImage']) && file_exists($product['ProductImage'])) {
            echo '<img src="' . htmlspecialchars($product['ProductImage']) . '" alt="' . htmlspecialchars($product['ProductName']) . '">';
        } else {
            echo '<img src="https://via.placeholder.com/200x200.png?text=No+Image" alt="No Image">';
        }
        ?>
        <div class="details-header-info">
            <h1><?php echo htmlspecialchars($product['ProductName']); ?></h1>
            <p><strong><?php echo htmlspecialchars($product['OwnerRole']); ?>:</strong> <?php echo htmlspecialchars($product['OwnerName']); ?></p>
            <p><?php echo nl2br(htmlspecialchars($product['ProductDescription'] ?? 'No description provided.')); ?></p>
        </div>
    </div>

    <h2>Product Info</h2>
    <div class="details-grid">
        <div class="detail-item"><label>Default Quantity/Unit:</label><p><?php echo htmlspecialchars($product['Quantity'] ?? 0); ?></p></div>
        <div class="detail-item"><label>Default Price (&#8369;):</label><p>&#8369;<?php echo number_format($product['Price'] ?? 0, 2); ?></p></div>
        <div class="detail-item"><label>Default Shelf Life:</label><p><?php echo htmlspecialchars($product['ShelfLifeDays'] ?? 0); ?> days</p></div>
        <div class="detail-item"><label>Product Type:</label><p><?php echo htmlspecialchars($product['ProductType'] ?? 'N/A'); ?></p></div>
    </div>

    <?php
    // --- Show Batches Section ONLY if conditions met ---
    if ($can_view_batches):
    ?>
        <h2>Product Batches</h2>
        <div style="overflow-x:auto;"> <table class="list-table">
            <thead>
                <tr>
                    <th>Batch #</th>
                    <th>Harvested</th>
                    <th>Sown</th>
                    <th>Crop Details</th>
                    <th>Soil Details</th>
                    <th>Farm Practice</th>
                    <th>QR Code</th>
                    <th>Trace</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Fetch batches for THIS product and THIS farmer
            $list_sql = "SELECT * FROM ProductBatches WHERE ProductID = ? AND UserID = ? ORDER BY HarvestedDate DESC";
            $list_stmt = $conn->prepare($list_sql);
            $no_batches = true; // Flag for no results message
            if ($list_stmt) {
                $list_stmt->bind_param("ii", $product_id, $user_id);
                $list_stmt->execute();
                $list_result = $list_stmt->get_result();

                if ($list_result->num_rows > 0) {
                    $no_batches = false;
                    while ($row = $list_result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>".htmlspecialchars($row['BatchNumber'])."</td>";
                        echo "<td>".htmlspecialchars($row['HarvestedDate'])."</td>";
                        echo "<td>".htmlspecialchars($row['SowingDate'] ?? 'N/A')."</td>";
                        echo "<td><p>".nl2br(htmlspecialchars($row['CropDetails'] ?? 'N/A'))."</p></td>";
                        echo "<td><p>".nl2br(htmlspecialchars($row['SoilDetails'] ?? 'N/A'))."</p></td>";
                        echo "<td><p>".nl2br(htmlspecialchars($row['FarmPractice'] ?? 'N/A'))."</p></td>";
                        // Display QR Code image
                        echo "<td>";
                        if (!empty($row['QRCodePath']) && file_exists($row['QRCodePath'])) {
                             echo '<img src="'.htmlspecialchars($row['QRCodePath']).'" alt="QR Code for Batch '.$row['BatchNumber'].'">';
                        } else { echo "N/A"; }
                        echo "</td>";
                        // Trace Link
                        echo "<td><a href='track_food.php?batch_id=".$row['BatchID']."' class='btn-details' target='_blank'>View</a></td>";
                        echo "</tr>";
                    }
                }
                 $list_stmt->close(); // Close statement
            } else {
                 // Error preparing batch query
                 echo "<tr><td colspan='8' style='color:red;'>Error fetching batch details: ".htmlspecialchars($conn->error)."</td></tr>";
                 error_log("View details fetch batches failed: ".$conn->error);
            }
            
            // **THIS IS THE FIX**
            // Changed $stmt to $list_stmt
            if ($no_batches && $list_stmt) { // Ensure query ran before showing no results
                 echo "<tr><td colspan='8' style='text-align:center;'>No batches have been created for this product yet. Use 'Add Batch' feature.</td></tr>";
            }
            // **END FIX**
            ?>
            </tbody>
        </table>
        </div> <?php endif; // End check for Farmer viewing own raw product ?>

     <div style="margin-top: 2rem; text-align: right;">
        <a href="javascript:history.back()" class="btn-cancel">Go Back</a>
    </div>

</div> </div></body>
</html>
<?php
// Close DB connection at the very end
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>