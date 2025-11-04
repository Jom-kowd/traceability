<?php
include_once 'header_app.php'; // Security, $conn, $role, $user_id

if ($role != 'Farmer' && $role != 'Manufacturer') { header("Location: manage_products.php?error=no_permission"); exit; }
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) { header("Location: manage_products.php?error=no_id"); exit; }

$error_msg = '';

// --- SAFETY CHECKS ---
try {
    // Check linked records before attempting delete
    $tables_to_check = [
        'ProductBatches' => 'ProductID',
        'ManufacturerFarmerOrders' => 'RawProductID',
        'Orders' => 'ProductID'
    ];
    foreach ($tables_to_check as $table => $column) {
        $sql_check = "SELECT COUNT(*) as count FROM `$table` WHERE `$column` = ?";
        $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) {
            $stmt_check->bind_param("i", $product_id); $stmt_check->execute();
            $row = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();
            if ($row['count'] > 0) {
                // Determine error code based on table
                $err_code = 'linked_data';
                if($table == 'ProductBatches') $err_code = 'batches_exist';
                if($table == 'ManufacturerFarmerOrders') $err_code = 'orders_exist_mf';
                if($table == 'Orders') $err_code = 'orders_exist_ord';
                 header("Location: manage_products.php?error=".$err_code); exit;
            }
        } else { throw new Exception("DB check prepare fail: ".$conn->error); }
    }

    // --- If safe, proceed with deletion ---
    // 1. Get image path
    $sql_img = "SELECT ProductImage FROM Products WHERE ProductID=? AND CreatedByUserID=?"; $stmt_img=$conn->prepare($sql_img); $img_path=null;
    if($stmt_img){ $stmt_img->bind_param("ii",$product_id,$user_id); $stmt_img->execute(); $res_img=$stmt_img->get_result(); if($prod=$res_img->fetch_assoc()){$img_path=$prod['ProductImage'];} $stmt_img->close(); } else {throw new Exception("Img fetch prep fail.");}

    // 2. Delete product record (check ownership again)
    $sql_del="DELETE FROM Products WHERE ProductID=? AND CreatedByUserID=?"; $stmt_del=$conn->prepare($sql_del);
    if($stmt_del){ $stmt_del->bind_param("ii",$product_id,$user_id);
        if($stmt_del->execute()){ if($stmt_del->affected_rows>0){
            // 3. Delete image file
            if(!empty($img_path)&&file_exists($img_path)&&is_writable($img_path)){unlink($img_path);}
            header("Location: manage_products.php?success=deleted"); exit;
        } else { throw new Exception("Product not found or not owned.");}} else { throw new Exception("DB delete exec fail: ".$stmt_del->error); } $stmt_del->close();
    } else { throw new Exception("DB delete prep fail: ".$conn->error); }

} catch (Exception $e) {
    error_log("Delete product fail ID $product_id: ".$e->getMessage());
    $err_code = 'delete_failed'; // Default
    if(strpos($e->getMessage(),'owned')!==false){$err_code='not_found';} elseif(strpos($e->getMessage(),'constraint fails')!==false){$err_code='linked_data_unexpected';}
    header("Location: manage_products.php?error=".$err_code); exit;
} finally { if (isset($conn)) $conn->close(); }
?>