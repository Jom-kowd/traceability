<?php
// --- 1. SET YOUR NEW PASSWORD HERE ---
$passwordToHash = 'admin123';

// --- 2. Generate the hash ---
$hashedPassword = password_hash($passwordToHash, PASSWORD_BCRYPT);

// --- 3. Display the hash ---
echo "Your new password: " . htmlspecialchars($passwordToHash) . "<br><br>";
echo "<strong>Copy this ENTIRE hash string:</strong><br>";
echo '<textarea rows="3" cols="70" readonly>' . htmlspecialchars($hashedPassword) . '</textarea>';
?>