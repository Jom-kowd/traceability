<?php
// Includes admin security check, session_start(), and $conn (database connection)
include_once 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - User Verification</title>
    <link rel="stylesheet" href="admin_style.css"> </head>
<body>
<div class="admin-container">
    <h1>Admin Dashboard</h1>
    <p> Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>! |
        <a href="performance_report.php">Performance Report</a> |
        <a href="trust_report.php">Trust Report</a> |
        <a href="../logout.php">Logout</a> </p>

    <?php
        if (isset($_GET['success'])) {
             $success_msg = ($_GET['success'] == 'updated') ? 'User status updated successfully!' : 'Operation successful!';
             echo '<p class="message success">'.htmlspecialchars($success_msg).'</p>';
        }
        if (isset($_GET['error'])) {
             $error_code = $_GET['error'];
             $error_text = 'An error occurred.';
             if ($error_code == 'invalidaction') $error_text = 'Invalid action specified.';
             if ($error_code == 'invaliduserid') $error_text = 'Invalid user ID specified.';
             if ($error_code == 'notpending_or_notfound') $error_text = 'User not found or status already updated.';
             if ($error_code == 'updatefailed_db') $error_text = 'Database error during update.';
             if ($error_code == 'preparefailed') $error_text = 'Database query preparation error.';

             echo '<p class="message error">Error: '.htmlspecialchars($error_text).'</p>';
        }
    ?>

    <h2>Pending User Verifications</h2>
    <div style="overflow-x:auto;">
        <table class="verification-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Certificate</th>
                    <th>Cert Expiry</th> <th>Valid ID</th> 
                    <th>ID Expiry</th> <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // ** UPDATED QUERY to fetch Expiry Dates **
            $sql = "SELECT u.UserID, u.FullName, u.Email, r.RoleName, 
                           u.CertificateInfo, u.ValidIDPath, 
                           u.CertificateExpiryDate, u.ValidIDExpiryDate
                    FROM Users u
                    JOIN UserRoles r ON u.RoleID = r.RoleID
                    WHERE u.VerificationStatus = 'Pending'
                    ORDER BY u.UserID ASC"; 
            $result = $conn->query($sql); 
            $today = new DateTime(); // Today's date for comparison

            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['UserID'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['FullName']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Email']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['RoleName']) . "</td>";
                    
                    // Certificate Link
                    echo "<td>";
                    $cert_path = '../' . $row['CertificateInfo']; 
                    if (!empty($row['CertificateInfo']) && file_exists($cert_path)) {
                        echo "<a href='" . htmlspecialchars($cert_path) . "' target='_blank'>View Cert</a>";
                    } else if (!empty($row['CertificateInfo'])) {
                        echo "<span style='color:grey;'>File Missing</span>";
                    } else { echo "N/A"; }
                    echo "</td>";

                    // ** NEW: Cert Expiry Date **
                    echo "<td>";
                    if (!empty($row['CertificateExpiryDate'])) {
                        $expiry_date = new DateTime($row['CertificateExpiryDate']);
                        $is_expired = $expiry_date < $today;
                        echo "<span style='" . ($is_expired ? "color:red; font-weight:bold;" : "") . "'>" . 
                             htmlspecialchars($row['CertificateExpiryDate']) . 
                             ($is_expired ? " (EXPIRED)" : "") . "</span>";
                    } else { echo "N/A"; }
                    echo "</td>";

                    // Valid ID Link
                    echo "<td>";
                    $id_path = '../' . $row['ValidIDPath']; 
                    if (!empty($row['ValidIDPath']) && file_exists($id_path)) {
                        echo "<a href='" . htmlspecialchars($id_path) . "' target='_blank'>View ID</a>";
                    } else if (!empty($row['ValidIDPath'])) {
                        echo "<span style='color:grey;'>File Missing</span>";
                    } else { echo "N/A"; }
                    echo "</td>";

                    // ** NEW: ID Expiry Date **
                    echo "<td>";
                    if (!empty($row['ValidIDExpiryDate'])) {
                        $expiry_date = new DateTime($row['ValidIDExpiryDate']);
                        $is_expired = $expiry_date < $today;
                        echo "<span style='" . ($is_expired ? "color:red; font-weight:bold;" : "") . "'>" . 
                             htmlspecialchars($row['ValidIDExpiryDate']) . 
                             ($is_expired ? " (EXPIRED)" : "") . "</span>";
                    } else { echo "N/A"; }
                    echo "</td>";

                    // Action links
                    echo "<td class='action-links'>";
                    echo "<a href='verify.php?id=" . $row['UserID'] . "&action=approve' class='approve'>Approve</a> ";
                    echo "<a href='verify.php?id=" . $row['UserID'] . "&action=reject' class='reject' onclick=\"return confirm('Are you sure you want to reject user #" . $row['UserID'] . "?');\">Reject</a>";
                    echo "</td>";
                    echo "</tr>";
                }
            } elseif ($result) {
                echo "<tr><td colspan='9' style='text-align:center;'>No pending user verifications found.</td></tr>"; // Updated colspan
            } else {
                 echo "<tr><td colspan='9' style='color:red; text-align:center;'>Error fetching pending users: " . htmlspecialchars($conn->error) . "</td></tr>"; // Updated colspan
                 error_log("Admin index - Fetch pending users failed: " . $conn->error); 
            }
            ?>
            </tbody>
        </table>
    </div> </div> <style>
.message { padding: 1rem; border-radius: 5px; margin: 1rem 0; font-weight: bold; text-align: center; }
.message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
</style>

</body>
</html>
<?php if (isset($conn)) $conn->close(); // Close connection ?>