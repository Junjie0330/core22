<?php 
include 'config.php';
session_start();

// Check for seller ID
$seller_id = $_SESSION['seller_id'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

// If no seller_id found, redirect to login
if (!$seller_id) {
    header("Location: login.php");
    exit;
}

// Enhanced DB Connection function

function displayConnectionError($errorMsg) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100">
        <div class="min-h-screen flex items-center justify-center py-12 px-4">
            <div class="max-w-md w-full bg-white shadow-lg rounded-lg p-8">
                <div class="text-center mb-6">
                    <div class="mx-auto h-12 w-12 text-red-600 mb-4">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Database Connection Error</h2>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="text-red-800 text-sm">
                        <?= htmlspecialchars($errorMsg) ?>
                    </div>
                </div>
                
                <div class="text-center">
                    <button onclick="location.reload()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Retry Connection
                    </button>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Initialize database connection
$pdo = getDBConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    if ($order_id && $action) {
        try {
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'processing' WHERE id = ? AND seller_id = ?");
                    $stmt->execute([$order_id, $seller_id]);
                    $message = "Order #$order_id has been approved.";
                    break;
                    
                case 'cancel':
                    $reason = $_POST['cancel_reason'] ?? 'No reason provided';
                    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ? AND seller_id = ?");
                    $stmt->execute([$order_id, $seller_id]);
                    $message = "Order #$order_id has been cancelled.";
                    break;
                    
                case 'ship':
                    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'shipped' WHERE id = ? AND seller_id = ?");
                    $stmt->execute([$order_id, $seller_id]);
                    $message = "Order #$order_id has been shipped.";
                    break;
                    
                case 'deliver':
                    $stmt = $pdo->prepare("UPDATE orders SET order_status = 'delivered' WHERE id = ? AND seller_id = ?");
                    $stmt->execute([$order_id, $seller_id]);
                    $message = "Order #$order_id has been delivered.";
                    break;
                    
                default:
                    $error = "Invalid action.";
                    break;
            }
            
            if (isset($message)) {
                $_SESSION['success_message'] = $message;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Display success message
$success_message = $_SESSION['success_message'] ?? null;
if ($success_message) {
    unset($_SESSION['success_message']);
}

// Fetch orders for this seller with customer details
$orders = [];
try {
    // Primary query - try the full join first
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COALESCE(o.order_status, 'pending') as status,
               CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
               c.email as customer_email, 
               c.phone as customer_phone,
               c.address as customer_address,
               p.name as product_name,
               p.price as product_price
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        LEFT JOIN products p ON o.product_id = p.id 
        WHERE o.seller_id = ? 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$seller_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up customer names and provide fallbacks
    foreach($orders as &$order) {
        $order['status'] = $order['status'] ?? 'pending'; // Ensure status is always set
        $order['customer_name'] = trim($order['customer_name']) ?: ('Customer #' . $order['customer_id']);
        $order['customer_email'] = $order['customer_email'] ?: 'N/A';
        $order['customer_phone'] = $order['customer_phone'] ?: 'N/A';
        $order['customer_address'] = trim($order['customer_address']) ?: 'N/A';
        $order['product_name'] = $order['product_name'] ?: ('Product #' . ($order['product_id'] ?? 'N/A'));
        $order['product_price'] = $order['product_price'] ?? $order['total_price'] ?? 0;
    }
    
} catch(PDOException $e) {
    // Fallback query - just get orders
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE seller_id = ? ORDER BY created_at DESC");
        $stmt->execute([$seller_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add default values for missing data
        foreach($orders as &$order) {
            $order['status'] = $order['status'] ?? 'pending'; // Ensure status is always set
            $order['customer_name'] = 'Customer #' . $order['customer_id'];
            $order['customer_email'] = 'N/A';
            $order['customer_phone'] = 'N/A';
            $order['customer_address'] = 'N/A';
            $order['product_name'] = 'Product #' . ($order['product_id'] ?? 'N/A');
            $order['product_price'] = $order['total_price'] ?? 0;
        }
        
    } catch(PDOException $e2) {
        $error = "Unable to fetch orders: " . $e2->getMessage();
        $orders = [];
    }
}

// Helper function for status colors
function getStatusColor($status) {
    $status = $status ?? 'pending'; // Safety check
    switch($status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'processing': return 'bg-blue-100 text-blue-800';
        case 'shipped': return 'bg-indigo-100 text-indigo-800';
        case 'delivered': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

// Filter orders by status for tabs
function filterOrdersByStatus($orders, $status) {
    if ($status === 'all') return $orders;
    return array_filter($orders, function($order) use ($status) {
        $orderStatus = $order['status'] ?? 'pending';
        return $orderStatus === $status;
    });
}

$all_orders = $orders;
$pending_orders = filterOrdersByStatus($orders, 'pending');
$processing_orders = filterOrdersByStatus($orders, 'processing');
$shipped_orders = filterOrdersByStatus($orders, 'shipped');
$delivered_orders = filterOrdersByStatus($orders, 'delivered');
$cancelled_orders = filterOrdersByStatus($orders, 'cancelled');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.show {
            display: flex;
        }
        
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        .btn-action {
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="bg-gray-50">
   
    
    <?php if (file_exists('includes/sidebar.php')): ?>
        <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="<?= (file_exists('includes/sidebar.php')) ? 'ml-64' : '' ?> <?= (file_exists('includes/navbar.php')) ? 'mt-16' : '' ?> p-6">
        <div class="container mx-auto">
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="bi bi-cart3 mr-3"></i>
                    Order Management
                </h1>
                <p class="text-gray-600 mt-2">Manage and track your orders</p>
            </div>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6" id="successAlert">
                    <div class="flex items-center">
                        <i class="bi bi-check-circle text-green-400 mr-2"></i>
                        <p class="text-green-700"><?= htmlspecialchars($success_message) ?></p>
                        <button onclick="closeAlert('successAlert')" class="ml-auto text-green-400 hover:text-green-600">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6" id="errorAlert">
                    <div class="flex items-center">
                        <i class="bi bi-exclamation-circle text-red-400 mr-2"></i>
                        <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                        <button onclick="closeAlert('errorAlert')" class="ml-auto text-red-400 hover:text-red-600">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Order Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600">Total Orders</p>
                            <p class="text-2xl font-bold text-gray-900"><?= count($all_orders) ?></p>
                        </div>
                        <i class="bi bi-cart text-gray-400 text-2xl"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-yellow-600"><?= count($pending_orders) ?></p>
                        </div>
                        <i class="bi bi-clock text-yellow-400 text-2xl"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600">Processing</p>
                            <p class="text-2xl font-bold text-blue-600"><?= count($processing_orders) ?></p>
                        </div>
                        <i class="bi bi-check-circle text-blue-400 text-2xl"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600">Shipped</p>
                            <p class="text-2xl font-bold text-indigo-600"><?= count($shipped_orders) ?></p>
                        </div>
                        <i class="bi bi-truck text-indigo-400 text-2xl"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600">Delivered</p>
                            <p class="text-2xl font-bold text-green-600"><?= count($delivered_orders) ?></p>
                        </div>
                        <i class="bi bi-check-all text-green-400 text-2xl"></i>
                    </div>
                </div>
                <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-1">
                            <p class="text-sm text-gray-600">Cancelled</p>
                            <p class="text-2xl font-bold text-red-600"><?= count($cancelled_orders) ?></p>
                        </div>
                        <i class="bi bi-x-circle text-red-400 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Orders Section with Tabs -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button onclick="switchTab('all')" class="tab-button py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap border-blue-500 text-blue-600" data-tab="all">
                            All Orders (<?= count($all_orders) ?>)
                        </button>
                        <button onclick="switchTab('pending')" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap" data-tab="pending">
                            Pending (<?= count($pending_orders) ?>)
                        </button>
                        <button onclick="switchTab('processing')" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap" data-tab="processing">
                            Processing (<?= count($processing_orders) ?>)
                        </button>
                        <button onclick="switchTab('shipped')" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap" data-tab="shipped">
                            Shipped (<?= count($shipped_orders) ?>)
                        </button>
                        <button onclick="switchTab('delivered')" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap" data-tab="delivered">
                            Delivered (<?= count($delivered_orders) ?>)
                        </button>
                        <button onclick="switchTab('cancelled')" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap" data-tab="cancelled">
                            Cancelled (<?= count($cancelled_orders) ?>)
                        </button>
                    </nav>
                </div>

                <!-- Tab Contents -->
                <?php 
                $tabs = [
                    'all' => $all_orders,
                    'pending' => $pending_orders,
                    'processing' => $processing_orders,
                    'shipped' => $shipped_orders,
                    'delivered' => $delivered_orders,
                    'cancelled' => $cancelled_orders
                ];

                foreach ($tabs as $tab_name => $tab_orders): ?>
                    <div id="<?= $tab_name ?>-content" class="tab-content <?= $tab_name === 'all' ? 'active' : '' ?> p-6">
                        <?php if(empty($tab_orders)): ?>
                            <div class="text-center py-12">
                                <i class="bi bi-inbox text-6xl text-gray-300 mb-4 block"></i>
                                <h3 class="text-xl font-medium text-gray-900 mb-2">No Orders Found</h3>
                                <p class="text-gray-600">No <?= $tab_name === 'all' ? '' : $tab_name ?> orders found.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Order #</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Customer</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Total</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Date</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                    <?php foreach($tab_orders as $order): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                                #<?= htmlspecialchars($order['id']) ?>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-600">
                                                <?= htmlspecialchars($order['customer_name']) ?>
                                            </td>
                                            <td class="px-4 py-4 text-sm font-semibold text-gray-900">
                                                ₱<?= number_format($order['total_price'] ?? $order['price'] ?? 0, 2) ?>
                                            </td>
                                            <td class="px-4 py-4">
                                                <?php 
                                                $orderStatus = $order['status'] ?? 'pending';
                                                ?>
                                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full <?= getStatusColor($orderStatus) ?>">
                                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $orderStatus))) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-600">
                                                <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                            </td>
                                            <td class="px-4 py-4">
                                                <button onclick='viewOrderDetails(<?= json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn-action bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm font-medium">
                                                    <i class="bi bi-eye mr-1"></i>View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Order Detail Modal -->
    <div class="modal" id="orderDetailModal">
        <div class="bg-white rounded-lg max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Order Details</h2>
                <button onclick="closeModal('orderDetailModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Order Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-3">Order Information</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Order ID:</span>
                                <span class="font-medium" id="modal-order-id"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span id="modal-order-status"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Amount:</span>
                                <span class="font-semibold text-lg" id="modal-order-total"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Order Date:</span>
                                <span id="modal-order-date"></span>
                            </div>
                            <div id="modal-cancellation-reason-row" class="hidden">
                                <div class="flex justify-between items-start">
                                    <span class="text-gray-600">Cancellation Reason:</span>
                                    <span class="font-medium text-red-600 text-right" id="modal-cancellation-reason"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-3">Customer Information</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Name:</span>
                                <span class="font-medium" id="modal-customer-name"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Email:</span>
                                <span id="modal-customer-email"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Phone:</span>
                                <span id="modal-customer-phone"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Address:</span>
                                <span id="modal-shipping-address"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Order Items</h3>
                    <div id="modal-order-items" class="space-y-3">
                        <!-- Items will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="border-t border-gray-200 pt-4">
                    <div class="flex justify-end space-x-3" id="modal-actions">
                        <!-- Action buttons will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal" id="cancelOrderModal">
        <div class="bg-white rounded-lg max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <i class="bi bi-exclamation-triangle text-yellow-500 text-2xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-gray-900">Cancel Order</h3>
                </div>
                
                <form id="cancelOrderForm" method="POST">
                    <input type="hidden" name="order_id" id="cancel-order-id">
                    <input type="hidden" name="action" value="cancel">
                    
                    <div class="mb-4">
                        <label for="cancel_reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Reason for cancellation <span class="text-red-500">*</span>
                        </label>
                        <select name="cancel_reason" id="cancel_reason" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select a reason...</option>
                            <option value="Out of stock">Out of stock</option>
                            <option value="Customer requested cancellation">Customer requested cancellation</option>
                            <option value="Payment issues">Payment issues</option>
                            <option value="Unable to fulfill order">Unable to fulfill order</option>
                            <option value="Duplicate order">Duplicate order</option>
                            <option value="Pricing error">Pricing error</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="button" onclick="closeModal('cancelOrderModal')" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded font-medium transition-colors">
                            Keep Order
                        </button>
                        <button type="submit" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-medium transition-colors">
                            Cancel Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Tab switching
    function switchTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Remove active class from all tab buttons
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('border-blue-500', 'text-blue-600');
            button.classList.add('border-transparent', 'text-gray-500');
        });
        
        // Show selected tab content
        document.getElementById(tabName + '-content').classList.add('active');
        
        // Add active class to selected tab button
        const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
        activeButton.classList.add('border-blue-500', 'text-blue-600');
        activeButton.classList.remove('border-transparent', 'text-gray-500');
    }

    // Modal Functions
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    // View order details
    function viewOrderDetails(order) {
        // Ensure status exists with default value
        if (!order.status) {
            order.status = 'pending';
        }
        
        // Populate modal with order data
        document.getElementById('modal-order-id').textContent = '#' + order.id;
        document.getElementById('modal-order-total').textContent = '₱' + parseFloat(order.total_price || order.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('modal-order-date').textContent = new Date(order.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Status badge
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'approved': 'bg-blue-100 text-blue-800',
            'shipped': 'bg-indigo-100 text-indigo-800',
            'delivered': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800'
        };
        const statusElement = document.getElementById('modal-order-status');
        statusElement.innerHTML = `<span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full ${statusColors[order.status] || 'bg-gray-100 text-gray-800'}">${order.status.charAt(0).toUpperCase() + order.status.slice(1).replace('_', ' ')}</span>`;
        
        // Customer info
        document.getElementById('modal-customer-name').textContent = order.customer_name || ('Customer ' + order.customer_id);
        document.getElementById('modal-customer-email').textContent = order.customer_email || 'N/A';
        document.getElementById('modal-customer-phone').textContent = order.customer_phone || 'N/A';
        document.getElementById('modal-shipping-address').textContent = order.customer_address || order.shipping_address || 'N/A';
        
        // Show/hide cancellation reason
        const cancellationReasonRow = document.getElementById('modal-cancellation-reason-row');
        const cancellationReasonText = document.getElementById('modal-cancellation-reason');
        
        if (order.status === 'cancelled' && order.cancellation_reason) {
            cancellationReasonRow.classList.remove('hidden');
            cancellationReasonText.textContent = order.cancellation_reason;
        } else {
            cancellationReasonRow.classList.add('hidden');
        }
        
        // Action buttons based on status
        const actionsElement = document.getElementById('modal-actions');
        let actionButtons = '';
        
        if (order.status === 'pending') {
            actionButtons = `
                <button onclick="cancelOrderFromModal(${order.id})" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-medium transition-colors">
                    <i class="bi bi-x mr-1"></i>Cancel Order
                </button>
                <button onclick="approveOrder(${order.id})" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded font-medium transition-colors">
                    <i class="bi bi-check mr-1"></i>Approve Order
                </button>
                <button onclick="closeModal('orderDetailModal')" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded font-medium transition-colors">
                    <i class="bi bi-x-lg mr-1"></i>Close
                </button>
            `;
        } else if (order.status === 'approved') {
            actionButtons = `
                <button onclick="shipOrder(${order.id})" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded font-medium transition-colors">
                    <i class="bi bi-truck mr-1"></i>Ship Order
                </button>
                <button onclick="closeModal('orderDetailModal')" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded font-medium transition-colors">
                    <i class="bi bi-x-lg mr-1"></i>Close
                </button>
            `;
        } else if (order.status === 'shipped') {
            actionButtons = `
                <button onclick="deliverOrder(${order.id})" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded font-medium transition-colors">
                    <i class="bi bi-check-circle mr-1"></i>Mark as Delivered
                </button>
                <button onclick="closeModal('orderDetailModal')" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded font-medium transition-colors">
                    <i class="bi bi-x-lg mr-1"></i>Close
                </button>
            `;
        } else {
            actionButtons = `
                <button onclick="closeModal('orderDetailModal')" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded font-medium transition-colors">
                    <i class="bi bi-x-lg mr-1"></i>Close
                </button>
            `;
        }
        
        actionsElement.innerHTML = actionButtons;
        
        // Load order items
        const orderItemsHtml = `
            <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                <div class="w-16 h-16 bg-gray-200 rounded-lg mr-4 flex items-center justify-center">
                    <i class="bi bi-box text-gray-400 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-medium text-gray-900">${order.product_name || 'Product #' + (order.product_id || 'N/A')}</h4>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-sm text-gray-600">Quantity: ${order.quantity || 1}</span>
                        <span class="text-sm font-medium text-gray-900">₱${parseFloat(order.product_price || order.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                    </div>
                    <div class="mt-1">
                        <span class="text-xs text-gray-500">Total: ₱${parseFloat(order.total_price || order.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('modal-order-items').innerHTML = orderItemsHtml;
        
        // Open modal
        openModal('orderDetailModal');
    }

    // Cancel order from modal
    function cancelOrderFromModal(orderId) {
        document.getElementById('cancel-order-id').value = orderId;
        closeModal('orderDetailModal');
        openModal('cancelOrderModal');
    }

    // Order action functions
    function approveOrder(orderId) {
        if (confirm('Are you sure you want to approve this order?')) {
            submitAction('approve', orderId);
        }
    }

    function shipOrder(orderId) {
        if (confirm('Are you sure you want to mark this order as shipped?')) {
            submitAction('ship', orderId);
        }
    }

    function deliverOrder(orderId) {
        if (confirm('Are you sure you want to mark this order as delivered?')) {
            submitAction('deliver', orderId);
        }
    }

    // Submit action
    function submitAction(action, orderId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>';
        form.innerHTML = `
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="action" value="${action}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    // Close alert function
    function closeAlert(alertId) {
        document.getElementById(alertId).style.display = 'none';
    }

    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);
    });

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
        }
    });
    </script>

</body>
</html> 