<?php
include_once 'header_app.php'; // Security, $conn, $role, $user_id
$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
$batch_details = null; $chain_history = []; $error_message = '';

if (!$batch_id) { $error_message = "No Batch ID provided."; }
else {
    // 1. Fetch Batch Details (Origin)
    $sql_batch = "SELECT pb.*, p.ProductName, p.ProductImage, u.Username as FarmerName FROM ProductBatches pb JOIN Products p ON pb.ProductID=p.ProductID JOIN Users u ON pb.UserID=u.UserID WHERE pb.BatchID=?";
    $stmt_b=$conn->prepare($sql_batch);
    if($stmt_b){ $stmt_b->bind_param("i",$batch_id); $stmt_b->execute(); $res_b=$stmt_b->get_result();
        if($res_b->num_rows==1){ $batch_details=$res_b->fetch_assoc();
            $chain_history[]=['step'=>'Origin','actor'=>$batch_details['FarmerName'],'action'=>'Harvested Batch #'.htmlspecialchars($batch_details['BatchNumber']),'date'=>$batch_details['HarvestedDate'],'status'=>'Completed','details'=>$batch_details];
            // 2. Trace Forward: Find Order linked to this BatchID
            $sql_ord="SELECT o.*, s.Username as SellerName, b.Username as BuyerName, d.Username as DistributorName FROM Orders o JOIN Users s ON o.SellerID=s.UserID JOIN Users b ON o.BuyerID=b.UserID LEFT JOIN Users d ON o.AssignedDistributorID=d.UserID WHERE o.SourceBatchID=? ORDER BY o.OrderDate ASC"; // Order by date
            $stmt_o=$conn->prepare($sql_ord);
            if($stmt_o){ $stmt_o->bind_param("i",$batch_id); $stmt_o->execute(); $res_o=$stmt_o->get_result();
                while($or=$res_o->fetch_assoc()){ // Loop through potentially multiple orders linked to same batch
                    $chain_history[]=['step'=>'Processing & Sale','actor'=>$or['SellerName'],'action'=>'Processed for Order #'.$or['OrderID'].' to '.$or['BuyerName'],'date'=>$or['OrderDate'],'status'=>$or['Status'],'details'=>$or];
                    if($or['AssignedDistributorID']){$chain_history[]=['step'=>'Distribution Assigned','actor'=>$or['SellerName'],'action'=>'Assigned Distributor '.htmlspecialchars($or['DistributorName']??'N/A'),'date'=>$or['OrderDate'],'status'=>$or['Status'],'details'=>$or];}
                    if($or['PickupDate']){$chain_history[]=['step'=>'In Transit','actor'=>$or['DistributorName']??'Distributor','action'=>'Picked up for Delivery','date'=>$or['PickupDate'],'status'=>$or['Status'],'details'=>$or];}
                    if($or['DeliveryDate']||$or['Status']=='Delivered'){$chain_history[]=['step'=>'Delivery Complete','actor'=>$or['DistributorName']??'Distributor','action'=>'Delivered to '.$or['BuyerName'],'date'=>$or['DeliveryDate']??$or['OrderDate'],'status'=>'Delivered','details'=>$or];}
                } $stmt_o->close();
            } else {$error_message.=" Error trace orders.";}
        } else {$error_message="Batch details not found."; $batch_id=null;} $stmt_b->close();
    } else {$error_message="Error fetch batch.";}
}
?>
<title>View Supply Chain - Batch #<?php echo htmlspecialchars($batch_details['BatchNumber'] ?? $batch_id ?? 'N/A'); ?></title>
<style> /* Styles */
.details-card{background:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);max-width:900px;margin:1rem auto} .details-header{display:flex;gap:2rem;align-items:center;border-bottom:2px solid #eee;padding-bottom:2rem;margin-bottom:2rem} .details-header img{width:150px;height:150px;object-fit:cover;border-radius:8px;border:1px solid #ddd} .details-header-info h1{margin-top:0;color:#333} .details-header-info p{font-size:1.1rem;color:#555;max-width:500px} .timeline{list-style:none;padding:0;margin-top:2rem;position:relative;border-left:3px solid #278A3F;margin-left:10px} .timeline li{margin-bottom:2rem;padding-left:2rem;position:relative} .timeline li::before{content:'';width:15px;height:15px;background:#fff;border:3px solid #278A3F;border-radius:50%;position:absolute;left:-11px;top:0} .timeline h4{margin:0 0 .5rem 0;color:#278A3F} .timeline p{margin:.25rem 0;font-size:.95rem;color:#555} .timeline .time{font-size:.85rem;color:#888;margin-top: .5rem; display: block;} .message.error{background:#f8d7da;color:#721c24;padding:1rem;border-radius:5px;margin-top:1rem;text-align:center; font-weight: bold;}
</style>
<div class="page-header"><h1>Supply Chain History</h1></div>
<?php if ($batch_details): ?>
<div class="details-card">
    <div class="details-header">
        <?php if(!empty($batch_details['ProductImage'])&&file_exists($batch_details['ProductImage'])){echo '<img src="'.htmlspecialchars($batch_details['ProductImage']).'" alt="">';}else{echo '<img src="https://via.placeholder.com/150?text=No+Image" alt="">';}?>
        <div><h1><?php echo htmlspecialchars($batch_details['ProductName']);?></h1><p>Batch #: <?php echo htmlspecialchars($batch_details['BatchNumber']);?></p></div>
    </div>
    <h2>Timeline</h2>
    <?php if(!empty($chain_history)): ?>
    <ul class="timeline">
        <?php foreach($chain_history as $e): ?><li><h4><?php echo htmlspecialchars($e['step']);?></h4><p><strong>Actor:</strong> <?php echo htmlspecialchars($e['actor']);?></p><p><strong>Action:</strong> <?php echo htmlspecialchars($e['action']);?></p><p><strong>Status:</strong> <?php echo htmlspecialchars($e['status']??'Completed');?></p><p class="time"><?php echo date('Y-m-d H:i', strtotime($e['date']));?></p></li><?php endforeach; ?>
    </ul>
    <?php else: ?><p>No supply chain history found for this batch yet.</p><?php endif; ?>
    <div style="margin-top: 2rem; text-align: right;"> <a href="javascript:history.back()" class="btn-cancel">Go Back</a> </div>
</div>
<?php elseif($error_message): ?>
    <p class="message error"><?php echo htmlspecialchars($error_message); ?></p>
<?php endif; ?>
</div></body></html>
<?php if (isset($conn)) $conn->close(); ?>