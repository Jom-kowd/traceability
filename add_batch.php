<?php
// Includes header_app.php: Security check, session_start(), $conn, $role, $user_id
include_once 'header_app.php';
// Include the QR Code Library
include_once 'lib/qrlib.php'; 

// --- Security Check ---
if ($role != 'Farmer') {
    echo "<h1 style='color:red;'>Access Denied: Only Farmers can add batches.</h1></div></body></html>";
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } 
    exit;
}

$message = ''; 
$selected_product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
$qr_code_image_path = null; 
$transaction_hash = null; 

// Redirect if no valid product ID is provided
if (!$selected_product_id) {
    header("Location: manage_products.php?error=select_product");
    exit;
}

// --- Fetch Product Name for Title (AND for Batch Code Prefix) ---
$product_name = "Raw Product";
$psql = "SELECT ProductName FROM Products WHERE ProductID=? AND CreatedByUserID=?"; 
$ps=$conn->prepare($psql);
if($ps){ 
    $ps->bind_param("ii",$selected_product_id,$user_id); 
    $ps->execute(); 
    $pr=$ps->get_result(); 
    if($prow=$pr->fetch_assoc()){
        $product_name=$prow['ProductName'];
    } else { 
        echo "<h1>Error</h1><p>Product not found/owned.</p></div></body></html>"; exit;
    } 
    $ps->close();
} else { 
    error_log("Fetch product name prep failed: ".$conn->error); 
}

// --- Handle Form Submission ---
if (isset($_POST['add_batch'])) {
    $product_id = $selected_product_id;
    
    // --- Get all form data ---
    $sowing_date = trim($_POST['sowing_date']);
    $harvested_date = trim($_POST['harvested_date']);
    $crop_details = trim($_POST['crop_details']);
    $soil_details = trim($_POST['soil_details']);
    $farm_practice = trim($_POST['farm_practice']);
    
    // !! --- NEW: Get Quantity Data --- !!
    $initial_quantity = filter_input(INPUT_POST, 'initial_quantity', FILTER_VALIDATE_FLOAT);
    $quantity_unit = trim($_POST['quantity_unit']);
    // !! --- END NEW --- !!
    
    $sow_db = !empty($sowing_date) ? $sowing_date : NULL;

    // --- Auto-Generate Batch Number ---
    $prefix_words = explode(' ', $product_name); $prefix = '';
    foreach ($prefix_words as $word) { if (!empty($word)) { $prefix .= strtoupper(substr($word, 0, 1)); } }
    if (empty($prefix) || strlen($prefix) < 2) { $prefix = strtoupper(substr($product_name, 0, 3)); }
    if (strlen($prefix) > 4) { $prefix = substr($prefix, 0, 4); } 
    date_default_timezone_set('Asia/Manila'); 
    $timestamp = date('Ymd-His');
    $batch_number = $prefix . '-' . $timestamp;
    // --- End Auto-Generate ---
    
    // --- !! UPDATED: Validation --- !!
    if (empty($harvested_date)) {
        $message = "<p class='message error'>Harvest Date is required.</p>";
    } elseif (empty($initial_quantity) || $initial_quantity <= 0) {
        $message = "<p class='message error'>Initial Quantity must be a valid number greater than 0.</p>";
    } elseif (empty($quantity_unit)) {
         $message = "<p class='message error'>Quantity Unit (e.g., kg) is required.</p>";
    }
    // --- !! END UPDATED --- !!
    else {
        // --- 1. Insert Batch into Database ---
        
        // !! UPDATED: SQL Query
        $sql = "INSERT INTO ProductBatches 
                    (ProductID, UserID, BatchNumber, SowingDate, HarvestedDate, CropDetails, SoilDetails, FarmPractice, 
                     InitialQuantity, RemainingQuantity, QuantityUnit) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Set RemainingQuantity to be the same as InitialQuantity
            $remaining_quantity = $initial_quantity; 
            
            // !! UPDATED: Bind parameters (added "dds" for the new fields)
            $stmt->bind_param("iissssssdds", 
                $product_id, $user_id, $batch_number, $sow_db, $harvested_date, 
                $crop_details, $soil_details, $farm_practice,
                $initial_quantity, $remaining_quantity, $quantity_unit 
            );

            if ($stmt->execute()) {
                // --- 2. Get the ID of the batch just inserted ---
                $new_batch_id = $conn->insert_id;

                if ($new_batch_id > 0) {
                    // --- 3. Generate QR Code ---
                    $qr_data = "http://localhost/traceability/track_food.php?batch_id=" . $new_batch_id;
                    $qr_folder = "uploads/qr/";
                    $qr_filename = "batch_" . $new_batch_id . ".png";
                    $qr_filepath = $qr_folder . $qr_filename;
                    if (!is_dir($qr_folder)) { mkdir($qr_folder, 0755, true); }
                    QRcode::png($qr_data, $qr_filepath, QR_ECLEVEL_L, 4, 2);

                    // ========================================================
                    // --- 4. Generate Transaction Hash ---
                    // ========================================================
                    
                    $time_start = microtime(true);

                    // !! UPDATED: Add new fields to the hash data !!
                    $data_to_hash = [
                        'BatchID'       => (string)$new_batch_id, 
                        'ProductID'     => (string)$product_id, 
                        'UserID'        => (string)$user_id, 
                        'BatchNumber'   => $batch_number, 
                        'SowingDate'    => $sow_db, 
                        'HarvestedDate' => $harvested_date,
                        'CropDetails'   => $crop_details,
                        'SoilDetails'   => $soil_details,
                        'FarmPractice'  => $farm_practice,
                        'InitialQuantity' => (string)$initial_quantity, // Add to hash
                        'QuantityUnit'    => $quantity_unit         // Add to hash
                    ];
                    $data_string = json_encode($data_to_hash);
                    $transaction_hash = hash('sha256', $data_string);
                    
                    $time_end = microtime(true);
                    $latency_ms = ($time_end - $time_start) * 1000; 

                    // --- Log Latency ---
                    try {
                        $log_sql = "INSERT INTO performance_logs (action_name, latency_ms) VALUES ('create_batch_hash', ?)";
                        $log_stmt = $conn->prepare($log_sql);
                        if ($log_stmt) {
                            $log_stmt->bind_param("d", $latency_ms);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                    } catch (Exception $e) { error_log("Failed to log performance: " . $e->getMessage()); }

                    // ========================================================

                    // --- 5. Update the batch record with QR Path AND Hash ---
                    $sql_update = "UPDATE ProductBatches SET QRCodePath = ?, TransactionHash = ? WHERE BatchID = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    if ($stmt_update) {
                        $stmt_update->bind_param("ssi", $qr_filepath, $transaction_hash, $new_batch_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                        $qr_code_image_path = $qr_filepath; 
                        
                        $message = "<p class='message success'>Batch '".htmlspecialchars($batch_number)."' created & verified!</p>";
                    
                    } else {
                         $message = "<p class='message error'>Batch added, but failed to save QR/Hash path.</p>";
                         error_log("Add batch QR/Hash update prepare failed: ".$conn->error);
                    }
                    
                } else { $message = "<p class='message error'>Batch added, but failed to get new Batch ID.</p>"; }
            } else { 
                 if($conn->errno == 1062){$message="<p class='message error'>Error: Batch number '".htmlspecialchars($batch_number)."' already exists.</p>";}
                 else{$message="<p class='message error'>Database insert error: " . $stmt->error . "</p>"; error_log("Add batch insert failed: " . $stmt->error);}
            }
            $stmt->close();
        } else { $message = "<p class='message error'>DB prep fail: ".$conn->error."</p>"; error_log("Add batch prepare failed: " . $conn->error); }
    }
}

?>
<title>Add Batch - <?php echo htmlspecialchars($product_name); ?></title>
<style>
/* ... (styles are unchanged) ... */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem} .form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:900px;margin:1rem auto} .form-card label{display:block;margin-bottom:.5rem;font-weight:700;color:#555} .form-card input[type=text],.form-card input[type=number],.form-card input[type=date],.form-card select,.form-card textarea{width:100%;padding:.75rem;margin-bottom:1.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box} .form-card textarea{min-height:100px;resize:vertical} .form-grid-full{grid-column:1 / -1} .btn-submit{background:#28a745;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer} .message{padding:1rem;border-radius:5px;margin-bottom:1rem;font-weight:700; text-align: center;} .message.error{background:#f8d7da;color:#721c24; border: 1px solid #f5c6cb;} .message.success{background:#d4edda;color:#155724; border: 1px solid #c3e6cb;}
.qr-code-display { text-align: center; margin-top: 1rem; border-top: 1px solid #eee; padding-top: 1.5rem;}
.qr-code-display img { display: inline-block; border: 4px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 150px; height: auto; }
.hash-display { text-align: center; font-size: 0.8rem; color: #777; word-break: break-all; max-width: 400px; margin: 0.5rem auto 0 auto; background: #f9f9f9; padding: 5px; border-radius: 4px;}
.btn-cancel{display:inline-block;background:#6c757d;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;margin-left:10px}

/* !! NEW: Style for quantity fields */
.quantity-group { display: flex; gap: 1rem; }
.quantity-group > div:first-child { flex: 3; } /* Quantity field */
.quantity-group > div:last-child { flex: 1; } /* Unit field */
</style>

<div class="page-header"><h1>Add Batch Details for: <?php echo htmlspecialchars($product_name); ?></h1></div>
<div class="form-card">
    <?php echo $message; ?>
    <?php if ($qr_code_image_path): ?>
        <div class="qr-code-display">
            <p style="font-weight: bold;">Batch "<?php echo htmlspecialchars($batch_number); ?>" Created!</p>
            <img src="<?php echo htmlspecialchars($qr_code_image_path); ?>" alt="Generated QR Code">
            <p style="font-weight: bold; margin-top: 1rem;">Transaction Hash (Simulated):</p>
            <p class="hash-display"><?php echo htmlspecialchars($transaction_hash); ?></p>
        </div>
    <?php endif; ?>
    
    <form action="add_batch.php?product_id=<?php echo $selected_product_id; ?>" method="POST">
        
        <div class="form-grid">
            <div class="form-grid-full quantity-group">
                <div>
                    <label for="initial_quantity">Initial Quantity:</label> 
                    <input type="number" id="initial_quantity" name="initial_quantity" step="0.01" placeholder="e.g., 100.5" required>
                </div>
                <div>
                    <label for="quantity_unit">Unit:</label> 
                    <input type="text" id="quantity_unit" name="quantity_unit" value="kg" placeholder="e.g., kg, units" required>
                </div>
            </div>
            <div><label for="sowing_date">Sowing Date:</label> <input type="date" id="sowing_date" name="sowing_date"></div>
            <div><label for="harvested_date">Harvest Date:</label> <input type="date" id="harvested_date" name="harvested_date" required></div>
            <div class="form-grid-full"><label for="crop_details">Crop Details:</label> <textarea id="crop_details" name="crop_details"></textarea></div>
            <div class="form-grid-full"><label for="soil_details">Soil Details:</label> <textarea id="soil_details" name="soil_details"></textarea></div>
            <div class="form-grid-full"><label for="farm_practice">Farm Practice:</label> <textarea id="farm_practice" name="farm_practice"></textarea></div>
        </div>
        <button type="submit" name="add_batch" class="btn-submit">Save & Verify Batch</button>
        <a href="manage_products.php" class="btn-cancel">Back to Products</a>
    </form>
</div>
</div></body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>