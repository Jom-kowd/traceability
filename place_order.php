<?php
// Use our header. This checks for login and shows the nav bar.
include 'header_app.php';
// $conn and $role are now available

// --- Security Check ---
// Only roles that can place orders should access this
if ($role != 'Manufacturer' && $role != 'Distributor' && $role != 'Retailer') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to place orders.</p>";
    echo "</div></body></html>";
    exit;
}

$message = ''; // To store success or error messages
$product_id = 0;

// --- Get the Product ID from the URL ---
if (isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
}

// --- Fetch Product Data (including Farmer ID) ---
$sql_fetch = "SELECT
                p.*,
                u.Username as FarmerName,
                u.UserID as FarmerID
              FROM Products p
              JOIN Users u ON p.CreatedByUserID = u.UserID
              WHERE p.ProductID = ?";

$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $product_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();

if ($result->num_rows == 1) {
    $product = $result->fetch_assoc();
    $product_price = $product['Price'] ?? 0; // Get price for calculation
    $farmer_id = $product['FarmerID'];
} else {
    echo "<h1 style='color:red;'>Error</h1><p>Product not found.</p>";
    echo "</div></body></html>";
    exit;
}

// --- Handle Form Submission (Placing the Order) ---
if (isset($_POST['place_order'])) {

    $order_quantity = $_POST['order_quantity'];
    // Recalculate total price on server-side for security
    $total_price = $order_quantity * $product_price;
    $manufacturer_id = $_SESSION['user_id']; // The logged-in user is placing the order

    // --- Validation ---
    if (empty($order_quantity) || !is_numeric($order_quantity) || $order_quantity <= 0) {
        $message = "<p class='message error'>Please enter a valid quantity greater than 0.</p>";
    } else {

        // --- Insert into ProductOrders table ---
        $sql_order = "INSERT INTO ProductOrders
                        (ProductID, FarmerID, ManufacturerID, OrderQuantity, TotalPrice, Status)
                      VALUES
                        (?, ?, ?, ?, ?, 'Pending')";

        $stmt_order = $conn->prepare($sql_order);
        // "iiidd" = integer, integer, integer, double, double
        $stmt_order->bind_param("iiidd",
            $product_id,
            $farmer_id,
            $manufacturer_id,
            $order_quantity,
            $total_price
        );

        if ($stmt_order->execute()) {
            $message = "<p class='message success'>Order placed successfully!</p>";
            // Optional: Redirect to an order confirmation page or the manufacturer's order list
            // header("Location: manufacturer_orders.php?success=order_placed");
            // exit;
        } else {
            $message = "<p class='message error'>Error placing order: " . $stmt_order->error . "</p>";
        }
        $stmt_order->close();
    }
}
$conn->close();
?>

<title>Place Order: <?php echo htmlspecialchars($product['ProductName']); ?></title>

<style>
/* ... (include form-card, message styles from previous steps) ... */
.form-card {
    background-color: #fff;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    max-width: 700px; /* Smaller card for this form */
    margin: 1rem auto;
}
.form-card label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
    color: #555;
}
.form-card input[type="text"],
.form-card input[type="number"] {
    width: 100%;
    padding: 0.75rem;
    margin-bottom: 1.5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}
.form-card input[disabled] {
    background-color: #e9ecef;
    cursor: not-allowed;
}
.product-summary {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    background-color: #f9f9f9;
    padding: 1.5rem;
    border-radius: 5px;
    border: 1px solid #eee;
}
.product-summary img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 5px;
}
.product-summary-info h3 {
    margin: 0 0 0.5rem 0;
}
.product-summary-info p {
    margin: 0.25rem 0;
    color: #555;
}
#total-price {
    font-weight: bold;
    font-size: 1.2rem;
    color: #28a745;
}
.btn-submit {
    background-color: #0d6efd; /* Blue */
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
}
.btn-cancel {
    display: inline-block;
    background-color: #6c757d;
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    margin-left: 10px;
}
.message {
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    font-weight: bold;
}
.message.error { background-color: #f8d7da; color: #721c24; }
.message.success { background-color: #d4edda; color: #155724; }
</style>

<div class="page-header">
    <h1>Place Order</h1>
</div>

<div class="form-card">

    <?php echo $message; ?>

    <div class="product-summary">
        <?php
        if (!empty($product['ProductImage']) && file_exists($product['ProductImage'])) {
            echo '<img src="' . htmlspecialchars($product['ProductImage']) . '" alt="' . htmlspecialchars($product['ProductName']) . '">';
        } else {
            echo '<img src="https://via.placeholder.com/100x100.png?text=No+Image" alt="No Image">';
        }
        ?>
        <div class="product-summary-info">
            <h3><?php echo htmlspecialchars($product['ProductName']); ?></h3>
            <p>From Farmer: <?php echo htmlspecialchars($product['FarmerName']); ?></p>
            <p>Price per Unit: $<?php echo number_format($product_price, 2); ?></p>
        </div>
    </div>

    <form action="place_order.php?product_id=<?php echo $product_id; ?>" method="POST">
        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
        <input type="hidden" name="farmer_id" value="<?php echo $farmer_id; ?>">
        <input type="hidden" id="product-price" value="<?php echo $product_price; ?>">

        <label for="order_quantity">Quantity to Order (Required):</label>
        <input type="number" id="order_quantity" name="order_quantity" step="0.01" min="0.01" required oninput="calculateTotal()">

        <label>Total Price:</label>
        <input type="text" id="total-price-display" value="$0.00" disabled>
        <input type="hidden" id="total_price" name="total_price" value="0">

        <button type="submit" name="place_order" class="btn-submit">Confirm Order</button>
        <a href="browse_products.php" class="btn-cancel">Cancel</a>
    </form>
</div>

<script>
    function calculateTotal() {
        const quantityInput = document.getElementById('order_quantity');
        const pricePerUnit = parseFloat(document.getElementById('product-price').value);
        const totalPriceDisplay = document.getElementById('total-price-display');
        const hiddenTotalPrice = document.getElementById('total_price');

        let quantity = parseFloat(quantityInput.value);
        if (isNaN(quantity) || quantity < 0) {
            quantity = 0;
        }

        const total = quantity * pricePerUnit;

        // Update the display field (formatted)
        totalPriceDisplay.value = '$' + total.toFixed(2);
        // Update the hidden input field (raw value)
        hiddenTotalPrice.value = total.toFixed(2);
    }

    // Calculate on page load in case of errors/reloads
    // calculateTotal();
</script>

<?php
// Close the HTML tags opened by header_app.php
?>
</div> </body>
</html>