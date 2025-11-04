<?php
// Include DB connection
include_once 'db.php';
// Start session to check login status for nav bar
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- (PHP Logic for fetching data) ---
$search_order_id = null; $search_batch_id = null; $batch_details = null; $order_details = null; $chain_history = [];
$error_message = ''; $is_origin_verified = false; $is_order_verified = false;

// --- FIX: Initialize $show_survey to false at the top ---
$show_survey = false; 

if (isset($_GET['order_id'])) { $search_order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT); }
elseif (isset($_GET['batch_id'])) { $search_batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT); }
if (isset($_POST['search_order'])) {
    $search_term = trim($_POST['search_term']);
    if (!empty($search_term) && is_numeric($search_term)) { $search_order_id = intval($search_term); $search_batch_id = null; }
    elseif (!empty($search_term)) { $error_message = "Please enter a valid numeric Order ID."; }
    else { $error_message = "Please enter an Order ID to search."; }
}
if ($search_order_id && $search_order_id > 0) {
    $sql_order = "SELECT o.*, s.Username as SellerName, s.Address as SellerAddress, b.Username as BuyerName, b.Address as BuyerAddress, d.Username as DistributorName, d.Address as DistributorAddress, p.ProductName as OrderedProductName, p.ProductImage as OrderedProductImage, p.Quantity as ProductUnitQty, p.Price as ProductUnitPrice, p.ShelfLifeDays as ProductShelfLife, p.ProductDescription, o.TransactionHash as OrderHash FROM Orders o JOIN Users s ON o.SellerID = s.UserID JOIN Users b ON o.BuyerID = b.UserID LEFT JOIN Users d ON o.AssignedDistributorID = d.UserID JOIN Products p ON o.ProductID = p.ProductID WHERE o.OrderID = ?";
    $stmt_order = $conn->prepare($sql_order);
    if ($stmt_order) { $stmt_order->bind_param("i", $search_order_id); $stmt_order->execute(); $result_order = $stmt_order->get_result();
        if ($result_order->num_rows == 1) { $order_details = $result_order->fetch_assoc(); $search_batch_id = $order_details['SourceBatchID'];
            $stored_order_hash = $order_details['OrderHash'];
            // --- FIX from previous error ---
            $order_data_to_rehash = ['ProductID' => (string)$order_details['ProductID'], 'SellerID' => (string)$order_details['SellerID'], 'BuyerID' => (string)$order_details['BuyerID'], 'OrderQuantity' => number_format($order_details['OrderQuantity'], 2, '.', ''), 'TotalPrice' => number_format($order_details['TotalPrice'], 2, '.', '')];
            $recalculated_order_hash = hash('sha256', json_encode($order_data_to_rehash));
            // --- End Fix ---
            if ($stored_order_hash !== null && $stored_order_hash === $recalculated_order_hash) { $is_order_verified = true; }
        } else { $error_message = "Order ID not found."; } $stmt_order->close();
    } else { $error_message = "Error searching order."; error_log("Track food order prep fail: ".$conn->error); }
} elseif ($search_batch_id && $search_batch_id > 0) {
     $sql_order_from_batch = "SELECT o.*, s.Username as SellerName, s.Address as SellerAddress, b.Username as BuyerName, b.Address as BuyerAddress, d.Username as DistributorName, d.Address as DistributorAddress, p.ProductName as OrderedProductName, p.ProductImage as OrderedProductImage, p.Quantity as ProductUnitQty, p.Price as ProductUnitPrice, p.ShelfLifeDays as ProductShelfLife, p.ProductDescription, o.TransactionHash as OrderHash FROM Orders o JOIN Users s ON o.SellerID = s.UserID JOIN Users b ON o.BuyerID = b.UserID LEFT JOIN Users d ON o.AssignedDistributorID = d.UserID JOIN Products p ON o.ProductID = p.ProductID WHERE o.SourceBatchID = ? ORDER BY o.OrderDate DESC LIMIT 1";
     $stmt_order_b = $conn->prepare($sql_order_from_batch);
     if ($stmt_order_b) { $stmt_order_b->bind_param("i", $search_batch_id); $stmt_order_b->execute(); $result_order_b = $stmt_order_b->get_result(); if ($result_order_b->num_rows >= 1) { $order_details = $result_order_b->fetch_assoc(); $search_order_id = $order_details['OrderID'];
        $stored_order_hash = $order_details['OrderHash'];
        $order_data_to_rehash = ['ProductID' => (string)$order_details['ProductID'], 'SellerID' => (string)$order_details['SellerID'], 'BuyerID' => (string)$order_details['BuyerID'], 'OrderQuantity' => number_format($order_details['OrderQuantity'], 2, '.', ''), 'TotalPrice' => number_format($order_details['TotalPrice'], 2, '.', '')];
        $recalculated_order_hash = hash('sha256', json_encode($order_data_to_rehash));
        if ($stored_order_hash !== null && $stored_order_hash === $recalculated_order_hash) { $is_order_verified = true; }
     } $stmt_order_b->close(); }
     else { $error_message = "Error searching order from batch."; error_log("Track food order(batch) prep fail: ".$conn->error);}
}
if ($search_batch_id && $search_batch_id > 0) {
    $sql_batch = "SELECT pb.*, p.ProductName as RawProductName, p.ProductImage as RawProductImage, u.Username as FarmerName, u.Address as FarmerAddress, pb.TransactionHash FROM ProductBatches pb JOIN Products p ON pb.ProductID = p.ProductID JOIN Users u ON pb.UserID = u.UserID WHERE pb.BatchID = ?";
    $stmt_batch = $conn->prepare($sql_batch);
    if ($stmt_batch) { $stmt_batch->bind_param("i", $search_batch_id); $stmt_batch->execute(); $result_batch = $stmt_batch->get_result();
        if ($result_batch->num_rows == 1) { $batch_details = $result_batch->fetch_assoc();
            $stored_hash = $batch_details['TransactionHash']; $data_to_rehash = ['BatchID' => (string)$batch_details['BatchID'], 'ProductID' => (string)$batch_details['ProductID'], 'UserID' => (string)$batch_details['UserID'], 'BatchNumber' => $batch_details['BatchNumber'], 'SowingDate' => $batch_details['SowingDate'], 'HarvestedDate' => $batch_details['HarvestedDate'], 'CropDetails' => $batch_details['CropDetails'], 'SoilDetails' => $batch_details['SoilDetails'], 'FarmPractice' => $batch_details['FarmPractice']];
            $recalculated_hash = hash('sha256', json_encode($data_to_rehash));
            $is_origin_verified = ($stored_hash !== null && $stored_hash === $recalculated_hash);
            
            // --- Check if this batch was already rated ---
            if (!isset($_COOKIE['rated_batch_' . $search_batch_id])) {
                $show_survey = true;
            }

        } else { if(empty($error_message)) $error_message = "Origin batch details (ID: $search_batch_id) not found."; $batch_details=null; } $stmt_batch->close();
    } else { $error_message = "Error preparing batch query."; error_log("Track food batch prep fail: ".$conn->error); $batch_details=null;}
}
elseif ($search_order_id && !$search_batch_id && $order_details) { if (empty($error_message)) $error_message = "Order found (#$search_order_id), traceability link pending."; }
$chain_history = [];
if ($batch_details) { $chain_history[]=['step'=>'Farmer','actor'=>$batch_details['FarmerName'],'location'=>$batch_details['FarmerAddress'],'action'=>'Harvested','date'=>$batch_details['HarvestedDate'],'status'=>'Completed', 'icon'=>'fa-tractor'];
    if($order_details && $order_details['SourceBatchID'] == $batch_details['BatchID']){
        $chain_history[]=['step'=>'Manufacturer','actor'=>$order_details['SellerName'],'location'=>$order_details['SellerAddress'],'action'=>'Processed','date'=>$order_details['OrderDate'],'status'=>$order_details['Status'], 'icon'=>'fa-industry'];
        if($order_details['AssignedDistributorID'] && !in_array($order_details['Status'], ['Pending Confirmation', 'Processing'])){ $dist_action = ($order_details['Status'] == 'In Transit' || $order_details['Status'] == 'Delivered') ? 'In Transit' : 'Assigned'; $dist_date = $order_details['PickupDate'] ?? $order_details['OrderDate']; $chain_history[]=['step'=>'Distributor','actor'=>$order_details['DistributorName']??'Distributor','location'=>$order_details['DistributorAddress'],'action'=>$dist_action,'date'=>$dist_date,'status'=>$order_details['Status'], 'icon'=>'fa-truck-fast']; }
        if($order_details['Status']=='Delivered'){ $del_date = $order_details['DeliveryDate'] ?? $order_details['OrderDate']; $chain_history[]=['step'=>'Retailer/Customer','actor'=>$order_details['BuyerName'],'location'=>$order_details['BuyerAddress'],'action'=>'Delivered','date'=>$del_date,'status'=>'Delivered', 'icon'=>'fa-store']; }
    }
} elseif ($order_details) { $chain_history[]=['step'=>'Order Placed','actor'=>$order_details['BuyerName'],'location'=>$order_details['BuyerAddress'],'action'=>'Order #'.$order_details['OrderID'],'date'=>$order_details['OrderDate'],'status'=>$order_details['Status'], 'icon'=>'fa-shopping-cart'];
     if(in_array($order_details['Status'], ['Pending Confirmation', 'Processing'])) { $chain_history[]=['step'=>'Traceability Pending','actor'=>'System','location'=>'N/A','action'=>'Waiting Link','date'=>$order_details['OrderDate'],'status'=>'','icon'=>'fa-hourglass-half']; }
     if($order_details['AssignedDistributorID'] && !in_array($order_details['Status'], ['Pending Confirmation', 'Processing'])){ $chain_history[]=['step'=>'Distribution','actor'=>$order_details['DistributorName']??'Distributor','location'=>$order_details['DistributorAddress'],'action'=>'Assigned/In Transit','date'=>$order_details['PickupDate']??$order_details['OrderDate'],'status'=>$order_details['Status'], 'icon'=>'fa-truck-fast'];}
     if($order_details['Status']=='Delivered'){$chain_history[]=['step'=>'Delivery','actor'=>$order_details['BuyerName'],'location'=>$order_details['BuyerAddress'],'action'=>'Delivered','date'=>$order_details['DeliveryDate']??$order_details['OrderDate'],'status'=>'Delivered', 'icon'=>'fa-store'];}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Food - Organic Food Traceability</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="track_style.css">
    <style> body { margin: 0; } </style>
</head>
<body>
    <header class="public-navbar">
        <a href="index.php" class="brand">Organic Food Traceability</a>
        <div class="links">
            <a href="track_food.php">Track Food</a>
            <?php
            // Show Dashboard/Logout if logged in, else Login/Register
            if (isset($_SESSION['user_id']) && isset($_SESSION['role_name'])) {
                echo '<a href="dashboard.php">Dashboard</a>';
                echo '<a href="logout.php">Logout</a>';
            } else {
                echo '<a href="login.php">Login</a>';
                echo '<a href="register.php">Register</a>';
            }
            ?>
        </div>
    </header>

    <div class="public-content-wrapper">

        <section class="tracker-section" style="max-width: 600px; margin: 2.5rem auto;">
            <h2>Track Your Food</h2>
            <div class="search-form">
                <form action="track_food.php" method="POST" id="searchForm">
                     <label for="search_term">Enter Order ID or Scan QR:</label>
                    <div>
                        <input type="text" id="search_term" name="search_term" placeholder="e.g., 123" value="<?php echo htmlspecialchars($_POST['search_term'] ?? $_GET['order_id'] ?? $_GET['batch_id'] ?? ''); ?>">
                        <button type="submit" name="search_order">Search</button>
                        <button type="button" id="scanBtn" class="scan-button"><i class="fas fa-qrcode"></i>Scan</button>
                    </div>
                </form>
                 <div class="scanner-container" id="scannerContainer" style="display: none;">
                     <div id="qr-reader"></div>
                     <p id="scanResult" style="font-weight: bold; margin-top: 1rem;"></p>
                 </div>
                <?php
                // Display error messages
                if (!empty($error_message) && (isset($_POST['search_order']) || isset($_GET['order_id']) || isset($_GET['batch_id'])) && !$order_details && !$batch_details ) { echo "<p class='message error'>".$error_message."</p>"; }
                elseif (!empty($error_message) && ($order_details || $batch_details)) { echo "<p class='message error' style='background-color: #fff3cd; color: #664d03; border-color: #ffecb5;'>Note: ".$error_message."</p>"; }
                ?>
            </div>
        </section>

        <?php if ($order_details || $batch_details): // Show results card ?>
        <div class="track-card">
            <div class="info-section">
                <h2>
                    Product Information
                    <?php if ($order_details): // Only show order verification if order details exist ?>
                        <?php if ($is_order_verified): ?>
                            <span class="verification-badge verified"><i class="fas fa-check-circle"></i> Order Verified</span>
                        <?php else: ?>
                             <span class="verification-badge manipulated"><i class="fas fa-exclamation-triangle"></i> Order Data Manipulated</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($batch_details || $order_details): ?>
                        <button id="viewChainBtn" class="btn-view-chain"><i class="fas fa-link"></i> View Transaction History</button>
                    <?php endif; ?>
                </h2>
                <?php
                $display_name = $order_details['OrderedProductName'] ?? ($batch_details['RawProductName'] ?? 'N/A');
                $display_image = $order_details['OrderedProductImage'] ?? ($batch_details['RawProductImage'] ?? null);
                $display_desc = $order_details['ProductDescription'] ?? ($batch_details['ProductDescription'] ?? 'N/A');
                ?>
                <?php if(!empty($display_image) && file_exists($display_image)){ echo '<img src="'.htmlspecialchars($display_image).'" alt="Product Image" class="product-image">'; } ?>
                <div class="details-grid">
                    <div class="detail-item"><label>Product Name</label><p><?php echo htmlspecialchars($display_name); ?></p></div>
                    <?php if($order_details): ?>
                        <div class="detail-item"><label>Order ID</label><p>#<?php echo htmlspecialchars($order_details['OrderID']); ?></p></div>
                        <div class="detail-item"><label>Quantity</label><p><?php echo htmlspecialchars($order_details['OrderQuantity']); ?></p></div>
                        <div class="detail-item"><label>Total Price</label><p>&#8369;<?php echo number_format($order_details['TotalPrice'], 2); ?></p></div>
                    <?php endif; ?>
                    <div class="detail-item full-width"><label>Description</label><p><?php echo nl2br(htmlspecialchars($display_desc)); ?></p></div>
                </div>
            </div>

            <?php if ($batch_details): ?>
            <div class="info-section">
                <h2>
                    Farming Information (Origin Batch)
                    <?php if (isset($is_origin_verified)): ?>
                        <?php if ($is_origin_verified): ?>
                            <span class="verification-badge verified"><i class="fas fa-check-circle"></i> Origin Verified</span>
                        <?php else: ?>
                             <span class="verification-badge manipulated"><i class="fas fa-exclamation-triangle"></i> Origin Data Manipulated</span>
                        <?php endif; ?>
                     <?php endif; ?>
                </h2>
                <div class="details-grid">
                    <div class="detail-item"><label>Batch Number</label><p><?php echo htmlspecialchars($batch_details['BatchNumber']); ?></p></div>
                    <div class="detail-item"><label>Farmer</label><p><?php echo htmlspecialchars($batch_details['FarmerName']); ?></p></div>
                    <div class="detail-item full-width"><label>Farmer Location</label><p><?php echo htmlspecialchars($batch_details['FarmerAddress'] ?? 'N/A'); ?></p></div>
                    <div class="detail-item"><label>Harvest Date</label><p><?php echo htmlspecialchars($batch_details['HarvestedDate']); ?></p></div>
                    <div class="detail-item"><label>Sowing Date</label><p><?php echo htmlspecialchars($batch_details['SowingDate'] ?? 'N/A'); ?></H4></div>
                    <div class="detail-item full-width"><label>Crop Detail</label><p><?php echo nl2br(htmlspecialchars($batch_details['CropDetails'] ?? 'N/A')); ?></p></div>
                    <div class="detail-item full-width"><label>Soil Detail</label><p><?php echo nl2br(htmlspecialchars($batch_details['SoilDetails'] ?? 'N/A')); ?></p></div>
                    <div class="detail-item full-width"><label>Farming Practice</label><p><?php echo nl2br(htmlspecialchars($batch_details['FarmPractice'] ?? 'N/A')); ?></p></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="info-section">
                <h2>History Chain</h2>
                <?php if (!empty($chain_history)): ?>
                    <div class="vertical-timeline">
                        <?php
                         $step_count = count($chain_history); $completed_status = ['Completed', 'Shipped', 'Received', 'Finished', 'Delivered']; $current_stage_found = false;
                        for ($index = $step_count - 1; $index >= 0; $index--): $event = $chain_history[$index]; $is_completed = in_array($event['status'] ?? 'Completed', $completed_status); $is_active = false; if (!$is_completed && !$current_stage_found) { $is_active = true; $current_stage_found = true; } $chain_history[$index]['is_completed'] = $is_completed; $chain_history[$index]['is_active'] = $is_active; endfor; if (!$current_stage_found && $step_count > 0) { $chain_history[$step_count - 1]['is_completed'] = true; }
                        
                        foreach ($chain_history as $index => $event):
                            $step_class = $event['is_completed'] ? 'completed' : '';
                            $step_class .= $event['is_active'] ? ' active' : '';
                        ?>
                            <div class="timeline-item <?php echo trim($step_class); ?>">
                                <div class="timeline-icon"><i class="fas <?php echo htmlspecialchars($event['icon'] ?? 'fa-question-circle'); ?>"></i></div>
                                <div class="timeline-content">
                                    <h4><?php echo htmlspecialchars($event['step']); ?></h4>
                                    <p><span class="actor"><?php echo htmlspecialchars($event['actor']); ?></span></p>
                                    <p class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></p>
                                    <p class="time"><?php echo date('Y-m-d H:i', strtotime($event['date'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align:center;">No tracking history available.</p>
                <?php endif; ?>
            </div>

        </div> <?php endif; ?>

    <footer class="public-footer">
        <p>&copy; <?php echo date("Y"); ?> Organic Food Traceability System. All rights reserved.</p>
    </footer>

</div>

<div id="blockchainModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeModalBtn">&times;</span>
        <div class="blockchain-visualizer">
            <h3>Blockchain Transaction History (Simulation)</h3>
            
            <?php if ($batch_details): // Only show block 1 if batch exists ?>
            <div class="block">
                <h4 class="block-header">Block 1: Genesis (Farmer Batch)</h4>
                <div class="block-data">
                    <strong>Block Type:</strong> Batch Creation<br>
                    <strong>Batch ID:</strong> <?php echo htmlspecialchars($batch_details['BatchID']); ?><br>
                    <strong>Farmer:</strong> <?php echo htmlspecialchars($batch_details['FarmerName']); ?><br>
                    <strong>Data Status:</strong> <?php echo $is_origin_verified ? '<span class="verified" style="padding: 2px 5px; font-size: 0.8rem;">Data Verified</span>' : '<span class="manipulated" style="padding: 2px 5px; font-size: 0.8rem;">Data Manipulated!</span>'; ?>
                </div>
                <strong>BATCH HASH:</strong>
                <div class="block-data hash-value"><?php echo htmlspecialchars($batch_details['TransactionHash'] ?? 'N/A'); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($order_details): // Show Order block if order exists ?>
                <?php if ($batch_details && $order_details['SourceBatchID'] == $batch_details['BatchID']): // Case 1: Order is LINKED to batch ?>
                    <div class="chain-link"><i class="fas fa-arrow-down"></i></div>
                    <div class="block">
                        <h4 class="block-header">Block 2: Transaction (Order)</h4>
                        <div class="block-data">
                            <strong>Block Type:</strong> Order Creation<br>
                            <strong>Order ID:</strong> <?php echo htmlspecialchars($order_details['OrderID']); ?><br>
                            <strong>Manufacturer:</strong> <?php echo htmlspecialchars($order_details['SellerName']); ?><br>
                            <strong>Buyer:</strong> <?php echo htmlspecialchars($order_details['BuyerName']); ?><br>
                            <strong>Quantity:</strong> <?php echo htmlspecialchars($order_details['OrderQuantity']); ?><br>
                            <strong>Total Price:</strong> &#8369;<?php echo number_format($order_details['TotalPrice'], 2); ?><br>
                            <strong>Data Status:</strong> <?php echo $is_order_verified ? '<span class="verified" style="padding: 2px 5px; font-size: 0.8rem;">Data Verified</span>' : '<span class="manipulated" style="padding: 2px 5px; font-size: 0.8rem;">Data Manipulated!</span>'; ?>
                        </div>
                        <strong>PREVIOUS HASH (from Block 1):</strong>
                        <div class="block-data prev-hash-value"><?php echo htmlspecialchars($batch_details['TransactionHash'] ?? 'N/A'); ?></div>
                        <strong>ORDER HASH:</strong>
                        <div class="block-data hash-value"><?php echo htmlspecialchars($order_details['TransactionHash'] ?? 'N/A'); ?></div>
                    </div>
                <?php elseif (!$batch_details): // Case 2: Order exists but NO batch (e.g., Order ID search, link pending) ?>
                     <div class="block">
                        <h4 class="block-header">Block: Order</h4>
                        <div class="block-data">
                            <strong>Block Type:</strong> Order Creation<br>
                            <strong>Order ID:</strong> <?php echo htmlspecialchars($order_details['OrderID']); ?><br>
                            <strong>Buyer:</strong> <?php echo htmlspecialchars($order_details['BuyerName']); ?><br>
                            <strong>Data Status:</strong> <?php echo $is_order_verified ? '<span class="verified" style="padding: 2px 5px; font-size: 0.8rem;">Data Verified</span>' : '<span class="manipulated" style="padding: 2px 5px; font-size: 0.8rem;">Data Manipulated!</span>'; ?>
                        </div>
                         <strong>ORDER HASH:</strong>
                        <div class="block-data hash-value"><?php echo htmlspecialchars($order_details['TransactionHash'] ?? 'N/A'); ?></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($show_survey && $batch_details): // Only render this modal if $show_survey is true ?>
<div id="surveyModal" class="survey-modal">
    <div class="survey-content">
        <span class="survey-close-btn" id="surveyCloseBtn">&times;</span>
        
        <div id="surveyForm">
            <h3>Trust This Product?</h3>
            <p>Based on the traceability info, how much do you trust this product's organic authenticity?</p>
            <form class="star-rating" id="starRatingForm">
                <input type="radio" id="star5" name="rating" value="5" /><label for="star5" title="5 stars">&#9733;</label>
                <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 stars">&#9733;</label>
                <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 stars">&#9733;</label>
                <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 stars">&#9733;</label>
                <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 star">&#9733;</label>
            </form>
            <input type="hidden" id="surveyBatchId" value="<?php echo htmlspecialchars($batch_details['BatchID']); ?>">
        </div>
        
        <div id="surveyThankYou">
            <p><i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i><br>Thank you for your feedback!</p>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- QR Scanner Logic ---
        const scanBtn = document.getElementById('scanBtn');
        const scannerContainer = document.getElementById('scannerContainer');
        const qrReaderDiv = document.getElementById('qr-reader');
        const scanResultP = document.getElementById('scanResult');
        let html5QrCode = null;

        function onScanSuccess(decodedText, decodedResult) {
            try {
                const url = new URL(decodedText);
                const batchId = url.searchParams.get('batch_id');
                const orderId = url.searchParams.get('order_id');
                
                if (batchId && !isNaN(batchId)) {
                    window.location.href = `track_food.php?batch_id=${batchId}`;
                    stopScanner();
                } else if (orderId && !isNaN(orderId)) {
                    window.location.href = `track_food.php?order_id=${orderId}`;
                    stopScanner();
                } else {
                    if(scanResultP) scanResultP.textContent = 'Error: Valid ID not found in QR code.';
                }
            } catch (e) {
                if(scanResultP) scanResultP.textContent = 'Error: Not a valid tracking URL.';
            }
        }

        function onScanFailure(error) {
            // This is called when no QR code is found. We can ignore it.
        }

        function startScanner() {
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("qr-reader");
            }
            if (scannerContainer) scannerContainer.style.display = 'block';
            if(scanResultP) scanResultP.textContent = 'Initializing camera...';
            
            const config = { fps: 10, qrbox: { width: 250, height: 250 } };
            
            html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
            .then(() => {
                if (scanBtn) scanBtn.innerHTML = '<i class="fas fa-stop-circle"></i> Stop';
                // The library adds its own stop button, this is a fallback
                const stopBtn = qrReaderDiv.querySelector('button');
                if (!stopBtn && qrReaderDiv) {
                    const button = document.createElement('button');
                    button.textContent = 'Stop Scanning';
                    button.onclick = stopScanner;
                    qrReaderDiv.appendChild(button);
                }
            }).catch(err => {
                if(scanResultP) scanResultP.textContent = `Camera Error: ${err}. Please grant camera permissions.`;
                if(scannerContainer) scannerContainer.style.display = 'none';
            });
        }

        function stopScanner() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().then(ignore => {
                    if(scannerContainer) scannerContainer.style.display = 'none';
                    if(scanBtn) scanBtn.innerHTML = '<i class="fas fa-qrcode"></i> Scan';
                    if(scanResultP) scanResultP.textContent = '';
                }).catch(err => {
                    // Stop failed, just hide
                    if(scannerContainer) scannerContainer.style.display = 'none';
                    if(scanBtn) scanBtn.innerHTML = '<i class="fas fa-qrcode"></i> Scan';
                });
            } else {
                if(scannerContainer) scannerContainer.style.display = 'none';
                if(scanBtn) scanBtn.innerHTML = '<i class="fas fa-qrcode"></i> Scan';
            }
        }

        if (scanBtn) {
            scanBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (html5QrCode && html5QrCode.isScanning) {
                    stopScanner();
                } else {
                    startScanner();
                }
            });
        }
    
        // --- Blockchain Modal Logic ---
        const modal = document.getElementById('blockchainModal');
        const btn = document.getElementById('viewChainBtn');
        const span = document.getElementById('closeModalBtn');
        if (btn) { btn.onclick = function() { if (modal) modal.style.display = "block"; } }
        if (span) { span.onclick = function() { if (modal) modal.style.display = "none"; } }
        window.onclick = function(event) { if (event.target == modal) { if (modal) modal.style.display = "none"; } }

        // --- Survey Modal Logic ---
        const surveyModal = document.getElementById('surveyModal');
        const surveyClose = document.getElementById('surveyCloseBtn');
        const starRatingForm = document.getElementById('starRatingForm');
        const surveyBatchIdInput = document.getElementById('surveyBatchId');
        
        const showSurvey = <?php echo json_encode($show_survey); ?>;

        if (showSurvey && surveyModal) {
            setTimeout(() => {
                surveyModal.style.display = 'block';
            }, 3000); // 3-second delay
        }

        if (surveyClose) {
            surveyClose.onclick = function() {
                surveyModal.style.display = "none";
            }
        }
        
        if (starRatingForm) {
            starRatingForm.addEventListener('change', function(e) {
                if (e.target.name === 'rating') {
                    const score = e.target.value;
                    const batch_id = surveyBatchIdInput.value;
                    submitRating(batch_id, score);
                }
            });
        }
        
        async function submitRating(batch_id, score) {
            try {
                const response = await fetch('submit_rating.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_id: batch_id, score: score })
                });
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('surveyForm').style.display = 'none';
                    document.getElementById('surveyThankYou').style.display = 'block';
                    setTimeout(() => {
                        if (surveyModal) surveyModal.style.display = 'none';
                    }, 2000);
                } else {
                    console.error('Failed to submit rating:', result.message);
                    alert('Error: Could not submit rating. ' + result.message);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Error: Could not connect to server.');
            }
        }

    });
</script>
</body>
</html>
<?php
// Close the database connection
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}
?>