<?php
// Enhanced Shop Profile Editor with improved security and features

// Database configuration - Consider moving to separate config file
$host = "localhost";
$user = "root";
$pass = "";
$port ='3306';
$db   = "core2_test";

// Create connection with error handling
try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$success = false;
$error = "";
$shop = null;

// Enhanced file upload validation function
function validateAndUploadFile($file, $type = 'image', $old_file = null) {
    $upload_dir = "uploads/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if (!isset($file) || $file["error"] !== 0) {
        return null;
    }
    
    // Enhanced file type validation
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = $file["type"];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);
    
    // Validate both reported and actual MIME types
    if (!in_array($file_type, $allowed_types) || !in_array($mime_type, $allowed_types)) {
        throw new Exception("Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.");
    }
    
    // Enhanced size validation (2MB limit)
    if ($file["size"] > 2000000) {
        throw new Exception("File size too large. Maximum 2MB allowed.");
    }
    
    // Enhanced filename sanitization
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file["name"], PATHINFO_FILENAME));
    $new_filename = $type . "_" . $safe_filename . "_" . time() . "." . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $upload_path)) {
        // Delete old file if exists
        if ($old_file && file_exists($old_file)) {
            unlink($old_file);
        }
        return $upload_path;
    } else {
        throw new Exception("Failed to upload file.");
    }
}

// Get shop info with error handling
try {
    $id = 1;
    $sql = "SELECT * FROM shop_settings WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $shop = $result->fetch_assoc();
    
    // If no record exists, create default
    if (!$shop) {
        $insert_sql = "INSERT INTO shop_settings (id, shop_name, contact_info, policies, payment_methods, logo, banner, created_at, updated_at) VALUES (?, ?, '', '', '', NULL, NULL, NOW(), NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $default_name = "My Shop";
        $insert_stmt->bind_param("is", $id, $default_name);
        
        if ($insert_stmt->execute()) {
            // Get the newly created record
            $stmt->execute(); // Re-run the original select statement
            $result = $stmt->get_result();
            $shop = $result->fetch_assoc();
        }
        $insert_stmt->close();
    }
    $stmt->close();
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Enhanced form processing with validation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Enhanced input validation and sanitization
        $shop_name = filter_var(trim($_POST["shop_name"] ?? ''), FILTER_SANITIZE_STRING);
        $contact_info = filter_var(trim($_POST["contact_info"] ?? ''), FILTER_SANITIZE_STRING);
        $policies = filter_var(trim($_POST["policies"] ?? ''), FILTER_SANITIZE_STRING);
        $payment_methods = filter_var(trim($_POST["payment_methods"] ?? ''), FILTER_SANITIZE_STRING);
        
        // Validate required fields
        if (empty($shop_name)) {
            throw new Exception("Shop name is required.");
        }
        
        if (strlen($shop_name) > 100) {
            throw new Exception("Shop name must be less than 100 characters.");
        }
        
        $logo = $shop["logo"];
        $banner = $shop["banner"];

        // Handle file uploads with enhanced validation
        if (isset($_FILES["logo"]) && $_FILES["logo"]["error"] == 0) {
            $uploaded_logo = validateAndUploadFile($_FILES["logo"], "logo", $shop["logo"]);
            if ($uploaded_logo) {
                $logo = $uploaded_logo;
            }
        }

        if (isset($_FILES["banner"]) && $_FILES["banner"]["error"] == 0) {
            $uploaded_banner = validateAndUploadFile($_FILES["banner"], "banner", $shop["banner"]);
            if ($uploaded_banner) {
                $banner = $uploaded_banner;
            }
        }

        // Update database with timestamp
        $update_sql = "UPDATE shop_settings SET shop_name = ?, contact_info = ?, policies = ?, payment_methods = ?, logo = ?, banner = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $update_stmt->bind_param("ssssssi", $shop_name, $contact_info, $policies, $payment_methods, $logo, $banner, $id);
        
        if ($update_stmt->execute()) {
            $success = true;
            // Refresh shop data
            $stmt = $conn->prepare("SELECT * FROM shop_settings WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $shop = $result->fetch_assoc();
        } else {
            throw new Exception("Failed to update shop settings: " . $update_stmt->error);
        }
        $update_stmt->close();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Shop Profile - Enhanced</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .file-upload-container {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s;
            position: relative;
        }
        
        .file-upload-container:hover {
            border-color: #3b82f6;
        }
        
        .file-upload-container input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .current-image, .image-preview {
            max-width: 200px;
            max-height: 150px;
            margin-top: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .image-preview {
            display: none;
        }
        
        .image-preview.show {
            display: block;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
        }
        
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .success-message {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .error-message {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/sidebar.php';?>

    <div class="main-content">
        
        <div class="header" style="max-width: 800px; margin: 0 auto 1.5rem auto;">
            <h1>setting</h1>
            <p>KULAS</p>
        </div>

        <?php if ($success): ?>
            <div class="message success-message">
                ✅ Shop profile updated successfully!
            </div>
        <?php elseif ($error): ?>
            <div class="message error-message">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" id="shopForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="shop_name">Shop Name *</label>
                        <input type="text" id="shop_name" name="shop_name" 
                               value="<?php echo htmlspecialchars($shop['shop_name'] ?? ''); ?>" 
                               required maxlength="100">
                        <small style="color: #6b7280;">Maximum 100 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="payment_methods">Payment Methods</label>
                        <input type="text" id="payment_methods" name="payment_methods" 
                               value="<?php echo htmlspecialchars($shop['payment_methods'] ?? ''); ?>" 
                               placeholder="e.g. Credit Card, PayPal, Bank Transfer">
                    </div>

                    <div class="form-group">
                        <label>Shop Logo</label>
                        <div class="file-upload-container">
                            <span class="upload-icon">📷</span>
                            <div class="upload-text">
                                <strong>Click to upload logo</strong><br>
                                PNG, JPG, GIF, WebP up to 2MB
                            </div>
                            <input type="file" name="logo" accept="image/*" onchange="previewImage(event, 'logoPreview')">
                        </div>
                        <?php if (!empty($shop['logo']) && file_exists($shop['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($shop['logo']); ?>?v=<?php echo time(); ?>" class="current-image" alt="Current Logo">
                        <?php endif; ?>
                        <img id="logoPreview" class="image-preview" alt="Logo Preview">
                    </div>

                    <div class="form-group">
                        <label>Shop Banner</label>
                        <div class="file-upload-container">
                            <span class="upload-icon">🖼️</span>
                            <div class="upload-text">
                                <strong>Click to upload banner</strong><br>
                                PNG, JPG, GIF, WebP up to 2MB
                            </div>
                            <input type="file" name="banner" accept="image/*" onchange="previewImage(event, 'bannerPreview')">
                        </div>
                        <?php if (!empty($shop['banner']) && file_exists($shop['banner'])): ?>
                            <img src="<?php echo htmlspecialchars($shop['banner']); ?>?v=<?php echo time(); ?>" class="current-image" alt="Current Banner">
                        <?php endif; ?>
                        <img id="bannerPreview" class="image-preview" alt="Banner Preview">
                    </div>

                    <div class="form-group full-width">
                        <label for="contact_info">Contact Information</label>
                        <textarea id="contact_info" name="contact_info" rows="3" 
                                  placeholder="Enter your contact details, address, phone number, email, etc."><?php echo htmlspecialchars($shop['contact_info'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="policies">Shop Policies</label>
                        <textarea id="policies" name="policies" rows="4" 
                                  placeholder="Enter your shop policies, return policy, shipping info, etc."><?php echo htmlspecialchars($shop['policies'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="button-container" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        ← Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        💾 Update Shop Profile
                    </button>
                </div>
            </form>
        </div>

    </div>

    <script>
        function previewImage(event, previewId) {
            const file = event.target.files[0];
            const preview = document.getElementById(previewId);
            
            if (file) {
                // Enhanced file validation on client side
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = 2000000; // 2MB
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, PNG, GIF, WebP).');
                    event.target.value = '';
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('File size too large. Please select an image smaller than 2MB.');
                    event.target.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.add('show');
                }
                reader.readAsDataURL(file);
            }
        }

        // Enhanced form validation
        document.getElementById('shopForm').addEventListener('submit', function(e) {
            const shopName = document.getElementById('shop_name').value.trim();
            if (!shopName) {
                alert('Shop name is required.');
                e.preventDefault();
                return;
            }
            
            if (shopName.length > 100) {
                alert('Shop name must be less than 100 characters.');
                e.preventDefault();
                return;
            }
            
            // Disable submit button to prevent double submission
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Updating...';
        });

        // Auto-hide messages after 5 seconds with fade effect
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>