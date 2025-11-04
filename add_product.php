<?php
include_once 'header_app.php'; // Security, $conn, $role, $user_id

if ($role != 'Farmer' && $role != 'Manufacturer') {
    echo "<h1 style='color:red;'>Access Denied</h1></div></body></html>"; if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } exit;
}
$message = '';

if (isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name']);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $shelf_life = filter_input(INPUT_POST, 'shelf_life', FILTER_VALIDATE_INT);
    $product_description = trim($_POST['product_description']);
    $image_path = NULL;
    $product_type = ($role == 'Farmer' ? 'Raw' : 'Processed'); // Set type based on role

    if (empty($product_name) || $quantity === false || $price === false || $quantity < 0 || $price < 0) { $message = "<p class='message error'>Name, valid non-negative Quantity, and Price required.</p>"; }
    else {
        // Handle Image Upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/'; if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) { $message = "<p class='message error'>Failed to create upload dir.</p>"; }
            else {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif']; $max_size = 5 * 1024 * 1024; // 5 MB
                $finfo=finfo_open(FILEINFO_MIME_TYPE); $file_type=finfo_file($finfo,$_FILES['product_image']['tmp_name']); finfo_close($finfo); $file_size=$_FILES['product_image']['size'];
                if (in_array($file_type,$allowed_types) && $file_size<=$max_size) {
                    $ext=pathinfo($_FILES['product_image']['name'],PATHINFO_EXTENSION); $fname='prod_'.bin2hex(random_bytes(8)).'.'.strtolower($ext); $target=$upload_dir.$fname;
                    if(move_uploaded_file($_FILES['product_image']['tmp_name'],$target)){ $image_path=$target; } else {$message="<p class='message error'>Failed saving image.</p>";}
                } else { if(!in_array($file_type,$allowed_types)){$message="<p class='message error'>Invalid image type.</p>";} elseif($file_size>$max_size){$message="<p class='message error'>Image > 5MB.</p>";}}
            }
        } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] != UPLOAD_ERR_NO_FILE) { $message = "<p class='message error'>Upload error code: ".$_FILES['product_image']['error']."</p>"; }

        // Insert if no errors
        if (empty($message)) {
            $sql = "INSERT INTO Products (ProductName, ProductDescription, CreatedByUserID, ProductImage, Quantity, Price, ShelfLifeDays, ProductType) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $shelf_life_db = ($shelf_life === false || $shelf_life === '') ? null : $shelf_life;
                $stmt->bind_param("ssisddis", $product_name, $product_description, $user_id, $image_path, $quantity, $price, $shelf_life_db, $product_type);
                if ($stmt->execute()) { header("Location: manage_products.php?success=added"); exit; }
                else { if($conn->errno==1062){$message="<p class='message error'>Product name exists.</p>";} else{$message="<p class='message error'>DB insert error: ".$stmt->error."</p>"; error_log("Add prod insert fail: ".$stmt->error);} }
                $stmt->close();
            } else { $message = "<p class='message error'>DB prepare error: ".$conn->error."</p>"; error_log("Add prod prep fail: ".$conn->error); }
        }
    }
}
?>
<title>Add New Product - Organic Traceability</title>
<style> /* Form Styles */
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:900px;margin:1rem auto} .form-card label{display:block;margin-bottom:.5rem;font-weight:700;color:#555} .form-card input[type=text],.form-card input[type=number],.form-card input[type=file],.form-card textarea{width:100%;padding:.75rem;margin-bottom:1.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box} .form-card textarea{min-height:120px;resize:vertical} .form-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem} .btn-submit{background:#28a745;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer} .btn-cancel{display:inline-block;background:#6c757d;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;margin-left:10px} .message{padding:1rem;border-radius:5px;margin-bottom:1rem;font-weight:700; text-align: center;} .message.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb} .message.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
</style>
<div class="page-header"><h1>Add New <?php echo ($role == 'Farmer' ? 'Raw Material' : 'Processed Product'); ?></h1></div>
<div class="form-card">
    <?php echo $message; ?>
    <form action="add_product.php" method="POST" enctype="multipart/form-data">
        <label for="product_name">Product Name:</label>
        <input type="text" id="product_name" name="product_name" required value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>">
        <div class="form-grid">
            <div><label for="quantity">Default Unit Qty:</label><input type="number" id="quantity" name="quantity" step="any" placeholder="e.g., 1" required value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>"></div>
            <div><label for="price">Price (per unit):</label><input type="number" id="price" name="price" step="0.01" placeholder="0.00" required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"></div>
            <div><label for="shelf_life">Shelf Life (days):</label><input type="number" id="shelf_life" name="shelf_life" step="1" placeholder="e.g., 7" value="<?php echo htmlspecialchars($_POST['shelf_life'] ?? ''); ?>"></div>
        </div>
        <label for="product_description">Description:</label>
        <textarea id="product_description" name="product_description"><?php echo htmlspecialchars($_POST['product_description'] ?? ''); ?></textarea>
        <label for="product_image">Photo (optional, max 5MB):</label>
        <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif">
        <button type="submit" name="add_product" class="btn-submit">Save Product</button>
        <a href="manage_products.php" class="btn-cancel">Cancel</a>
    </form>
</div>
</div></body></html>
<?php if (isset($conn)) $conn->close(); ?>