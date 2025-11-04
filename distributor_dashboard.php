<?php
// Includes header_app.php: Security check, session_start(), $conn, $role, $user_id
include_once 'header_app.php';

// --- Security Check: Role ---
if ($role != 'Distributor') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to view this page.</p></div></body></html>";
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
    exit;
}

// ==================================================================
// --- Profile Completion Check for this specific page ---
// ==================================================================
$sql_profile_check = "SELECT FullName, Address FROM Users WHERE UserID = ?";
$stmt_profile_check = $conn->prepare($sql_profile_check);
$profile_incomplete = true; // Assume incomplete

if ($stmt_profile_check) {
    $stmt_profile_check->bind_param("i", $user_id);
    $stmt_profile_check->execute();
    $result_profile = $stmt_profile_check->get_result();
    if($user_profile = $result_profile->fetch_assoc()) {
        if (!empty($user_profile['FullName']) && !empty($user_profile['Address'])) {
            $profile_incomplete = false; // Profile is complete!
        }
    }
    $stmt_profile_check->close();
} else {
    echo "<h1>Error</h1><p>Could not verify user profile. Please try again later.</p></div></body></html>";
    if (isset($conn)) $conn->close(); exit;
}

if ($profile_incomplete) {
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
    header("Location: profile.php?error=complete_profile");
    exit;
}
// --- End of Profile Completion Check ---


$message = ''; // For feedback messages

// --- Handle Delivery Actions ---
// Action 1: Mark as Picked Up (Status: Assigned -> In Transit)
if (isset($_GET['action']) && $_GET['action'] == 'pickup') {
    $order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
    if ($order_id) {
        // Set Status to 'In Transit' and set PickupDate
        $sql_pickup = "UPDATE Orders SET Status = 'In Transit', PickupDate = NOW() WHERE OrderID = ? AND AssignedDistributorID = ? AND Status = 'Assigned'";
        $stmt_pickup = $conn->prepare($sql_pickup);
        if ($stmt_pickup) {
            $stmt_pickup->bind_param("ii", $order_id, $user_id);
            if($stmt_pickup->execute() && $stmt_pickup->affected_rows > 0) {
                 header("Location: distributor_dashboard.php?success=picked_up"); exit;
            } else { $message = "<p class='message error'>Failed to mark as picked up (check status?).</p>";}
            $stmt_pickup->close();
        } else { $message = "<p class='message error'>Pickup prepare failed.</p>"; }
    } else { $message = "<p class='message error'>Invalid ID for pickup.</p>"; }
}

// Action 2: Mark as Delivered (Status: In Transit -> Delivered)
if (isset($_GET['action']) && $_GET['action'] == 'deliver') {
    $order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
    if ($order_id) {
        // Set Status to 'Delivered' and set DeliveryDate
        $sql_deliver = "UPDATE Orders SET Status = 'Delivered', DeliveryDate = NOW() WHERE OrderID = ? AND AssignedDistributorID = ? AND Status = 'In Transit'";
        $stmt_deliver = $conn->prepare($sql_deliver);
        if ($stmt_deliver) {
            $stmt_deliver->bind_param("ii", $order_id, $user_id);
             if($stmt_deliver->execute() && $stmt_deliver->affected_rows > 0) {
                 header("Location: distributor_dashboard.php?success=delivered"); exit;
            } else { $message = "<p class='message error'>Failed to mark as delivered (check status?).</p>";}
            $stmt_deliver->close();
        } else { $message = "<p class='message error'>Deliver prepare failed.</p>"; }
    } else { $message = "<p class='message error'>Invalid ID for delivery.</p>"; }
}

// Display feedback messages
if(empty($message)) {
    if(isset($_GET['success'])){ if($_GET['success']=='picked_up')$message="<p class'message success'>Order marked as picked up (In Transit).</p>"; elseif($_GET['success']=='delivered')$message="<p class='message success'>Order marked as delivered.</p>";}
    if(isset($_GET['error'])) $message = "<p class='message error'>Operation failed: ".htmlspecialchars($_GET['error'])."</p>";
}
?>

<title>Manage Deliveries - Distributor</title>
<style> /* Styles */
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);margin:1rem auto;max-width:1400px} .list-table{width:100%;border-collapse:collapse;margin-top:1rem;font-size:.9rem} .list-table th,.list-table td{border:1px solid #ddd;padding:10px;text-align:left;vertical-align:middle} .list-table th{background-color:#f2f2f2; font-weight: bold;} .status-assigned{color:#6f42c1;font-weight:700} .status-in-transit{color:#0d6efd;font-weight:700} .status-delivered{color:#198754;font-weight:700} .btn-details,.btn-action{display:inline-block;padding:5px 10px;font-size:.8rem;color:#fff;text-decoration:none;border-radius:4px;border:none;cursor:pointer; margin: 2px;} .btn-details{background-color:#555} .btn-action{background-color:#0d6efd} .btn-action.green{background-color:#198754} .btn-action.disabled{background-color:#6c757d;pointer-events:none} .message{padding:1rem;border-radius:5px;margin:1rem 0;font-weight:700;text-align:center;} .message.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;} .message.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
</style>

<div class="page-header"><h1>Manage Assigned Deliveries</h1></div>

<div class="form-card">
    <?php echo $message; ?>
    <p>This section shows orders assigned to you for pickup and delivery.</p>
    <div style="overflow-x:auto;">
    <table class="list-table">
        <thead><tr><th>ID</th><th>Product</th><th>Qty</th><th>From (Mfr)</th><th>To (Buyer)</th><th>Status</th><th>Pickup Date</th><th>Delivery Date</th><th>Action</th><th>Trace</th></tr></thead>
        <tbody>
            <?php
            // Query: Orders assigned to this distributor (Status 'Assigned' or 'In Transit')
            $sql="SELECT o.*, p.ProductName, s.Username as SellerName, b.Username as BuyerName FROM Orders o JOIN Products p ON o.ProductID=p.ProductID JOIN Users s ON o.SellerID=s.UserID JOIN Users b ON o.BuyerID=b.UserID WHERE o.AssignedDistributorID=? AND o.Status IN ('Assigned', 'In Transit') ORDER BY o.OrderDate ASC";
            $stmt=$conn->prepare($sql); $no_orders = true;
            if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $result=$stmt->get_result();
                if ($result->num_rows > 0) { $no_orders = false; while ($row = $result->fetch_assoc()) {
                    $sc='status-'.strtolower(str_replace(' ','-',$row['Status'])); $st=htmlspecialchars($row['Status']); if($st=='Assigned')$st='Ready for Pickup';
                    $pickup_d = $row['PickupDate'] ? date('Y-m-d H:i', strtotime($row['PickupDate'])) : 'N/A';
                    $deliver_d = $row['DeliveryDate'] ? date('Y-m-d H:i', strtotime($row['DeliveryDate'])) : 'N/A';
                    
                    // ** THIS IS THE FIX **
                    // Changed $row_['OrderQuantity'] to $row['OrderQuantity']
                    echo "<tr><td>".$row['OrderID']."</td><td>".htmlspecialchars($row['ProductName'])."</td><td>".htmlspecialchars($row['OrderQuantity'])."</td><td>".htmlspecialchars($row['SellerName'])."</td><td>".htmlspecialchars($row['BuyerName'])."</td><td class='".$sc."'>".$st."</td><td>".$pickup_d."</td><td>".$deliver_d."</td>";
                    // ** END FIX **

                    echo "<td>"; // Action
                    if ($row['Status'] == 'Assigned') { echo "<a href='distributor_dashboard.php?action=pickup&order_id=".$row['OrderID']."' class='btn-action'>Mark Picked Up</a>"; }
                    elseif ($row['Status'] == 'In Transit') { echo "<a href='distributor_dashboard.php?action=deliver&order_id=".$row['OrderID']."' class='btn-action green'>Mark Delivered</a>"; }
                    else { echo "<span class='btn-action disabled'>Locked</span>"; }
                    echo "</td>";
                    echo "<td>"; // Trace
                    if ($row['SourceBatchID']) { echo "<a href='track_food.php?batch_id=".$row['SourceBatchID']."' class='btn-details' target='_blank'>View Chain</a>"; }
                    else { echo "Mfr Pending Link"; }
                    echo "</td></tr>";
                }} if($no_orders) { echo "<tr><td colspan='10'>No deliveries currently assigned to you.</td></tr>"; }
                $stmt->close();
            } else { echo "<tr><td colspan='10' style='color:red;'>Error preparing query: ".htmlspecialchars($conn->error)."</td></tr>"; error_log("Dist dash prep fail: ".$conn->error); }
            ?>
        </tbody>
    </table>
    </div>
</div>

</div></body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>