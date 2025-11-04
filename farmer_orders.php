<?php
include_once 'header_app.php'; // Security, $conn, $role, $user_id
if ($role != 'Farmer') { echo "<h1>Access Denied: Only Farmers can view this page.</h1></div></body></html>"; exit; }

$message = ''; // For feedback messages

// --- Handle Actions ---
// Action 1: Confirm Order (Pending Confirmation -> Confirmed)
if (isset($_GET['action']) && $_GET['action'] == 'confirm_mf_order') {
    $mf_order_id = filter_input(INPUT_GET, 'mf_order_id', FILTER_VALIDATE_INT);
    if ($mf_order_id) {
        $sql_upd = "UPDATE ManufacturerFarmerOrders SET Status = 'Confirmed' WHERE MF_OrderID = ? AND FarmerID = ? AND Status = 'Pending Confirmation'";
        $stmt_upd = $conn->prepare($sql_upd);
        if($stmt_upd){
            $stmt_upd->bind_param("ii", $mf_order_id, $user_id);
            if($stmt_upd->execute() && $stmt_upd->affected_rows > 0) {
                 header("Location: farmer_orders.php?success=confirmed"); exit;
            } else { header("Location: farmer_orders.php?error=confirm_failed"); exit; }
            $stmt_upd->close();
        } else { header("Location: farmer_orders.php?error=confirm_prepare_failed"); exit; }
    } else { header("Location: farmer_orders.php?error=invalid_id"); exit; }
}

// Action 2: Assign Batch & Ship (Confirmed -> Shipped)
if (isset($_POST['assign_mf_batch'])) {
    $mf_order_id = filter_input(INPUT_POST, 'mf_order_id', FILTER_VALIDATE_INT);
    $assigned_batch_id = filter_input(INPUT_POST, 'assigned_batch_id', FILTER_VALIDATE_INT);
    if ($mf_order_id && $assigned_batch_id) {
        // Ensure the batch belongs to this farmer and product? (Optional extra check)
        $sql_upd = "UPDATE ManufacturerFarmerOrders SET AssignedBatchID = ?, Status = 'Shipped' WHERE MF_OrderID = ? AND FarmerID = ? AND Status = 'Confirmed'";
        $stmt_upd = $conn->prepare($sql_upd);
        if($stmt_upd){
            $stmt_upd->bind_param("iii", $assigned_batch_id, $mf_order_id, $user_id);
             if($stmt_upd->execute() && $stmt_upd->affected_rows > 0) {
                 header("Location: farmer_orders.php?success=shipped"); exit;
            } else { header("Location: farmer_orders.php?error=assign_failed"); exit; } // Maybe status wasn't 'Confirmed'
            $stmt_upd->close();
        } else { header("Location: farmer_orders.php?error=assign_prepare_failed"); exit; }
    } else { header("Location: farmer_orders.php?error=missing_ids"); exit; }
}

// Display messages
if(isset($_GET['success'])) $message = "<p class='message success'>Order status updated!</p>";
if(isset($_GET['error'])) $message = "<p class='message error'>Failed to update order status.</p>";

?>
<title>Incoming Orders - Organic Traceability</title>
<style> /* Styles */
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);margin:1rem auto;max-width:1400px} .list-table{width:100%;border-collapse:collapse;margin-top:1rem;font-size:.9rem} .list-table th,.list-table td{border:1px solid #ddd;padding:10px;text-align:left;vertical-align:middle} .list-table th{background-color:#f2f2f2; font-weight: bold;} .status-pending-confirmation{color:#dc3545;font-weight:700} .status-confirmed{color:#ffc107;font-weight:700} .status-shipped{color:#0d6efd;font-weight:700} .status-received{color:#198754;font-weight:700} .btn-details,.btn-action,.btn-cancel{display:inline-block;padding:5px 10px;font-size:.8rem;color:#fff;text-decoration:none;border-radius:4px;border:none;cursor:pointer; margin: 2px;} .btn-details{background-color:#555} .btn-action{background-color:#28a745} .btn-action.blue{background-color:#0d6efd} .btn-action.disabled{background-color:#6c757d;pointer-events:none} .assign-form{display:flex; flex-wrap: wrap; gap: 5px; align-items: center;} .assign-form select,.assign-form button{padding:5px 8px;font-size:.8rem;} .message{padding:1rem;border-radius:5px;margin:1rem 0;font-weight:700;text-align:center;} .message.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;} .message.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
</style>
<div class="page-header"> <h1>Incoming Orders from Manufacturers</h1> </div>
<div class="form-card">
    <?php echo $message; ?>
    <p>Orders placed *to you* by Manufacturers for your raw products.</p>
    <div style="overflow-x:auto;"> <table class="list-table">
        <thead><tr><th>Order ID</th><th>Product</th><th>Qty</th><th>Price (₱)</th><th>From (Mfr)</th><th>Date</th><th>Status</th><th>Action</th><th>Assigned Batch</th><th>Trace</th></tr></thead>
        <tbody>
            <?php
            $sql = "SELECT mfo.*, p.ProductName, m.Username as ManufacturerName, pb.BatchNumber
                    FROM ManufacturerFarmerOrders mfo
                    JOIN Products p ON mfo.RawProductID = p.ProductID
                    JOIN Users m ON mfo.ManufacturerID = m.UserID
                    LEFT JOIN ProductBatches pb ON mfo.AssignedBatchID = pb.BatchID
                    WHERE mfo.FarmerID = ? ORDER BY mfo.OrderDate DESC";
            $stmt = $conn->prepare($sql);
            if($stmt){
                $stmt->bind_param("i", $user_id); $stmt->execute(); $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $dt = new DateTime($row['OrderDate']); $date = $dt->format('Y-m-d H:i');
                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $row['Status']));
                        $assigned_batch_display = $row['BatchNumber'] ?? 'N/A';

                        echo "<tr>";
                        echo "<td>".$row['MF_OrderID']."</td>";
                        echo "<td>".htmlspecialchars($row['ProductName'])."</td>";
                        echo "<td>".htmlspecialchars($row['OrderQuantity'])."</td>";
                        echo "<td>₱".number_format($row['TotalPrice'],2)."</td>";
                        echo "<td>".htmlspecialchars($row['ManufacturerName'])."</td>";
                        echo "<td>".$date."</td>";
                        echo "<td class='".$status_class."'>".htmlspecialchars($row['Status'])."</td>";
                        // Action Column
                        echo "<td>";
                        if ($row['Status'] == 'Pending Confirmation') {
                             echo "<a href='farmer_orders.php?action=confirm_mf_order&mf_order_id=".$row['MF_OrderID']."' class='btn-action'>Confirm</a>";
                        } elseif ($row['Status'] == 'Confirmed') {
                             echo '<form action="farmer_orders.php" method="POST" class="assign-form">';
                             echo '<input type="hidden" name="mf_order_id" value="'.$row['MF_OrderID'].'">';
                             echo '<select name="assigned_batch_id" required><option value="">-- Select Batch --</option>';
                             // Dropdown of Farmer's batches for this specific Raw Product ID
                             $batch_sql="SELECT BatchID, BatchNumber, HarvestedDate FROM ProductBatches WHERE ProductID = ? AND UserID = ? ORDER BY HarvestedDate DESC";
                             $batch_stmt=$conn->prepare($batch_sql);
                             if ($batch_stmt) {
                                $batch_stmt->bind_param("ii", $row['RawProductID'], $user_id);
                                $batch_stmt->execute();
                                $batch_result = $batch_stmt->get_result();
                                while ($batch=$batch_result->fetch_assoc()){
                                    echo "<option value='".$batch['BatchID']."'>#".htmlspecialchars($batch['BatchNumber'])." (".$batch['HarvestedDate'].")</option>";
                                }
                                $batch_stmt->close();
                             } else { echo "<option value=''>Error loading</option>"; }
                             echo '</select><button type="submit" name="assign_mf_batch" class="btn-action blue">Assign & Ship</button></form>';
                        } else { // Shipped or potentially Received
                             echo "<span class='btn-action disabled'>Locked</span>";
                        }
                        echo "</td>";
                        // Assigned Batch Column
                        echo "<td>".htmlspecialchars($assigned_batch_display)."</td>";
                        // Trace Column
                        echo "<td>";
                        if ($row['AssignedBatchID']) {
                            echo "<a href='view_chain.php?batch_id=".$row['AssignedBatchID']."' class='btn-details' target='_blank'>View Chain</a>";
                        } else { echo "N/A"; }
                        echo "</td>";
                        echo "</tr>";
                    }
                } else { echo "<tr><td colspan='10'>No incoming orders found.</td></tr>"; }
                $stmt->close();
            } else { echo "<tr><td colspan='10' style='color:red;'>Error preparing query: ".htmlspecialchars($conn->error)."</td></tr>"; error_log("Farmer orders prepare fail: ".$conn->error); }
            ?>
        </tbody>
    </table>
    </div> </div>
</div></body>
</html>
<?php if (isset($conn)) $conn->close(); ?>