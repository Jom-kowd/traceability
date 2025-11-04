<?php
/* Database Connection */
$servername = "localhost";
$username = "root";       // Default WAMP username
$password = "";           // Default WAMP password
$dbname = "OrganicTraceabilityDB"; // Make sure this matches your DB name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Database Connection failed: " . $conn->connect_error);
    die("Database connection failed. Please check server configuration.");
}
$conn->set_charset("utf8mb4"); // Set character set
?>