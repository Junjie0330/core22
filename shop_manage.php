<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$port = '3306';
$db_name = 'core2_test'; 

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $port);

if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

$error = '';
$success = false;
$current_data = [];

// Fetch current user data
$fetch_stmt = $conn->prepare("SELECT shop_name, logo, banner, contact_info, policies, payment_methods FROM sellers WHERE id = ?");
$fetch_stmt->bind_param("i", $current_user_id);
$fetch_stmt->execute();
$result = $fetch_stmt->get_result();
if ($result->num_rows > 0) {
    $current_data = $result->fetch_assoc();
}
$fetch_stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shop_name = trim($_POST['shop_name']);
    $payment_methods = trim($_POST['payment_methods']);
    $contact_info = trim($_POST['contact_info']);
    $policies = trim($_POST['policies']);

    if (empty($shop_name)) {
        $error = "Shop name is required.";
    }

    if (empty($error)) {
        
        // Upload function
        function handle_upload($file_key, $current_file = null) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_tmp = $_FILES[$file_key]['tmp_name'];
                $file_name = $_FILES[$file_key]['name'];
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = mime_content_type($file_tmp);
                
                if (!in_array($file_type, $allowed_types)) {
                    return $current_file;
                }
                
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_filename = $file_key . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    if ($current_file && file_exists($current_file)) {
                        unlink($current_file);
                    }
                    return $destination;
                }
            }
            return $current_file;
        }

        // Handle uploads
        $logo_path = handle_upload('logo', $current_data['logo'] ?? null);
        $banner_path = handle_upload('banner', $current_data['banner'] ?? null);

        // Update seller table
        $update_stmt = $conn->prepare(
            "UPDATE seller SET 
                shop_name = ?, 
                payment_methods = ?, 
                contact_info = ?, 
                policies = ?, 
                logo = ?, 
                banner = ?
            WHERE id = ?"
        );
        
        $update_stmt->bind_param("ssssssi", 
            $shop_name, 
            $payment_methods, 
            $contact_info, 
            $policies, 
            $logo_path, 
            $banner_path, 
            $current_user_id
        );
        
        if ($update_stmt->execute()) {
            $success = true;
            $error = "Shop settings saved successfully!";
            
            // Refresh data
            $fetch_stmt = $conn->prepare("SELECT shop_name, logo, banner, contact_info, policies, payment_methods FROM seller WHERE id = ?");
            $fetch_stmt->bind_param("i", $current_user_id);
            $fetch_stmt->execute();
            $result = $fetch_stmt->get_result();
            if ($result->num_rows > 0) {
                $current_data = $result->fetch_assoc();
            }
            $fetch_stmt->close();
        } else {
            $error = "Database error: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f3f4f6; }
        .main-content { padding: 2rem; margin-left: 250px; }
        .form-container { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; }
        .file-upload-container { border: 2px dashed #d1d5db; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; transition: border-color 0.2s; position: relative; }
        .file-upload-container:hover { border-color: #3b82f6; }
        .file-upload-container input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .image-preview { max-width: 200px; max-height: 150px; margin: 1rem auto 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); object-fit: cover; display: none; }
        .image-preview.show { display: block; }
        .current-image { max-width: 200px; max-height: 150px; margin: 1rem auto 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); object-fit: cover; display: block; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background-color: #3b82f6; color: white; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-secondary { background-color: #6b7280; color: white; }
        .btn-secondary:hover { background-color: #4b5563; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; max-width: 800px; margin-left: auto; margin-right: auto; }
        .success-message { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error-message { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .full-width { grid-column: 1 / -1; }
        .logout-btn { background-color: #dc3545; color: white; margin-left: 10px; }
        .logout-btn:hover { background-color: #c82333; }
        .header-box { max-width: 800px; margin: 0 auto 1.5rem auto; display: flex; justify-content: space-between; align-items: center; background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .header-box h1 { font-size: 1.75rem; font-weight: 700; color: #1f2937; margin: 0; }
        .header-box p { color: #6b7280; margin: 0.25rem 0 0 0; }
        @media (max-width: 768px) {
            .main-content { padding: 1rem; margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; }
            .header-box { flex-direction: column; gap: 1rem; text-align: center; }
        }
    </style>
</head>
<body>
    <?php if (file_exists('includes/sidebar.php')) include 'includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="header-box">
            <div>
                <h1>🏪 Shop Settings</h1>
                <p>Manage your shop information</p>
            </div>
            <a href="logout.php" class="btn btn-secondary logout-btn">🚪 Logout</a>
        </div>

        <?php if ($success): ?>
            <div class="message success-message">
                ✅ Shop settings saved successfully!
            </div>
        <?php elseif (!empty($error) && !$success): ?>
            <div class="message error-message">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" id="shopForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="shop_name">Shop Name *</label>
                        <input type="text" 
                               id="shop_name" 
                               name="shop_name" 
                               value="<?php echo htmlspecialchars($current_data['shop_name'] ?? ''); ?>" 
                               placeholder="Enter your shop name"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="payment_methods">Payment Methods</label>
                        <textarea id="payment_methods" 
                                  name="payment_methods" 
                                  rows="1" 
                                  placeholder="e.g. GCash, PayPal, COD"><?php echo htmlspecialchars($current_data['payment_methods'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Shop Logo</label>
                        <?php if (!empty($current_data['logo']) && file_exists($current_data['logo'])): ?>
                            <div style="text-align: center; margin-bottom: 0.5rem;">
                                <p style="font-size: 0.875rem; color: #6b7280;">Current Logo:</p>
                                <img src="<?php echo htmlspecialchars($current_data['logo']); ?>" class="current-image" alt="Current Logo">
                            </div>
                        <?php endif; ?>
                        <div class="file-upload-container">
                            <span style="font-size: 2rem;">📷</span>
                            <div><strong>Click to upload logo</strong><br>PNG, JPG, GIF, WebP</div>
                            <input type="file" name="logo" accept="image/*" onchange="previewImage(event, 'logoPreview')">
                        </div>
                        <img id="logoPreview" class="image-preview" alt="Logo Preview">
                    </div>

                    <div class="form-group">
                        <label>Shop Banner</label>
                        <?php if (!empty($current_data['banner']) && file_exists($current_data['banner'])): ?>
                            <div style="text-align: center; margin-bottom: 0.5rem;">
                                <p style="font-size: 0.875rem; color: #6b7280;">Current Banner:</p>
                                <img src="<?php echo htmlspecialchars($current_data['banner']); ?>" class="current-image" alt="Current Banner">
                            </div>
                        <?php endif; ?>
                        <div class="file-upload-container">
                            <span style="font-size: 2rem;">🖼️</span>
                            <div><strong>Click to upload banner</strong><br>PNG, JPG, GIF, WebP</div>
                            <input type="file" name="banner" accept="image/*" onchange="previewImage(event, 'bannerPreview')">
                        </div>
                        <img id="bannerPreview" class="image-preview" alt="Banner Preview">
                    </div>

                    <div class="form-group full-width">
                        <label for="contact_info">Contact Information</label>
                        <textarea id="contact_info" 
                                  name="contact_info" 
                                  rows="3" 
                                  placeholder="Enter address, phone, email, etc."><?php echo htmlspecialchars($current_data['contact_info'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="policies">Shop Policies</label>
                        <textarea id="policies" 
                                  name="policies" 
                                  rows="4" 
                                  placeholder="Return policy, shipping info, etc."><?php echo htmlspecialchars($current_data['policies'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        💾 Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(event, previewId) {
            const file = event.target.files[0];
            const preview = document.getElementById(previewId);
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.add('show');
            }
            reader.readAsDataURL(file);
        }

        document.getElementById('shopForm').addEventListener('submit', function(e) {
            const shopName = document.getElementById('shop_name').value.trim();
            if (!shopName) {
                alert('Shop name is required.');
                e.preventDefault();
                return;
            }
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '💾 Saving...';
        });

        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(() => { message.style.display = 'none'; }, 500);
            });
        }, 5000);
    </script>
</body>
</html>