<?php
// Use our header. This checks for login and shows the nav bar.
include 'header_app.php';
// $conn and $role are now available

// --- Security Check ---
if ($role != 'Distributor') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to view this page.</p>";
    echo "</div></body></html>";
    exit;
}

// --- Handle Cancel Outgoing Order Action ---
// (Keep this section as it was)
if (isset($_GET['action']) && $_GET['action'] == 'cancel_dist_order') {
    $dist_order_id = intval($_GET['dist_order_id']);
    $distributor_id = $_SESSION['user_id'];
    $sql_cancel = "DELETE FROM DistributorOrders WHERE DistOrderID = ? AND DistributorID = ? AND Status = 'Pending'";
    $stmt_cancel = $conn->prepare($sql_cancel);
    $stmt_cancel->bind_param("ii", $dist_order_id, $distributor_id);
    if ($stmt_cancel->execute() && $stmt_cancel->affected_rows > 0) {
        header("Location: distributor_orders.php?success=cancelled"); exit;
    } else {
        header("Location: distributor_orders.php?error=cancel_failed"); exit;
    }
}


// --- Handle INCOMING Order Actions ---
// Action 1: "Finish" (Assign Source Order from Manufacturer) - NO LONGER NEEDED FOR DISTRIBUTOR
// Distributor only delivers now. Manufacturer assigns source.

// Action 2: "Delivered" for Incoming Orders
if (isset($_GET['action']) && $_GET['action'] == 'deliver_retail_order') {
    $retail_order_id = intval($_GET['retail_order_id']);
    $distributor_id = $_SESSION['user_id']; // Current logged-in distributor

    if (!empty($retail_order_id)) {
        // Update the Retailer order: Set Status to 'Delivered'
        // ONLY if the status is currently 'Finished' (meaning Mfr assigned Distributor)
        // AND the order is assigned to THIS distributor
        $sql_deliver_retail = "UPDATE RetailerOrders
                               SET Status = 'Delivered'
                               WHERE RetailOrderID = ? AND AssignedDistributorID = ? AND Status = 'Finished'"; // Check AssignedDistributorID
        $stmt_deliver_retail = $conn->prepare($sql_deliver_retail);
        $stmt_deliver_retail->bind_param("ii", $retail_order_id, $distributor_id);
        if ($stmt_deliver_retail->execute() && $stmt_deliver_retail->affected_rows > 0) {
             header("Location: distributor_orders.php?success=delivered"); exit;
        } else {
             // Failed (maybe status wasn't 'Finished' or wrong distributor)
             header("Location: distributor_orders.php?error=deliver_failed"); exit;
        }
    } else {
         header("Location: distributor_orders.php?error=deliver_missing_id"); exit;
    }
}
?>

<title>Manage Deliveries - Organic Traceability</title>

<style>
/* ... (Keep all existing styles) ... */
.form-card { background-color: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin: 1rem auto; max-width: 1400px; }
.list-table { width: 100%; border-collapse: collapse; margin-top: 1rem; margin-bottom: 3rem; font-size: 0.9rem; }
.list-table th, .list-table td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: middle; }
.list-table th { background-color: #f2f2f2; }
.status-pending { color: #ffc107; font-weight: bold; } /* Waiting for Mfr Assign */
.status-assigned { color: #6f42c1; font-weight: bold; } /* Assigned to Distributor */
.status-finished { color: #0d6efd; font-weight: bold; } /* Ready for Delivery (Old Finished) */
.status-delivered { color: #198754; font-weight: bold; } /* Delivered by Distributor */
.btn-details, .btn-action { display: inline-block; padding: 5px 10px; font-size: 0.8rem; color: #fff; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
.btn-details { background-color: #555; }
.btn-action { background-color: #198754; } /* Green for Deliver */
.btn-action.disabled { background-color: #6c757d; pointer-events: none; }
</style>

<div class="page-header">
    <h1>Manage Deliveries</h1>
</div>

<div class="form-card">

    <h2>Orders Assigned for Delivery</h2>
    <p>This section shows orders from Retailers/Customers assigned to you for delivery.</p>
    <table class="list-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Total Price ($)</th>
                <th>Deliver To (Customer)</th>
                <th>Date Ordered</th>
                <th>Status</th> <th>Manipulated?</th>
                <th>Action</th>
                <th>Trace Details</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $current_user_id = $_SESSION['user_id']; // Distributor's ID

            // ==================================================================
            // THE FIX: Query WHERE ro.AssignedDistributorID = ?
            // ==================================================================
            $sql_incoming = "SELECT
                                ro.*,
                                p.ProductName,
                                cust.Username as CustomerName
                            FROM RetailerOrders ro
                            JOIN Products p ON ro.ProductID = p.ProductID
                            JOIN Users cust ON ro.RetailerID = cust.UserID
                            WHERE ro.AssignedDistributorID = ? -- Filter by the logged-in distributor
                            ORDER BY ro.OrderDate DESC";

            $stmt_incoming = $conn->prepare($sql_incoming);
            $stmt_incoming->bind_param("i", $current_user_id);
            $stmt_incoming->execute();
            $result_incoming = $stmt_incoming->get_result();

            if ($result_incoming->num_rows > 0) {
                while ($row_in = $result_incoming->fetch_assoc()) {
                    $datetime_in = new DateTime($row_in['OrderDate']);
                    $date_in = $datetime_in->format('Y-m-d');
                    $time_in = $datetime_in->format('h:i A');

                    $status_class_in = '';
                    $status_text = htmlspecialchars($row_in['Status']);
                    // Adjusted status text for clarity in Distributor view
                    if ($row_in['Status'] == 'Pending') {
                        $status_class_in = 'status-pending';
                        $status_text = 'Waiting Assignment'; // Waiting for Manufacturer to assign Distributor
                    }
                     if ($row_in['Status'] == 'Assigned') { // New status (set by Mfr)
                        $status_class_in = 'status-assigned';
                        $status_text = 'Ready for Pickup/Delivery';
                    }
                    if ($row_in['Status'] == 'Finished') { // 'Finished' could represent Manufacturer processing complete
                         $status_class_in = 'status-finished';
                         $status_text = 'Ready for Delivery'; // Or keep as 'Finished'
                    }
                    if ($row_in['Status'] == 'Delivered') $status_class_in = 'status-delivered';


                    $manipulated_in = $row_in['isManipulated'] ? "<span style='color:red; font-weight:bold;'>Yes</span>" : "No";

                    echo "<tr>";
                    echo "<td>" . $row_in['RetailOrderID'] . "</td>";
                    echo "<td>" . htmlspecialchars($row_in['ProductName']) . "</td>";
                    echo "<td>" . htmlspecialchars($row_in['OrderQuantity']) . "</td>";
                    echo "<td>$" . number_format($row_in['TotalPrice'], 2) . "</td>";
                    echo "<td>" . htmlspecialchars($row_in['CustomerName']) . "</td>";
                    echo "<td>" . $date_in . " at " . $time_in . "</td>";
                    echo "<td class='" . $status_class_in . "'>" . $status_text . "</td>";
                    echo "<td>" . $manipulated_in . "</td>";

                    // --- Action Button (Deliver) ---
                    echo "<td>";
                    // Distributor can deliver if status is 'Finished' (or 'Assigned' depending on workflow)
                    if ($row_in['Status'] == 'Finished' || $row_in['Status'] == 'Assigned') {
                        echo "<a href='distributor_orders.php?action=deliver_retail_order&retail_order_id=" . $row_in['RetailOrderID'] . "' class='btn-action'>Mark Delivered</a>"; // Green
                    } else {
                        // Status is Pending or Delivered
                        echo "<span class='btn-action disabled'>N/A</span>";
                    }
                    echo "</td>";

                    // --- Trace Details (OldLink) Button ---
                    // This trace logic needs rethinking based on the new workflow
                    // How does the RetailerOrder link back to the source batch?
                    // We need the Manufacturer to link RetailerOrder -> ProductOrder -> BatchID
                    echo "<td>";
                    echo "Trace TBD"; // Placeholder - requires Manufacturer update
                    // Example: if Manufacturer adds ProductOrderID to RetailerOrders:
                    /*
                    if ($row_in['SourceProductOrderID']) { // Assuming Mfr adds this link
                         $trace_sql = "SELECT BatchID FROM ProductOrders WHERE OrderID = ?";
                         // ... rest of trace logic ...
                    } else {
                         echo "Waiting for Mfr";
                    }
                    */
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='10'>No deliveries assigned to you.</td></tr>";
            }
            $stmt_incoming->close();
            $conn->close();
            ?>
        </tbody>
    </table>

</div>

<?php
// Close the HTML tags opened by header_app.php
?>
</div> </body>
</html>