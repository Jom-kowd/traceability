<?php
// Use our header. This checks for login and shows the nav bar.
include_once 'header_app.php';
// $conn and $role are now available

// --- Security Check ---
if ($role != 'Manufacturer') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to view this page.</p>";
    echo "</div></body></html>";
    exit;
}
$message = ''; // For feedback messages

// --- Handle INCOMING Order Actions ---
// Action 1: Confirm Order (Pending Confirmation -> Processing)
if (isset($_GET['action']) && $_GET['action'] == 'confirm_order') {
    $order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
    $manufacturer_id = $_SESSION['user_id'];
    if ($order_id) {
        $sql_confirm = "UPDATE Orders SET Status = 'Processing' WHERE OrderID = ? AND SellerID = ? AND Status = 'Pending Confirmation'";
        $stmt_confirm = $conn->prepare($sql_confirm);
        if ($stmt_confirm) {
            $stmt_confirm->bind_param("ii", $order_id, $manufacturer_id);
            if($stmt_confirm->execute() && $stmt_confirm->affected_rows > 0) { header("Location: manufacturer_orders.php?success=confirmed"); exit; }
            else { $message = "<p class='message error'>Failed to confirm (already processed or DB error).</p>";}
            $stmt_confirm->close();
        } else { $message = "<p class='message error'>Error preparing confirm action.</p>"; error_log("Mfr confirm prep fail: ".$conn->error); }
    } else { $message = "<p class='message error'>Invalid Order ID for confirmation.</p>"; }
}

// Action 2: Link Source Batch (Processing -> Ready for Pickup)
if (isset($_POST['link_batch'])) {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $source_batch_id = filter_input(INPUT_POST, 'source_batch_id', FILTER_VALIDATE_INT);
    $manufacturer_id = $_SESSION['user_id'];

    if ($order_id && $source_batch_id) {
        
        // --- 1. Fetch the Farmer's Batch Hash (Previous Hash) ---
        $previous_hash = null;
        $sql_fetch_hash = "SELECT TransactionHash FROM ProductBatches WHERE BatchID = ?";
        $stmt_fetch_hash = $conn->prepare($sql_fetch_hash);
        if ($stmt_fetch_hash) {
            $stmt_fetch_hash->bind_param("i", $source_batch_id);
            $stmt_fetch_hash->execute();
            $result_hash = $stmt_fetch_hash->get_result();
            if ($row_hash = $result_hash->fetch_assoc()) {
                $previous_hash = $row_hash['TransactionHash'];
            }
            $stmt_fetch_hash->close();
        }
        
        if ($previous_hash === null) {
            $message = "<p class='message error'>Failed to link: The selected Farmer's batch is not verified (missing hash).</p>";
        } else {
            // --- 2. Generate NEW Transaction Hash (Blockchain Sim) ---
            $data_to_hash = [
                'OrderID' => $order_id,
                'SourceBatchID' => $source_batch_id,
                'ManufacturerID' => $manufacturer_id,
                'Timestamp' => date('Y-m-d H:i:s'), // Use a consistent timestamp
                'PreviousHash' => $previous_hash // This creates the "chain"
            ];
            $data_string = json_encode($data_to_hash);
            $transaction_hash = hash('sha256', $data_string);

            // --- 3. Update the Order with BatchID, Status, and new Hash ---
            $sql_link = "UPDATE Orders
                         SET SourceBatchID = ?, Status = 'Ready for Pickup', TransactionHash = ?
                         WHERE OrderID = ? AND SellerID = ? AND Status = 'Processing'";
            $stmt_link = $conn->prepare($sql_link);
             if ($stmt_link) {
                $stmt_link->bind_param("isiii", $source_batch_id, $transaction_hash, $order_id, $manufacturer_id);
                if ($stmt_link->execute()) {
                    if ($stmt_link->affected_rows > 0) { header("Location: manufacturer_orders.php?success=linked"); exit; }
                    else { $message = "<p class='message error'>Failed to link (Order status not 'Processing'?).</p>"; }
                } else { $message = "<p class='message error'>DB error linking batch: " . $stmt_link->error . "</p>"; error_log("Mfr link execute fail: ".$stmt_link->error); }
                $stmt_link->close();
            } else { $message = "<p class='message error'>DB error preparing link: " . $conn->error . "</p>"; error_log("Mfr link prep fail: ".$conn->error); }
        }
    } else { $message = "<p class='message error'>Missing Order ID or Batch ID.</p>"; }
}

// Action 3: Assign Distributor (Ready for Pickup -> Assigned)
if (isset($_POST['assign_distributor'])) {
    // ... (This logic remains the same as before) ...
    $order_id=filter_input(INPUT_POST,'order_id',FILTER_VALIDATE_INT); $distributor_id=filter_input(INPUT_POST,'distributor_id',FILTER_VALIDATE_INT);
    if($order_id && $distributor_id){ $sql_assign="UPDATE Orders SET AssignedDistributorID=?, Status='Assigned', PickupDate=NULL WHERE OrderID=? AND SellerID=? AND Status='Ready for Pickup'"; $s=$conn->prepare($sql_assign); if($s){$s->bind_param("iii",$distributor_id,$order_id,$user_id);if($s->execute()&&$s->affected_rows>0){header("Location:?success=assigned");exit;}else{$message="<p class='message error'>Assign fail (check status?)</p>";}}else{$message="<p class='message error'>Assign prep fail</p>";}}else{$message="<p class='message error'>Missing IDs</p>";}
}

// --- Handle Cancel OUTGOING Farmer Order Action ---
if (isset($_GET['action']) && $_GET['action'] == 'cancel_farmer_order') {
    // ... (This logic remains the same as before) ...
    $mf_order_id=filter_input(INPUT_GET,'mf_order_id',FILTER_VALIDATE_INT); if($mf_order_id){$sql="DELETE FROM ManufacturerFarmerOrders WHERE MF_OrderID=? AND ManufacturerID=? AND Status='Pending Confirmation'"; $s=$conn->prepare($sql);if($s){$s->bind_param("ii",$mf_order_id,$user_id);if($s->execute()&&$s->affected_rows>0){header("Location:?success=cancelled");exit;}else{$message="<p class='message error'>Cancel failed (wrong status?)</p>";}$s->close();}else{$message="<p class='message error'>Cancel prep fail</p>";}}else{$message="<p class='message error'>Invalid ID</p>";}
}

// --- Display feedback messages ---
// ... (This logic remains the same) ...
if(empty($message)) { if(isset($_GET['success'])){ /* ... show success messages ... */ } if(isset($_GET['error'])) { /* ... show error messages ... */ } }
?>
<title>Manage Orders - Manufacturer</title>
<style> /* Styles */
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);margin:1rem auto;max-width:1400px} .list-table{width:100%;border-collapse:collapse;margin-top:1rem;margin-bottom:3rem;font-size:.9rem} .list-table th,.list-table td{border:1px solid #ddd;padding:10px;text-align:left;vertical-align:middle} .list-table th{background-color:#f2f2f2; font-weight: bold;} .status-pending-confirmation{color:#dc3545;font-weight:700} .status-processing, .status-confirmed{color:#ffc107;font-weight:700} .status-ready-for-pickup, .status-assigned{color:#6f42c1;font-weight:700} .status-in-transit, .status-shipped{color:#0d6efd;font-weight:700} .status-delivered, .status-received{color:#198754;font-weight:700} .btn-details,.btn-cancel,.btn-action{display:inline-block;padding:5px 10px;font-size:.8rem;color:#fff;text-decoration:none;border-radius:4px;border:none;cursor:pointer; margin: 2px;} .btn-details{background-color:#555} .btn-cancel{background-color:#dc3545} .btn-action{background-color:#28a745} .btn-action.blue{background-color:#0d6efd} .btn-action.purple{background-color:#6f42c1} .btn-action.disabled{background-color:#6c757d;pointer-events:none}
.assign-form{display:flex; flex-wrap: wrap; gap: 5px; align-items: center;}
.assign-form select,.assign-form button{padding:5px 8px;font-size:.8rem;}
.assign-form img { width: 40px; height: 40px; border: 1px solid #ccc; vertical-align: middle; margin-left: 5px; display: none; } /* QR Preview */
.message{padding:1rem;border-radius:5px;margin:1rem 0;font-weight:700;text-align:center;} .message.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;} .message.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
</style>
<div class="page-header"><h1>Manage Your Orders</h1></div>
<div class="form-card">
    <?php echo $message; // Display feedback ?>
    <h2>Orders Received (From Retailers/Consumers)</h2>
    <div style="overflow-x:auto;">
    <table class="list-table">
         <thead><tr><th>ID</th><th>Product</th><th>Qty</th><th>Price</th><th>Buyer</th><th>Date</th><th>Status</th><th>Action</th><th>Distributor</th><th>Trace</th></tr></thead>
         <tbody>
         <?php
         $sql_in="SELECT o.*, p.ProductName, b.Username as BuyerName, d.Username as DistributorName FROM Orders o JOIN Products p ON o.ProductID=p.ProductID JOIN Users b ON o.BuyerID=b.UserID LEFT JOIN Users d ON o.AssignedDistributorID=d.UserID WHERE o.SellerID=? ORDER BY o.OrderDate DESC";
         $stmt_in=$conn->prepare($sql_in); $no_incoming = true;
         if ($stmt_in) { $stmt_in->bind_param("i",$user_id); $stmt_in->execute(); $res_in=$stmt_in->get_result();
            if($res_in->num_rows > 0){ $no_incoming = false; while($row_in=$res_in->fetch_assoc()){
                $dt=new DateTime($row_in['OrderDate']); $d=$dt->format('Y-m-d H:i'); $sc='status-'.strtolower(str_replace(' ','-',$row_in['Status'])); $st=htmlspecialchars($row_in['Status']); $ad=htmlspecialchars($row_in['DistributorName']??'N/A');
                echo "<tr><td>".$row_in['OrderID']."</td><td>".htmlspecialchars($row_in['ProductName'])."</td><td>".htmlspecialchars($row_in['OrderQuantity'])."</td><td>$".number_format($row_in['TotalPrice'],2)."</td><td>".htmlspecialchars($row_in['BuyerName'])."</td><td>".$d."</td><td class='".$sc."'>".$st."</td>";
                echo "<td>"; // Action Column
                if($st=='Pending Confirmation'){ echo "<a href='manufacturer_orders.php?action=confirm_order&order_id=".$row_in['OrderID']."' class='btn-action'>Confirm</a>"; }
                elseif($st=='Processing'){
                     echo '<form action="manufacturer_orders.php" method="POST" class="assign-form">';
                     echo '<input type="hidden" name="order_id" value="'.$row_in['OrderID'].'">';
                     echo '<select name="source_batch_id" required onchange="showQrPreview(this, \'qrPreview_'.$row_in['OrderID'].'\')" id="batchSelect_'.$row_in['OrderID'].'">';
                     echo '<option value="">--Select Farmer Batch--</option>';
                     // Fetch batch, including QRCodePath
                     $bsql="SELECT pb.BatchID, pb.BatchNumber, u.Username as FarmerName, pb.HarvestedDate, pb.QRCodePath FROM ProductBatches pb JOIN Users u ON pb.UserID=u.UserID JOIN Products p ON pb.ProductID = p.ProductID WHERE p.ProductType = 'Raw'";
                     $bs=$conn->prepare($bsql); $batches_found=false;
                     if($bs){ $bs->execute(); $br=$bs->get_result();
                         while($b=$br->fetch_assoc()){
                             $qrPathAttr = !empty($b['QRCodePath']) && file_exists($b['QRCodePath']) ? 'data-qrpath="'.htmlspecialchars($b['QRCodePath']).'"' : '';
                             echo "<option value='".$b['BatchID']."' ".$qrPathAttr.">".$b['FarmerName']." #".$b['BatchNumber']." (".$b['HarvestedDate'].")</option>"; $batches_found=true; } $bs->close();
                     } else { echo "<option value=''>Error</option>"; }
                     echo '</select>';
                     echo '<img id="qrPreview_'.$row_in['OrderID'].'" src="" alt="QR" style="width: 40px; height: 40px; border: 1px solid #ccc; vertical-align: middle; margin-left: 5px; display: none;">';
                     echo '<button type="submit" name="link_batch" class="btn-action blue" '.(!$batches_found?'disabled':'disabled').'>Link&Ready</button>';
                     echo '</form>';
                     if(!$batches_found) {echo '<small style="color:red; display:block;">No raw batches found.</small>';}
                }
                elseif($st=='Ready for Pickup'){ /* ... Assign Distributor form ... */
                     echo '<form action="manufacturer_orders.php" method="POST" class="assign-form"><input type="hidden" name="order_id" value="'.$row_in['OrderID'].'"><select name="distributor_id" required><option value="">--Select Distributor--</option>';
                     $dsql="SELECT UserID, Username FROM Users WHERE RoleID=(SELECT RoleID FROM UserRoles WHERE RoleName='Distributor') AND VerificationStatus='Approved'"; $dr=$conn->query($dsql); $dist_found=false;
                     if($dr){while($d=$dr->fetch_assoc()){ echo "<option value='".$d['UserID']."'>".$d['Username']."</option>"; $dist_found=true;} } echo '</select><button type="submit" name="assign_distributor" class="btn-action purple" '.(!$dist_found?'disabled':'').'>Assign&Ship</button></form>';
                     if(!$dist_found) {echo '<small style="color:red; display:block;">No distributors found.</small>';}
                } else { echo "<span class='btn-action disabled'>Locked</span>"; }
                echo "</td>";
                echo "<td>".$ad."</td>"; // Assigned Distributor
                echo "<td>"; // Trace
                if($row_in['SourceBatchID']){ echo "<a href='track_food.php?batch_id=".$row_in['SourceBatchID']."' class='btn-details' target='_blank'>View Chain</a>"; } else { echo "N/A"; }
                echo "</td></tr>";
             }} if($no_incoming) { echo "<tr><td colspan='10'>No incoming orders found.</td></tr>"; }
             $stmt_in->close();
         } else { echo "<tr><td colspan='10' style='color:red;'>Error preparing query: ".htmlspecialchars($conn->error)."</td></tr>"; error_log("Mfr orders incoming prep fail: ".$conn->error); }
         ?>
         </tbody>
    </table>
    </div>

    <hr style="margin: 3rem 0;">

    <h2>Orders Placed (To Farmers)</h2>
    <div style="overflow-x:auto;">
     <table class="list-table">
         <thead><tr><th>ID</th><th>Product</th><th>Qty</th><th>Price</th><th>To (Farmer)</th><th>Date</th><th>Status</th><th>Action</th><th>Assigned Batch</th><th>Trace</th></tr></thead>
         <tbody>
          <?php
            $sql_out="SELECT mfo.*, p.ProductName, f.Username as FarmerName, pb.BatchNumber FROM ManufacturerFarmerOrders mfo JOIN Products p ON mfo.RawProductID=p.ProductID JOIN Users f ON mfo.FarmerID=f.UserID LEFT JOIN ProductBatches pb ON mfo.AssignedBatchID=pb.BatchID WHERE mfo.ManufacturerID=? ORDER BY mfo.OrderDate DESC";
            $stmt_out=$conn->prepare($sql_out); $no_outgoing = true;
            if ($stmt_out) { $stmt_out->bind_param("i",$user_id); $stmt_out->execute(); $res_out=$stmt_out->get_result();
                if($res_out->num_rows > 0){ $no_outgoing = false; while($row_out=$res_out->fetch_assoc()){
                    $dt=new DateTime($row_out['OrderDate']); $d=$dt->format('Y-m-d H:i'); $sc='status-'.strtolower(str_replace(' ','-',$row_out['Status'])); $ab=htmlspecialchars($row_out['BatchNumber']??'N/A');
                    echo "<tr><td>".$row_out['MF_OrderID']."</td><td>".htmlspecialchars($row_out['ProductName'])."</td><td>".htmlspecialchars($row_out['OrderQuantity'])."</td><td>$".number_format($row_out['TotalPrice'],2)."</td><td>".htmlspecialchars($row_out['FarmerName'])."</td><td>".$d."</td><td class='".$sc."'>".htmlspecialchars($row_out['Status'])."</td>";
                    echo "<td>"; if($row_out['Status']=='Pending Confirmation'){ echo "<a href='manufacturer_orders.php?action=cancel_farmer_order&mf_order_id=".$row_out['MF_OrderID']."' class='btn-cancel' onclick=\"return confirm('Cancel?');\">Cancel</a>"; } else { echo "<span class='btn-action disabled'>Locked</span>"; } echo "</td>";
                    echo "<td>".$ab."</td>";
                    echo "<td>"; if($row_out['AssignedBatchID']){ echo "<a href='view_chain.php?batch_id=".$row_out['AssignedBatchID']."' class='btn-details' target='_blank'>View</a>"; } else { echo "N/A"; } echo "</td></tr>";
                }} if ($no_outgoing) { echo "<tr><td colspan='10'>No orders placed.</td></tr>"; }
                $stmt_out->close();
            } else { echo "<tr><td colspan='10' style='color:red;'>Error query: ".htmlspecialchars($conn->error)."</td></tr>"; error_log("Mfr orders outgoing prep fail: ".$conn->error); }
          ?>
         </tbody>
     </table>
     </div>
</div>

<script>
function showQrPreview(selectElement, imgId) { /* ... (JS code from previous step) ... */
    var imgPreview = document.getElementById(imgId);
    var submitButton = selectElement.closest('form').querySelector('button[name="link_batch"]');
    var selectedOption = selectElement.options[selectElement.selectedIndex];
    var qrPath = selectedOption.getAttribute('data-qrpath');
    if (qrPath && imgPreview) {
        imgPreview.src = qrPath; imgPreview.style.display = 'inline-block';
        if (submitButton) submitButton.disabled = false;
    } else {
        if (imgPreview) { imgPreview.src = ""; imgPreview.style.display = 'none'; }
        if (submitButton) submitButton.disabled = true;
    }
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select[name="source_batch_id"]').forEach(function(selectElement){
         var orderId = selectElement.closest('form').querySelector('input[name="order_id"]').value;
         var imgPreview = document.getElementById('qrPreview_' + orderId);
         var submitButton = selectElement.closest('form').querySelector('button[name="link_batch"]');
         if (selectElement.value) {
             var selectedOption = selectElement.options[selectElement.selectedIndex];
             var qrPath = selectedOption.getAttribute('data-qrpath');
             if(submitButton) submitButton.disabled = !selectElement.value || !qrPath;
             if(qrPath && imgPreview){ imgPreview.src = qrPath; imgPreview.style.display = 'inline-block'; }
         } else { if(submitButton) submitButton.disabled = true; }
    });
});
</script>

</div></body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } // Close connection ?>