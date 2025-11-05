<?php
include_once 'header_app.php'; 
if ($role != 'Farmer' && $role != 'Manufacturer') {
    echo "<h1 style='color:red;'>Access Denied</h1></div></body></html>"; exit;
}
$is_farmer = ($role == 'Farmer'); 
?>
<title>My <?php echo $is_farmer ? 'Raw' : 'Processed'; ?> Products</title>
<style>
/* ... (styles are unchanged) ... */
.table-wrapper{background:#fff;padding:1.5rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);overflow-x:auto}
table{width:100%;border-collapse:collapse} th,td{padding:1rem;border-bottom:1px solid #eee;text-align:left;vertical-align:middle} th{background:#f9f9f9}
.btn-add-new{display:inline-block;background:#0d6efd;color:#fff;padding:.75rem 1.5rem;border-radius:5px;text-decoration:none;font-weight:600;margin-bottom:1.5rem}
.action-icons a{color:#555;margin-right:15px;font-size:1.1rem;text-decoration:none}
.action-icons a.icon-delete{color:#dc3545}
.product-image-thumb{width:60px;height:60px;object-fit:cover;border-radius:5px;margin-right:15px;vertical-align:middle}
.batches-table th, .batches-table td { background: #fdfdfd; padding: 0.75rem; font-size: 0.9rem; }
.batches-table { margin-top: 1rem; border: 1px solid #eee; }
.batches-table th { background: #fafafa; }
</style>

<div class="page-header">
    <h1>My <?php echo $is_farmer ? 'Raw' : 'Processed'; ?> Products</h1>
</div>

<?php
if (isset($_GET['success'])) { echo '<p class="message success">Product operation successful!</p>'; }
if (isset($_GET['error'])) { echo '<p class="message error">Error: ' . htmlspecialchars($_GET['error']) . '</p>'; }
?>

<a href="add_product.php" class="btn-add-new"><i class="fas fa-plus-circle"></i> Add New Product</a>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Description</th>
                <th>Price</th>
                <th>Shelf Life</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $product_type = $is_farmer ? 'Raw' : 'Processed';
            $sql = "SELECT * FROM Products WHERE CreatedByUserID = ? AND ProductType = ? ORDER BY ProductName ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $user_id, $product_type);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td>
                        <?php if(!empty($row['ProductImage']) && file_exists($row['ProductImage'])){echo '<img src="'.htmlspecialchars($row['ProductImage']).'" alt="" class="product-image-thumb">';} ?>
                        <strong><?php echo htmlspecialchars($row['ProductName']); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars(substr($row['ProductDescription'] ?? '', 0, 50)) . '...'; ?></td>
                    <td>₱<?php echo number_format($row['Price'], 2); ?></td>
                    <td><?php echo htmlspecialchars($row['ShelfLifeDays']); ?> days</td>
                    <td class="action-icons">
                        <?php if ($is_farmer): ?>
                            <a href="add_batch.php?product_id=<?php echo $row['ProductID']; ?>" title="Add Batch Details"><i class="fas fa-plus-circle"></i></a>
                        <?php endif; ?>
                        <a href="edit_product.php?id=<?php echo $row['ProductID']; ?>" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="delete_product.php?id=<?php echo $row['ProductID']; ?>" class="icon-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this product? This action CANNOT be undone.');"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php
                // If Farmer, show batches for this product
                if ($is_farmer) {
                    // !! UPDATED: Query to select new quantity columns
                    $sql_batches = "SELECT BatchID, BatchNumber, HarvestedDate, QRCodePath, 
                                           InitialQuantity, RemainingQuantity, QuantityUnit 
                                    FROM ProductBatches 
                                    WHERE ProductID = ? 
                                    ORDER BY HarvestedDate DESC";
                    $stmt_batches = $conn->prepare($sql_batches);
                    $stmt_batches->bind_param("i", $row['ProductID']);
                    $stmt_batches->execute();
                    $result_batches = $stmt_batches->get_result();
                    
                    if ($result_batches->num_rows > 0) { ?>
                        <tr>
                            <td colspan="5" style="padding-top: 0; padding-bottom: 1.5rem;">
                                <table class="batches-table">
                                    <thead>
                                        <tr>
                                            <th>Batch Number</th>
                                            <th>Harvest Date</th>
                                            <th>Stock (Remaining / Initial)</th> <th>QR Code</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($batch = $result_batches->fetch_assoc()) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($batch['BatchNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($batch['HarvestedDate']); ?></td>
                                            
                                            <td>
                                                <?php 
                                                echo htmlspecialchars($batch['RemainingQuantity']) . ' / ' . 
                                                     htmlspecialchars($batch['InitialQuantity']) . ' ' . 
                                                     htmlspecialchars($batch['QuantityUnit']); 
                                                ?>
                                            </td>
                                            
                                            <td>
                                                <?php if (!empty($batch['QRCodePath']) && file_exists($batch['QRCodePath'])): ?>
                                                    <a href="<?php echo htmlspecialchars($batch['QRCodePath']); ?>" target="_blank" title="View/Print QR Code">View QR</a>
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
                echo "<tr><td colspan='5' style='text-align:center;'>You have not added any products yet.</td></tr>";
            }
            $stmt->close();
            ?>
        </tbody>
    </table>
</div>

</div> </body>
</html>
<?php if (isset($conn)) $conn->close(); ?>