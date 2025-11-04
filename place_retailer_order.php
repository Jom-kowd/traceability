<?php
include_once 'header_app.php'; // Security, $conn, $role, $user_id
if ($role != 'Retailer' && $role != 'Consumer') { echo "<h1>Access Denied</h1></div></body></html>"; exit; }

// --- (Profile Completion Check - same as before) ---
if ($role == 'Retailer') {
    $sql_profile_check = "SELECT FullName, Address FROM Users WHERE UserID = ?";
    $stmt_profile_check = $conn->prepare($sql_profile_check);
    $profile_incomplete = true; 
    if ($stmt_profile_check) {
        $stmt_profile_check->bind_param("i", $user_id); $stmt_profile_check->execute();
        $result_profile = $stmt_profile_check->get_result();
        if($user_profile = $result_profile->fetch_assoc()) {
            if (!empty($user_profile['FullName']) && !empty($user_profile['Address'])) { $profile_incomplete = false; }
        } $stmt_profile_check->close();
    } else { echo "<h1>Error</h1><p>Could not verify user profile.</p></div></body></html>"; if (isset($conn)) $conn->close(); exit; }
    if ($profile_incomplete) {
        if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); }
        header("Location: profile.php?error=complete_profile"); exit;
    }
}
// --- End of Profile Completion Check ---

$message = ''; $product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$product_id) { echo "<h1>Invalid Product ID</h1></div></body></html>"; exit; }

// Fetch Manufacturer's Product Data
$sql_fetch = "SELECT p.*, u.Username as ManufacturerName, u.UserID as ManufacturerID FROM Products p JOIN Users u ON p.CreatedByUserID=u.UserID JOIN UserRoles ur ON u.RoleID=ur.RoleID WHERE p.ProductID=? AND ur.RoleName='Manufacturer' AND p.ProductType='Processed'";
$stmt_fetch = $conn->prepare($sql_fetch); $product = null; $product_price = 0; $manufacturer_id = 0;
if($stmt_fetch){ $stmt_fetch->bind_param("i", $product_id); $stmt_fetch->execute(); $result=$stmt_fetch->get_result();
    if($result->num_rows==1){ $product=$result->fetch_assoc(); $product_price=$product['Price']??0; $manufacturer_id=$product['ManufacturerID']; } $stmt_fetch->close();
}
if(!$product){ echo "<h1>Error</h1><p>Manufacturer product not found.</p></div></body></html>"; exit; }

// Handle Form Submission
if (isset($_POST['place_retailer_order'])) {
    $order_quantity = filter_input(INPUT_POST, 'order_quantity', FILTER_VALIDATE_FLOAT);
    if ($order_quantity === false || $order_quantity <= 0) { $message = "<p class='message error'>Invalid quantity.</p>"; }
    else {
        $total_price = $order_quantity * $product_price;
        $buyer_id = $user_id; // Logged-in user is buyer

        // ========================================================
        // --- 1. **FIXED**: Generate Transaction Hash for the Order ---
        // ========================================================

        // --- START TIMER ---
        $time_start = microtime(true);

        // We cast all values to strings and format decimals to 2 places
        // to ensure the hash is identical during verification.
        $data_to_hash = [
            'ProductID' => (string)$product_id,
            'SellerID' => (string)$manufacturer_id,
            'BuyerID' => (string)$buyer_id,
            'OrderQuantity' => number_format($order_quantity, 2, '.', ''), // e.g., "12.00"
            'TotalPrice' => number_format($total_price, 2, '.', '')      // e.g., "1140.00"
        ];
        $transaction_hash = hash('sha256', json_encode($data_to_hash));
        
        // --- END TIMER ---
        $time_end = microtime(true);
        $latency_ms = ($time_end - $time_start) * 1000; // Calculate latency in milliseconds

        // --- NEW: Log Latency ---
        try {
            $log_sql = "INSERT INTO performance_logs (action_name, latency_ms) VALUES ('create_order_hash', ?)";
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt) {
                $log_stmt->bind_param("d", $latency_ms); // "d" for double (float)
                $log_stmt->execute();
                $log_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Failed to log performance: " . $e->getMessage()); // Log if logging fails
        }
        // --- END NEW SECTION ---

        // ========================================================

        // 2. Insert into Orders table WITH the new hash
        $sql_order = "INSERT INTO Orders (ProductID, SellerID, BuyerID, OrderQuantity, TotalPrice, Status, TransactionHash) VALUES (?,?,?,?,?, 'Pending Confirmation', ?)";
        $stmt_order=$conn->prepare($sql_order);
        if($stmt_order){
            // "iiidds" = int, int, int, double, double, string
            $stmt_order->bind_param("iiidds", $product_id, $manufacturer_id, $buyer_id, $order_quantity, $total_price, $transaction_hash);
            if($stmt_order->execute()){ $message="<p class='message success'>Order placed successfully! View in <a href='retailer_orders.php'>My Orders</a>.</p>"; }
            else{$message="<p class='message error'>Order failed: ".$stmt_order->error."</p>"; error_log("Place Retailer Order Fail: ".$stmt_order->error);}
            $stmt_order->close();
        } else {$message="<p class='message error'>DB prepare error: ".$conn->error."</p>"; error_log("Place Retailer Order Prep Fail: ".$conn->error);}
    }
}
?>
<title>Place Order: <?php echo htmlspecialchars($product['ProductName']); ?></title>
<style> /* Styles */
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:700px;margin:1rem auto} .form-card label{display:block;margin-bottom:.5rem;font-weight:700;color:#555} .form-card input[type=text],.form-card input[type=number]{width:100%;padding:.75rem;margin-bottom:1.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box} .form-card input[disabled]{background-color:#e9ecef;cursor:not-allowed} .product-summary{display:flex;align-items:center;gap:1.5rem;margin-bottom:2rem;background-color:#f9f9f9;padding:1.5rem;border-radius:5px;border:1px solid #eee} .product-summary img{width:100px;height:100px;object-fit:cover;border-radius:5px} .product-summary-info h3{margin:0 0 .5rem 0} .product-summary-info p{margin:.25rem 0;color:#555} #total-price-display{font-weight:700;font-size:1.2rem;color:#28a745} .btn-submit{background-color:#0d6efd;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer} .btn-cancel{display:inline-block;background-color:#6c757d;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;margin-left:10px} .message{padding:1rem;border-radius:5px;margin-bottom:1rem;font-weight:700; text-align: center;} .message.error{background-color:#f8d7da;color:#721c24} .message.success{background-color:#d4edda;color:#155724}
</style>
<div class="page-header"> <h1>Place Order from Manufacturer</h1> </div>
<div class="form-card">
    <?php echo $message; ?>
    <div class="product-summary">
        <?php if(!empty($product['ProductImage'])&&file_exists($product['ProductImage'])){echo'<img src="'.htmlspecialchars($product['ProductImage']).'" alt="">';}else{echo'<img src="https://via.placeholder.com/100?text=No+Image" alt="">';}?>
        <div><h3><?php echo htmlspecialchars($product['ProductName']);?></h3><p>From: <?php echo htmlspecialchars($product['ManufacturerName']);?></p><p>Price: &#8369;<?php echo number_format($product_price,2);?> / unit</p></div>
    </div>
    <form action="place_retailer_order.php?product_id=<?php echo $product_id; ?>" method="POST">
        <input type="hidden" id="product-price" value="<?php echo $product_price; ?>">
        <label for="order_quantity">Quantity:</label><input type="number" id="order_quantity" name="order_quantity" step="0.01" min="0.01" required oninput="calculateTotal()">
        <label>Total Price:</label><input type="text" id="total-price-display" value="&#8369;0.00" disabled>
        <input type="hidden" id="total_price_hidden" name="total_price" value="0">
        <button type="submit" name="place_retailer_order" class="btn-submit">Confirm Order</button>
        <a href="browse_manufacturer_products.php" class="btn-cancel">Cancel</a>
    </form>
</div>
<script>function calculateTotal(){const q=parseFloat(document.getElementById('order_quantity').value)||0,p=parseFloat(document.getElementById('product-price').value)||0,t=(q*p).toFixed(2);document.getElementById('total-price-display').value='₱'+t;document.getElementById('total_price_hidden').value=t;}</script>
</div></body></html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>