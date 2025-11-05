<?php
// Use our header.
include 'header_app.php';
// $conn and $role available

// --- Security Check ---
if ($role != 'Manufacturer') {
    echo "<h1 style='color:red;'>Access Denied</h1>"; exit;
}

$message = ''; $product_id = 0;

// Get Product ID
if (isset($_GET['product_id'])) { $product_id = intval($_GET['product_id']); }

// Fetch Farmer's Product Data
$sql_fetch = "SELECT p.*, u.Username as FarmerName, u.UserID as FarmerID
              FROM Products p JOIN Users u ON p.CreatedByUserID = u.UserID
              JOIN UserRoles ur ON u.RoleID = ur.RoleID
              WHERE p.ProductID = ? AND ur.RoleName = 'Farmer'";
$stmt_fetch = $conn->prepare($sql_fetch);
if(!$stmt_fetch){ die("Error prepare fetch: ".$conn->error); }
$stmt_fetch->bind_param("i", $product_id); $stmt_fetch->execute(); $result = $stmt_fetch->get_result();

if ($result->num_rows == 1) {
    $product = $result->fetch_assoc();
    $product_price = $product['Price'] ?? 0;
    $farmer_id = $product['FarmerID'];
} else { echo "<h1 style='color:red;'>Error</h1><p>Farmer product not found.</p>"; exit; }

// --- Handle Form Submission ---
if (isset($_POST['place_mf_order'])) {
    $order_quantity = $_POST['order_quantity'];
    $total_price = $order_quantity * $product_price;
    $manufacturer_id = $_SESSION['user_id'];

    if (empty($order_quantity) || !is_numeric($order_quantity) || $order_quantity <= 0) {
        $message = "<p class='message error'>Invalid quantity.</p>";
    } else {
        // Insert into ManufacturerFarmerOrders table
        $sql_order = "INSERT INTO ManufacturerFarmerOrders
                        (RawProductID, FarmerID, ManufacturerID, OrderQuantity, TotalPrice, Status)
                      VALUES (?, ?, ?, ?, ?, 'Pending Confirmation')";
        $stmt_order = $conn->prepare($sql_order);
        if($stmt_order){
            // "iiidd" = int(ProdID), int(FarmerID), int(MfrID), double(Qty), double(Price)
            $stmt_order->bind_param("iiidd", $product_id, $farmer_id, $manufacturer_id, $order_quantity, $total_price);
            if ($stmt_order->execute()) {
                $message = "<p class='message success'>Order placed successfully to Farmer!</p>";
                // Optional Redirect: header("Location: manufacturer_orders.php?section=outgoing"); exit;
            } else { $message = "<p class='message error'>Order failed: " . $stmt_order->error . "</p>"; }
            $stmt_order->close();
        } else { $message = "<p class='message error'>Prepare failed: " . $conn->error . "</p>"; }
    }
}
$conn->close();
?>

<title>Order Raw Materials: <?php echo htmlspecialchars($product['ProductName']); ?></title>

<style>
/* ... (Include styles for .form-card, .product-summary, inputs, buttons, messages) ... */
.form-card{background-color:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:700px;margin:1rem auto}
.form-card label{display:block;margin-bottom:.5rem;font-weight:700;color:#555}
.form-card input[type=text],.form-card input[type=number]{width:100%;padding:.75rem;margin-bottom:1.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}
.form-card input[disabled]{background-color:#e9ecef;cursor:not-allowed}
.product-summary{display:flex;align-items:center;gap:1.5rem;margin-bottom:2rem;background-color:#f9f9f9;padding:1.5rem;border-radius:5px;border:1px solid #eee}
.product-summary img{width:100px;height:100px;object-fit:cover;border-radius:5px}
.product-summary-info h3{margin:0 0 .5rem 0}
.product-summary-info p{margin:.25rem 0;color:#555}
#total-price-display{font-weight:700;font-size:1.2rem;color:#28a745}
.btn-submit{background-color:#0d6efd;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer}
.btn-cancel{display:inline-block;background-color:#6c757d;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;margin-left:10px}
.message{padding:1rem;border-radius:5px;margin-bottom:1rem;font-weight:700}
.message.error{background-color:#f8d7da;color:#721c24}
.message.success{background-color:#d4edda;color:#155724}
</style>

<div class="page-header"> <h1>Order Raw Materials</h1> </div>

<div class="form-card">
    <?php echo $message; ?>
    <div class="product-summary">
        <?php /* Image */
        if (!empty($product['ProductImage']) && file_exists($product['ProductImage'])) { echo '<img src="' . htmlspecialchars($product['ProductImage']) . '" alt="' . htmlspecialchars($product['ProductName']) . '">'; }
        else { echo '<img src="https://via.placeholder.com/100x100.png?text=No+Image" alt="No Image">'; }
        ?>
        <div>
            <h3><?php echo htmlspecialchars($product['ProductName']); ?></h3>
            <p>From Farmer: <?php echo htmlspecialchars($product['FarmerName']); ?></p>
            <p>Price per Unit: $<?php echo number_format($product_price, 2); ?></p>
        </div>
    </div>
    
    <form action="place_manufacturer_products.php?product_id=<?php echo $product_id; ?>" method="POST">
        <input type="hidden" id="product-price" value="<?php echo $product_price; ?>">
        <label for="order_quantity">Quantity to Order (Required):</label>
        <input type="number" id="order_quantity" name="order_quantity" step="0.01" min="0.01" required oninput="calculateTotal()">
        <label>Total Price:</label>
        <input type="text" id="total-price-display" value="$0.00" disabled>
        <button type="submit" name="place_mf_order" class="btn-submit">Confirm Order</button>
        <a href="browse_farmer_products.php" class="btn-cancel">Cancel</a>
    </form>
</div>

<script>
    function calculateTotal() {
        const quantity = parseFloat(document.getElementById('order_quantity').value) || 0;
        const price = parseFloat(document.getElementById('product-price').value) || 0;
        document.getElementById('total-price-display').value = '$' + (quantity * price).toFixed(2);
    }
</script>

<?php
// Close HTML
?>
</div></body></html>