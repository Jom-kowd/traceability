<?php
// Use our header to check for login
include 'header_app.php';

// --- Security Check ---
// Only Manufacturer should be able to cancel their orders
if ($role != 'Manufacturer') {
    header("Location: dashboard.php?error=no_permission");
    exit;
}

// Check if an OrderID was provided
if (isset($_GET['order_id'])) {

    $order_id = intval($_GET['order_id']);
    $manufacturer_id = $_SESSION['user_id'];

    // Delete the order ONLY if it's still 'Pending'
    // We also check ManufacturerID to make sure this user owns the order.
    $sql = "DELETE FROM ProductOrders
            WHERE OrderID = ? AND ManufacturerID = ? AND Status = 'Pending'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $order_id, $manufacturer_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Success! Redirect back to the orders page
            header("Location: manufacturer_orders.php?success=cancelled");
        } else {
            // Order not found, not pending, or not owned by user
            header("Location: manufacturer_orders.php?error=cancel_failed_status");
        }
    } else {
        // Failure
        header("Location: manufacturer_orders.php?error=cancel_failed_db");
    }

    $stmt->close();
    $conn->close();
    exit;

} else {
    // No ID provided
    header("Location: manufacturer_orders.php");
    exit;
}
?>