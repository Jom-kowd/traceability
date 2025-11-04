<?php
include_once 'header_app.php'; // Security, $conn, $role, $user_id
if ($role != 'Manufacturer') { echo "<h1>Access Denied</h1></div></body></html>"; exit; }
$message = ''; $product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$product_id) { echo "<h1>Invalid Product ID</h1></div></body></html>"; exit; }

// Fetch Farmer's Product Data
$sql_fetch = "SELECT p.*, u.Username as FarmerName, u.UserID as FarmerID FROM Products p JOIN Users u ON p.CreatedByUserID=u.UserID JOIN UserRoles ur ON u.RoleID=ur.RoleID WHERE p.ProductID=? AND ur.RoleName='Farmer' AND p.ProductType='Raw'";
$stmt_fetch = $conn->prepare($sql_fetch); $product = null; $product_price = 0; $farmer_id = 0;
if($stmt_fetch){ $stmt_fetch->bind_param("i", $product_id); $stmt_fetch->execute(); $result=$stmt_fetch->get_result();
    if ($result->num_rows == 1) { $product=$result->fetch_assoc(); $product_price=$product['Price']??0; $farmer_id=$product['FarmerID']; }
    $stmt_fetch->close();
} else { error_log("Place Mfr Order - Fetch Prep Fail: ".$conn->error); }
if (!$product) { echo "<h1>Error</h1><p>Farmer raw product not found.</p></div></body></html>"; exit; }

// Handle Form Submission
if (isset($_POST['place_mf_order'])) {
    $order_quantity = filter_input(INPUT_POST, 'order_quantity', FILTER_VALIDATE_FLOAT);
    if ($order_quantity === false || $order_quantity <= 0) { $message = "<p class='message error'>Invalid quantity.</p>"; }
    else {
        $total_price = $order_quantity * $product_price;
        $sql_order="INSERT INTO ManufacturerFarmerOrders (RawProductID, FarmerID, ManufacturerID, OrderQuantity, TotalPrice, Status) VALUES (?,?,?,?,?, 'Pending Confirmation')";
        $stmt_order=$conn->prepare($sql_order);
        if($stmt_order){
            $stmt_order->bind_param("iiidd", $product_id, $farmer_id, $user_id, $order_quantity, $total_price);
            if($stmt_order->execute()){ $message="<p class='message success'>Order placed successfully! View in <a href='manufacturer_orders.php'>Manage Orders</a>.</p>"; }
            else{$message="<p class='message error'>Order failed: ".$stmt_order->error."</p>"; error_log("Place Mfr Order Fail: ".$stmt_order->error);}
            $stmt_order->close();
        } else {$message="<p class='message error'>DB prepare error: ".$conn->error."</p>"; error_log("Place Mfr Order Prep Fail: ".$conn->error);}
    }
}
?>
<title>Order Raw Materials: <?php echo htmlspecialchars($product['ProductName']); ?></title>
<style> /* Styles */
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:700px;margin:1rem auto} .form-card label{display:block;margin-bottom:.5rem;font-weight:700;color:#555} .form-card input[type=text],.form-card input[type=number]{width:100%;padding:.75rem;margin-bottom:1.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box} .form-card input[disabled]{background-color:#e9ecef;cursor:not-allowed} .product-summary{display:flex;align-items:center;gap:1.5rem;margin-bottom:2rem;background-color:#f9f9f9;padding:1.5rem;border-radius:5px;border:1px solid #eee} .product-summary img{width:100px;height:100px;object-fit:cover;border-radius:5px} .product-summary-info h3{margin:0 0 .5rem 0} .product-summary-info p{margin:.25rem 0;color:#555} #total-price-display{font-weight:700;font-size:1.2rem;color:#28a745} .btn-submit{background-color:#0d6efd;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer} .btn-cancel{display:inline-block;background-color:#6c757d;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;margin-left:10px} .message{padding:1rem;border-radius:5px;margin-bottom:1rem;font-weight:700} .message.error{background-color:#f8d7da;color:#721c24} .message.success{background-color:#d4edda;color:#155724}
</style>
<div class="page-header"> <h1>Order Raw Materials</h1> </div>
<div class="form-card">
    <?php echo $message; ?>
    <div class="product-summary">
        <?php if(!empty($product['ProductImage'])&&file_exists($product['ProductImage'])){echo'<img src="'.htmlspecialchars($product['ProductImage']).'" alt="">';}else{echo'<img src="https://via.placeholder.com/100?text=No+Image" alt="">';}?>
        <div><h3><?php echo htmlspecialchars($product['ProductName']);?></h3><p>From: <?php echo htmlspecialchars($product['FarmerName']);?></p><p>Price: ₱<?php echo number_format($product_price,2);?> / unit</p></div>
    </div>
    <form action="place_manufacturer_order.php?product_id=<?php echo $product_id; ?>" method="POST">
        <input type="hidden" id="product-price" value="<?php echo $product_price; ?>">
        <label for="order_quantity">Quantity:</label><input type="number" id="order_quantity" name="order_quantity" step="0.01" min="0.01" required oninput="calculateTotal()">
        <label>Total Price:</label><input type="text" id="total-price-display" value="0.00" disabled>
        <input type="hidden" id="total_price_hidden" name="total_price" value="0"> <button type="submit" name="place_mf_order" class="btn-submit">Confirm Order</button>
        <a href="browse_farmer_products.php" class="btn-cancel">Cancel</a>
    </form>
</div>
<script>function calculateTotal(){const q=parseFloat(document.getElementById('order_quantity').value)||0,p=parseFloat(document.getElementById('product-price').value)||0,t=(q*p).toFixed(2);document.getElementById('total-price-display').value='₱'+t;document.getElementById('total_price_hidden').value=t;}</script>
</div></body></html>
<?php if (isset($conn)) $conn->close(); ?>