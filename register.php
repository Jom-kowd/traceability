<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once 'db.php';
$error_msg = '';
$form_values = ['fullname' => '','email' => '','username' => '','role_id' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $fullname = trim($_POST['fullname']); $email = trim($_POST['email']); $username = trim($_POST['username']);
    $password = $_POST['password']; $role_id = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
    $form_values = ['fullname' => $fullname,'email' => $email,'username' => $username,'role_id' => $role_id];
    
    // --- Validation and registration logic ---
    if (empty($fullname)||empty($email)||empty($username)||empty($password)||empty($role_id)) { $error_msg="All fields are required."; }
    elseif (strlen($password)<6) { $error_msg="Password must be at least 6 characters."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error_msg="Invalid email address."; }
    elseif ($role_id===false || $role_id<1 || $role_id>5) { $error_msg="Invalid role selected."; }
    else {
        // Check for existing user
        $sql_check = "SELECT UserID FROM Users WHERE Username = ? OR Email = ?"; $stmt_check = $conn->prepare($sql_check);
        if ($stmt_check) { $stmt_check->bind_param("ss", $username, $email); $stmt_check->execute(); $stmt_check->store_result(); if ($stmt_check->num_rows > 0) { $error_msg = "Username or Email already exists."; } $stmt_check->close();
        } else { $error_msg = "DB error (Check User)."; error_log("Reg check prep fail: ".$conn->error); }

        if (empty($error_msg)) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $certificate_path = NULL;
            $valid_id_path = NULL; // <-- NEW: Variable for the ID
            $verification_status = '';
            
            // Roles 1, 2, 3, 4 require verification. Role 5 (Consumer) is auto-approved.
            $is_business_role = ($role_id >= 1 && $role_id <= 4);
            $is_producer_role = ($role_id == 1 || $role_id == 2); // Farmer or Mfr

            if ($is_business_role) {
                $verification_status = 'Pending'; // All business roles must be approved
                
                // --- Helper Function for File Uploads ---
                // (We create this to avoid repeating code)
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $max_size = 5 * 1024 * 1024; // 5MB

                $file_upload_error = function($file_key) use ($allowed_types, $max_size, &$error_msg, $upload_dir) {
                    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == UPLOAD_ERR_OK) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $file_type = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
                        finfo_close($finfo);
                        $file_size = $_FILES[$file_key]['size'];

                        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                            $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                            $fname = 'cert_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
                            $target = $upload_dir . $fname;
                            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target)) {
                                return $target; // Success: return file path
                            } else {
                                $error_msg = "Could not save uploaded file."; return NULL;
                            }
                        } else {
                            if (!in_array($file_type, $allowed_types)) { $error_msg = "Invalid file type for ".$file_key."."; }
                            elseif ($file_size > $max_size) { $error_msg = "File ".$file_key." is larger than 5MB."; }
                            return NULL;
                        }
                    } elseif (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] != UPLOAD_ERR_NO_FILE) {
                        $error_msg = "File upload error: " . $_FILES[$file_key]['error']; return NULL;
                    } else {
                        // This means UPLOAD_ERR_NO_FILE, which is an error if the field is required
                        $error_msg = "A required document (".$file_key.") is missing."; return NULL;
                    }
                };
                // --- End Helper Function ---

                // ** NEW LOGIC **
                // 1. Check for Valid ID (Required for roles 1, 2, 3, 4)
                $valid_id_path = $file_upload_error('valid_id');
                if ($valid_id_path === NULL && empty($error_msg)) { $error_msg = "Valid ID is required for this role."; }

                // 2. Check for Certificate (Required for roles 1, 2 ONLY)
                if (empty($error_msg) && $is_producer_role) {
                    $certificate_path = $file_upload_error('certificate');
                    if ($certificate_path === NULL && empty($error_msg)) { $error_msg = "Certificate is required for this role."; }
                }

            } else {
                // This is Role 5 (Consumer)
                $verification_status = 'Approved';
            }

            if (empty($error_msg)) {
                // ** UPDATED INSERT QUERY **
                $sql_insert = "INSERT INTO Users (Username, PasswordHash, RoleID, FullName, Email, CertificateInfo, ValidIDPath, VerificationStatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                if ($stmt_insert) {
                    // Note the new 's' for ValidIDPath and the new variable
                    $stmt_insert->bind_param("ssisssss", $username, $password_hash, $role_id, $fullname, $email, $certificate_path, $valid_id_path, $verification_status);
                    
                    if ($stmt_insert->execute()) {
                        $success_msg = "Registration successful! " . ($verification_status == 'Pending' ? "Your account requires admin approval." : "You can now log in.");
                        header("Location: login.php?success=" . urlencode($success_msg));
                        exit;
                    } else {
                        if ($conn->errno == 1062) { $error_msg = "Username or Email already exists."; }
                        else { $error_msg = "DB insert error."; error_log("Reg insert fail:" . $stmt_insert->error); }
                    }
                    $stmt_insert->close();
                } else { $error_msg = "DB prepare error."; error_log("Reg prep fail:" . $conn->error); }
            }
        }
    }
    // If we're here, an error occurred, and the page will reload with $error_msg
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Organic Food Traceability</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style> body { margin: 0; } </style>
</head>
<body>
    <header class="public-navbar">
        <a href="index.php" class="brand">Organic Food Traceability</a>
        <div class="links">
            <a href="track_food.php">Track Food</a>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </div>
    </header>

    <div class="public-content-wrapper">
        <div class="form-container">
            <h2>Register New Account</h2>
            <?php if (!empty($error_msg)) { echo '<p class="message error">Error: '.htmlspecialchars($error_msg).'</p>'; } ?>
            <form action="register.php" method="post" enctype="multipart/form-data">
                <label for="fullname">Full Name:</label>
                <input type="text" id="fullname" name="fullname" required value="<?php echo htmlspecialchars($form_values['fullname']); ?>">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($form_values['email']); ?>">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($form_values['username']); ?>">
                <label for="password">Password (min. 6):</label>
                <input type="password" id="password" name="password" required minlength="6">
                <label for="role">Select Your Role:</label>
                <select id="role" name="role_id" onchange="checkRole()" required>
                    <option value="">-- Select --</option>
                    <option value="1" <?php if($form_values['role_id']==1)echo'selected';?>>Farmer</option>
                    <option value="2" <?php if($form_values['role_id']==2)echo'selected';?>>Manufacturer</option>
                    <option value="3" <?php if($form_values['role_id']==3)echo'selected';?>>Distributor</option>
                    <option value="4" <?php if($form_values['role_id']==4)echo'selected';?>>Retailer</option>
                    <option value="5" <?php if($form_values['role_id']==5)echo'selected';?>>Consumer</option>
                </select>
                
                <div id="valid-id-upload" style="display: none;">
                    <label for="valid_id">Valid ID (Business Permit, Gov't ID):</label>
                    <p>Required for all business roles (Farmer, Mfr, Distributor, Retailer).</p>
                    <input type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png">
                </div>

                <div id="certificate-upload" style="display: none;">
                    <label for="certificate">Organic Certificate (PDF/JPG/PNG):</label>
                    <p>Required for Farmer/Mfr.</p>
                    <input type="file" id="certificate" name="certificate" accept=".pdf,.jpg,.jpeg,.png">
                </div>

                <button type="submit" name="register">Register</button>
                <p>Already have an account? <a href="login.php">Login here</a>.</p>
            </form>
        </div>
        <footer class="public-footer"><p>&copy; <?php echo date("Y"); ?> Organic Food Traceability System.</p></footer>
    </div>
    
    <script>
    function checkRole(){
        var r = document.getElementById("role");
        var val = r.value;

        // Get elements
        var certUploadDiv = document.getElementById("certificate-upload");
        var certInput = document.getElementById("certificate");
        var idUploadDiv = document.getElementById("valid-id-upload");
        var idInput = document.getElementById("valid_id");

        // Define role logic
        var isProducer = (val == "1" || val == "2"); // Farmer, Mfr
        var isBusiness = (val == "1" || val == "2" || val == "3" || val == "4"); // Farmer, Mfr, Dist, Retailer

        // Show/hide Certificate
        certUploadDiv.style.display = isProducer ? "block" : "none";
        certInput.required = isProducer;

        // Show/hide Valid ID
        idUploadDiv.style.display = isBusiness ? "block" : "none";
        idInput.required = isBusiness;
    } 
    // Run on page load to check pre-filled values
    checkRole();
    </script>
</body>
</html>
<?php if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) { $conn->close(); } ?>