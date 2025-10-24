<?php
session_start();
require_once "config.php";

// Generate CSRF token once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// ✅ If redirected back with success message
if (!empty($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF validation
    if (!isset($_POST["csrf_token"]) || $_POST["csrf_token"] !== $_SESSION["csrf_token"]) {
        die("❌ CSRF validation failed.");
    }

    // Sanitize inputs
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All required fields must be filled!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } elseif (!preg_match("/[0-9]/", $password) || !preg_match("/[a-zA-Z]/", $password)) {
        $error = "Password must contain at least one letter and one number!";
    } else {
        try {
            $pdo = getDBConnection();

            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM sellers WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $error = "Email already exists!";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Generate unique username from email
                $username = explode('@', $email)[0] . rand(1000, 9999);

                // Insert into sellers table with username
                $stmt = $pdo->prepare("INSERT INTO sellers (name, email, username, password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $username, $hashed_password]);

                // ✅ Redirect to prevent duplicate inserts on refresh
                $_SESSION['success_message'] = "Registration successful! You can now login.";
                header("Location: register.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - iMarket Seller Centre</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: skyblue;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            color: #24c7f8ff;
            font-weight: 700;
        }
        
        .logo-icon, img {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .need-help {
            color: #4fbcfcff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: opacity 0.3s;
            padding: 8px 16px;
            border-radius: 8px;
            border: 2px solid #6cd5ffff;
        }
        
        .need-help:hover {
            background: rgba(102, 126, 234, 0.1);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .content-wrapper {
            max-width: 1100px;
            width: 100%;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 60px;
            align-items: center;
        }
        
        /* Left Side */
        .left-side {
            color: white;
        }
        
        .main-title {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .subtitle {
            font-size: 18px;
            margin-bottom: 40px;
            line-height: 1.6;
            opacity: 0.95;
        }
        
        /* Right Side - Register Form */
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 45px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .register-title {
            font-size: 28px;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .register-subtitle {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        /* Alerts */
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .alert svg {
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .alert-danger {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-success a {
            color: #0891b2;
            font-weight: 600;
            text-decoration: underline;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-text {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .register-btn {
            width: 100%;
            padding: 15px;
            background: skyblue;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .signup-link {
            text-align: center;
            font-size: 14px;
            color: #7f8c8d;
            padding-top: 20px;
            border-top: 1px solid #e0e6ed;
            margin-top: 20px;
        }
        
        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 968px) {
            .content-wrapper {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .left-side {
                text-align: center;
            }
            
            .main-title {
                font-size: 36px;
            }
            
            .register-container {
                padding: 35px 25px;
                max-width: 450px;
                margin: 0 auto;
            }
        }

        @media (max-width: 480px) {
            .main-title {
                font-size: 28px;
            }
            
            .subtitle {
                font-size: 16px;
            }
            
            .register-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">
                    <img src="images/logo.png" alt="">
                </div>
                <div>
                    <div>iMarket</div>
                    <div style="font-size: 13px; font-weight: 400; color: #7f8c8d;">Seller Centre</div>
                </div>
            </div>
            <a href="#" class="need-help">Need Help?</a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-wrapper">
            <!-- Left Side -->
            <div class="left-side">
                <h1 class="main-title">Join iMarket Today</h1>
                <p class="subtitle">Create your seller account and start reaching millions of customers across the platform</p>
            </div>

            <!-- Right Side - Register Form -->
            <div class="register-container">
                <div class="register-header">
                    <h2 class="register-title">Create Account</h2>
                    <p class="register-subtitle">Fill in your details to get started</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= htmlspecialchars($success) ?> <a href="index.php">Login now</a></span>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="form-group">
                        <label for="name" class="form-label">Full Name</label>
                        <input 
                            type="text" 
                            id="name"
                            name="name"
                            class="form-input" 
                            placeholder="Enter your full name" 
                            required
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input 
                            type="email" 
                            id="email"
                            name="email"
                            class="form-input" 
                            placeholder="Enter your email address" 
                            required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input 
                            type="password" 
                            id="password"
                            name="password"
                            class="form-input" 
                            placeholder="Create a password" 
                            required
                        >
                        <span class="form-text">At least 6 characters, must include letters and numbers</span>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input 
                            type="password" 
                            id="confirm_password"
                            name="confirm_password"
                            class="form-input" 
                            placeholder="Confirm your password" 
                            required
                        >
                    </div>
                    
                    <button type="submit" class="register-btn">
                        Create Account
                    </button>
                </form>

                <div class="signup-link">
                    Already have an account? <a href="index.php">Sign in instead</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>