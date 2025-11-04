<?php
// Use our header. This checks for login and shows the nav bar.
include 'header_app.php'; 
// We also need our QR Code Library
include 'lib/qrlib.php'; 
// $conn and $role are now available

// --- Security Check ---
if ($role == 'Consumer') {
    echo "<h1 style='color:red;'>Access Denied</h1><p>You do not have permission to view this page.</p>";
    echo "</div></body></html>"; 
    exit;
}

$message = ''; // To store success or error messages

// --- Handle Form Submission (Adding a New Batch) ---
if (isset($_POST['add_batch'])) {
    
    // --- Get All Form Data ---
    $product_id = $_POST['product_id'];
    $batch_number = $_POST['batch_number'];
    $quantity = $_POST['quantity'];
    $unit = $_POST['unit'];
    $price = $_POST['price'];
    $shelf_life = $_POST['shelf_life'];
    $sowing_date = $_POST['sowing_date'];
    $harvested_date = $_POST['harvested_date'];
    $crop_details = $_POST['crop_details'];
    $soil_details = $_POST['soil_details'];
    $farm_practice = $_POST['farm_practice'];
    $current_user_id = $_SESSION['user_id'];
    
    // --- Validation (Simple) ---
    if (empty($product_id) || empty($batch_number) || empty($quantity) || empty($harvested_date)) {
        $message = "<p class='message error'>Error: Product, Batch Number, Quantity, and Harvested Date are required.</p>";
    } else {
        
        // --- 1. Insert Batch into Database (without QR code) ---
        $sql = "INSERT INTO EntryProductList 
                    (ProductID, UserID, BatchNumber, Quantity, Unit, Price, ShelfLifeDays, SowingDate, HarvestedDate, CropDetails, SoilDetails, FarmPractice, EntryDate)
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        // "iisdsdisssss" = integer, integer, string, double, string, double, integer, string, string, string, string, string
        $stmt->bind_param("iisdsdisssss", 
            $product_id, $current_user_id, $batch_number, $quantity, $unit, $price, $shelf_life, 
            $sowing_date, $harvested_date, $crop_details, $soil_details, $farm_practice
        );

        if ($stmt->execute()) {
            // --- 2. Get the new ID of the batch we just inserted ---
            $new_entry_id = $conn->insert_id;
            
            // --- 3. Generate the QR Code ---
            $qr_data = "http://localhost/traceability/track_food.php?batch_id=" . $new_entry_id;
            $qr_path = "uploads/qr/batch_" . $new_entry_id . ".png";
            
            QRcode::png($qr_data, $qr_path, 'L', 4, 2);
            
            // --- 4. Update the batch record with the path to its QR code ---
            $sql_update = "UPDATE EntryProductList SET QRCodePath = ? WHERE EntryID = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $qr_path, $new_entry_id);
            $stmt_update->execute();
            
            $message = "<p class='message success'>Batch added successfully! Here is the QR Code:</p>";
            $message .= "<img src='" . htmlspecialchars($qr_path) . "' alt='QR Code for Batch " . htmlspecialchars($new_entry_id) . "'>";
            
        } else {
            $message = "<p class='message error'>Error: Could not add batch. " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

// --- Get the ProductID from the URL (from the blue button) ---
$selected_product_id = '';
if (isset($_GET['product_id'])) {
    $selected_product_id = intval($_GET['product_id']);
}
?>

<title>Entry Product List (Add Batch) - Organic Traceability</title>

<style>
/* ... (all the styles from add_product.php are needed) ... */
.form-card {
    background-color: #fff;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    max-width: 900px;
    margin: 1rem auto;
}
.form-card label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
    color: #555;
}
.form-card input[type="text"],
.form-card input[type="number"],
.form-card input[type="date"],
.form-card select,
.form-card textarea {
    width: 100%;
    padding: 0.75rem;
    margin-bottom: 1.5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box; /* Important */
}
.form-card textarea {
    min-height: 100px;
    resize: vertical;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr; /* Three columns */
    gap: 1.5rem;
}
.form-grid-full {
    grid-column: 1 / -1; /* Span all columns */
}
.btn-submit {
    background-color: #28a745;
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
}
.btn-submit:hover {
    background-color: #218838;
}
.message {
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    font-weight: bold;
}
.message.error { background-color: #f8d7da; color: #721c24; }
.message.success { background-color: #d4edda; color: #155724; }
.message img {
    display: block;
    margin: 1rem auto;
    border: 5px solid #fff;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
/* Styles for the existing batch list */
.list-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 2rem;
}
.list-table th, .list-table td {
    border: 1px solid #ddd;
    padding: 12px;
    text-align: left;
}
.list-table th {
    background-color: #f2f2f2;
}
</style>

<div class="page-header">
    <h1>Add Produce Details (New Batch)</h1>
</div>

<div class="form-card">
    
    <?php echo $message; ?>

    <form action="entry_list.php" method="POST">
        <div class="form-grid">
            
            <div>
                <label for="product_id">Master Product (Required):</label>
                <select id="product_id" name="product_id" required>
                    <option value="">-- Select Product --</option>
                    <?php
                    // Fetch all products created by this user to populate the dropdown
                    $prod_sql = "SELECT ProductID, ProductName FROM Products WHERE CreatedByUserID = ?";
                    $prod_stmt = $conn->prepare($prod_sql);
                    $prod_stmt->bind_param("i", $_SESSION['user_id']);
                    $prod_stmt->execute();
                    $prod_result = $prod_stmt->get_result();
                    while ($prod = $prod_result->fetch_assoc()) {
                        // Check if this is the product we clicked on from the previous page
                        $is_selected = ($prod['ProductID'] == $selected_product_id) ? 'selected' : '';
                        echo "<option value='" . $prod['ProductID'] . "' " . $is_selected . ">" . htmlspecialchars($prod['ProductName']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <label for="batch_number">Batch Number (Required):</label>
                <input type="text" id="batch_number" name="batch_number" required>
            </div>

            <div>
                <label for="quantity">Quantity (Required):</label>
                <input type="number" id="quantity" name="quantity" step="0.01" required>
            </div>
            
            <div>
                <label for="unit">Unit (e.g., kg, L, units):</label>
                <input type="text" id="unit" name="unit" placeholder="kg">
            </div>

            <div>
                <label for="price">Price (per Unit):</label>
                <input type="number" id="price" name="price" step="0.01" placeholder="0.00">
            </div>

            <div>
                <label for="shelf_life">Shelf Life (in days):</label>
                <input type="number" id="shelf_life" name="shelf_life" step="1" placeholder="7">
            </div>
            
            <div>
                <label for="sowing_date">Sowing Date:</label>
                <input type="date" id="sowing_date" name="sowing_date">
            </div>

            <div>
                <label for="harvested_date">Harvested Date (Required):</label>
                <input type="date" id="harvested_date" name="harvested_date" required>
            </div>
            
            <div>
                </div>
            
            <div class="form-grid-full">
                <label for="crop_details">Crop Details:</label>
                <textarea id="crop_details" name="crop_details" placeholder="e.g., Variety, amendments used..."></textarea>
            </div>
            
            <div class="form-grid-full">
                <label for="soil_details">Soil Details:</label>
                <textarea id="soil_details" name="soil_details" placeholder="e.g., Soil type, last test results..."></textarea>
            </div>

            <div class="form-grid-full">
                <label for="farm_practice">Farm Practice:</label>
                <textarea id="farm_practice" name="farm_practice" placeholder="e.g., No-till, organic pesticides used..."></textarea>
            </div>

        </div> <button type="submit" name="add_batch" class="btn-submit">Save Batch & Generate QR Code</button>
    </form>
</div>


<div class="page-header" style="margin-top: 3rem;">
    <h1>Existing Batches (Entry List)</h1>
</div>

<div class="form-card">
    
    <table class="list-table">
        <thead>
            <tr>
                <th>Batch #</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Harvested On</th>
                <th>QR Code</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Fetch all existing batches for this user
            $list_sql = "SELECT e.*, p.ProductName 
                         FROM EntryProductList e
                         JOIN Products p ON e.ProductID = p.ProductID
                         WHERE e.UserID = ?
                         ORDER BY e.HarvestedDate DESC";
            
            $list_stmt = $conn->prepare($list_sql);
            $list_stmt->bind_param("i", $_SESSION['user_id']);
            $list_stmt->execute();
            $list_result = $list_stmt->get_result();
            
            if ($list_result->num_rows > 0) {
                while ($row = $list_result->fetch_assoc()) {
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['BatchNumber']); ?></td>
                    <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                    
                    <td><?php echo htmlspecialchars($row['Quantity']) . ' ' . htmlspecialchars($row['Unit']); ?></td>
                    
                    <td><?php echo htmlspecialchars($row['HarvestedDate']); ?></td>
                    <td>
                        <?php if (!empty($row['QRCodePath']) && file_exists($row['QRCodePath'])): ?>
                            <img src="<?php echo htmlspecialchars($row['QRCodePath']); ?>" alt="QR Code" style="width: 75px; height: 75px;">
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="view_chain.php?batch_id=<?php echo $row['EntryID']; ?>">View Chain</a>
                    </td>
                </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='6'>No batches found. Add one using the form above.</td></tr>";
            }
            $conn->close();
            ?>
        </tbody>
    </table>
</div>


<?php 
// Close the HTML tags opened by header_app.php
?>
</div> </body>
</html>