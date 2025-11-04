<?php
include_once 'header_app.php'; // Security, $conn, $role, $user_id
if ($role != 'Manufacturer') { echo "<h1>Access Denied</h1></div></body></html>"; exit; }
$message = ''; // For feedback

// --- Handle INCOMING Retailer Order Actions ---
if (isset($_GET['action']) && $_GET['action'] == 'confirm_order') { /* Confirm */
    $order_id=filter_input(INPUT_GET,'order_id',FILTER_VALIDATE_INT); if($order_id){ $sql="UPDATE Orders SET Status='Processing' WHERE OrderID=? AND SellerID=? AND Status='Pending Confirmation'"; $s=$conn->prepare($sql);if($s){$s->bind_param("ii",$order_id,$user_id);$s->execute();$s->close();header("Location:?success=confirmed");exit;}else{$message="<p class='message error'>Confirm prep fail</p>";}}else{$message="<p class='message error'>Invalid ID</p>";}}
if (isset($_POST['link_batch'])) { /* Link Batch */
    $order_id=filter_input(INPUT_POST,'order_id',FILTER_VALIDATE_INT); $source_batch_id=filter_input(INPUT_POST,'source_batch_id',FILTER_VALIDATE_INT);
    if($order_id && $source_batch_id){ $sql="UPDATE Orders SET SourceBatchID=?, Status='Ready for Pickup' WHERE OrderID=? AND SellerID=? AND Status='Processing'"; $s=$conn->prepare($sql);if($s){$s->bind_param("iii",$source_batch_id,$order_id,$user_id);if($s->execute()){header("Location:?success=linked");exit;}else{$message="<p class='message error'>Link fail:".$s->error."</p>";}$s->close();}else{$message="<p class='message error'>Link prep fail</p>";}}else{$message="<p class='message error'>Missing IDs</p>";}}
if (isset($_POST['assign_distributor'])) { /* Assign Distributor */
    $order_id=filter_input(INPUT_POST,'order_id',FILTER_VALIDATE_INT); $distributor_id=filter_input(INPUT_POST,'distributor_id',FILTER_VALIDATE_INT);
    if($order_id && $distributor_id){ $sql="UPDATE Orders SET AssignedDistributorID=?, Status='Assigned', PickupDate=NULL WHERE OrderID=? AND SellerID=? AND Status='Ready for Pickup'"; $s=$conn->prepare($sql);if($s){$s->bind_param("iii",$distributor_id,$order_id,$user_id);if($s->execute()){header("Location:?success=assigned");exit;}else{$message="<p class='message error'>Assign fail:".$s->error."</p>";}$s->close();}else{$message="<p class='message error'>Assign prep fail</p>";}}else{$message="<p class='message error'>Missing IDs</p>";}}
// --- Handle Cancel OUTGOING Farmer Order Action ---
if (isset($_GET['action']) && $_GET['action'] == 'cancel_farmer_order') {
    $mf_order_id=filter_input(INPUT_GET,'mf_order_id',FILTER_VALIDATE_INT); if($mf_order_id){$sql="DELETE FROM ManufacturerFarmerOrders WHERE MF_OrderID=? AND ManufacturerID=? AND Status='Pending Confirmation'"; $s=$conn->prepare($sql);if($s){$s->bind_param("ii",$mf_order_id,$user_id);if($s->execute()&&$s->affected_rows>0){header("Location:?success=cancelled");exit;}else{$message="<p class='message error'>Cancel failed (wrong status?)</p>";}$s->close();}else{$message="<p class='message error'>Cancel prep fail</p>";}}else{$message="<p class='message error'>Invalid ID for cancel</p>";}}

// Display feedback messages
if(empty($message)) { /* ... (Display redirect messages as before) ... */
    if(isset($_GET['success'])){ if($_GET['success']=='confirmed')$message="<p class='message success'>Incoming order confirmed.</p>"; elseif($_GET['success']=='linked')$message="<p class='message success'>Batch linked, ready for pickup.</p>"; elseif($_GET['success']=='assigned')$message="<p class='message success'>Distributor assigned.</p>"; elseif($_GET['success']=='cancelled')$message="<p class='message success'>Order to farmer cancelled.</p>";}
    if(isset($_GET['error'])) $message = "<p class='message error'>Operation failed: ".htmlspecialchars($_GET['error'])."</p>";
 }
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
         <thead><tr><th>ID</th><th>Product</th><th>Qty</th><th>Price (&#8369;)</th><th>Buyer</th><th>Date</th><th>Status</th><th>Action</th><th>Distributor</th><th>Trace</th></tr></thead>
         <tbody>
         <?php
         $sql_in="SELECT o.*, p.ProductName, b.Username as BuyerName, d.Username as DistributorName FROM Orders o JOIN Products p ON o.ProductID=p.ProductID JOIN Users b ON o.BuyerID=b.UserID LEFT JOIN Users d ON o.AssignedDistributorID=d.UserID WHERE o.SellerID=? ORDER BY o.OrderDate DESC";
         $stmt_in=$conn->prepare($sql_in); $no_incoming = true;
         if ($stmt_in) { $stmt_in->bind_param("i",$user_id); $stmt_in->execute(); $res_in=$stmt_in->get_result();
            if($res_in->num_rows > 0){ $no_incoming = false; while($row_in=$res_in->fetch_assoc()){
                $dt=new DateTime($row_in['OrderDate']); $d=$dt->format('Y-m-d H:i'); $sc='status-'.strtolower(str_replace(' ','-',$row_in['Status'])); $st=htmlspecialchars($row_in['Status']); $ad=htmlspecialchars($row_in['DistributorName']??'N/A');
                echo "<tr><td>".$row_in['OrderID']."</td><td>".htmlspecialchars($row_in['ProductName'])."</td><td>".htmlspecialchars($row_in['OrderQuantity'])."</td><td>&#8369;".number_format($row_in['TotalPrice'],2)."</td><td>".htmlspecialchars($row_in['BuyerName'])."</td><td>".$d."</td><td class='".$sc."'>".$st."</td>";
                echo "<td>"; // Action Column
                if($st=='Pending Confirmation'){ echo "<a href='manufacturer_orders.php?action=confirm_order&order_id=".$row_in['OrderID']."' class='btn-action'>Confirm</a>"; }
                elseif($st=='Processing'){
                     echo '<form action="manufacturer_orders.php" method="POST" class="assign-form">';
                     echo '<input type="hidden" name="order_id" value="'.$row_in['OrderID'].'">';
                     // Note the added onchange event and unique ID using OrderID
                     echo '<select name="source_batch_id" required onchange="showQrPreview(this, \'qrPreview_'.$row_in['OrderID'].'\')" id="batchSelect_'.$row_in['OrderID'].'">';
                     echo '<option value="">--Select Farmer Batch--</option>';
                     // --- UPDATED BATCH QUERY: Fetch QRCodePath ---
                     $bsql="SELECT pb.BatchID, pb.BatchNumber, u.Username as FarmerName, pb.HarvestedDate, pb.QRCodePath FROM ProductBatches pb JOIN Users u ON pb.UserID=u.UserID JOIN Products p ON pb.ProductID = p.ProductID WHERE p.ProductType = 'Raw'"; // Simplified: Show all raw batches
                     $bs=$conn->prepare($bsql); $batches_found=false;
                     if($bs){
                         //$bs->bind_param("i",$row_in['ProductID']); // Removed product ID filter
                         $bs->execute(); $br=$bs->get_result();
                         while($b=$br->fetch_assoc()){
                             // --- Add data-qrpath attribute ---
                             $qrPathAttr = !empty($b['QRCodePath']) && file_exists($b['QRCodePath']) ? 'data-qrpath="'.htmlspecialchars($b['QRCodePath']).'"' : '';
                             echo "<option value='".$b['BatchID']."' ".$qrPathAttr.">".$b['FarmerName']." #".$b['BatchNumber']." (".$b['HarvestedDate'].")</option>";
                             $batches_found=true;
                         }
                         $bs->close();
                     } else { echo "<option value=''>Error loading batches</option>"; error_log("Link batch dropdown prepare fail: ".$conn->error); }
                     echo '</select>';
                     // --- Add QR Preview Image ---
                     echo '<img id="qrPreview_'.$row_in['OrderID'].'" src="" alt="QR" style="width: 40px; height: 40px; border: 1px solid #ccc; vertical-align: middle; margin-left: 5px; display: none;">';
                     echo '<button type="submit" name="link_batch" class="btn-action blue" '.(!$batches_found?'disabled title="No batches available"':'disabled').'>Link&Ready</button>'; // Initially disable button
                     echo '</form>';
                     if(!$batches_found) {echo '<small style="color:red; display:block;">No raw material batches found.</small>';}
                }
                elseif($st=='Ready for Pickup'){ /* ... Assign Distributor form ... */
                     echo '<form action="manufacturer_orders.php" method="POST" class="assign-form"><input type="hidden" name="order_id" value="'.$row_in['OrderID'].'"><select name="distributor_id" required><option value="">--Select Distributor--</option>';
                     $dsql="SELECT UserID, Username FROM Users WHERE RoleID=(SELECT RoleID FROM UserRoles WHERE RoleName='Distributor') AND VerificationStatus='Approved'"; $dr=$conn->query($dsql); $dist_found=false;
                     if($dr){while($d=$dr->fetch_assoc()){ echo "<option value='".$d['UserID']."'>".$d['Username']."</option>"; $dist_found=true;} } echo '</select><button type="submit" name="assign_distributor" class="btn-action purple" '.(!$dist_found?'disabled title="No distributors found"':'').'>Assign&Ship</button></form>';
                     if(!$dist_found) {echo '<small style="color:red; display:block;">No approved distributors found.</small>';}
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
    </div> <hr style="margin: 3rem 0;">

    <h2>Orders Placed (To Farmers)</h2>
    <div style="overflow-x:auto;">
     <table class="list-table">
         <thead><tr><th>ID</th><th>Product</th><th>Qty</th><th>Price (&#8369;)</th><th>To (Farmer)</th><th>Date</th><th>Status</th><th>Action</th><th>Assigned Batch</th><th>Trace</th></tr></thead>
         <tbody>
          <?php
            $sql_out="SELECT mfo.*, p.ProductName, f.Username as FarmerName, pb.BatchNumber FROM ManufacturerFarmerOrders mfo JOIN Products p ON mfo.RawProductID=p.ProductID JOIN Users f ON mfo.FarmerID=f.UserID LEFT JOIN ProductBatches pb ON mfo.AssignedBatchID=pb.BatchID WHERE mfo.ManufacturerID=? ORDER BY mfo.OrderDate DESC";
            $stmt_out=$conn->prepare($sql_out); $no_outgoing = true;
            if ($stmt_out) { $stmt_out->bind_param("i",$user_id); $stmt_out->execute(); $res_out=$stmt_out->get_result();
                if($res_out->num_rows > 0){ $no_outgoing = false; while($row_out=$res_out->fetch_assoc()){
                    $dt=new DateTime($row_out['OrderDate']); $d=$dt->format('Y-m-d H:i'); $sc='status-'.strtolower(str_replace(' ','-',$row_out['Status'])); $ab=htmlspecialchars($row_out['BatchNumber']??'N/A');
                    echo "<tr><td>".$row_out['MF_OrderID']."</td><td>".htmlspecialchars($row_out['ProductName'])."</td><td>".htmlspecialchars($row_out['OrderQuantity'])."</td><td>&#8369;".number_format($row_out['TotalPrice'],2)."</td><td>".htmlspecialchars($row_out['FarmerName'])."</td><td>".$d."</td><td class='".$sc."'>".htmlspecialchars($row_out['Status'])."</td>";
                    echo "<td>"; // Action
                    if($row_out['Status']=='Pending Confirmation'){ echo "<a href='manufacturer_orders.php?action=cancel_farmer_order&mf_order_id=".$row_out['MF_OrderID']."' class='btn-cancel' onclick=\"return confirm('Cancel this order?');\">Cancel</a>"; } else { echo "<span class='btn-action disabled'>Locked</span>"; }
                    echo "</td>";
                    echo "<td>".$ab."</td>"; // Assigned Batch
                    echo "<td>"; // Trace
                    if($row_out['AssignedBatchID']){ echo "<a href='view_chain.php?batch_id=".$row_out['AssignedBatchID']."' class='btn-details' target='_blank'>View Chain</a>"; } else { echo "N/A"; }
                    echo "</td></tr>";
                }} if ($no_outgoing) { echo "<tr><td colspan='10'>No orders placed to farmers.</td></tr>"; }
                $stmt_out->close();
            } else { echo "<tr><td colspan='10' style='color:red;'>Error preparing outgoing orders query: ".htmlspecialchars($conn->error)."</td></tr>"; error_log("Mfr orders outgoing prep fail: ".$conn->error); }
          ?>
         </tbody>
     </table>
     </div> </div>

<script>
function showQrPreview(selectElement, imgId) {
    var imgPreview = document.getElementById(imgId);
    var submitButton = selectElement.closest('form').querySelector('button[name="link_batch"]');
    var selectedOption = selectElement.options[selectElement.selectedIndex];
    var qrPath = selectedOption.getAttribute('data-qrpath');

    if (qrPath && imgPreview) {
        imgPreview.src = qrPath;
        imgPreview.style.display = 'inline-block';
        if (submitButton) submitButton.disabled = false; // Enable button
    } else {
        if (imgPreview) {
             imgPreview.src = ""; // Clear src
             imgPreview.style.display = 'none'; // Hide
        }
        if (submitButton) submitButton.disabled = true; // Disable if no selection or no QR
    }
}

// Optional: Initialize buttons on page load if needed (e.g., if form retains values)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('select[name="source_batch_id"]').forEach(function(selectElement){
         var orderId = selectElement.closest('form').querySelector('input[name="order_id"]').value;
         var imgPreview = document.getElementById('qrPreview_' + orderId);
         var submitButton = selectElement.closest('form').querySelector('button[name="link_batch"]');
         if (selectElement.value) {
             var selectedOption = selectElement.options[selectElement.selectedIndex];
             var qrPath = selectedOption.getAttribute('data-qrpath');
             if(submitButton) submitButton.disabled = !selectElement.value || !qrPath;
             if(qrPath && imgPreview){
                 imgPreview.src = qrPath;
                 imgPreview.style.display = 'inline-block';
             }
         } else {
             if(submitButton) submitButton.disabled = true;
         }
    });
});
</script>

</div></body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } // Close connection at the very end ?>