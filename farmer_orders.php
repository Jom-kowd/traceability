<?php
include_once 'header_app.php'; 
if ($role != 'Farmer') {
    echo "<h1 style='color:red;'>Access Denied</h1></div></body></html>"; exit;
}

$message = '';

// --- Handle "Confirm" Order ---
if (isset($_POST['confirm_order'])) {
    $mf_order_id = $_POST['mf_order_id'];
    $sql_confirm = "UPDATE ManufacturerFarmerOrders SET Status = 'Confirmed' WHERE MF_OrderID = ? AND FarmerID = ? AND Status = 'Pending Confirmation'";
    $stmt_confirm = $conn->prepare($sql_confirm);
    if ($stmt_confirm) {
        $stmt_confirm->bind_param("ii", $mf_order_id, $user_id);
        if ($stmt_confirm->execute()) {
            if ($stmt_confirm->affected_rows > 0) { $message = "<p class='message success'>Order #$mf_order_id confirmed. Please assign a batch.</p>"; }
            else { $message = "<p class='message error'>Order not found or already confirmed.</p>"; }
        } else { $message = "<p class='message error'>DB error (confirm): " . $stmt_confirm->error . "</p>"; }
        $stmt_confirm->close();
    }
}

// !! --- Handle "Assign & Ship" with Stock Check --- !!
if (isset($_POST['assign_ship'])) {
    $mf_order_id = $_POST['mf_order_id'];
    $batch_id = $_POST['batch_id'];

    if (empty($batch_id)) {
        $message = "<p class='message error'>You must select a batch to assign.</p>";
    } else {
        // We need to check stock. Start a transaction.
        $conn->begin_transaction();
        
        try {
            // 1. Get the order quantity needed
            $order_quantity_needed = 0;
            // !! FIX: Also get ManufacturerID and FarmerID for the check
            $sql_order_qty = "SELECT OrderQuantity, ManufacturerID, FarmerID FROM ManufacturerFarmerOrders WHERE MF_OrderID = ? AND FarmerID = ? AND Status = 'Confirmed'";
            $stmt_order_qty = $conn->prepare($sql_order_qty);
            $stmt_order_qty->bind_param("ii", $mf_order_id, $user_id);
            $stmt_order_qty->execute();
            $result_order_qty = $stmt_order_qty->get_result();
            if ($result_order_qty->num_rows == 1) {
                $order_row = $result_order_qty->fetch_assoc();
                $order_quantity_needed = (float)$order_row['OrderQuantity'];
            }
            $stmt_order_qty->close();

            if ($order_quantity_needed <= 0) { throw new Exception("Could not find order or order is not in 'Confirmed' state."); }
            
            // 2. Get the batch's remaining stock (and lock the row for update)
            $remaining_stock = -1; // Use -1 to check if a row was found
            $sql_batch_stock = "SELECT RemainingQuantity FROM ProductBatches WHERE BatchID = ? AND UserID = ? FOR UPDATE";
            $stmt_batch_stock = $conn->prepare($sql_batch_stock);
            $stmt_batch_stock->bind_param("ii", $batch_id, $user_id);
            $stmt_batch_stock->execute();
            $result_batch_stock = $stmt_batch_stock->get_result();
            if ($result_batch_stock->num_rows == 1) {
                $remaining_stock = (float)$result_batch_stock->fetch_assoc()['RemainingQuantity'];
            }
            $stmt_batch_stock->close();

            if ($remaining_stock == -1) { throw new Exception("Batch not found or does not belong to you."); }

            // 3. Compare stock vs. needed
            if ($remaining_stock >= $order_quantity_needed) {
                // SUCCESS: We have enough stock
                
                // 3a. Reduce the batch stock
                $new_remaining_stock = $remaining_stock - $order_quantity_needed;
                $sql_update_stock = "UPDATE ProductBatches SET RemainingQuantity = ? WHERE BatchID = ?";
                $stmt_update_stock = $conn->prepare($sql_update_stock);
                $stmt_update_stock->bind_param("di", $new_remaining_stock, $batch_id);
                $stmt_update_stock->execute();
                $stmt_update_stock->close();
                
                // 3b. Update the order status
                $sql_assign = "UPDATE ManufacturerFarmerOrders SET Status = 'Shipped', AssignedBatchID = ? WHERE MF_OrderID = ? AND FarmerID = ?";
                $stmt_assign = $conn->prepare($sql_assign);
                $stmt_assign->bind_param("iii", $batch_id, $mf_order_id, $user_id);
                $stmt_assign->execute();
                $stmt_assign->close();
                
                // 4. Commit all changes
                $conn->commit();
                $message = "<p class='message success'>Order #$mf_order_id assigned & shipped! Stock updated.</p>";

            } else {
                // FAILURE: Not enough stock
                throw new Exception("Not enough stock in batch #$batch_id. Required: $order_quantity_needed, Available: $remaining_stock");
            }

        } catch (Exception $e) {
            // An error occurred. Roll back all changes.
            $conn->rollback();
            $message = "<p class='message error'>Error: " . $e->getMessage() . "</p>";
            error_log("Farmer Assign Stock Error: " . $e->getMessage());
        }
    }
}
?>
<title>Farmer - Incoming Orders</title>
<style>
/* ... (styles are unchanged) ... */
.table-wrapper{background:#fff;padding:1.5rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:800px} th,td{padding:1rem;border-bottom:1px solid #eee;text-align:left} th{background:#f9f9f9}
.status{padding:.25rem .5rem;border-radius:15px;font-weight:600;font-size:.8rem;white-space:nowrap}
.status-pending-confirmation{background:#fff3cd;color:#664d03}
.status-confirmed{background:#cfe2ff;color:#052c65}
.status-shipped{background:#d4edda;color:#155724}
.status-received{color:#198754;font-weight:700}
.action-form{display:flex;gap:10px;align-items:center; flex-wrap: wrap;}
.action-form select{padding:.5rem;border:1px solid #ccc;border-radius:4px}
.btn-small{padding:.5rem 1rem;font-size:.875rem;border:none;border-radius:4px;cursor:pointer;font-weight:600;text-decoration:none}
.btn-confirm{background-color:#0d6efd;color:#fff}
.btn-assign{background-color:#198754;color:#fff}
.message{padding:1rem;border-radius:5px;margin-bottom:1rem;font-weight:700;text-align:center}
.message.error{background-color:#f8d7da;color:#721c24}
.message.success{background-color:#d4edda;color:#155724}
</style>

<div class="page-header"><h1>Incoming Orders (from Manufacturers)</h1></div>
<?php echo $message; ?>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Manufacturer</th>
                <th>Product</th>
                <th>Qty Ordered</th>
                <th>Total Price</th>
                <th>Order Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // !! --- FIX: MODIFIED SQL QUERY --- !!
            // Added LEFT JOIN to ProductBatches to get the QuantityUnit
            $sql_mf_orders = "SELECT mf.*, u.Username as ManufacturerName, p.ProductName, pb.QuantityUnit 
                              FROM ManufacturerFarmerOrders mf
                              JOIN Users u ON mf.ManufacturerID = u.UserID
                              JOIN Products p ON mf.RawProductID = p.ProductID
                              LEFT JOIN ProductBatches pb ON mf.AssignedBatchID = pb.BatchID
                              WHERE mf.FarmerID = ?
                              ORDER BY mf.OrderDate DESC";
            
            $stmt_mf_orders = $conn->prepare($sql_mf_orders);
            if (!$stmt_mf_orders) {
                echo "<tr><td colspan='8' style='color:red;'>Error preparing query: ".htmlspecialchars($conn->error)."</td></tr>";
                error_log("Farmer orders prep fail: ".$conn->error);
            } else {
                $stmt_mf_orders->bind_param("i", $user_id);
                $stmt_mf_orders->execute();
                $result_mf_orders = $stmt_mf_orders->get_result();

                if ($result_mf_orders->num_rows > 0) {
                    while($row = $result_mf_orders->fetch_assoc()) {
                        $order_qty_needed = (float)$row['OrderQuantity']; 
                        
                        // !! FIX: Use null coalescing (??) to provide a default value
                        // This fixes the Warning and Deprecated errors
                        $unit_display = htmlspecialchars($row['QuantityUnit'] ?? 'units');
                        
                        // Set a default unit if status is not 'Shipped' and unit is unknown
                        if ($row['Status'] != 'Shipped' && $unit_display == 'units') {
                            // You might want to fetch the default unit from the product,
                            // but for now, "units" is a safe fallback.
                            $unit_display = 'units'; 
                        }
                        
                        ?>
                        <tr>
                            <td>#<?php echo $row['MF_OrderID']; ?></td>
                            <td><?php echo htmlspecialchars($row['ManufacturerName']); ?></td>
                            <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                            
                            <td><?php echo htmlspecialchars($order_qty_needed) . " " . $unit_display; ?></td>
                            
                            <td>₱<?php echo number_format($row['TotalPrice'], 2); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($row['OrderDate'])); ?></td>
                            <td><span class="status status-<?php echo strtolower(str_replace(' ','-',$row['Status'])); ?>"><?php echo htmlspecialchars($row['Status']); ?></span></td>
                            <td>
                                <?php if ($row['Status'] == 'Pending Confirmation'): ?>
                                    <form action="farmer_orders.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="mf_order_id" value="<?php echo $row['MF_OrderID']; ?>">
                                        <button type="submit" name="confirm_order" class="btn-small btn-confirm">Confirm</button>
                                    </form>
                                <?php elseif ($row['Status'] == 'Confirmed'): ?>
                                    <form action="farmer_orders.php" method="POST" class="action-form">
                                        <input type="hidden" name="mf_order_id" value="<?php echo $row['MF_OrderID']; ?>">
                                        <select name="batch_id" required>
                                            <option value="">-- Select a Batch --</option>
                                            <?php
                                            // Query to fetch only batches with enough stock
                                            $sql_batches = "SELECT BatchID, BatchNumber, RemainingQuantity, QuantityUnit 
                                                            FROM ProductBatches 
                                                            WHERE UserID = ? 
                                                            AND ProductID = ? 
                                                            AND RemainingQuantity >= ?";
                                            $stmt_batches = $conn->prepare($sql_batches);
                                            $stmt_batches->bind_param("iid", $user_id, $row['RawProductID'], $order_qty_needed);
                                            $stmt_batches->execute();
                                            $result_batches = $stmt_batches->get_result();
                                            
                                            $batch_found = false;
                                            while ($batch = $result_batches->fetch_assoc()) {
                                                $batch_found = true;
                                                echo "<option value='{$batch['BatchID']}'>" . htmlspecialchars($batch['BatchNumber']) . 
                                                     " (Stock: " . htmlspecialchars($batch['RemainingQuantity']) . " " . htmlspecialchars($batch['QuantityUnit']) . ")</option>";
                                            }
                                            $stmt_batches->close();
                                            
                                            if (!$batch_found) {
                                                echo "<option value='' disabled>No batches with sufficient stock</option>";
                                            }
                                            ?>
                                        </select>
                                        <button type="submit" name="assign_ship" class="btn-small btn-assign" <?php if(!$batch_found) echo 'disabled'; ?>>Assign & Ship</button>
                                    </form>
                                <?php elseif ($row['Status'] == 'Shipped'): ?>
                                    Batch #<?php echo htmlspecialchars($row['AssignedBatchID'] ?? 'N/A'); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php }
                } else {
                    echo "<tr><td colspan='8' style='text-align:center;'>No incoming orders found.</td></tr>";
                }
                $stmt_mf_orders->close();
            }
            ?>
        </tbody>
    </table>
</div>

</div> </body>
</html>
<?php if (isset($conn)) $conn->close(); ?>