<?php
session_start();

// Check if seller is logged in
if (!isset($_SESSION['seller_id'])) {
    // For testing purposes, set a default seller_id
    // REMOVE THIS IN PRODUCTION and redirect to login
    $_SESSION['seller_id'] = 1;
    // header('Location: login.php');
    // exit;
}

// Database configuration
$host = 'localhost';
$dbname = 'core2_test';
$username = 'root';
$password = '';

// Get the logged-in seller ID from session
$seller_id = $_SESSION['seller_id'];

// Handle AJAX request for saving booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    header('Content-Type: application/json');
    
    try {
        // Create PDO connection
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get JSON data from request
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        
        // Validate required fields
        if (empty($data['logistics']) || empty($data['service']) || empty($data['dateTime'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }
        
        // Generate unique order ID
        $order_id = 'ORD-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        // Map logistics partner codes to full names
        $logistics_map = [
            'jnt' => 'J&T Express',
            'lbc' => 'LBC Express',
            'ninja' => 'Ninja Van',
            'flash' => 'Flash Express'
        ];
        
        // Map service codes to full names
        $service_map = [
            'standard' => 'Standard Delivery (3-5 days)',
            'express' => 'Express Delivery (Next day)',
            'sameday' => 'Same Day Delivery'
        ];
        
        $logistics_name = $logistics_map[$data['logistics']] ?? $data['logistics'];
        $service_name = $service_map[$data['service']] ?? $data['service'];
        
        // Calculate delivery fee based on service type
        $delivery_fees = [
            'standard' => 50.00,
            'express' => 100.00,
            'sameday' => 150.00
        ];
        $delivery_fee = $delivery_fees[$data['service']] ?? 50.00;
        
        // Get customer info from order ID if provided, or use provided data
        $customer_name = $data['customer_name'] ?? 'Walk-in Customer';
        $customer_email = $data['customer_email'] ?? '';
        $destination = $data['destination'] ?? '';
        
        // Prepare SQL statement - INCLUDING seller_id
        $sql = "INSERT INTO logistics_bookings (
            order_id,
            seller_id,
            logistics_partner, 
            service_type, 
            pickup_datetime, 
            special_instructions, 
            customer_name,
            customer_email,
            destination,
            delivery_fee,
            status,
            created_at
        ) VALUES (
            :order_id,
            :seller_id,
            :logistics_partner, 
            :service_type, 
            :pickup_datetime, 
            :special_instructions,
            :customer_name,
            :customer_email,
            :destination,
            :delivery_fee,
            'pending',
            NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':seller_id', $seller_id, PDO::PARAM_INT);
        $stmt->bindParam(':logistics_partner', $logistics_name);
        $stmt->bindParam(':service_type', $service_name);
        $stmt->bindParam(':pickup_datetime', $data['dateTime']);
        $stmt->bindParam(':special_instructions', $data['instructions']);
        $stmt->bindParam(':customer_name', $customer_name);
        $stmt->bindParam(':customer_email', $customer_email);
        $stmt->bindParam(':destination', $destination);
        $stmt->bindParam(':delivery_fee', $delivery_fee);
        
        // Execute the statement
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Booking saved successfully',
                'order_id' => $order_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save booking'
            ]);
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Fetch bookings ONLY for this seller with order data
$bookings = [];
$error_message = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get bookings ONLY for the logged-in seller
    $sql = "SELECT 
                lb.id,
                lb.order_id as booking_order_id,
                lb.seller_id,
                lb.logistics_partner,
                lb.service_type,
                lb.pickup_datetime,
                lb.special_instructions,
                lb.status,
                lb.customer_name,
                lb.customer_email,
                lb.destination,
                lb.delivery_fee,
                lb.created_at,
                o.total_price,
                o.shipping_address,
                o.shipping_city,
                o.shipping_postal_code
            FROM logistics_bookings lb
            LEFT JOIN orders o ON o.tracking_number COLLATE utf8mb4_general_ci = lb.order_id COLLATE utf8mb4_general_ci 
                               AND o.seller_id = :seller_id
            WHERE lb.seller_id = :seller_id
            ORDER BY lb.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':seller_id', $seller_id, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Fetch seller information
$seller_info = [];
try {
    $sql = "SELECT shop_name, name, CONCAT(first_name, ' ', last_name) as full_name FROM sellers WHERE id = :seller_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':seller_id', $seller_id, PDO::PARAM_INT);
    $stmt->execute();
    $seller_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Determine display name priority: shop_name > name > full_name
    $display_name = $seller_info['shop_name'] ?? ($seller_info['name'] ?? ($seller_info['full_name'] ?? 'Seller'));
} catch (PDOException $e) {
    $display_name = 'Seller';
}

// Fetch available orders for this seller that don't have bookings yet
$available_orders = [];
try {
    $sql = "SELECT 
                o.id,
                o.tracking_number,
                o.total_price,
                o.shipping_address,
                o.shipping_city,
                o.shipping_postal_code
            FROM orders o
            LEFT JOIN logistics_bookings lb ON o.tracking_number COLLATE utf8mb4_general_ci = lb.order_id COLLATE utf8mb4_general_ci
            WHERE o.seller_id = :seller_id 
            AND lb.id IS NULL
            AND o.order_status IN ('pending', 'processing')
            ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':seller_id', $seller_id, PDO::PARAM_INT);
    $stmt->execute();
    $available_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silent fail for available orders
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Coordination - <?php echo htmlspecialchars($display_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <?php include 'includes/sidebar.php';?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-left {
            flex: 1;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.75rem;
            font-weight: 600;
            color: #4285f4;
            margin-bottom: 5px;
        }

        .seller-badge {
            display: inline-block;
            background: #e8f1ff;
            color: #4285f4;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .header-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #4285f4;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #1a73e8;
        }

        .btn-secondary {
            background: #ffffff;
            border: 1px solid #dee2e6;
            color: #4285f4;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #4285f4;
        }

        .error-alert {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 20px;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
            margin-bottom: 20px;
        }

        .filter-tabs {
            display: flex;
            gap: 2px;
            margin-bottom: 20px;
            background: #ffffff;
            border-radius: 8px;
            padding: 4px;
            border: 1px solid #dee2e6;
            width: fit-content;
        }

        .filter-tab {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-tab.active {
            background: #4285f4;
            color: white;
        }

        .filter-tab:not(.active):hover {
            background: #f8f9fa;
            color: #495057;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f8f9fa;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #dee2e6;
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        .order-id {
            color: #4285f4;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .logistics-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            font-weight: 600;
        }

        .logistics-jnt { background: linear-gradient(135deg, #ff6b35, #f7931e); }
        .logistics-lbc { background: linear-gradient(135deg, #dc3545, #c82333); }
        .logistics-ninja { background: linear-gradient(135deg, #6f42c1, #8b5cf6); }
        .logistics-flash { background: linear-gradient(135deg, #28a745, #20c997); }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-pickup { background: #cce7ff; color: #0056b3; }
        .status-transit { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .price {
            font-weight: 700;
            font-size: 1rem;
            color: #28a745;
        }

        .date-text {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .track-btn { background: #4285f4; color: white; }
        .track-btn:hover { background: #1a73e8; }

        .print-btn { background: #28a745; color: white; }
        .print-btn:hover { background: #218838; }

        .edit-btn { background: #ffc107; color: #212529; }
        .edit-btn:hover { background: #e0a800; }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .tracking-modal-content {
            max-width: 700px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #495057;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            color: #495057;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #4285f4;
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .tracking-timeline {
            margin: 30px 0;
        }

        .timeline-track {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            margin-bottom: 40px;
        }

        .timeline-track::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(to right, #4285f4 0%, #4285f4 66%, #ddd 66%, #ddd 100%);
            z-index: 0;
        }

        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1;
            flex: 1;
            position: relative;
        }

        .timeline-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            border: 4px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .timeline-step.active .timeline-circle {
            border-color: #4285f4;
            background: #e8f1ff;
            box-shadow: 0 0 0 8px rgba(66, 133, 244, 0.1);
        }

        .timeline-step.completed .timeline-circle {
            border-color: #28a745;
            background: #e8f5e9;
        }

        .timeline-label {
            text-align: center;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
            margin-top: 10px;
        }

        .timeline-date {
            text-align: center;
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .timeline-step.active .timeline-label {
            color: #4285f4;
        }

        .timeline-step.completed .timeline-label {
            color: #28a745;
        }

        .tracking-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #6c757d;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        .receipt-container {
            background: white;
            padding: 30px;
            border: 2px solid #333;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            max-width: 500px;
            text-align: center;
        }

        .receipt-header {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .receipt-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .receipt-section {
            text-align: left;
            margin-bottom: 20px;
            border-bottom: 1px dashed #333;
            padding-bottom: 15px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .receipt-label {
            font-weight: bold;
        }

        .receipt-qr {
            margin: 20px 0;
            text-align: center;
        }

        .receipt-qr svg {
            width: 120px;
            height: 120px;
        }

        .receipt-barcode {
            margin: 20px 0;
            font-size: 1.2rem;
            letter-spacing: 4px;
            font-weight: bold;
        }

        .receipt-footer {
            margin-top: 20px;
            font-size: 0.85rem;
            font-style: italic;
        }

        .receipt-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-container, .receipt-container * {
                visibility: visible;
            }
            .receipt-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .receipt-actions {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .table-container {
                overflow-x: auto;
            }
            
            .table {
                min-width: 1000px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: stretch;
            }

            .timeline-track::before {
                top: 35px;
            }

            .timeline-circle {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container">
            <div class="header">
                <div class="header-left">
                    <h1 class="header-title">
                        🚚 Logistics Coordination
                        <span class="seller-badge">👤 <?php echo htmlspecialchars($display_name); ?></span>
                    </h1>
                    <p class="header-subtitle">Manage your deliveries, track shipments, and coordinate with logistics partners</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="showBookPickupModal()">
                        📦 Book Pickup
                    </button>
                </div>
            </div>

            <?php if ($error_message): ?>
            <div class="error-alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterDeliveries('all')">
                    📋 All Deliveries
                </button>
                <button class="filter-tab" onclick="filterDeliveries('pending')">
                    ⏳ Pending Pickup
                </button>
                <button class="filter-tab" onclick="filterDeliveries('transit')">
                    🚛 In Transit
                </button>
                <button class="filter-tab" onclick="filterDeliveries('delivered')">
                    ✅ Delivered
                </button>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Logistics Partner</th>
                            <th>Customer</th>
                            <th>Destination</th>
                            <th>Service</th>
                            <th>Order Total</th>
                            <th>Delivery Fee</th>
                            <th>Status</th>
                            <th>Pickup Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="deliveriesTable">
                        <?php
                        if (empty($bookings)) {
                            echo '<tr><td colspan="10" style="text-align: center; padding: 40px; color: #6c757d;">
                                    <div style="font-size: 3rem;">📦</div>
                                    <div style="margin-top: 10px; font-size: 1.1rem;">No bookings found</div>
                                    <div style="margin-top: 5px; font-size: 0.9rem;">Click "Book Pickup" to create your first booking</div>
                                  </td></tr>';
                        } else {
                            foreach ($bookings as $booking) {
                                // Determine logistics icon and abbreviation
                                $logistics_class = 'logistics-jnt';
                                $logistics_abbr = 'J&T';
                                
                                if (stripos($booking['logistics_partner'], 'LBC') !== false) {
                                    $logistics_class = 'logistics-lbc';
                                    $logistics_abbr = 'LBC';
                                } elseif (stripos($booking['logistics_partner'], 'Ninja') !== false) {
                                    $logistics_class = 'logistics-ninja';
                                    $logistics_abbr = 'NV';
                                } elseif (stripos($booking['logistics_partner'], 'Flash') !== false) {
                                    $logistics_class = 'logistics-flash';
                                    $logistics_abbr = 'FE';
                                }
                                
                                // Format status
                                $status = strtolower($booking['status']);
                                $status_class = 'status-' . $status;
                                $status_icon = '⏳';
                                $status_text = ucfirst($status);
                                
                                if ($status === 'pending') {
                                    $status_icon = '⏳';
                                    $status_text = 'Pending Pickup';
                                } elseif ($status === 'pickup' || $status === 'transit') {
                                    $status_class = 'status-transit';
                                    $status_icon = '🚛';
                                    $status_text = 'In Transit';
                                } elseif ($status === 'delivered') {
                                    $status_icon = '✅';
                                    $status_text = 'Delivered';
                                } elseif ($status === 'cancelled') {
                                    $status_icon = '❌';
                                    $status_text = 'Cancelled';
                                }
                                
                                // Format date
                                $pickup_date = new DateTime($booking['pickup_datetime']);
                                $formatted_date = $pickup_date->format('M d, Y');
                                $formatted_time = $pickup_date->format('h:i A');
                                
                                // Get destination - prioritize order address over booking destination
                                $destination = '';
                                if (!empty($booking['shipping_address'])) {
                                    $destination = $booking['shipping_address'];
                                    if (!empty($booking['shipping_city'])) {
                                        $destination .= ', ' . $booking['shipping_city'];
                                    }
                                    if (!empty($booking['shipping_postal_code'])) {
                                        $destination .= ' ' . $booking['shipping_postal_code'];
                                    }
                                } else {
                                    $destination = $booking['destination'] ?: 'N/A';
                                }
                                
                                // Customer info
                                $customer_name = $booking['customer_name'] ?: 'N/A';
                                $customer_email = $booking['customer_email'] ?: '';
                                
                                // Get total price from order if available
                                $order_total = !empty($booking['total_price']) ? '₱' . number_format($booking['total_price'], 2) : 'N/A';
                                $delivery_fee = $booking['delivery_fee'] ? '₱' . number_format($booking['delivery_fee'], 2) : '₱0.00';
                                
                                echo '<tr data-status="' . htmlspecialchars($status) . '">';
                                echo '<td><span class="order-id">#' . htmlspecialchars($booking['booking_order_id']) . '</span></td>';
                                echo '<td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="logistics-icon ' . $logistics_class . '">' . $logistics_abbr . '</div>
                                            <div>
                                                <div style="font-weight: 600;">' . htmlspecialchars($booking['logistics_partner']) . '</div>
                                                <div style="font-size: 0.8rem; color: #6c757d;">' . htmlspecialchars($booking['service_type']) . '</div>
                                            </div>
                                        </div>
                                      </td>';
                                echo '<td>
                                        <div>
                                            <div style="font-weight: 600;">' . htmlspecialchars($customer_name) . '</div>';
                                if ($customer_email) {
                                    echo '<div style="font-size: 0.8rem; color: #6c757d;">' . htmlspecialchars($customer_email) . '</div>';
                                }
                                echo '  </div>
                                      </td>';
                                echo '<td>
                                        <div style="font-size: 0.85rem; line-height: 1.4;">' . nl2br(htmlspecialchars($destination)) . '</div>
                                      </td>';
                                echo '<td>
                                        <div style="font-size: 0.85rem;">' . htmlspecialchars($booking['service_type']) . '</div>
                                      </td>';
                                echo '<td><span class="price">' . $order_total . '</span></td>';
                                echo '<td><span class="price">' . $delivery_fee . '</span></td>';
                                echo '<td><span class="status-badge ' . $status_class . '">' . $status_icon . ' ' . $status_text . '</span></td>';
                                echo '<td>
                                        <div class="date-text">' . $formatted_date . '<br>' . $formatted_time . '</div>
                                      </td>';
                                echo '<td>
                                        <div class="actions">
                                            <button class="action-btn track-btn" onclick="trackDelivery(\'' . htmlspecialchars($booking['booking_order_id']) . '\', \'' . htmlspecialchars($status) . '\')" title="Track">🔍</button>
                                            <button class="action-btn print-btn" onclick="printLabel(\'' . htmlspecialchars($booking['booking_order_id']) . '\', \'' . htmlspecialchars(addslashes($customer_name)) . '\', \'' . htmlspecialchars(addslashes($destination)) . '\', \'' . $delivery_fee . '\', \'' . htmlspecialchars(addslashes($booking['logistics_partner'])) . '\', \'' . $order_total . '\')" title="Print Label">🖨️</button>';
                                if ($status === 'pending') {
                                    echo '<button class="action-btn edit-btn" onclick="editDelivery(\'' . htmlspecialchars($booking['booking_order_id']) . '\')" title="Edit">✏️</button>';
                                }
                                echo '  </div>
                                      </td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tracking Modal -->
    <div class="modal" id="trackingModal">
        <div class="modal-content tracking-modal-content">
            <div class="modal-header">
                <h3 class="modal-title">📦 Track Your Order</h3>
                <button class="close-btn" onclick="closeModal('trackingModal')">&times;</button>
            </div>

            <div class="tracking-timeline">
                <div class="timeline-track">
                    <div class="timeline-step completed">
                        <div class="timeline-circle">🛍️</div>
                        <div class="timeline-label">Order Placed</div>
                        <div class="timeline-date">Sep 20, 2025</div>
                    </div>
                    <div class="timeline-step" id="step-shipped">
                        <div class="timeline-circle">📦</div>
                        <div class="timeline-label">Shipped</div>
                        <div class="timeline-date" id="date-shipped">Sep 21, 2025</div>
                    </div>
                    <div class="timeline-step" id="step-transit">
                        <div class="timeline-circle">🚚</div>
                        <div class="timeline-label">In Transit</div>
                        <div class="timeline-date" id="date-transit">Sep 22, 2025</div>
                    </div>
                    <div class="timeline-step" id="step-delivered">
                        <div class="timeline-circle">✅</div>
                        <div class="timeline-label">Delivered</div>
                        <div class="timeline-date" id="date-delivered">Sep 24, 2025</div>
                    </div>
                </div>
            </div>

            <div class="tracking-details">
                <div class="detail-row">
                    <span class="detail-label">Tracking Number:</span>
                    <span class="detail-value" id="trackingNum">TRK1234567890</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Logistics Partner:</span>
                    <span class="detail-value" id="logisticsPartner">J&T Express</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Current Location:</span>
                    <span class="detail-value" id="currentLocation">Distribution Center</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Estimated Delivery:</span>
                    <span class="detail-value" id="estimatedDelivery">Sep 24, 2025</span>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="closeModal('trackingModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Print Receipt Modal -->
    <div class="modal" id="printReceiptModal">
        <div class="modal-content">
            <div class="receipt-container" id="receiptContent">
                <div class="receipt-header"><?php echo strtoupper(substr($display_name, 0, 15)); ?></div>
                <div class="receipt-title">SHIPPING LABEL</div>
                
                <div class="receipt-section">
                    <div class="receipt-row">
                        <span class="receipt-label">TRACKING #:</span>
                        <span id="receiptTrackingNum"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">ORDER ID:</span>
                        <span id="receiptOrderId"></span>
                    </div>
                </div>

                <div class="receipt-section">
                    <div class="receipt-row">
                        <span class="receipt-label">SHIP FROM:</span>
                    </div>
                    <div style="font-size: 0.85rem; margin-left: 10px;"><?php echo htmlspecialchars($display_name); ?></div>
                    <div style="font-size: 0.85rem; margin-left: 10px;">Seller ID: <?php echo $seller_id; ?></div>
                    
                    <div class="receipt-row" style="margin-top: 10px;">
                        <span class="receipt-label">SHIP TO:</span>
                    </div>
                    <div style="font-size: 0.85rem; margin-left: 10px;" id="receiptCustomer"></div>
                    <div style="font-size: 0.85rem; margin-left: 10px;" id="receiptDestination"></div>
                </div>

                <div class="receipt-section">
                    <div class="receipt-row">
                        <span class="receipt-label">SERVICE:</span>
                        <span id="receiptService"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">ORDER TOTAL:</span>
                        <span id="receiptOrderTotal"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">DELIVERY FEE:</span>
                        <span id="receiptFee"></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">DATE:</span>
                        <span id="receiptDate"></span>
                    </div>
                </div>

                <div class="receipt-qr">
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <rect width="100" height="100" fill="white" stroke="black" stroke-width="2"/>
                        <rect x="10" y="10" width="30" height="30" fill="black"/>
                        <rect x="60" y="10" width="30" height="30" fill="black"/>
                        <rect x="10" y="60" width="30" height="30" fill="black"/>
                        <circle cx="50" cy="50" r="8" fill="black"/>
                    </svg>
                </div>

                <div class="receipt-barcode" id="receiptBarcode">1234 5678 9012 3456</div>

                <div class="receipt-footer">
                    <p>Thank you for your business!</p>
                    <p>Keep this receipt for your records</p>
                </div>
            </div>

            <div class="receipt-actions">
                <button class="btn btn-primary" onclick="window.print()">🖨️ Print Receipt</button>
                <button class="btn btn-secondary" onclick="closeModal('printReceiptModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Book Pickup Modal -->
    <div class="modal" id="bookPickupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">📦 Book Pickup</h3>
                <button class="close-btn" onclick="closeModal('bookPickupModal')">&times;</button>
            </div>
            <form id="pickupForm">
                <div class="form-group">
                    <label class="form-label">Select Order (Optional)</label>
                    <select class="form-select" id="pickupOrder" onchange="loadOrderDetails(this.value)">
                        <option value="">Manual Entry (No Order)</option>
                        <?php foreach ($available_orders as $order): ?>
                            <option value="<?php echo htmlspecialchars($order['id']); ?>" 
                                    data-address="<?php echo htmlspecialchars($order['shipping_address'] . ', ' . $order['shipping_city'] . ' ' . $order['shipping_postal_code']); ?>"
                                    data-total="<?php echo htmlspecialchars($order['total_price']); ?>">
                                Order #<?php echo htmlspecialchars($order['tracking_number']); ?> - ₱<?php echo number_format($order['total_price'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Customer Name</label>
                    <input type="text" class="form-input" id="pickupCustomerName" placeholder="Enter customer name">
                </div>
                <div class="form-group">
                    <label class="form-label">Customer Email</label>
                    <input type="email" class="form-input" id="pickupCustomerEmail" placeholder="customer@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Destination Address</label>
                    <textarea class="form-textarea" id="pickupDestination" placeholder="Full delivery address with city and postal code"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Logistics Partner</label>
                    <select class="form-select" id="pickupLogistics" required>
                        <option value="">Select logistics partner</option>
                        <option value="jnt">J&T Express</option>
                        <option value="lbc">LBC Express</option>
                        <option value="ninja">Ninja Van</option>
                        <option value="flash">Flash Express</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Service Type</label>
                    <select class="form-select" id="pickupService" required onchange="updateDeliveryFee()">
                        <option value="">Select service</option>
                        <option value="standard">Standard Delivery (3-5 days) - ₱50.00</option>
                        <option value="express">Express Delivery (Next day) - ₱100.00</option>
                        <option value="sameday">Same Day Delivery - ₱150.00</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Pickup Date & Time</label>
                    <input type="datetime-local" class="form-input" id="pickupDateTime" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Special Instructions</label>
                    <textarea class="form-textarea" id="pickupInstructions" placeholder="Any special pickup instructions..."></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('bookPickupModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Book Pickup</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Available orders data
        const availableOrders = <?php echo json_encode($available_orders); ?>;

        // Load order details when order is selected
        function loadOrderDetails(orderId) {
            if (!orderId) {
                document.getElementById('pickupDestination').value = '';
                document.getElementById('pickupCustomerName').value = '';
                document.getElementById('pickupCustomerEmail').value = '';
                return;
            }

            const order = availableOrders.find(o => o.id == orderId);
            if (order) {
                const address = order.shipping_address + ', ' + order.shipping_city + ' ' + order.shipping_postal_code;
                document.getElementById('pickupDestination').value = address;
            }
        }

        // Update delivery fee display
        function updateDeliveryFee() {
            const serviceSelect = document.getElementById('pickupService');
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            console.log('Selected service:', selectedOption.text);
        }

        // Filter deliveries by status
        function filterDeliveries(status) {
            const tabs = document.querySelectorAll('.filter-tab');
            const rows = document.querySelectorAll('#deliveriesTable tr');

            tabs.forEach(tab => tab.classList.remove('active'));
            event.currentTarget.classList.add('active');

            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (!rowStatus) return;
                
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Modal functions
        function showBookPickupModal() {
            document.getElementById('bookPickupModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Tracking delivery function with timeline
        function trackDelivery(orderId, status) {
            document.querySelectorAll('.timeline-step').forEach(step => {
                step.classList.remove('active', 'completed');
            });

            const shippedStep = document.getElementById('step-shipped');
            const transitStep = document.getElementById('step-transit');
            const deliveredStep = document.getElementById('step-delivered');

            if (status === 'pending') {
                shippedStep.classList.add('active');
            } else if (status === 'transit' || status === 'pickup') {
                shippedStep.classList.add('completed');
                transitStep.classList.add('active');
            } else if (status === 'delivered') {
                shippedStep.classList.add('completed');
                transitStep.classList.add('completed');
                deliveredStep.classList.add('completed');
            }

            document.getElementById('trackingNum').textContent = 'TRK' + Math.random().toString(36).substr(2, 9).toUpperCase();
            
            const currentLocation = status === 'delivered' ? 'Delivered to Customer' : 
                                   status === 'transit' ? 'In Transit - Distribution Center' :
                                   'Pending Pickup';
            document.getElementById('currentLocation').textContent = currentLocation;

            document.getElementById('trackingModal').classList.add('show');
        }

        // Print Label function with receipt popup
        function printLabel(orderId, customer, destination, fee, logistics, orderTotal) {
            const trackingNum = 'TRK' + Math.random().toString(36).substr(2, 9).toUpperCase();
            const barcode = Math.random().toString().substr(2, 16).replace(/\D/g, '').substr(0, 16);
            const today = new Date().toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });

            document.getElementById('receiptOrderId').textContent = orderId;
            document.getElementById('receiptTrackingNum').textContent = trackingNum;
            document.getElementById('receiptCustomer').textContent = customer;
            document.getElementById('receiptDestination').textContent = destination;
            document.getElementById('receiptService').textContent = logistics;
            document.getElementById('receiptOrderTotal').textContent = orderTotal || 'N/A';
            document.getElementById('receiptFee').textContent = fee;
            document.getElementById('receiptDate').textContent = today;
            
            const formattedBarcode = barcode.match(/.{1,4}/g).join(' ');
            document.getElementById('receiptBarcode').textContent = formattedBarcode;

            document.getElementById('printReceiptModal').classList.add('show');
        }

        // Action functions
        function editDelivery(orderId) {
            alert(`Edit functionality for order ${orderId} would be implemented here.\nThis would open a modal with pre-filled data for editing.`);
        }

        // Form submission with database save
        document.getElementById('pickupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                logistics: document.getElementById('pickupLogistics').value,
                service: document.getElementById('pickupService').value,
                dateTime: document.getElementById('pickupDateTime').value,
                instructions: document.getElementById('pickupInstructions').value || '',
                customer_name: document.getElementById('pickupCustomerName').value || 'Walk-in Customer',
                customer_email: document.getElementById('pickupCustomerEmail').value || '',
                destination: document.getElementById('pickupDestination').value || ''
            };

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Booking...';

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Pickup booked successfully!\n\nOrder ID: ${data.order_id}\n\nThe page will now reload to show your new booking.`);
                    closeModal('bookPickupModal');
                    location.reload();
                } else {
                    alert('❌ Error booking pickup:\n' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error booking pickup:\n' + error.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Set minimum date to today for the date picker
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            const dateTimeInput = document.getElementById('pickupDateTime');
            if (dateTimeInput) {
               dateTimeInput.min = now.toISOString().slice(0, 16);
            }
        });
    </script>
</body>
</html>