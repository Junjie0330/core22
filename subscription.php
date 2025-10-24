<?php

// Only execute the API logic if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set headers for JSON response
    header('Content-Type: application/json');
    // ... (rest of the headers are the same)

    // --- DATABASE CONFIGURATION IS THE SAME ---
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "core2_test";
    $conn = new mysqli($host, $user, $pass, $db);
    // ... (rest of the connection check is the same)

    $conn->set_charset("utf8mb4");

    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input (user_email and user_name are no longer needed from the front-end)
    if (!$data || !isset($data['plan_id']) || !isset($data['plan_name']) || !isset($data['price'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: plan_id, plan_name, price']);
        exit;
    }

    // Extract data
    $plan_id = trim($data['plan_id']);
    $plan_name = trim($data['plan_name']);
    $price = floatval($data['price']);

    // ===================================================================
    // MODIFICATION: GET USER ID FROM SESSION (HARDCODED FOR EXAMPLE)
    // ===================================================================
    // In a real application, you would get this from the user's login session
    // For example: $user_id = $_SESSION['user_id'];
    $user_id = 1; // We assume the logged-in user has an ID of 1

    try {
        // Start transaction
        $conn->autocommit(FALSE);

        // 1. Check if the subscription plan is valid and active (No change here)
        $check_plan_sql = "SELECT id FROM subscription_plans WHERE plan_id = ? AND status = 'active'";
        $check_plan_stmt = $conn->prepare($check_plan_sql);
        $check_plan_stmt->bind_param("s", $plan_id);
        $check_plan_stmt->execute();
        if ($check_plan_stmt->get_result()->num_rows === 0) {
            throw new Exception('Invalid or inactive subscription plan');
        }
        $check_plan_stmt->close();


        // ===================================================================
        // MODIFICATION: GET USER INFO FROM 'users' TABLE
        // ===================================================================
        // 2. Get user information from the `users` table
        $user_sql = "SELECT name, email FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();

        if ($user_result->num_rows === 0) {
            throw new Exception('Logged-in user not found.');
        }

        $user_data = $user_result->fetch_assoc();
        $user_email = $user_data['email']; // Get email from the database
        $user_name = $user_data['name'];   // Get name from the database
        $user_stmt->close();


        // 3. Check for existing active subscription for this user (using fetched email)
        $check_existing_sql = "SELECT id, expiry_date FROM subscriptions WHERE user_email = ? AND status = 'active'";
        $check_existing_stmt = $conn->prepare($check_existing_sql);
        $check_existing_stmt->bind_param("s", $user_email);
        $check_existing_stmt->execute();
        $existing_result = $check_existing_stmt->get_result();

        if ($existing_result->num_rows > 0) {
            $existing_sub = $existing_result->fetch_assoc();
            if ($existing_sub['expiry_date'] && strtotime($existing_sub['expiry_date']) > time()) {
                throw new Exception('You already have an active subscription.');
            }
        }
        $check_existing_stmt->close();
        
        // 4. Calculate expiry date (No change here)
        $subscription_date = date('Y-m-d H:i:s');
        $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));

        // 5. Insert new subscription using the fetched user details
        $insert_subscription_sql = "INSERT INTO subscriptions (plan_id, plan_name, price, user_email, user_name, status, subscription_date, expiry_date) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)";
        $insert_stmt = $conn->prepare($insert_subscription_sql);
        // Bind the fetched $user_email and $user_name
        $insert_stmt->bind_param("ssdssss", $plan_id, $plan_name, $price, $user_email, $user_name, $subscription_date, $expiry_date);
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to create subscription: ' . $insert_stmt->error);
        }
        $subscription_id = $conn->insert_id;
        $insert_stmt->close();

        // 6. Cancel any other active subscriptions for this user
        $cancel_others_sql = "UPDATE subscriptions SET status = 'cancelled' WHERE user_email = ? AND id != ?";
        $cancel_stmt = $conn->prepare($cancel_others_sql);
        $cancel_stmt->bind_param("si", $user_email, $subscription_id);
        $cancel_stmt->execute();
        $cancel_stmt->close();

        // Commit and respond
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully subscribed to {$plan_name}!",
            'data' => [
                'subscription_id' => $subscription_id,
                'plan_id' => $plan_id,
                'user_email' => $user_email, // Respond with the user's actual email
                'user_name' => $user_name
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } finally {
        $conn->autocommit(TRUE);
        $conn->close();
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            padding: 20px;
            margin-left: 240px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f8f9fa;
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }

        .table td {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        .plan-id {
            color: #4285f4;
            font-weight: 600;
            font-size: 0.9rem;
            display: block;
        }

        .plan-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .plan-icon.basic { background: linear-gradient(135deg, #28a745, #20c997); }
        .plan-icon.pro { background: linear-gradient(135deg, #4285f4, #1a73e8); }
        .plan-icon.enterprise { background: linear-gradient(135deg, #6f42c1, #8b5cf6); }

        .plan-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .plan-name {
            font-weight: 600;
            font-size: 1.05rem;
            color: #212529;
        }

        .plan-description {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .features-list {
            color: #6c757d;
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .price {
            font-weight: 700;
            font-size: 1.25rem;
            color: #28a745;
            display: block;
        }

        .price-period {
            color: #6c757d;
            font-weight: normal;
            font-size: 0.85rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-popular {
            background: #cce7ff;
            color: #0056b3;
        }

        .status-premium {
            background: #e2d9f3;
            color: #5a2d7c;
        }

        .subscribe-btn {
            background: linear-gradient(135deg, #4285f4, #1a73e8);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 24px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            min-width: 120px;
        }

        .subscribe-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
        }

        .subscribe-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .subscribe-btn.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .subscribe-btn.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(400px);
            transition: all 0.3s ease;
            max-width: 400px;
        }

        .notification.success {
            background: #28a745;
        }

        .notification.error {
            background: #dc3545;
        }

        .notification.show {
            transform: translateX(0);
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading.show {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4285f4;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .current-plan {
            background: #fff3cd;
            border: 2px solid #ffc107;
        }

        @media (max-width: 768px) {
            body {
                margin-left: 0;
                padding: 10px;
            }

            .table-container {
                overflow-x: auto;
            }
            
            .table {
                min-width: 900px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Subscription Plans</h1>
            <p>Choose the perfect plan to grow your business and reach more customers</p>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 100px;">PLAN ID</th>
                        <th style="width: 80px;">ICON</th>
                        <th style="width: 200px;">NAME</th>
                        <th style="width: 280px;">FEATURES</th>
                        <th style="width: 140px;">PRICE</th>
                        <th style="width: 140px;">STATUS</th>
                        <th style="width: 140px;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="plan-id">#BASIC</span>
                        </td>
                        <td>
                            <div class="plan-icon basic">🌟</div>
                        </td>
                        <td>
                            <div class="plan-info">
                                <div class="plan-name">Basic Plan</div>
                                <div class="plan-description">Get started with essentials</div>
                            </div>
                        </td>
                        <td>
                            <div class="features-list">
                                5 products auto-boosted 6hrs daily<br>
                                2,000 homepage impressions
                            </div>
                        </td>
                        <td>
                            <span class="price">Free</span>
                            <span class="price-period">/month</span>
                        </td>
                        <td>
                            <span class="status-badge status-active">✅ Current Plan</span>
                        </td>
                        <td>
                            <button class="subscribe-btn" disabled>
                                Current Plan
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="plan-id">#PRO</span>
                        </td>
                        <td>
                            <div class="plan-icon pro">⚡</div>
                        </td>
                        <td>
                            <div class="plan-info">
                                <div class="plan-name">Pro Plan</div>
                                <div class="plan-description">Scale your business reach</div>
                            </div>
                        </td>
                        <td>
                            <div class="features-list">
                                10 products boosted 12hrs daily<br>
                                5,000 impressions + banner slots
                            </div>
                        </td>
                        <td>
                            <span class="price">₱499.00</span>
                            <span class="price-period">/month</span>
                        </td>
                        <td>
                            <span class="status-badge status-popular">⭐ Popular</span>
                        </td>
                        <td>
                            <button class="subscribe-btn" onclick="subscribe('PRO', 'Pro Plan', 499.00, this)">
                                Subscribe
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="plan-id">#ENTERPRISE</span>
                        </td>
                        <td>
                            <div class="plan-icon enterprise">👑</div>
                        </td>
                        <td>
                            <div class="plan-info">
                                <div class="plan-name">Enterprise Plan</div>
                                <div class="plan-description">Maximum visibility & growth</div>
                            </div>
                        </td>
                        <td>
                            <div class="features-list">
                                20 products boosted 24/7<br>
                                15,000 impressions + priority placement
                            </div>
                        </td>
                        <td>
                            <span class="price">₱1,999.00</span>
                            <span class="price-period">/month</span>
                        </td>
                        <td>
                            <span class="status-badge status-premium">👑 Premium</span>
                        </td>
                        <td>
                            <button class="subscribe-btn" onclick="subscribe('ENTERPRISE', 'Enterprise Plan', 1999.00, this)">
                                Subscribe
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <div class="notification" id="notification"></div>

    <script>
        function subscribe(planId, planName, price, buttonElement) {
            document.getElementById('loading').classList.add('show');
            
            buttonElement.disabled = true;
            buttonElement.textContent = 'Processing...';

            const subscriptionData = {
                plan_id: planId,
                plan_name: planName,
                price: price
            };

            fetch('subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscriptionData)
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').classList.remove('show');
                if (data.success) {
                    showNotification(data.message, 'success');
                    buttonElement.classList.add('success');
                    buttonElement.textContent = '✓ Subscribed';
                    setTimeout(() => {
                        updateCurrentPlanUI(planId);
                    }, 1500);
                } else {
                    showNotification(data.message || 'Subscription failed.', 'error');
                    buttonElement.disabled = false;
                    buttonElement.textContent = 'Subscribe';
                }
            })
            .catch(error => {
                document.getElementById('loading').classList.remove('show');
                showNotification('An error occurred. Please try again.', 'error');
                buttonElement.disabled = false;
                buttonElement.textContent = 'Subscribe';
                console.error('Error:', error);
            });
        }

        function updateCurrentPlanUI(newPlanId) {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const planIdElement = row.querySelector('.plan-id');
                const statusCell = row.querySelector('td:nth-child(6)');
                const actionCell = row.querySelector('td:nth-child(7)');
                
                if (planIdElement && planIdElement.textContent === '#' + newPlanId) {
                    statusCell.innerHTML = '<span class="status-badge status-active">✅ Current Plan</span>';
                    actionCell.innerHTML = '<button class="subscribe-btn" disabled>Current Plan</button>';
                    row.classList.add('current-plan');
                } else {
                    const currentPlanBadge = statusCell.querySelector('.status-active');
                    if (currentPlanBadge) {
                        const planName = planIdElement.textContent.replace('#', '');
                        if (planName === 'PRO') {
                            statusCell.innerHTML = '<span class="status-badge status-popular">⭐ Popular</span>';
                        } else if (planName === 'ENTERPRISE') {
                            statusCell.innerHTML = '<span class="status-badge status-premium">👑 Premium</span>';
                        }
                        const price = row.querySelector('.price').textContent;
                        const planFullName = row.querySelector('.plan-name').textContent;
                        const priceValue = parseFloat(price.replace('₱', '').replace(',', ''));
                        actionCell.innerHTML = `<button class="subscribe-btn" onclick="subscribe('${planName}', '${planFullName}', ${priceValue}, this)">Subscribe</button>`;
                        row.classList.remove('current-plan');
                    }
                }
            });
        }

        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = 'notification ' + type;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 4000);
        }
    </script>
</body>
</html>