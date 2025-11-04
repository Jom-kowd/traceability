<?php
// Includes admin security check, session_start(), and $conn (database connection)
include_once 'header.php';

// Fetch performance data
$sql_batch = "SELECT AVG(latency_ms) as avg_latency, COUNT(*) as count FROM performance_logs WHERE action_name = 'create_batch_hash'";
$result_batch = $conn->query($sql_batch);
$batch_data = $result_batch->fetch_assoc();

$sql_order = "SELECT AVG(latency_ms) as avg_latency, COUNT(*) as count FROM performance_logs WHERE action_name = 'create_order_hash'";
$result_order = $conn->query($sql_order);
$order_data = $result_order->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Performance Report</title>
    <link rel="stylesheet" href="admin_style.css"> 
</head>
<body>
<div class="admin-container">
    <h1>System Performance Report</h1>
    <p> Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>! |
        <a href="index.php">User Verification</a> |
        <a href="../logout.php">Logout</a> </p>

    <h2>Hash Generation Latency</h2>
    <p>This report shows the average time (in milliseconds) it takes the server to perform the SHA-256 hashing operations, as requested by the panel.</p>
    
    <div style="overflow-x:auto;">
        <table class="verification-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Average Latency (ms)</th>
                    <th>Total Samples Logged</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Farmer: Create Batch Hash</strong><br><small>(in add_batch.php)</small></td>
                    <td><?php echo $batch_data['count'] > 0 ? number_format($batch_data['avg_latency'], 4) . ' ms' : 'N/A'; ?></td>
                    <td><?php echo $batch_data['count']; ?></td>
                </tr>
                <tr>
                    <td><strong>Retailer: Create Order Hash</strong><br><small>(in place_retailer_order.php)</small></td>
                    <td><?php echo $order_data['count'] > 0 ? number_format($order_data['avg_latency'], 4) . ' ms' : 'N/A'; ?></td>
                    <td><?php echo $order_data['count']; ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div> 
</body>
</html>
<?php if (isset($conn)) $conn->close(); // Close connection ?>