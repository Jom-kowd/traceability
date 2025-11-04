<?php
// Includes admin security check, session_start(), and $conn (database connection)
include_once 'header.php';

// Fetch performance data
$sql_report = "SELECT 
                    AVG(trust_score) as avg_trust, 
                    COUNT(*) as total_ratings,
                    SUM(CASE WHEN trust_score = 5 THEN 1 ELSE 0 END) as '5_star',
                    SUM(CASE WHEN trust_score = 4 THEN 1 ELSE 0 END) as '4_star',
                    SUM(CASE WHEN trust_score = 3 THEN 1 ELSE 0 END) as '3_star',
                    SUM(CASE WHEN trust_score = 2 THEN 1 ELSE 0 END) as '2_star',
                    SUM(CASE WHEN trust_score = 1 THEN 1 ELSE 0 END) as '1_star'
                FROM trust_survey";
$result_report = $conn->query($sql_report);
$report_data = $result_report->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Trust Report</title>
    <link rel="stylesheet" href="admin_style.css"> 
    <style>
        .report-card {
            background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;
            padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;
        }
        .report-card h3 { margin-top: 0; font-size: 1.2rem; color: #555; }
        .report-card .score { font-size: 2.5rem; font-weight: 700; color: #278A3F; }
        .report-card .total { font-size: 1rem; color: #666; }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>Consumer Trust Report</h1>
    <p> Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>! |
        <a href="index.php">User Verification</a> |
        <a href="performance_report.php">Performance Report</a> |
        <a href="../logout.php">Logout</a> </p>

    <h2>Overall Trust Rating</h2>
    <p>This report shows the average "Trust in Authenticity" score submitted by consumers on the tracking page.</p>

    <div class="report-card">
        <h3>Average Trust Score (Likert Scale 1-5)</h3>
        <div class="score">
            <?php echo $report_data['total_ratings'] > 0 ? number_format($report_data['avg_trust'], 2) : 'N/A'; ?>
        </div>
        <div class="total">
            Based on <strong><?php echo $report_data['total_ratings']; ?></strong> total ratings
        </div>
    </div>
    
    <div style="overflow-x:auto;">
        <table class="verification-table">
            <thead>
                <tr>
                    <th>Rating</th>
                    <th>Total Received</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>5 Stars (Full Trust)</td>
                    <td><?php echo $report_data['5_star']; ?></td>
                </tr>
                <tr>
                    <td>4 Stars</td>
                    <td><?php echo $report_data['4_star']; ?></td>
                </tr>
                <tr>
                    <td>3 Stars (Neutral)</td>
                    <td><?php echo $report_data['3_star']; ?></td>
                </tr>
                <tr>
                    <td>2 Stars</td>
                    <td><?php echo $report_data['2_star']; ?></td>
                </tr>
                <tr>
                    <td>1 Star (No Trust)</td>
                    <td><?php echo $report_data['1_star']; ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div> 
</body>
</html>
<?php if (isset($conn)) $conn->close(); // Close connection ?>