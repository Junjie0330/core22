<?php
// Always start the session at the very top
session_start();

// Define session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// --- SESSION VALIDATION ---
if (!isset($_SESSION['user_id'])) {
    // User not logged in → redirect to login
    header( "Location: login.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    // Session expired → destroy and redirect
    session_unset();
    session_destroy();
    header( "Location: login.php?timeout=1");
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// --- Dummy dashboard values (replace with DB queries) ---
$totalProducts = 120;
$totalOrders = 45;
$totalRevenue = 15230.50;
$totalCustomers = 300;
$salesDates = json_encode(["Mon","Tue","Wed","Thu","Fri","Sat","Sun"]);
$salesData = json_encode([500, 700, 400, 900, 650, 1200, 800]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
  


  <!-- Include sidebar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="lg:ml-64 p-6">
    <div class="max-w-7xl mx-auto">
      
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
          <p class="text-gray-500">
            Welcome back, <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>! 
            Here's what's happening with your store.
          </p>
        </div>
        <div class="flex gap-2 mt-4 md:mt-0">
          <button class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-100">
            <i class="bi bi-download mr-2"></i>Export
          </button>
          <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <i class="bi bi-plus mr-2"></i>Add New
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center">
            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 mr-4">
              <i class="bi bi-box text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500">Total Products</p>
              <h3 class="text-xl font-bold text-gray-800"><?php echo number_format($totalProducts); ?></h3>
              <span class="text-green-600 text-xs"><i class="bi bi-arrow-up"></i> 12% from last month</span>
            </div>
          </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center">
            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-green-100 text-green-600 mr-4">
              <i class="bi bi-cart-check text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500">Total Orders</p>
              <h3 class="text-xl font-bold text-gray-800"><?php echo number_format($totalOrders); ?></h3>
              <span class="text-green-600 text-xs"><i class="bi bi-arrow-up"></i> 8% from last month</span>
            </div>
          </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center">
            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-yellow-100 text-yellow-600 mr-4">
              <i class="bi bi-currency-dollar text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500">Total Revenue</p>
              <h3 class="text-xl font-bold text-gray-800">$<?php echo number_format($totalRevenue, 2); ?></h3>
              <span class="text-green-600 text-xs"><i class="bi bi-arrow-up"></i> 15% from last month</span>
            </div>
          </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
          <div class="flex items-center">
            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-cyan-100 text-cyan-600 mr-4">
              <i class="bi bi-people text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-gray-500">Total Customers</p>
              <h3 class="text-xl font-bold text-gray-800"><?php echo number_format($totalCustomers); ?></h3>
              <span class="text-green-600 text-xs"><i class="bi bi-arrow-up"></i> 5% from last month</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts & Recent Activity -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sales Chart -->
        <div class="bg-white p-6 rounded-xl shadow col-span-2">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-700">Sales Performance</h2>
            <select class="text-sm border-gray-300 rounded-lg">
              <option>Last 7 days</option>
              <option>Last 30 days</option>
              <option>Last 3 months</option>
            </select>
          </div>
          <div class="h-80">
            <canvas id="salesChart"></canvas>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white p-6 rounded-xl shadow">
          <h2 class="text-lg font-bold text-gray-700 mb-4">Recent Activity</h2>
          <div class="space-y-4">
            <div class="flex items-center">
              <div class="w-10 h-10 flex items-center justify-center bg-green-100 text-green-600 rounded-full mr-3">
                <i class="bi bi-cart-plus"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-800">New order #1234</p>
                <p class="text-xs text-gray-500">2 minutes ago</p>
              </div>
            </div>
            <div class="flex items-center">
              <div class="w-10 h-10 flex items-center justify-center bg-blue-100 text-blue-600 rounded-full mr-3">
                <i class="bi bi-person-plus"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-800">New customer registered</p>
                <p class="text-xs text-gray-500">5 minutes ago</p>
              </div>
            </div>
            <div class="flex items-center">
              <div class="w-10 h-10 flex items-center justify-center bg-yellow-100 text-yellow-600 rounded-full mr-3">
                <i class="bi bi-exclamation-triangle"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-800">Low stock alert</p>
                <p class="text-xs text-gray-500">10 minutes ago</p>
              </div>
            </div>
            <div class="flex items-center">
              <div class="w-10 h-10 flex items-center justify-center bg-cyan-100 text-cyan-600 rounded-full mr-3">
                <i class="bi bi-box"></i>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-800">Product updated</p>
                <p class="text-xs text-gray-500">15 minutes ago</p>
              </div>
            </div>
          </div>
          <div class="text-center mt-4">
            <a href="#" class="text-blue-600 text-sm hover:underline">View all activity</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ChartJS Script -->
  <script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?php echo $salesDates; ?>,
        datasets: [{
          label: 'Sales ($)',
          data: <?php echo $salesData; ?>,
          borderColor: '#2563eb',
          backgroundColor: 'rgba(37,99,235,0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) { return '$' + value; }
            }
          }
        }
      }
    });
  </script>
</body>
</html>
