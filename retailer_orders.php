<?php
// Includes header_app.php: Security check, session_start(), $conn, $role, $user_id
// This header ALSO checks if the profile is complete and redirects if not.
include_once 'header_app.php';

// --- Security Check: Role ---
// This page is for Retailers and Consumers to view their orders
if ($role != 'Retailer' && $role != 'Consumer') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to view this page.</p></div></body></html>";
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
    exit;
}

// ==================================================================
// --- Profile Completion Check (Backup for this specific page) ---
// ==================================================================
// This check is *also* in header_app.php, but we add it here for extra security.
// Consumers are exempt from this check.
if ($role == 'Retailer') {
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
        if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
        exit;
    }

    if ($profile_incomplete) {
        if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
        header("Location: profile.php?error=complete_profile");
        exit;
    }
}
// --- End of Profile Completion Check ---


// --- Handle Cancel Action (Optional) ---
if (isset($_GET['action']) && $_GET['action'] == 'cancel') {
    $order_id_to_cancel = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
    if ($order_id_to_cancel) {
        // Can only cancel if it's pending and belongs to this user
        $sql_cancel = "DELETE FROM Orders WHERE OrderID = ? AND BuyerID = ? AND Status = 'Pending Confirmation'";
        $stmt_cancel = $conn->prepare($sql_cancel);
        if ($stmt_cancel) {
            $stmt_cancel->bind_param("ii", $order_id_to_cancel, $user_id);
            $stmt_cancel->execute();
            if ($stmt_cancel->affected_rows > 0) {
                 header("Location: retailer_orders.php?success=cancelled"); exit;
            } else {
                 header("Location: retailer_orders.php?error=cancel_failed"); exit;
            }
            $stmt_cancel->close();
        }
    }
}

?>

<title>My Orders - Organic Traceability</title>

<style>
/* ... (Ensure these styles are in your app_style.css) ... */
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);margin:1rem auto;max-width:1400px}
.list-table{width:100%;border-collapse:collapse;margin-top:1rem;font-size:.9rem}
.list-table th,.list-table td{border:1px solid #ddd;padding:10px;text-align:left;vertical-align:middle}
.list-table th{background-color:#f2f2f2; font-weight: bold;}
.status-pending-confirmation{color:#dc3545;font-weight:700}
.status-processing{color:#ffc107;font-weight:700}
.status-ready-for-pickup{color:#6f42c1;font-weight:700}
.status-assigned{color:#6f42c1;font-weight:700} /* Same as ready */
.status-in-transit{color:#0d6efd;font-weight:700}
.status-delivered{color:#198754;font-weight:700}
.btn-details{display:inline-block;padding:5px 10px;font-size:.8rem;color:#fff;text-decoration:none;border-radius:4px;background-color:#555}
.btn-details:hover{background-color:#333;}
.btn-cancel{display:inline-block;padding:5px 10px;font-size:.8rem;color:#fff;text-decoration:none;border-radius:4px;background-color:#dc3545; margin-left: 5px;}
.btn-cancel:hover{background-color:#c82333;}
.btn-action.disabled{background-color:#6c757d;pointer-events:none;padding:5px 10px;font-size:.8rem;color:#fff;border-radius:4px; display: inline-block;}
</style>

<div class="page-header">
    <h1>My Orders</h1>
</div>

<div class="form-card">
    <p>This section shows all orders you have placed to Manufacturers.</p>
    <div style="overflow-x:auto;">
    <table class="list-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Total Price (₱)</th>
                <th>From (Manufacturer)</th>
                <th>Date Ordered</th>
                <th>Status</th>
                <th>Assigned Distributor</th>
                <th>Action</th>
                <th>Trace Details</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Query: Orders placed BY this user (BuyerID)
            $sql_my_orders = "SELECT
                                o.*,
                                p.ProductName,
                                s.Username as SellerName, -- Manufacturer
                                d.Username as DistributorName -- Assigned Distributor
                            FROM Orders o
                            JOIN Products p ON o.ProductID = p.ProductID
                            JOIN Users s ON o.SellerID = s.UserID
                            LEFT JOIN Users d ON o.AssignedDistributorID = d.UserID -- LEFT JOIN for distributor
                            WHERE o.BuyerID = ? -- Filter by the logged-in user
                            ORDER BY o.OrderDate DESC";

            $stmt_my_orders = $conn->prepare($sql_my_orders);
            $no_orders_found = true;
            if ($stmt_my_orders) {
                $stmt_my_orders->bind_param("i", $user_id);
                $stmt_my_orders->execute();
                $result_my_orders = $stmt_my_orders->get_result();

                if ($result_my_orders->num_rows > 0) {
                    $no_orders_found = false;
                    while ($row = $result_my_orders->fetch_assoc()) {

                        $datetime = new DateTime($row['OrderDate']);
                        $date = $datetime->format('Y-m-d H:i');

                        // User-friendly status text
                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $row['Status']));
                        $status_text = htmlspecialchars($row['Status']);
                        if ($row['Status'] == 'Pending Confirmation') $status_text = 'Waiting for Confirmation';
                        if ($row['Status'] == 'Ready for Pickup') $status_text = 'Preparing for Shipment';
                        if ($row['Status'] == 'Assigned') $status_text = 'Out for Delivery';
                        if ($row['Status'] == 'In Transit') $status_text = 'Out for Delivery';

                        $assigned_distributor = $row['DistributorName'] ?? 'Not Assigned Yet';

                        echo "<tr>";
                        // **THIS IS THE FIX**
                        echo "<td>" . $row['OrderID'] . "</td>";
                        // **END FIX**
                        echo "<td>" . htmlspecialchars($row['ProductName']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['OrderQuantity']) . "</td>";
                        echo "<td>₱" . number_format($row['TotalPrice'], 2) . "</td>";
                        echo "<td>" . htmlspecialchars($row['SellerName']) . "</td>";
                        echo "<td>" . $date . "</td>";
                        echo "<td class='" . $status_class . "'>" . $status_text . "</td>";
                        echo "<td>" . htmlspecialchars($assigned_distributor) . "</td>";
                        
                        // --- Action Column (Cancel Button) ---
                        echo "<td>";
                        if ($row['Status'] == 'Pending Confirmation') {
                            echo "<a href='retailer_orders.php?action=cancel&order_id=" . $row['OrderID'] . "' class='btn-cancel' onclick=\"return confirm('Are you sure you want to cancel this order?');\">Cancel</a>";
                        } else {
                            echo "<span class='btn-action disabled'>Locked</span>";
                        }
                        echo "</td>";

                        // --- Trace Details (View Chain Link) ---
                        echo "<td>";
                         if ($row['SourceBatchID']) {
                              echo "<a href='track_food.php?batch_id=" . $row['SourceBatchID'] . "' class='btn-details' target='_blank'>View Chain</a>";
                         } else {
                              echo "N/A"; // Manufacturer hasn't linked source batch yet
                         }
                        echo "</td>";

                        echo "</tr>";
                    }
                }
                $stmt_my_orders->close();
            } else {
                 echo "<tr><td colspan='10' style='color:red;'>Error preparing query: ".htmlspecialchars($conn->error)."</td></tr>";
                 error_log("Retailer orders prep fail: ".$conn->error);
            }
            
            if ($no_orders_found && $stmt_my_orders) { // Check if query ran
                echo "<tr><td colspan='10' style='text-align:center;'>You haven't placed any orders yet.</td></tr>"; // Adjusted colspan
            }
            ?>
        </tbody>
    </table>
    </div>
</div>

</div></body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>