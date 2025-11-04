<?php
include_once 'db.php'; // Include DB connection

// Get the raw POST data
$data = json_decode(file_get_contents("php://input"));

$batch_id = filter_var($data->batch_id ?? null, FILTER_VALIDATE_INT);
$score = filter_var($data->score ?? null, FILTER_VALIDATE_INT);

$response = ['success' => false, 'message' => 'Invalid input.'];

// Check if data is valid
if ($batch_id && $score >= 1 && $score <= 5) {
    try {
        $sql = "INSERT INTO trust_survey (batch_id, trust_score) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ii", $batch_id, $score);
            if ($stmt->execute()) {
                // Set a cookie to prevent rating this batch again
                // Cookie expires in 1 year (3600 * 24 * 365)
                setcookie("rated_batch_" . $batch_id, "true", time() + 31536000, "/"); 
                
                $response = ['success' => true, 'message' => 'Rating submitted!'];
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $response['message'] = 'Database prepare error: ' . $conn->error;
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// Close connection
if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
    $conn->close();
}

// Send JSON response back to JavaScript
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>