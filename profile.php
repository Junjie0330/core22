<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Database Configuration
$host = "localhost";
$user = "root";
$pass = "";
$db   = "core2_test";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

$success = false;
$error = "";

// Fetch user data from sellers table
$user_sql = "SELECT * FROM sellers WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $seller_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_shop') {
    try {
        // Get form data
        $full_name = trim($_POST["full_name"]);
        $email_address = trim($_POST["email_address"]);
        $shop_name = trim($_POST["shop_name"]);
        $contact_info = trim($_POST["contact_info"]);
        $policies = trim($_POST["policies"]);
        $payment_methods = trim($_POST["payment_methods"]);
        
        $logo = $user["logo"];
        $banner = $user["banner"];

        // Create uploads directory
        $upload_dir = "uploads/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Handle logo upload
        if (isset($_FILES["logo"]) && $_FILES["logo"]["error"] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES["logo"]["type"];
            
            if (in_array($file_type, $allowed_types) && $_FILES["logo"]["size"] <= 5000000) {
                $file_extension = pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION);
                $new_filename = "logo_" . time() . "." . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["logo"]["tmp_name"], $upload_path)) {
                    if ($user["logo"] && file_exists($user["logo"])) {
                        unlink($user["logo"]);
                    }
                    $logo = $upload_path;
                }
            }
        }

        // Handle banner upload
        if (isset($_FILES["banner"]) && $_FILES["banner"]["error"] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES["banner"]["type"];
            
            if (in_array($file_type, $allowed_types) && $_FILES["banner"]["size"] <= 5000000) {
                $file_extension = pathinfo($_FILES["banner"]["name"], PATHINFO_EXTENSION);
                $new_filename = "banner_" . time() . "." . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["banner"]["tmp_name"], $upload_path)) {
                    if ($user["banner"] && file_exists($user["banner"])) {
                        unlink($user["banner"]);
                    }
                    $banner = $upload_path;
                }
            }
        }

        // Update sellers table with all information
        $update_sql = "UPDATE sellers SET 
                        name = ?, 
                        email = ?, 
                        shop_name = ?, 
                        contact_info = ?, 
                        policies = ?, 
                        payment_methods = ?, 
                        logo = ?, 
                        banner = ? 
                      WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssssi", 
            $full_name, 
            $email_address, 
            $shop_name, 
            $contact_info, 
            $policies, 
            $payment_methods, 
            $logo, 
            $banner, 
            $seller_id
        );
        
        if ($update_stmt->execute()) {
            $success = true;
            
            // Update session data
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_email'] = $email_address;
            
            // Refresh user data
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("i", $seller_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user = $user_result->fetch_assoc();
            $user_stmt->close();
        } else {
            throw new Exception("Failed to update profile.");
        }

        $update_stmt->close();
        
    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
    }
}

$conn->close();

// Helper function to format the date
function format_date($date_string) {
    if (empty($date_string)) return 'N/A';
    return date('F j, Y', strtotime($date_string));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <?php if (file_exists('includes/sidebar.php')) include 'includes/sidebar.php';?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif; background-color: #f8f9fa; color: #333; line-height: 1.6; padding: 20px; }
        .main-content { margin-left: 250px; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header-left { flex: 1; }
        .header-title { display: flex; align-items: center; gap: 10px; font-size: 1.75rem; font-weight: 600; color: #4285f4; margin-bottom: 5px; }
        .header-subtitle { color: #6c757d; font-size: 0.95rem; }
        .edit-btn { background: #ffffff; border: 1px solid #dee2e6; border-radius: 6px; padding: 8px 16px; color: #4285f4; text-decoration: none; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s ease; }
        .edit-btn:hover { background: #f8f9fa; border-color: #4285f4; }
        .profile-container { background: white; border-radius: 8px; border: 1px solid #dee2e6; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .profile-header { background: #f8f9fa; padding: 20px; display: flex; align-items: center; gap: 20px; border-bottom: 1px solid #dee2e6; }
        .avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #4285f4, #1a73e8); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: white; font-weight: bold; }
        .profile-info { flex: 1; }
        .user-name { font-size: 1.5rem; font-weight: 600; color: #212529; margin-bottom: 5px; }
        .user-role { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; background: #cce7ff; color: #0056b3; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-weight: 600; font-size: 0.85rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #dee2e6; width: 25%; }
        .info-table td { padding: 16px; border-bottom: 1px solid #f1f3f4; vertical-align: middle; }
        .info-table tr:last-child td { border-bottom: none; }
        .info-table tr:hover { background-color: #f8f9fa; }
        .info-label { font-weight: 600; color: #495057; }
        .info-value { color: #6c757d; }
        .actions-section { background: white; border-radius: 8px; border: 1px solid #dee2e6; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .actions-header { background: #f8f9fa; padding: 16px 20px; border-bottom: 1px solid #dee2e6; }
        .actions-title { font-size: 1.1rem; font-weight: 600; color: #495057; }
        .actions-content { padding: 20px; }
        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .action-btn { padding: 10px 16px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; color: #495057; text-decoration: none; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
        .action-btn:hover { background: #e9ecef; border-color: #4285f4; color: #4285f4; }
        
        /* Shop Settings Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 2% auto; padding: 0; border: none; border-radius: 8px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .modal-title { margin: 0; font-size: 1.5rem; font-weight: 600; color: #495057; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .modal-body { padding: 20px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; transition: border-color 0.15s ease-in-out; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        .full-width { grid-column: 1 / -1; }
        .file-upload-container { position: relative; border: 2px dashed #dee2e6; border-radius: 6px; padding: 30px; text-align: center; background: #f8f9fa; cursor: pointer; transition: all 0.2s ease; margin-bottom: 10px; }
        .file-upload-container:hover { border-color: #007bff; background: #f0f8ff; }
        .file-upload-container input[type="file"] { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .upload-icon { font-size: 2rem; margin-bottom: 10px; }
        .upload-text { color: #6c757d; }
        .current-image, .image-preview { max-width: 200px; max-height: 150px; border-radius: 6px; margin: 10px auto; display: block; }
        .image-preview { display: none; }
        .image-preview.show { display: block; }
        .button-container { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #545b62; }
        .logout-btn { background-color: #dc3545; color: white; border: none; }
        .logout-btn:hover { background-color: #c82333; }
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; }
            .form-grid { grid-template-columns: 1fr; } 
            .header { flex-direction: column; align-items: flex-start; gap: 15px; } 
            .profile-header { flex-direction: column; text-align: center; } 
            .action-buttons { flex-direction: column; } 
            .modal-content { width: 95%; margin: 5% auto; } 
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container">
            <div class="header">
                <div class="header-left">
                    <h1 class="header-title">👤 User Profile</h1>
                    <p class="header-subtitle">View and manage your account information</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="edit-btn" onclick="openShopModal()">✏️ Edit Profile</button>
                    <a href="logout.php" class="edit-btn logout-btn">🚪 Logout</a>
                </div>
            </div>

            <?php if ($user): ?>
            <div class="profile-container">
                <div class="profile-header">
                    <div class="avatar">
                        <?php 
                            $name_parts = explode(' ', htmlspecialchars($user['name'] ?? ''));
                            $initials = ($name_parts[0] ? strtoupper(substr($name_parts[0], 0, 1)) : '') . (isset($name_parts[1]) ? strtoupper(substr($name_parts[1], 0, 1)) : '');
                            echo $initials ?: 'U';
                        ?>
                    </div>
                    <div class="profile-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></div>
                        <span class="user-role">🏪 Seller Account</span>
                    </div>
                </div>

                <table class="info-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Information</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="info-label">Email Address</span></td>
                            <td><span class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span></td>
                        </tr>
                        <tr>
                            <td><span class="info-label">Contact Info</span></td>
                            <td><span class="info-value"><?php echo htmlspecialchars($user['contact_info'] ?? 'Not Provided'); ?></span></td>
                        </tr>
                        <tr>
                            <td><span class="info-label">Shop Name</span></td>
                            <td><span class="info-value"><?php echo htmlspecialchars($user['shop_name'] ?? 'Not Set'); ?></span></td>
                        </tr>
                        <tr>
                            <td><span class="info-label">Payment Methods</span></td>
                            <td><span class="info-value"><?php echo htmlspecialchars($user['payment_methods'] ?? 'Not Set'); ?></span></td>
                        </tr>
                        <tr>
                            <td><span class="info-label">Member Since</span></td>
                            <td><span class="info-value"><?php echo format_date($user['created_at']); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="actions-section">
                <div class="actions-header">
                    <h3 class="actions-title">Account Actions</h3>
                </div>
                <div class="actions-content">
                    <div class="action-buttons">
                        <button class="action-btn" onclick="openShopModal()">🏪 Manage Shop</button>
                        <button class="action-btn" onclick="alert('Password change feature coming soon!')">🔒 Change Password</button>
                        <button class="action-btn" onclick="alert('Notification settings coming soon!')">🔔 Notifications</button>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
                <div class="profile-container" style="padding: 40px; text-align: center;">
                    <h2>User Not Found</h2>
                    <p>The requested user profile could not be found in the database.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Shop Settings Modal -->
    <div id="shopModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">✏️ Edit Profile</h2>
                <span class="close" onclick="closeShopModal()">&times;</span>
            </div>
            <div class="modal-body">
                <?php if ($success): ?>
                    <div class="message success-message">
                        ✅ Profile updated successfully!
                    </div>
                <?php elseif ($error): ?>
                    <div class="message error-message">
                        ❌ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_shop">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="email_address">Email Address *</label>
                            <input type="email" id="email_address" name="email_address" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="shop_name">Shop Name *</label>
                            <input type="text" id="shop_name" name="shop_name" 
                                   value="<?php echo htmlspecialchars($user['shop_name'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="payment_methods">Payment Methods</label>
                            <input type="text" id="payment_methods" name="payment_methods" 
                                   value="<?php echo htmlspecialchars($user['payment_methods'] ?? ''); ?>" 
                                   placeholder="e.g. GCash, PayPal, COD">
                        </div>

                        <div class="form-group">
                            <label>Shop Logo</label>
                            <div class="file-upload-container">
                                <span class="upload-icon">📷</span>
                                <div class="upload-text">
                                    <strong>Click to upload logo</strong><br>
                                    PNG, JPG, GIF, WebP (Max 5MB)
                                </div>
                                <input type="file" name="logo" accept="image/*" onchange="previewImage(event, 'logoPreview')">
                            </div>
                            <?php if (!empty($user['logo']) && file_exists($user['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($user['logo']); ?>" class="current-image" alt="Current Logo">
                            <?php endif; ?>
                            <img id="logoPreview" class="image-preview" alt="Logo Preview">
                        </div>

                        <div class="form-group">
                            <label>Shop Banner</label>
                            <div class="file-upload-container">
                                <span class="upload-icon">🖼️</span>
                                <div class="upload-text">
                                    <strong>Click to upload banner</strong><br>
                                    PNG, JPG, GIF, WebP (Max 5MB)
                                </div>
                                <input type="file" name="banner" accept="image/*" onchange="previewImage(event, 'bannerPreview')">
                            </div>
                            <?php if (!empty($user['banner']) && file_exists($user['banner'])): ?>
                                <img src="<?php echo htmlspecialchars($user['banner']); ?>" class="current-image" alt="Current Banner">
                            <?php endif; ?>
                            <img id="bannerPreview" class="image-preview" alt="Banner Preview">
                        </div>

                        <div class="form-group full-width">
                            <label for="contact_info">Contact Information</label>
                            <textarea id="contact_info" name="contact_info" rows="3" 
                                      placeholder="Address, phone number, etc."><?php echo htmlspecialchars($user['contact_info'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="policies">Shop Policies</label>
                            <textarea id="policies" name="policies" rows="4" 
                                      placeholder="Return policy, shipping info, etc."><?php echo htmlspecialchars($user['policies'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="button-container">
                        <button type="button" class="btn btn-secondary" onclick="closeShopModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            💾 Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openShopModal() {
            document.getElementById('shopModal').style.display = 'block';
        }

        function closeShopModal() {
            document.getElementById('shopModal').style.display = 'none';
        }

        function previewImage(event, previewId) {
            const file = event.target.files[0];
            const preview = document.getElementById(previewId);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.add('show');
                }
                reader.readAsDataURL(file);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('shopModal');
            if (event.target == modal) {
                closeShopModal();
            }
        }

        // Auto-hide messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(() => { message.style.display = 'none'; }, 500);
            });
        }, 5000);

        // Auto-open modal if there's a success/error message
        <?php if ($success || $error): ?>
            document.getElementById('shopModal').style.display = 'block';
        <?php endif; ?>
    </script>
</body>
</html>