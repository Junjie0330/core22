<?php
session_start();
include 'config.php';

// Check for seller ID
$seller_id = $_SESSION['seller_id'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

if (!$seller_id) {
    header("Location: login.php");
    exit;
}

$pdo = getDBConnection();
$sellerId = (int)$seller_id;

// ============================================
// HELPER FUNCTIONS
// ============================================

function sendRefundNotification($email, $orderData, $status, $additionalInfo = []) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = "Refund Update - Order #{$orderData['id']}";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: hak.com\r\n";

    $statusMessages = [
        'approved' => 'Your refund request has been approved!',
        'rejected' => 'Your refund request has been rejected.',
        'completed' => 'Your refund has been processed successfully!',
    ];

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
            .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
            .status { padding: 10px; margin: 15px 0; border-radius: 5px; }
            .status.approved { background: #dbeafe; border-left: 4px solid #3b82f6; }
            .status.rejected { background: #fee2e2; border-left: 4px solid #ef4444; }
            .status.completed { background: #d1fae5; border-left: 4px solid #10b981; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Refund Status Update</h2>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <div class='status {$status}'>
                    <strong>{$statusMessages[$status]}</strong>
                </div>
                <p><strong>Order ID:</strong> #{$orderData['id']}</p>
                <p><strong>Refund Amount:</strong> ₱" . number_format($orderData['refund_amount'] ?? $orderData['total_price'], 2) . "</p>
    ";

    if ($status === 'rejected' && isset($additionalInfo['reason'])) {
        $message .= "<p><strong>Reason:</strong> " . htmlspecialchars($additionalInfo['reason']) . "</p>";
    }

    if ($status === 'completed') {
        $message .= "<p><strong>Refund Method:</strong> " . ucfirst($additionalInfo['method'] ?? 'Manual') . "</p>";
        $message .= "<p>The refund has been processed. Please check your account.</p>";
        if (isset($additionalInfo['notes'])) {
            $message .= "<p><strong>Notes:</strong> " . nl2br(htmlspecialchars($additionalInfo['notes'])) . "</p>";
        }
    }

    $message .= "
                <p>If you have any questions, please contact our support team.</p>
                <p>Thank you for your patience.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Your Store. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return mail($email, $subject, $message, $headers);
}

function logRefundAction($pdo, $orderId, $sellerId, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO refund_logs (order_id, seller_id, action, details, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$orderId, $sellerId, $action, $details]);
    } catch (PDOException $e) {
        error_log("Refund Log: Order #$orderId - $action - $details");
    }
}

function getStatusBadgeClass($status) {
    $classes = [
        'requested' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'approved' => 'bg-blue-100 text-blue-800 border-blue-200',
        'processing' => 'bg-orange-100 text-orange-800 border-orange-200',
        'completed' => 'bg-green-100 text-green-800 border-green-200',
        'rejected' => 'bg-red-100 text-red-800 border-red-200',
    ];
    return $classes[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
}

function formatCurrency($amount) {
    return '₱' . number_format(floatval($amount), 2);
}

function formatDate($date, $format = 'M d, Y') {
    if (!$date || $date === '0000-00-00 00:00:00') return 'N/A';
    return date($format, strtotime($date));
}

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $orderId = (int)$_POST['order_id'];
    $action = trim($_POST['action']);

    try {
        $pdo->beginTransaction();

        // Verify order belongs to seller
        $checkStmt = $pdo->prepare("
            SELECT o.id, o.payment_method, o.refund_status, o.refund_amount, o.total_price,
                   o.payment_transaction_id, c.email as customer_email, c.first_name, c.last_name
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id = ? AND o.seller_id = ?
        ");
        $checkStmt->execute([$orderId, $sellerId]);
        $orderCheck = $checkStmt->fetch();

        if (!$orderCheck) {
            throw new Exception('Order not found or access denied!');
        }

        $currentStatus = $orderCheck['refund_status'];
        $paymentMethod = $orderCheck['payment_method'];
        $refundAmount = $orderCheck['refund_amount'] ?? $orderCheck['total_price'];

        switch ($action) {
            // ============ ADD DISCUSSION ============
            case 'add_discussion':
                $message = trim($_POST['discussion_message'] ?? '');
                $isInternal = isset($_POST['internal_note']) ? 1 : 0;
                
                if (empty($message) || strlen($message) < 3) {
                    throw new Exception('Message must be at least 3 characters');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO refund_discussions (order_id, user_id, user_type, message, is_internal, created_at)
                    VALUES (?, ?, 'seller', ?, ?, NOW())
                ");
                $stmt->execute([$orderId, $sellerId, $message, $isInternal]);

                // Send notification to customer if not internal
                if (!$isInternal) {
                    // Add notification logic here
                }

                $_SESSION['message'] = 'Message added successfully!';
                $_SESSION['message_type'] = 'success';
                break;

            // ============ APPROVE REFUND ============
            case 'approve':
                if ($currentStatus !== 'requested') {
                    throw new Exception('Can only approve pending refund requests');
                }

                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET refund_status = 'approved', 
                        approved_at = NOW(),
                        approved_by = ?
                    WHERE id = ? AND seller_id = ?
                ");
                $stmt->execute([$sellerId, $orderId, $sellerId]);

                logRefundAction($pdo, $orderId, $sellerId, 'approved', 'Refund request approved');
                sendRefundNotification($orderCheck['customer_email'], $orderCheck, 'approved');

                $_SESSION['message'] = 'Refund request approved successfully! Customer has been notified.';
                $_SESSION['message_type'] = 'success';
                break;

            // ============ REJECT REFUND ============
            case 'reject':
                if ($currentStatus !== 'requested' && $currentStatus !== 'approved') {
                    throw new Exception('Can only reject pending or approved refunds');
                }

                $rejectionReason = trim($_POST['rejection_reason'] ?? '');
                if (empty($rejectionReason) || strlen($rejectionReason) < 10) {
                    throw new Exception('Rejection reason must be at least 10 characters');
                }

                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET refund_status = 'rejected', 
                        rejection_reason = ?,
                        rejection_date = NOW(),
                        rejected_by = ?
                    WHERE id = ? AND seller_id = ?
                ");
                $stmt->execute([$rejectionReason, $sellerId, $orderId, $sellerId]);

                logRefundAction($pdo, $orderId, $sellerId, 'rejected', $rejectionReason);
                sendRefundNotification($orderCheck['customer_email'], $orderCheck, 'rejected', [
                    'reason' => $rejectionReason
                ]);

                $_SESSION['message'] = 'Refund request rejected. Customer has been notified.';
                $_SESSION['message_type'] = 'success';
                break;

            // ============ PROCESS REFUND (BOTH COD AND ONLINE) ============
            case 'process_refund':
                if ($currentStatus !== 'approved') {
                    throw new Exception('Order must be approved before processing refund');
                }

                $refundMethod = trim($_POST['refund_method'] ?? '');
                $refundNotes = trim($_POST['refund_notes'] ?? '');

                if (empty($refundMethod)) {
                    throw new Exception('Please select refund method');
                }

                if (empty($refundNotes) || strlen($refundNotes) < 10) {
                    throw new Exception('Refund details must be at least 10 characters');
                }

                // Handle proof upload
                $proofUrl = null;
                if (isset($_FILES['refund_proof']) && $_FILES['refund_proof']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/refund_proofs/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                    $fileType = $_FILES['refund_proof']['type'];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception('Only JPG, PNG, GIF, and PDF files are allowed');
                    }

                    if ($_FILES['refund_proof']['size'] > 5242880) { // 5MB
                        throw new Exception('File size must not exceed 5MB');
                    }

                    $extension = pathinfo($_FILES['refund_proof']['name'], PATHINFO_EXTENSION);
                    $fileName = 'refund_' . $orderId . '_' . uniqid() . '.' . $extension;
                    $filePath = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['refund_proof']['tmp_name'], $filePath)) {
                        $proofUrl = $filePath;
                    } else {
                        throw new Exception('Failed to upload proof file');
                    }
                } else {
                    throw new Exception('Proof of refund is required');
                }

                // Update order with refund details
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET refund_status = 'completed',
                        refund_method_used = ?,
                        refund_notes = ?,
                        refund_proof_url = ?,
                        refund_processed_date = NOW(),
                        refund_processed_by = ?
                    WHERE id = ? AND seller_id = ?
                ");
                $stmt->execute([$refundMethod, $refundNotes, $proofUrl, $sellerId, $orderId, $sellerId]);

                logRefundAction($pdo, $orderId, $sellerId, 'refund_completed', 
                    "Method: $refundMethod | Payment: $paymentMethod | Notes: $refundNotes");

                sendRefundNotification($orderCheck['customer_email'], $orderCheck, 'completed', [
                    'method' => $refundMethod,
                    'notes' => $refundNotes
                ]);

                $_SESSION['message'] = 'Refund marked as completed successfully! Customer has been notified.';
                $_SESSION['message_type'] = 'success';
                break;

            // ============ REOPEN REJECTED REFUND ============
            case 'reopen':
                if ($currentStatus !== 'rejected') {
                    throw new Exception('Can only reopen rejected refunds');
                }

                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET refund_status = 'requested',
                        rejection_reason = NULL,
                        rejection_date = NULL,
                        rejected_by = NULL
                    WHERE id = ? AND seller_id = ?
                ");
                $stmt->execute([$orderId, $sellerId]);

                logRefundAction($pdo, $orderId, $sellerId, 'reopened', 'Refund request reopened for review');

                $_SESSION['message'] = 'Refund request reopened. It\'s now pending your review.';
                $_SESSION['message_type'] = 'success';
                break;

            default:
                throw new Exception('Invalid action');
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }

    header("Location: " . $_SERVER['PHP_SELF'] . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

// ============================================
// FETCH ORDERS WITH PROOF IMAGES
// ============================================

$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, 
            COALESCE(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')), CONCAT('Customer #', o.customer_id)) AS customer_name,
            COALESCE(c.email, '') AS customer_email,
            COALESCE(c.phone, '') AS customer_phone,
            COALESCE(p.name, CONCAT('Product #', o.product_id)) AS product_name,
            COALESCE(p.price, 0) AS product_price,
            o.refund_proof_images,
            o.refund_issue_description
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN products p ON o.product_id = p.id
        WHERE o.seller_id = ? AND o.refund_status IS NOT NULL AND o.refund_status != ''
        ORDER BY 
            CASE o.refund_status
                WHEN 'requested' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'processing' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'rejected' THEN 5
                ELSE 6
            END,
            o.refund_requested_date DESC
    ");
    $stmt->execute([$sellerId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $orders[$row['id']] = $row;
    }
} catch (PDOException $e) {
    $_SESSION['message'] = 'Database error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

// ============================================
// FETCH DISCUSSIONS FOR EACH ORDER
// ============================================
$discussions = [];
try {
    $orderIds = array_keys($orders);
    if (!empty($orderIds)) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = $pdo->prepare("
            SELECT rd.*, 
                CASE 
                    WHEN rd.user_type = 'customer' THEN COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Customer')
                    WHEN rd.user_type = 'seller' THEN 'You (Seller)'
                    ELSE 'System'
                END as sender_name
            FROM refund_discussions rd
            LEFT JOIN customers c ON rd.user_id = c.id AND rd.user_type = 'customer'
            WHERE rd.order_id IN ($placeholders)
            ORDER BY rd.created_at ASC
        ");
        $stmt->execute($orderIds);
        $discussionRows = $stmt->fetchAll();
        
        foreach ($discussionRows as $row) {
            $discussions[$row['order_id']][] = $row;
        }
    }
} catch (PDOException $e) {
    // Silent fail for discussions
}

// ============================================
// FILTER ORDERS
// ============================================

$allowedFilters = ['all', 'requested', 'approved', 'processing', 'completed', 'rejected'];
$filter = in_array($_GET['status'] ?? 'all', $allowedFilters) ? $_GET['status'] : 'all';

$filteredOrders = ($filter === 'all') 
    ? $orders 
    : array_filter($orders, fn($o) => ($o['refund_status'] ?? '') === $filter);

$statusCounts = [
    'all' => count($orders),
    'requested' => count(array_filter($orders, fn($o) => ($o['refund_status'] ?? '') === 'requested')),
    'approved' => count(array_filter($orders, fn($o) => ($o['refund_status'] ?? '') === 'approved')),
    'processing' => count(array_filter($orders, fn($o) => ($o['refund_status'] ?? '') === 'processing')),
    'completed' => count(array_filter($orders, fn($o) => ($o['refund_status'] ?? '') === 'completed')),
    'rejected' => count(array_filter($orders, fn($o) => ($o['refund_status'] ?? '') === 'rejected')),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return/Refund Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .modal-backdrop { backdrop-filter: blur(2px); }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .discussion-message {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .proof-image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        .proof-image {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .proof-image:hover {
            transform: scale(1.05);
        }
        .lightbox {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        .lightbox-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            position: relative;
            top: 50%;
            transform: translateY(-50%);
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Include navbar and sidebar -->
    <?php if (file_exists('includes/navbar.php')): include 'includes/navbar.php'; endif; ?>
    <?php if (file_exists('includes/sidebar.php')): include 'includes/sidebar.php'; endif; ?>

    <!-- Lightbox for images -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <span style="position: absolute; top: 20px; right: 40px; color: white; font-size: 40px; cursor: pointer;">&times;</span>
        <img id="lightbox-img" class="lightbox-content">
    </div>

    <!-- Main Content -->
    <div class="<?= file_exists('includes/sidebar.php') ? 'ml-64' : '' ?> <?= file_exists('includes/navbar.php') ? 'pt-16' : '' ?>">
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-undo-alt mr-3 text-blue-600"></i>Return/Refund Management
                </h1>
                <p class="text-gray-600 mt-2">Process customer return and refund requests with proof verification</p>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-6 p-4 rounded-lg border animate-slide-in <?= $_SESSION['message_type'] === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200' ?>" id="alertMessage">
                <div class="flex items-center justify-between">
                    <span><i class="fas fa-<?= $_SESSION['message_type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i><?= htmlspecialchars($_SESSION['message']) ?></span>
                    <button onclick="closeAlert()" class="text-current hover:opacity-70"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

            <!-- Status Tabs -->
            <div class="mb-6 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 overflow-x-auto">
                    <?php 
                    $tabs = [
                        'all' => ['label' => 'All', 'icon' => 'list'],
                        'requested' => ['label' => 'Pending', 'icon' => 'clock'],
                        'approved' => ['label' => 'Approved', 'icon' => 'check'],
                        'completed' => ['label' => 'Completed', 'icon' => 'check-circle'],
                        'rejected' => ['label' => 'Rejected', 'icon' => 'times-circle'],
                    ];
                    foreach ($tabs as $key => $tab): 
                    ?>
                    <a href="?status=<?= $key ?>" class="<?= $filter === $key ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300' ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                        <i class="fas fa-<?= $tab['icon'] ?>"></i>
                        <?= $tab['label'] ?> 
                        <span class="<?= $filter === $key ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600' ?> py-0.5 px-2 rounded-full text-xs"><?= $statusCounts[$key] ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <?php if (empty($filteredOrders)): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-undo-alt text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg font-medium">No refund requests found</p>
                        <p class="text-gray-500 text-sm mt-2">When customers request refunds, they will appear here</p>
                    </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proof</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($filteredOrders as $order): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-bold text-blue-600">#<?= $order['id'] ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                                    <small class="text-gray-500"><?= htmlspecialchars($order['customer_email']) ?></small>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 bg-gray-100 rounded text-xs font-medium uppercase">
                                        <i class="fas fa-<?= $order['payment_method'] === 'cod' ? 'money-bill-wave' : 'credit-card' ?> mr-1"></i>
                                        <?= strtoupper($order['payment_method'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-green-600"><?= formatCurrency($order['refund_amount'] ?? $order['total_price']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full border <?= getStatusBadgeClass($order['refund_status']) ?>">
                                        <?= ucfirst($order['refund_status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($order['refund_proof_images'])): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-medium">
                                            <i class="fas fa-images mr-1"></i>
                                            <?= count(json_decode($order['refund_proof_images'], true)) ?> image(s)
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">
                                            <i class="fas fa-image-slash mr-1"></i>None
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= formatDate($order['refund_requested_date']) ?></td>
                                <td class="px-6 py-4 text-right">
                                    <button onclick="openModal('modal<?= $order['id'] ?>')" class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals for each order -->
    <?php foreach ($orders as $order): ?>
    <div id="modal<?= $order['id'] ?>" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-5xl w-full max-h-[90vh] overflow-y-auto animate-slide-in">
            <!-- Modal Header -->
            <div class="sticky top-0 px-6 py-4 border-b bg-white flex justify-between items-center z-10">
                <div>
                    <h3 class="text-lg font-bold">Order #<?= $order['id'] ?></h3>
                    <p class="text-sm text-gray-600">
                        Status: <span class="font-bold <?= $order['refund_status'] === 'completed' ? 'text-green-600' : ($order['refund_status'] === 'rejected' ? 'text-red-600' : 'text-blue-600') ?>">
                            <?= ucfirst($order['refund_status']) ?>
                        </span>
                    </p>
                </div>
                <button onclick="closeModal('modal<?= $order['id'] ?>')" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- LEFT COLUMN -->
                    <div class="space-y-6">
                        <!-- Customer Information -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-user mr-2 text-blue-600"></i>Customer Information
                            </h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Name:</span>
                                    <p class="font-medium"><?= htmlspecialchars($order['customer_name']) ?></p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Email:</span>
                                    <p class="font-medium"><?= htmlspecialchars($order['customer_email'] ?: 'N/A') ?></p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Phone:</span>
                                    <p class="font-medium"><?= htmlspecialchars($order['customer_phone'] ?: 'N/A') ?></p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Customer ID:</span>
                                    <p class="font-medium">#<?= $order['customer_id'] ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Refund Details -->
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-money-bill-wave mr-2 text-blue-600"></i>Refund Details
                            </h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Product:</span>
                                    <p class="font-medium"><?= htmlspecialchars($order['product_name']) ?></p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Refund Amount:</span>
                                    <p class="font-bold text-green-600 text-lg"><?= formatCurrency($order['refund_amount'] ?? $order['total_price']) ?></p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Payment Method:</span>
                                    <p class="font-medium uppercase">
                                        <i class="fas fa-<?= $order['payment_method'] === 'cod' ? 'money-bill-wave' : 'credit-card' ?> mr-1"></i>
                                        <?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Refund Method:</span>
                                    <p class="font-medium"><?= htmlspecialchars($order['refund_method_used'] ?? 'Not yet processed') ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Customer's Issue Description -->
                        <?php if ($order['refund_issue_description']): ?>
                        <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                            <h4 class="font-bold text-yellow-900 mb-2 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Issue Description
                            </h4>
                            <p class="text-yellow-800"><?= nl2br(htmlspecialchars($order['refund_issue_description'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Customer's Reason -->
                        <?php if ($order['refund_reason']): ?>
                        <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                            <h4 class="font-bold text-orange-900 mb-2 flex items-center">
                                <i class="fas fa-comment-alt mr-2"></i>Customer's Reason
                            </h4>
                            <p class="text-orange-800"><?= nl2br(htmlspecialchars($order['refund_reason'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Customer's Proof Images -->
                        <?php if (!empty($order['refund_proof_images'])): 
                            $proofImages = json_decode($order['refund_proof_images'], true);
                            if (is_array($proofImages) && count($proofImages) > 0):
                        ?>
                        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                            <h4 class="font-bold text-purple-900 mb-3 flex items-center">
                                <i class="fas fa-images mr-2"></i>Customer's Proof of Issue (<?= count($proofImages) ?> images)
                            </h4>
                            <div class="proof-image-grid">
                                <?php foreach ($proofImages as $img): ?>
                                <div class="relative">
                                    <img 
                                        src="<?= htmlspecialchars($img) ?>" 
                                        alt="Proof" 
                                        class="proof-image w-full h-32 object-cover rounded-lg border-2 border-purple-300 shadow-sm"
                                        onclick="openLightbox('<?= htmlspecialchars($img) ?>')"
                                    >
                                    <div class="absolute bottom-2 right-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
                                        <i class="fas fa-search-plus"></i>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-xs text-purple-700 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>Click any image to view full size
                            </p>
                        </div>
                        <?php endif; endif; ?>

                        <!-- Timeline -->
                        <div class="border-l-4 border-blue-500 pl-4">
                            <h4 class="font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-history mr-2 text-blue-600"></i>Timeline
                            </h4>
                            <div class="space-y-3">
                                <div class="mb-4">
                                    <p class="text-sm text-gray-600">Requested</p>
                                    <p class="font-bold"><?= formatDate($order['refund_requested_date'], 'M d, Y H:i') ?></p>
                                </div>
                                <?php if ($order['approved_at']): ?>
                                <div class="mb-4">
                                    <p class="text-sm text-gray-600">Approved</p>
                                    <p class="font-bold"><?= formatDate($order['approved_at'], 'M d, Y H:i') ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($order['refund_processed_date']): ?>
                                <div class="mb-4">
                                    <p class="text-sm text-gray-600">Processed</p>
                                    <p class="font-bold"><?= formatDate($order['refund_processed_date'], 'M d, Y H:i') ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Refund Completion Details -->
                        <?php if (in_array($order['refund_status'], ['completed'])): ?>
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <h4 class="font-bold text-green-900 mb-3 flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>Refund Completion Details
                            </h4>
                            <div class="space-y-2 text-sm">
                                <p><strong>Method Used:</strong> <?= ucfirst(htmlspecialchars($order['refund_method_used'])) ?></p>
                                <p><strong>Refund Notes:</strong></p>
                                <p class="bg-white p-2 rounded border border-green-300"><?= nl2br(htmlspecialchars($order['refund_notes'])) ?></p>
                                <?php if ($order['refund_proof_url']): ?>
                                <p>
                                    <a href="<?= htmlspecialchars($order['refund_proof_url']) ?>" target="_blank" class="text-green-600 underline flex items-center gap-2 hover:text-green-800">
                                        <i class="fas fa-file-alt"></i> View Proof of Refund
                                    </a>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Rejection Details -->
                        <?php if ($order['refund_status'] === 'rejected' && $order['rejection_reason']): ?>
                        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                            <h4 class="font-bold text-red-900 mb-2 flex items-center">
                                <i class="fas fa-times-circle mr-2"></i>Rejection Reason
                            </h4>
                            <p class="text-red-800"><?= nl2br(htmlspecialchars($order['rejection_reason'])) ?></p>
                            <p class="text-sm text-red-600 mt-2">Rejected: <?= formatDate($order['rejection_date'], 'M d, Y H:i') ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- RIGHT COLUMN - DISCUSSION PANEL -->
                    <div class="space-y-4">
                        <div class="bg-gradient-to-br from-indigo-50 to-blue-50 p-4 rounded-lg border-2 border-indigo-200 sticky top-0">
                            <h4 class="font-bold text-gray-900 mb-3 flex items-center justify-between">
                                <span><i class="fas fa-comments mr-2 text-indigo-600"></i>Discussion Thread</span>
                                <span class="text-xs bg-indigo-600 text-white px-2 py-1 rounded-full">
                                    <?= isset($discussions[$order['id']]) ? count($discussions[$order['id']]) : 0 ?> messages
                                </span>
                            </h4>

                            <!-- Discussion Messages -->
                            <div class="bg-white rounded-lg border border-indigo-200 p-3 mb-3 max-h-96 overflow-y-auto" id="discussionBox<?= $order['id'] ?>">
                                <?php if (isset($discussions[$order['id']]) && count($discussions[$order['id']]) > 0): ?>
                                    <?php foreach ($discussions[$order['id']] as $msg): ?>
                                    <div class="discussion-message mb-3 p-3 rounded-lg <?= $msg['user_type'] === 'seller' ? 'bg-blue-50 border-l-4 border-blue-500' : 'bg-gray-50 border-l-4 border-gray-400' ?>">
                                        <div class="flex items-start justify-between mb-1">
                                            <span class="font-bold text-sm <?= $msg['user_type'] === 'seller' ? 'text-blue-700' : 'text-gray-700' ?>">
                                                <i class="fas fa-<?= $msg['user_type'] === 'seller' ? 'user-shield' : 'user' ?> mr-1"></i>
                                                <?= htmlspecialchars($msg['sender_name']) ?>
                                            </span>
                                            <?php if ($msg['is_internal']): ?>
                                            <span class="text-xs bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded-full">
                                                <i class="fas fa-lock mr-1"></i>Internal
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm text-gray-700 mb-1"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                        <span class="text-xs text-gray-500">
                                            <i class="far fa-clock mr-1"></i><?= formatDate($msg['created_at'], 'M d, Y H:i') ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-400">
                                        <i class="fas fa-comment-slash text-4xl mb-2"></i>
                                        <p class="text-sm">No messages yet. Start the conversation!</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Add Message Form -->
                            <form method="post" class="space-y-3" onsubmit="return validateDiscussionForm(<?= $order['id'] ?>)">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="action" value="add_discussion">
                                
                                <textarea 
                                    id="discussionMessage<?= $order['id'] ?>" 
                                    name="discussion_message" 
                                    rows="3" 
                                    placeholder="Type your message to customer..."
                                    class="w-full px-3 py-2 border border-indigo-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm resize-none"
                                ></textarea>
                                
                                <div class="flex items-center justify-between gap-2">
                                    <label class="flex items-center text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" name="internal_note" class="mr-2 rounded">
                                        <i class="fas fa-lock mr-1 text-yellow-600"></i>
                                        <span>Internal note (hidden from customer)</span>
                                    </label>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium">
                                        <i class="fas fa-paper-plane mr-1"></i>Send
                                    </button>
                                </div>
                            </form>

                            <div class="mt-3 p-2 bg-blue-100 border border-blue-300 rounded text-xs text-blue-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Tip:</strong> Use this to communicate with the customer about the refund process, ask for additional information, or provide updates.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer with Actions -->
            <div class="sticky bottom-0 px-6 py-4 bg-gray-50 border-t flex gap-2 justify-end flex-wrap">
                <?php $status = $order['refund_status']; ?>

                <!-- Pending Requests - Approve/Reject -->
                <?php if ($status === 'requested'): ?>
                <form method="post" class="inline" onsubmit="return confirm('Approve this refund request?')">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition font-medium">
                        <i class="fas fa-check mr-1"></i>Approve Refund
                    </button>
                </form>
                <button type="button" onclick="openRejectModal(<?= $order['id'] ?>)" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition font-medium">
                    <i class="fas fa-times mr-1"></i>Reject Request
                </button>

                <!-- Approved Orders - Process Refund -->
                <?php elseif ($status === 'approved'): ?>
                <button type="button" onclick="openRefundModal(<?= $order['id'] ?>, '<?= htmlspecialchars($order['payment_method']) ?>')" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition font-medium">
                    <i class="fas fa-money-check-alt mr-1"></i>Process Refund
                </button>

                <!-- Rejected - Reopen -->
                <?php elseif ($status === 'rejected'): ?>
                <form method="post" class="inline" onsubmit="return confirm('Reopen this refund request?')">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="action" value="reopen">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition font-medium">
                        <i class="fas fa-redo mr-1"></i>Reopen Request
                    </button>
                </form>
                <?php endif; ?>

                <button onclick="closeModal('modal<?= $order['id'] ?>')" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 transition font-medium">
                    <i class="fas fa-times mr-1"></i>Close
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- ===== REJECT REFUND MODAL ===== -->
    <div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[70] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-md w-full animate-slide-in">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-bold flex items-center">
                    <i class="fas fa-times-circle mr-2 text-red-600"></i>Reject Refund Request
                </h3>
            </div>
            <form method="post" onsubmit="return validateRejectForm()">
                <div class="px-6 py-4 space-y-4">
                    <input type="hidden" name="order_id" id="rejectOrderId">
                    <input type="hidden" name="action" value="reject">
                    
                    <div>
                        <label for="rejection_reason" class="block text-sm font-bold text-gray-700 mb-2">
                            Reason for Rejection <span class="text-red-500">*</span>
                        </label>
                        <textarea 
                            id="rejection_reason" 
                            name="rejection_reason" 
                            rows="4" 
                            maxlength="500"
                            placeholder="Explain why you're rejecting this refund request..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"
                        ></textarea>
                        <div class="flex justify-between mt-2">
                            <p class="text-sm text-gray-600">This reason will be sent to the customer</p>
                            <p class="text-xs text-gray-500"><span id="rejectCharCount">0</span>/500</p>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 font-medium">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 font-medium">
                        <i class="fas fa-times mr-1"></i>Reject Refund
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== PROCESS REFUND MODAL (UNIFIED FOR COD & ONLINE) ===== -->
    <div id="refundModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-[70] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto animate-slide-in">
            <div class="sticky top-0 px-6 py-4 border-b bg-white flex justify-between items-center z-10">
                <h3 class="text-lg font-bold flex items-center">
                    <i class="fas fa-money-check-alt mr-2 text-blue-600"></i>Process Refund
                </h3>
                <button onclick="closeRefundModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post" enctype="multipart/form-data" onsubmit="return validateRefundForm()">
                <div class="px-6 py-4 space-y-6">
                    <input type="hidden" name="order_id" id="refundOrderId">
                    <input type="hidden" name="action" value="process_refund">

                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-800 flex items-start gap-2">
                            <i class="fas fa-info-circle mt-1"></i>
                            <span>Please provide proof of refund for both COD and online payment orders. This helps maintain transparency and record-keeping.</span>
                        </p>
                    </div>

                    <div id="paymentMethodInfo" class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <p class="text-sm font-medium text-gray-700">
                            <i class="fas fa-credit-card mr-2"></i>Payment Method: <span id="displayPaymentMethod" class="font-bold uppercase"></span>
                        </p>
                    </div>

                    <!-- Refund Method -->
                    <div>
                        <label for="refund_method" class="block text-sm font-bold text-gray-700 mb-2">
                            Refund Method <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="refund_method" 
                            name="refund_method" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">-- Select Refund Method --</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                            <option value="credit_card_reversal">Credit Card Reversal</option>
                            <option value="paypal">PayPal</option>
                            <option value="cash">Cash (In Person)</option>
                            <option value="store_credit">Store Credit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Refund Details/Notes -->
                    <div>
                        <label for="refund_notes" class="block text-sm font-bold text-gray-700 mb-2">
                            Refund Details <span class="text-red-500">*</span>
                        </label>
                        <textarea 
                            id="refund_notes" 
                            name="refund_notes" 
                            rows="4"
                            maxlength="500"
                            placeholder="Examples:&#10;• Bank transfer to account ending 1234, Reference: TXN123456&#10;• GCash payment sent to 09XXXXXXXXX&#10;• Credit card refund processed, will reflect in 3-5 days&#10;• Cash refund given on 2024-01-15"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                        ></textarea>
                        <div class="flex justify-between mt-2">
                            <p class="text-sm text-gray-600">Include transaction ID or reference number</p>
                            <p class="text-xs text-gray-500"><span id="refundCharCount">0</span>/500</p>
                        </div>
                    </div>

                    <!-- Proof Upload -->
                    <div>
                        <label for="refund_proof" class="block text-sm font-bold text-gray-700 mb-2">
                            Upload Proof of Refund <span class="text-red-500">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition">
                            <input 
                                type="file" 
                                id="refund_proof" 
                                name="refund_proof" 
                                accept="image/*,.pdf"
                                required
                                class="hidden"
                                onchange="updateFileName()"
                            >
                            <label for="refund_proof" class="cursor-pointer block">
                                <i class="fas fa-cloud-upload-alt text-5xl text-gray-400 mb-3"></i>
                                <p class="text-sm font-medium text-gray-700">Click to upload or drag and drop</p>
                                <p class="text-xs text-gray-500 mt-1">Bank receipt, screenshot, or transaction proof</p>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF, or PDF (Max 5MB)</p>
                                <p id="fileName" class="text-sm text-blue-600 mt-3 font-medium"></p>
                            </label>
                        </div>
                    </div>

                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <p class="text-sm text-yellow-800 flex items-start gap-2">
                            <i class="fas fa-exclamation-triangle mt-1"></i>
                            <span><strong>Important:</strong> Once you mark this refund as completed, the customer will be notified via email. Make sure all information is accurate.</span>
                        </p>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-2">
                    <button type="button" onclick="closeRefundModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 font-medium">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 font-medium">
                        <i class="fas fa-check mr-1"></i>Complete Refund
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ===== LIGHTBOX FUNCTIONS =====
        function openLightbox(imgSrc) {
            document.getElementById('lightbox').style.display = 'block';
            document.getElementById('lightbox-img').src = imgSrc;
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
            document.body.style.overflow = '';
        }

        // ===== MODAL FUNCTIONS =====
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            // Scroll discussion to bottom
            const orderId = id.replace('modal', '');
            const discussionBox = document.getElementById('discussionBox' + orderId);
            if (discussionBox) {
                setTimeout(() => {
                    discussionBox.scrollTop = discussionBox.scrollHeight;
                }, 100);
            }
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = '';
        }

        // ===== DISCUSSION FUNCTIONS =====
        function validateDiscussionForm(orderId) {
            const message = document.getElementById('discussionMessage' + orderId).value.trim();
            if (message.length < 3) {
                alert('Message must be at least 3 characters');
                return false;
            }
            return true;
        }

        // ===== REJECT MODAL FUNCTIONS =====
        function openRejectModal(orderId) {
            document.getElementById('rejectOrderId').value = orderId;
            document.getElementById('rejection_reason').value = '';
            updateRejectCharCount();
            document.getElementById('rejectModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            document.getElementById('rejection_reason').focus();
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        function updateRejectCharCount() {
            const textarea = document.getElementById('rejection_reason');
            const count = document.getElementById('rejectCharCount');
            count.textContent = textarea.value.length;
            if (textarea.value.length > 450) {
                count.parentElement.classList.add('text-red-500');
            } else {
                count.parentElement.classList.remove('text-red-500');
            }
        }

        function validateRejectForm() {
            const reason = document.getElementById('rejection_reason').value.trim();
            if (reason.length < 10) {
                alert('Rejection reason must be at least 10 characters');
                return false;
            }
            return confirm('Are you sure? This message will be sent to the customer.');
        }

        // ===== REFUND MODAL FUNCTIONS =====
        function openRefundModal(orderId, paymentMethod) {
            // Close any open modals first
            document.querySelectorAll('[id^="modal"]').forEach(m => m.classList.add('hidden'));
            
            document.getElementById('refundOrderId').value = orderId;
            document.getElementById('displayPaymentMethod').textContent = paymentMethod.toUpperCase();
            document.getElementById('refund_method').value = '';
            document.getElementById('refund_notes').value = '';
            document.getElementById('refund_proof').value = '';
            document.getElementById('fileName').textContent = '';
            updateRefundCharCount();
            document.getElementById('refundModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeRefundModal() {
            document.getElementById('refundModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        function updateRefundCharCount() {
            const textarea = document.getElementById('refund_notes');
            const count = document.getElementById('refundCharCount');
            count.textContent = textarea.value.length;
            if (textarea.value.length > 450) {
                count.parentElement.classList.add('text-red-500');
            } else {
                count.parentElement.classList.remove('text-red-500');
            }
        }

        function updateFileName() {
            const input = document.getElementById('refund_proof');
            const fileName = document.getElementById('fileName');
            if (input.files.length > 0) {
                const file = input.files[0];
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                fileName.innerHTML = `<i class="fas fa-check-circle text-green-600"></i> ${file.name} (${sizeMB} MB)`;
            } else {
                fileName.textContent = '';
            }
        }

        function validateRefundForm() {
            const method = document.getElementById('refund_method').value;
            const notes = document.getElementById('refund_notes').value.trim();
            const proof = document.getElementById('refund_proof').files;

            if (!method) {
                alert('Please select a refund method');
                return false;
            }

            if (notes.length < 10) {
                alert('Refund details must be at least 10 characters');
                return false;
            }

            if (proof.length === 0) {
                alert('Please upload proof of refund');
                return false;
            }

            const maxSize = 5 * 1024 * 1024; // 5MB
            if (proof[0].size > maxSize) {
                alert('File size must not exceed 5MB');
                return false;
            }

            return confirm('Mark this refund as completed? Customer will be notified via email.');
        }

        // ===== CLOSE ALERT =====
        function closeAlert() {
            const alert = document.getElementById('alertMessage');
            if (alert) {
                alert.style.display = 'none';
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.getElementById('alertMessage');
            if (alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.3s';
                    setTimeout(() => alert.style.display = 'none', 300);
                }, 5000);
            }

            // Character counters
            document.getElementById('rejection_reason')?.addEventListener('input', updateRejectCharCount);
            document.getElementById('refund_notes')?.addEventListener('input', updateRefundCharCount);

            // Close modals on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('[id^="modal"]:not(.hidden)').forEach(m => m.classList.add('hidden'));
                    document.getElementById('rejectModal')?.classList.add('hidden');
                    document.getElementById('refundModal')?.classList.add('hidden');
                    closeLightbox();
                    document.body.style.overflow = '';
                }
            });

            // Close modals on backdrop click
            document.querySelectorAll('.modal-backdrop').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                        document.body.style.overflow = '';
                    }
                });
            });
        });
    </script>

</body>
</html>