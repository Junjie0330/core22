<?php
session_start();

// ADD THIS SESSION CHECK AT THE TOP
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$host = "localhost";
$user = "root"; 
$pass = "";     
$port = "3306";
$db   = "core2_test";

$conn = new mysqli($host, $user, $pass,  $db, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ADD THIS LINE TO GET CURRENT USER
$current_user_id = $_SESSION['user_id'];

// DELETE product - ADD USER CHECK
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // MODIFY: Check ownership before getting image
    $stmt = $conn->prepare("SELECT image FROM products WHERE id=? AND seller_id=?");
    $stmt->bind_param("ii", $id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if ($product) {
        // Delete product from database - ADD USER CHECK
        $stmt = $conn->prepare("DELETE FROM products WHERE id=? AND seller_id=?");
        $stmt->bind_param("ii", $id, $current_user_id);
        
        if ($stmt->execute()) {
            // Delete image file if exists
            if (!empty($product['image']) && file_exists("uploads/" . $product['image'])) {
                unlink("uploads/" . $product['image']);
            }
            $_SESSION['success'] = "🗑 Product deleted successfully!";
        } else {
            $_SESSION['error'] = "❌ Failed to delete product!";
        }
    } else {
        $_SESSION['error'] = "❌ Product not found or access denied!";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ADD or UPDATE product
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $seller_id = $current_user_id; // CHANGE: Use current user instead of hardcoded 1
    $name = $conn->real_escape_string($_POST['product_name']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);

    // Handle image upload
    $imageName = "";
    $oldImage = "";
    
    if ($id > 0) {
        // MODIFY: Get old image with user check
        $stmt = $conn->prepare("SELECT image FROM products WHERE id=? AND seller_id=?");
        $stmt->bind_param("ii", $id, $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $oldProduct = $result->fetch_assoc();
        
        if (!$oldProduct) {
            $_SESSION['error'] = "❌ Product not found or access denied!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
        $oldImage = $oldProduct['image'];
    }
    
    if (!empty($_FILES['product_image']['name'])) {
        $imageName = time() . "_" . basename($_FILES['product_image']['name']);
        $targetPath = "uploads/" . $imageName;
        
        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
            // Delete old image if updating
            if (!empty($oldImage) && file_exists("uploads/" . $oldImage)) {
                unlink("uploads/" . $oldImage);
            }
        } else {
            $_SESSION['error'] = "❌ Failed to upload image!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        // Keep old image if no new image uploaded
        $imageName = $oldImage;
    }

    if ($id > 0) {
        // Update product - ADD USER CHECK
        if (!empty($_FILES['product_image']['name'])) {
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category=?, description=?, image=? WHERE id=? AND seller_id=?");
            $stmt->bind_param("sdisssii", $name, $price, $stock, $category, $description, $imageName, $id, $current_user_id);
        } else {
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category=?, description=? WHERE id=? AND seller_id=?");
            $stmt->bind_param("sdissii", $name, $price, $stock, $category, $description, $id, $current_user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "✏️ Product updated successfully!";
        } else {
            $_SESSION['error'] = "❌ Update failed: " . $conn->error;
        }
    } else {
        // Insert new product
        $stmt = $conn->prepare("INSERT INTO products (seller_id, name, price, image, stock, category, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdisss", $seller_id, $name, $price, $imageName, $stock, $category, $description);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "✅ Product added successfully!";
        } else {
            $_SESSION['error'] = "❌ Failed to add product: " . $conn->error;
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// MODIFY: Fetch products with user filter
$sql = "SELECT *, 
        CASE 
            WHEN stock = 0 THEN 'out-of-stock'
            WHEN stock <= 10 THEN 'low-stock' 
            ELSE 'in-stock'
        END as status,
        DATE_FORMAT(created_at, '%b %d, %Y<br>%h:%i %p') as formatted_date
        FROM products 
        WHERE seller_id = ?
        ORDER BY id DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <?php include 'includes/sidebar.php';?>
</head>
<body>

<style>
  .main-content {
    margin-left: 250px; /* Adjust this value to match your sidebar's width */
    padding: 20px;
  }
</style>

  <div class="alert alert-success" id="successAlert"></div>
  <div class="alert alert-error" id="errorAlert"></div>

  <div class="main-content">
    <div class="header-section">
      <div>
        <div class="page-title">
          <i class="fas fa-box"></i>
          <h1>My Product Management</h1>
        </div>
        <p class="page-subtitle">Manage and track your products inventory</p>
      </div>
      <div style="display: flex; align-items: center; gap: 10px;">
        
        <a href="#" class="dashboard-btn" onclick="openModal()" style="background-color: #0d6efd;">
          <i class="fas fa-plus"></i>
          Add Product
        </a>

        <a href="#" class="dashboard-btn">
          <i class="fas fa-chart-bar"></i>
          Dashboard
        </a>
        <a href="logout.php" class="dashboard-btn" style="background-color: #dc3545;">
          <i class="fas fa-sign-out-alt"></i>
          Logout
        </a>
      </div>
    </div>

    <div class="filter-tabs">
      <a href="?filter=all" class="tab-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
        <i class="fas fa-list"></i>
        All Products
      </a>
      <a href="?filter=in-stock" class="tab-btn <?php echo $filter == 'in-stock' ? 'active' : ''; ?>">
        <i class="fas fa-check-circle"></i>
        In Stock
      </a>
      <a href="?filter=low-stock" class="tab-btn <?php echo $filter == 'low-stock' ? 'active' : ''; ?>">
        <i class="fas fa-exclamation-triangle"></i>
        Low Stock
      </a>
      <a href="?filter=out-of-stock" class="tab-btn <?php echo $filter == 'out-of-stock' ? 'active' : ''; ?>">
        <i class="fas fa-times-circle"></i>
        Out of Stock
      </a>
    </div>

    <div class="products-table">
      <table>
        <thead>
          <tr>
            <th>PRODUCT #</th>
            <th>IMAGE</th>
            <th>NAME</th>
            <th>CATEGORY</th>
            <th>PRICE</th>
            <th>STOCK</th>
            <th>STATUS</th>
            <th>DATE ADDED</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody id="productsTable">
          <?php 
          $count = 1;
          while($row = $result->fetch_assoc()): 
            // Apply filter
            if ($filter != 'all' && $row['status'] != $filter) {
              continue;
            }
          ?>
          <tr data-status="<?php echo $row['status']; ?>">
            <td class="product-id">#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
            <td>
              <?php if (!empty($row['image']) && file_exists("uploads/" . $row['image'])): ?>
                <img src="uploads/<?php echo htmlspecialchars($row['image']); ?>" class="product-image" alt="Product">
              <?php else: ?>
                <div style="width:40px;height:40px;background:#f8f9fa;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                  <i class="fas fa-image" style="color:#6c757d;"></i>
                </div>
              <?php endif; ?>
            </td>
            <td class="product-name"><?php echo htmlspecialchars($row['name']); ?></td>
            <td><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
            <td class="price">₱<?php echo number_format($row['price'], 2); ?></td>
            <td><?php echo $row['stock']; ?></td>
            <td>
              <?php
              $statusClass = 'status-' . $row['status'];
              $statusText = ucwords(str_replace('-', ' ', $row['status']));
              $statusIcon = $row['status'] == 'in-stock' ? 'check' : ($row['status'] == 'low-stock' ? 'exclamation-triangle' : 'times-circle');
              ?>
              <span class="status-badge <?php echo $statusClass; ?>">
                <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                <?php echo $statusText; ?>
              </span>
            </td>
            <td>
              <div class="date-time"><?php echo $row['formatted_date']; ?></div>
            </td>
            <td>
              <div class="action-buttons">
                <button class="btn-icon btn-edit" onclick='editProduct(<?php echo json_encode($row); ?>)'>
                  <i class="fas fa-edit"></i>
                </button>
                <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this product?')">
                  <button class="btn-icon btn-delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </a>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
          
          <?php if ($result->num_rows == 0): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: #6c757d;">
              <i class="fas fa-box" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
              No products found.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal" id="productModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="modalTitle">Add Product</h2>
        <button class="close-btn" onclick="closeModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data" id="productForm">
          <input type="hidden" name="id" id="product_id">
          
          <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" class="form-control" name="product_name" id="product_name" required>
          </div>

          <div class="form-group">
            <label class="form-label">Price (₱) *</label>
            <input type="number" step="0.01" class="form-control" name="price" id="price" required>
          </div>

          <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-control" name="category" id="category">
              <option value="">-- Select Category --</option>
              <option value="Electronics">Electronics</option>
              <option value="Clothing">Clothing</option>
              <option value="Home">Home</option>
              <option value="Food">Food</option>
              <option value="Sports">Sports</option>
              <option value="Books">Books</option>
              <option value="Toys">Toys</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Stock Quantity *</label>
            <input type="number" class="form-control" name="stock" id="stock" min="0" required>
          </div>

          <div class="form-group">
            <label class="form-label">Product Image</label>
            <input type="file" class="form-control" name="product_image" id="product_image" accept="image/*">
            <small style="color: #6c757d; font-size: 12px;">JPG, PNG, GIF files are allowed</small>
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" id="description" rows="3"></textarea>
          </div>

          <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i>
            Save Product
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Modal functions
    function openModal() {
      document.getElementById("modalTitle").textContent = "Add Product";
      document.getElementById("productForm").reset();
      document.getElementById("product_id").value = "";
      document.getElementById("productModal").style.display = "flex";
    }

    function closeModal() {
      document.getElementById("productModal").style.display = "none";
    }

    function editProduct(product) {
      document.getElementById("modalTitle").textContent = "Edit Product";
      document.getElementById("product_id").value = product.id;
      document.getElementById("product_name").value = product.name;
      document.getElementById("price").value = product.price;
      document.getElementById("category").value = product.category || '';
      document.getElementById("stock").value = product.stock;
      document.getElementById("description").value = product.description || '';
      document.getElementById("productModal").style.display = "flex";
    }

    // Close modal on outside click
    window.onclick = function(event) {
      if (event.target === document.getElementById("productModal")) {
        closeModal();
      }
    }

    // Show alerts
    <?php if (isset($_SESSION['success'])): ?>
      showAlert("<?php echo $_SESSION['success']; ?>", "success");
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      showAlert("<?php echo $_SESSION['error']; ?>", "error");
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    function showAlert(message, type) {
      const alertElement = document.getElementById(type === "success" ? "successAlert" : "errorAlert");
      alertElement.textContent = message;
      alertElement.style.display = "block";
      setTimeout(() => {
        alertElement.style.display = "none";
      }, 4000);
    }
  </script>
</body>
</html>