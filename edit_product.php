<?php
include_once 'header_app.php'; // Security, $conn, $role, $user_id
if ($role != 'Farmer' && $role != 'Manufacturer') { echo "<h1>Access Denied</h1></div></body></html>"; exit; }
$message = ''; $product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) { echo "<h1>Invalid ID</h1></div></body></html>"; exit; }

// Handle Update
if (isset($_POST['update_product'])) {
    $product_name=trim($_POST['product_name']); $quantity=filter_input(INPUT_POST,'quantity',FILTER_VALIDATE_FLOAT); $price=filter_input(INPUT_POST,'price',FILTER_VALIDATE_FLOAT); $shelf_life=filter_input(INPUT_POST,'shelf_life',FILTER_VALIDATE_INT); $product_description=trim($_POST['product_description']); $old_image_path=$_POST['old_image_path']??''; $image_path=$old_image_path;

    if(empty($product_name)||$quantity===false||$price===false||$quantity<0||$price<0){$message="<p class='message error'>Name, valid Qty, Price required.</p>";}
    else {
        if(isset($_FILES['product_image'])&&$_FILES['product_image']['error']==UPLOAD_ERR_OK){ /* Handle new image */
            $udir='uploads/'; $atypes=['image/jpeg','image/png','image/gif']; $msize=5*1024*1024; $finfo=finfo_open(FILEINFO_MIME_TYPE); $ftype=finfo_file($finfo,$_FILES['product_image']['tmp_name']); finfo_close($finfo); $fsize=$_FILES['product_image']['size'];
            if(in_array($ftype,$atypes)&&$fsize<=$msize){$ext=pathinfo($_FILES['product_image']['name'],PATHINFO_EXTENSION); $fn='prod_'.bin2hex(random_bytes(8)).'.'.strtolower($ext); $target=$udir.$fn; if(move_uploaded_file($_FILES['product_image']['tmp_name'],$target)){$image_path=$target; if(!empty($old_image_path)&&file_exists($old_image_path)&&is_writable($old_image_path)){unlink($old_image_path);}} else{$message="<p class='message error'>Save new image failed.</p>";}} else{if(!in_array($ftype,$atypes)){$message="<p class='message error'>Invalid type.</p>";} elseif($fsize>$msize){$message="<p class='message error'>Image > 5MB.</p>";}}
        } elseif(isset($_FILES['product_image'])&&$_FILES['product_image']['error']!=UPLOAD_ERR_NO_FILE){$message="<p class='message error'>Upload error code: ".$_FILES['product_image']['error']."</p>";}
        if(empty($message)){ // Update DB
            $sql="UPDATE Products SET ProductName=?, ProductDescription=?, ProductImage=?, Quantity=?, Price=?, ShelfLifeDays=? WHERE ProductID=? AND CreatedByUserID=?"; $stmt=$conn->prepare($sql);
            if($stmt){ $shelf_db=($shelf_life===false||$shelf_life==='')?null:$shelf_life; $stmt->bind_param("sssddiii",$product_name,$product_description,$image_path,$quantity,$price,$shelf_db,$product_id,$user_id);
                if($stmt->execute()){header("Location: manage_products.php?success=updated");exit;} else{$message="<p class='message error'>Update failed: ".$stmt->error."</p>";error_log("Edit product fail: ".$stmt->error);} $stmt->close();
            } else {$message="<p class='message error'>DB prep fail: ".$conn->error."</p>";error_log("Edit prep fail: ".$conn->error);}
        }
    }
}

// Fetch existing data
$sql_f="SELECT * FROM Products WHERE ProductID=? AND CreatedByUserID=?"; $stmt_f=$conn->prepare($sql_f); $product=null;
if($stmt_f){ $stmt_f->bind_param("ii",$product_id,$user_id); $stmt_f->execute(); $res=$stmt_f->get_result(); if($res->num_rows==1){$product=$res->fetch_assoc();} $stmt_f->close(); } else {error_log("Fetch edit prep fail: ".$conn->error);}
if(!$product){ echo "<h1>Error</h1><p>Product not found or access denied.</p></div></body></html>"; exit; }
?>
<title>Edit Product: <?php echo htmlspecialchars($product['ProductName']); ?></title>
<style> /* Styles */
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:900px;margin:1rem auto} .form-card label{display:block;margin-bottom:.5rem;font-weight:700;color:#555} .form-card input[type=text],.form-card input[type=number],.form-card input[type=file],.form-card textarea{width:100%;padding:.75rem;margin-bottom:1.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box} .form-card textarea{min-height:120px;resize:vertical} .form-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem} .btn-submit{background:#28a745;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer} .btn-cancel{display:inline-block;background:#6c757d;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;margin-left:10px} .message{padding:1rem;border-radius:5px;margin-bottom:1rem;font-weight:700} .message.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb} .current-image{font-weight:700;margin-bottom:1rem} .current-image img{width:150px;height:150px;object-fit:cover;border:2px solid #eee;border-radius:5px;margin-top:5px}
</style>
<div class="page-header"><h1>Edit: <?php echo htmlspecialchars($product['ProductName']); ?></h1></div>
<div class="form-card">
    <?php echo $message; ?>
    <form action="edit_product.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data">
        <label for="product_name">Name:</label><input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product['ProductName']); ?>" required>
        <div class="form-grid">
            <div><label for="quantity">Qty:</label><input type="number" id="quantity" name="quantity" step="any" value="<?php echo htmlspecialchars($product['Quantity']??0);?>" required></div>
            <div><label for="price">Price:</label><input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($product['Price']??0.00);?>" required></div>
            <div><label for="shelf_life">Shelf Life:</label><input type="number" id="shelf_life" name="shelf_life" step="1" value="<?php echo htmlspecialchars($product['ShelfLifeDays']??'');?>"></div>
        </div>
        <label for="product_description">Description:</label><textarea id="product_description" name="product_description"><?php echo htmlspecialchars($product['ProductDescription']);?></textarea>
        <label for="product_image">Change Photo:</label><input type="file" id="product_image" name="product_image" accept="image/*">
        <input type="hidden" name="old_image_path" value="<?php echo htmlspecialchars($product['ProductImage']??'');?>">
        <?php if(!empty($product['ProductImage'])&&file_exists($product['ProductImage'])):?><div class="current-image">Current:<br><img src="<?php echo htmlspecialchars($product['ProductImage']);?>" alt="Current"></div><?php endif;?>
        <button type="submit" name="update_product" class="btn-submit">Update</button>
        <a href="manage_products.php" class="btn-cancel">Cancel</a>
    </form>
</div>
</div></body></html>
<?php if(isset($conn))$conn->close();?>