<?php
// Includes header_app.php: Security check, session_start(), $conn, $role, $user_id
include_once 'header_app.php';
// Include the QR Code Library
include_once 'lib/qrlib.php'; // Make sure the path is correct and library files are present

// --- Security Check ---
if ($role != 'Farmer') {
    echo "<h1 style='color:red;'>Access Denied: Only Farmers can add batches.</h1></div></body></html>";
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } // Close connection
    exit;
}

$message = ''; // For feedback messages
$selected_product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
$qr_code_image_path = null; // Variable to hold path for display
$transaction_hash = null; // Variable to hold hash for display

// Redirect if no valid product ID is provided
if (!$selected_product_id) {
    header("Location: manage_products.php?error=select_product");
    exit;
}

// --- Handle Form Submission ---
if (isset($_POST['add_batch'])) {
    $product_id = $selected_product_id;
    $batch_number = trim($_POST['batch_number']);
    $sowing_date = trim($_POST['sowing_date']);
    $harvested_date = trim($_POST['harvested_date']);
    $crop_details = trim($_POST['crop_details']);
    $soil_details = trim($_POST['soil_details']);
    $farm_practice = trim($_POST['farm_practice']);
    
    // Ensure dates are NULL if empty
    $sow_db = !empty($sowing_date) ? $sowing_date : NULL;

    // Validation
    if (empty($batch_number) || empty($harvested_date)) {
        $message = "<p class='message error'>Batch Number and Harvest Date are required.</p>";
    } else {
        // --- 1. Insert Batch into Database (without QR path or Hash initially) ---
        $sql = "INSERT INTO ProductBatches (ProductID, UserID, BatchNumber, SowingDate, HarvestedDate, CropDetails, SoilDetails, FarmPractice) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iissssss", $product_id, $user_id, $batch_number, $sow_db, $harvested_date, $crop_details, $soil_details, $farm_practice);

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
                    // --- 4. **FIXED**: Generate Transaction Hash ---
                    // ========================================================
                    // We must cast IDs to strings to ensure consistent data types
                    $data_to_hash = [
                        'BatchID'       => (string)$new_batch_id,    // Cast to string
                        'ProductID'     => (string)$product_id,     // Cast to string
                        'UserID'        => (string)$user_id,       // Cast to string
                        'BatchNumber'   => $batch_number,
                        'SowingDate'    => $sow_db, // Already NULL or a string
                        'HarvestedDate' => $harvested_date,
                        'CropDetails'   => $crop_details,
                        'SoilDetails'   => $soil_details,
                        'FarmPractice'  => $farm_practice
                    ];
                    $data_string = json_encode($data_to_hash);
                    $transaction_hash = hash('sha256', $data_string);
                    // ========================================================

                    // --- 5. Update the batch record with QR Path AND Hash ---
                    $sql_update = "UPDATE ProductBatches SET QRCodePath = ?, TransactionHash = ? WHERE BatchID = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    if ($stmt_update) {
                        $stmt_update->bind_param("ssi", $qr_filepath, $transaction_hash, $new_batch_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                        $qr_code_image_path = $qr_filepath; // For display
                        $message = "<p class='message success'>Batch '".htmlspecialchars($batch_number)."' created & verified!</p>";
                    } else {
                         $message = "<p class='message error'>Batch added, but failed to save QR/Hash path.</p>";
                         error_log("Add batch QR/Hash update prepare failed: ".$conn->error);
                    }
                    
                } else { $message = "<p class='message error'>Batch added, but failed to get new Batch ID.</p>"; }
            } else { // Execute failed
                 if($conn->errno == 1062){$message="<p class='message error'>Error: Batch number '".htmlspecialchars($batch_number)."' already exists.</p>";}
                 else{$message="<p class='message error'>Database insert error: " . $stmt->error . "</p>"; error_log("Add batch insert failed: " . $stmt->error);}
            }
            $stmt->close();
        } else { $message = "<p class='message error'>DB prep fail: ".$conn->error."</p>"; error_log("Add batch prepare failed: " . $conn->error); }
    }
}

// --- Fetch Product Name for Title ---
$product_name = "Raw Product";
$psql = "SELECT ProductName FROM Products WHERE ProductID=? AND CreatedByUserID=?"; $ps=$conn->prepare($psql);
if($ps){ $ps->bind_param("ii",$selected_product_id,$user_id); $ps->execute(); $pr=$ps->get_result(); if($prow=$pr->fetch_assoc()){$product_name=$prow['ProductName'];} else { echo "<h1>Error</h1><p>Product not found/owned.</p></div></body></html>"; exit;} $ps->close();} else { error_log("Fetch product name prep failed: ".$conn->error); }
?>
<title>Add Batch - <?php echo htmlspecialchars($product_name); ?></title>
<style>
.form-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:900px;margin:1rem auto} .form-card label{display:block;margin-bottom:.5rem;font-weight:700;color:#555} .form-card input[type=text],.form-card input[type=number],.form-card input[type=date],.form-card select,.form-card textarea{width:100%;padding:.75rem;margin-bottom:1.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box} .form-card textarea{min-height:100px;resize:vertical} .form-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem} .form-grid-full{grid-column:1 / -1} .btn-submit{background:#28a745;color:#fff;padding:.75rem 1.5rem;border:none;border-radius:4px;font-size:1rem;font-weight:700;cursor:pointer} .message{padding:1rem;border-radius:5px;margin-bottom:1rem;font-weight:700; text-align: center;} .message.error{background:#f8d7da;color:#721c24; border: 1px solid #f5c6cb;} .message.success{background:#d4edda;color:#155724; border: 1px solid #c3e6cb;}
.qr-code-display { text-align: center; margin-top: 1rem; border-top: 1px solid #eee; padding-top: 1.5rem;}
.qr-code-display img { display: inline-block; border: 4px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 150px; height: auto; }
.hash-display { text-align: center; font-size: 0.8rem; color: #777; word-break: break-all; max-width: 400px; margin: 0.5rem auto 0 auto; background: #f9f9f9; padding: 5px; border-radius: 4px;}
</style>
<div class="page-header"><h1>Add Batch Details for: <?php echo htmlspecialchars($product_name); ?></h1></div>
<div class="form-card">
    <?php echo $message; ?>
    <?php if ($qr_code_image_path): ?>
        <div class="qr-code-display">
            <p style="font-weight: bold;">Generated QR Code:</p>
            <img src="<?php echo htmlspecialchars($qr_code_image_path); ?>" alt="Generated QR Code">
            <p style="font-weight: bold; margin-top: 1rem;">Transaction Hash (Simulated):</p>
            <p class="hash-display"><?php echo htmlspecialchars($transaction_hash); ?></p>
        </div>
    <?php endif; ?>
    <form action="add_batch.php?product_id=<?php echo $selected_product_id; ?>" method="POST">
        <div class="form-grid">
            <div><label>Product:</label> <input type="text" value="<?php echo htmlspecialchars($product_name); ?>" disabled></div>
            <div><label for="batch_number">Batch Number:</label> <input type="text" id="batch_number" name="batch_number" required></div>
            <div></div>
            <div><label for="sowing_date">Sowing Date:</label> <input type="date" id="sowing_date" name="sowing_date"></div>
            <div><label for="harvested_date">Harvest Date:</label> <input type="date" id="harvested_date" name="harvested_date" required></div>
            <div></div>
            <div class="form-grid-full"><label for="crop_details">Crop Details:</label> <textarea id="crop_details" name="crop_details"></textarea></div>
            <div class="form-grid-full"><label for="soil_details">Soil Details:</label> <textarea id="soil_details" name="soil_details"></textarea></div>
            <div class="form-grid-full"><label for="farm_practice">Farm Practice:</label> <textarea id="farm_practice" name="farm_practice"></textarea></div>
        </div>
        <button type="submit" name="add_batch" class="btn-submit">Save & Verify Batch</button>
        <a href="manage_products.php" class="btn-cancel" style="background-color:#555">Back to Products</a>
    </form>
</div>
</div></body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>