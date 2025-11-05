<?php
// Includes header_app.php: Security check, session_start(), $conn, $role, $user_id
include_once 'header_app.php';

// --- Security Check (Farmer & Mfr access this page) ---
if ($role != 'Farmer' && $role != 'Manufacturer') {
    echo "<h1 style='color:red;'>Access Denied</h1></div></body></html>";
    if (isset($conn)) { $conn->close(); } exit;
}
$is_farmer = ($role == 'Farmer'); 

// --- Profile Completion Check ---
$sql_profile_check = "SELECT FullName, Address FROM Users WHERE UserID = ?";
$stmt_profile_check = $conn->prepare($sql_profile_check);
$profile_incomplete = true; 

if ($stmt_profile_check) {
    $stmt_profile_check->bind_param("i", $user_id);
    $stmt_profile_check->execute();
    $result_profile = $stmt_profile_check->get_result();
    if($user_profile = $result_profile->fetch_assoc()) {
        if (!empty($user_profile['FullName']) && !empty($user_profile['Address'])) {
            $profile_incomplete = false; 
        }
    }
    $stmt_profile_check->close();
} else {
    error_log("Profile check prepare failed: " . $conn->error);
    echo "<h1>Error</h1><p>Could not verify user profile. Please try again later.</p></div></body></html>";
    if (isset($conn)) $conn->close();
    exit;
}
if ($profile_incomplete) {
    if (isset($conn)) { $conn->close(); }
    header("Location: profile.php?error=complete_profile");
    exit;
}
// --- End of Profile Completion Check ---

$search_term = $_GET['search'] ?? '';
?>
<title>Manage <?php echo ($role == 'Farmer' ? 'Raw' : 'Processed'); ?> Products</title>

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
/* ... (styles are unchanged) ... */
.table-wrapper{background:#fff;padding:1.5rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);overflow-x:auto}
table{width:100%;border-collapse:collapse} th,td{padding:1rem;border-bottom:1px solid #eee;text-align:left;vertical-align:middle} th{background:#f9f9f9}
.action-icons a{color:#555;margin-right:15px;font-size:1.1rem;text-decoration:none}
.action-icons a.icon-delete{color:#dc3545}
.product-image-thumb{width:60px;height:60px;object-fit:cover;border-radius:5px;margin-right:15px;vertical-align:middle}

/* Sub-table for batches */
.batches-table-container {
    padding-left: 75px; /* Indent the batch table */
}
.batches-table { width: 100%; margin-top: 0.5rem; border: 1px solid #e0e0e0; border-collapse: collapse; }
.batches-table th, .batches-table td { background: #fdfdfd; padding: 0.75rem; font-size: 0.9rem; }
.batches-table th { background: #fafafa; font-weight: 600; }
.batches-table tr:last-child td { border-bottom: none; }

/* !! NEW: Style for the QR code link */
.btn-qr {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    background-color: #555;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 600;
}
.btn-qr:hover {
    background-color: #333;
}
</style>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Shelf Life</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $product_type = $is_farmer ? 'Raw' : 'Processed';
            $sql = "SELECT * FROM Products WHERE CreatedByUserID = ? AND ProductType = ?";
            $params = [$user_id, $product_type];
            $types = "is"; 

            if (!empty($search_term)) {
                $sql .= " AND ProductName LIKE ?";
                $search_like = "%" . $search_term . "%";
                $params[] = $search_like;
                $types .= "s";
            }
            $sql .= " ORDER BY ProductName ASC"; 

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while ($product = $result->fetch_assoc()) {
                        ?>
                        <tr style="border-top: 2px solid #ddd;">
                            <td>
                                <?php if (!empty($product['ProductImage']) && file_exists($product['ProductImage'])) {
                                    echo '<img src="'.htmlspecialchars($product['ProductImage']).'" alt="" class="product-image-thumb">';
                                } ?>
                                <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($product['ProductName']); ?></strong>
                                <p style="font-size: 0.9rem; color: #666; margin: 4px 0 0 0;"><?php echo htmlspecialchars(substr($product['ProductDescription'] ?? '', 0, 70)) . '...'; ?></p>
                            </td>
                            <td>₱<?php echo number_format($product['Price'] ?? 0, 2); ?></td>
                            <td><?php echo htmlspecialchars($product['ShelfLifeDays'] ?? 0); ?> days</td>
                            <td class="action-icons">
                                <?php if ($is_farmer): ?>
                                    <a href="add_batch.php?product_id=<?php echo $product['ProductID']; ?>" title="Add Batch Details"><i class="fas fa-plus-circle"></i></a>
                                <?php endif; ?>
                                <a href="view_details.php?id=<?php echo $product['ProductID']; ?>" title="View Details"><i class="fas fa-eye"></i></a>
                                <a href="edit_product.php?id=<?php echo $product['ProductID']; ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete_product.php?id=<?php echo $product['ProductID']; ?>" class="icon-delete" title="Delete" onclick="return confirm('Are you sure? Deleting may fail if batches or orders are linked.');"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php
                        // If Farmer, show batches for this product
                        if ($is_farmer) {
                            $sql_batches = "SELECT BatchID, BatchNumber, HarvestedDate, QRCodePath, 
                                                   InitialQuantity, RemainingQuantity, QuantityUnit 
                                            FROM ProductBatches 
                                            WHERE ProductID = ? AND UserID = ?
                                            ORDER BY HarvestedDate DESC";
                            $stmt_batches = $conn->prepare($sql_batches);
                            $stmt_batches->bind_param("ii", $product['ProductID'], $user_id);
                            $stmt_batches->execute();
                            $result_batches = $stmt_batches->get_result();
                            
                            if ($result_batches->num_rows > 0) { ?>
                                <tr>
                                    <td colspan="4" class="batches-table-container" style="padding: 0 1rem 1.5rem 1.5rem;">
                                        <table class="batches-table">
                                            <thead>
                                                <tr>
                                                    <th>Batch Number</th>
                                                    <th>Harvest Date</th>
                                                    <th>Stock (Remaining / Initial)</th>
                                                    <th>QR Code</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php while ($batch = $result_batches->fetch_assoc()) { ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($batch['BatchNumber']); ?></td>
                                                    <td><?php echo htmlspecialchars($batch['HarvestedDate']); ?></td>
                                                    <td>
                                                        <strong style="font-size: 1.1em;"><?php echo htmlspecialchars($batch['RemainingQuantity']); ?></strong> / 
                                                        <?php echo htmlspecialchars($batch['InitialQuantity']); ?> 
                                                        <?php echo htmlspecialchars($batch['QuantityUnit']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($batch['QRCodePath']) && file_exists($batch['QRCodePath'])): ?>
                                                            <a href="<?php echo htmlspecialchars($batch['QRCodePath']); ?>" class="btn-qr" target="_blank" title="View/Print QR Code">
                                                                <i class="fas fa-qrcode"></i> View/Print
                                                            </a>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php } 
                            $stmt_batches->close();
                        } // end if farmer
                    } // end while products
                } else {
                    echo "<tr><td colspan='4' style='text-align:center;'>No products found. Click '+ Add New Product' to get started.</td></tr>";
                }
                $stmt->close();
            } else {
                echo "<tr><td colspan='4' style='color:red'>Error preparing database query: ".htmlspecialchars($conn->error)."</td></tr>";
                error_log("Manage products prepare failed: ".$conn->error);
            }
            ?>
        </tbody>
    </table>
</div> 

</div></body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>