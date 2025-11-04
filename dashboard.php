<?php
// Include the header first - it provides $conn and checks login
include_once 'header_app.php';
// $conn, $role, $user_id are available if login was successful
?>
<title>Dashboard - Organic Traceability</title>

<h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h1>
<p>This is the main dashboard for the Organic Food Traceability System.</p>
<p>Your current role is: <strong><?php echo htmlspecialchars($role); ?></strong>.</p>
<p>Please use the links in the navigation bar above to access features.</p>

<?php
// --- End of specific page content ---
?>

</div></body>
</html>
<?php
// **CRITICAL:** Close the connection *only* if it exists and is open, at the VERY END.
// Line 25 (or around there, depending on exact lines) should NOT be closing the connection.
// This block should be the last thing in the file.
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>