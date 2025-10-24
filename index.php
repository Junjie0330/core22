<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

// Session timeout duration (30 minutes)
define('SESSION_TIMEOUT', 1800);
// Login attempt settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

/**
 * Database connection
 */
function getDBConnection() {
    $db_host = "localhost";
    $db_port = "3307";
    $db_user = "root";
    $db_pass = ""; 
    $db_name = "core2_test";

    try {
        $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Mailer configuration (Gmail with App Password)
 */
function getMailer() {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mahinlorouvel@gmail.com';
    $mail->Password   = 'vixt qyty emfx lnrd';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('mahinlorouvel@gmail.com', 'iMarket Seller Centre');
    return $mail;
}

/**
 * Check if account is locked due to too many attempts
 */
function isAccountLocked($email) {
    $attempts_file = __DIR__ . '/login_attempts/' . md5($email) . '.json';
    
    if (!file_exists($attempts_file)) {
        return false;
    }
    
    $data = json_decode(file_get_contents($attempts_file), true);
    
    if (time() - $data['last_attempt'] > LOCKOUT_DURATION) {
        unlink($attempts_file);
        return false;
    }
    
    return $data['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

/**
 * Record failed login attempt
 */
function recordFailedAttempt($email) {
    $attempts_dir = __DIR__ . '/login_attempts';
    
    if (!is_dir($attempts_dir)) {
        mkdir($attempts_dir, 0755, true);
    }
    
    $attempts_file = $attempts_dir . '/' . md5($email) . '.json';
    
    if (file_exists($attempts_file)) {
        $data = json_decode(file_get_contents($attempts_file), true);
        $data['attempts']++;
        $data['last_attempt'] = time();
    } else {
        $data = [
            'email' => $email,
            'attempts' => 1,
            'last_attempt' => time()
        ];
    }
    
    file_put_contents($attempts_file, json_encode($data));
}

/**
 * Clear login attempts on successful login
 */
function clearLoginAttempts($email) {
    $attempts_file = __DIR__ . '/login_attempts/' . md5($email) . '.json';
    if (file_exists($attempts_file)) {
        unlink($attempts_file);
    }
}

/**
 * Get remaining lockout time
 */
function getRemainingLockoutTime($email) {
    $attempts_file = __DIR__ . '/login_attempts/' . md5($email) . '.json';
    
    if (!file_exists($attempts_file)) {
        return 0;
    }
    
    $data = json_decode(file_get_contents($attempts_file), true);
    $remaining = LOCKOUT_DURATION - (time() - $data['last_attempt']);
    
    return $remaining > 0 ? $remaining : 0;
}

// --- SESSION CHECK ---
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header("Location: seller_login.php?timeout=1");
        exit();
    } else {
        $_SESSION['last_activity'] = time();
        header("Location: seller_dashboard.php");
        exit();
    }
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$remaining_lockout = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST["csrf_token"]) || $_POST["csrf_token"] !== $_SESSION["csrf_token"]) {
        header("Location: seller_login.php");
        exit();
    }
    $username =trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (isAccountLocked($email)) {
        $remaining_lockout = getRemainingLockoutTime($email);
        $minutes = ceil($remaining_lockout / 60);
        $error = "Too many failed login attempts. Please try again in $minutes minute(s).";
    } elseif (empty($email) || empty($password)) {
        $error = "Please fill in all fields!";
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, email, password FROM sellers WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                clearLoginAttempts($email);
                
                $otp = rand(100000, 999999);

                $_SESSION['pending_user']   = $user['id'];
                $_SESSION['pending_name']   = $user['username'];
                $_SESSION['pending_email']  = $user['email'];
                $_SESSION['otp']            = (string)$otp;
                $_SESSION['otp_expires']    = time() + 300;

                try {
                    $mail = getMailer();
                    $mail->addAddress($user['email'], $user['username']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Your OTP Code - iMarket Seller';
                    $mail->Body    = "Hello {$user['username']},<br><br>Your OTP is: <b>{$otp}</b><br><br>This code expires in 5 minutes.";

                    $mail->send();

                    header("Location: verify_otp.php");
                    exit();

                } catch (Exception $e) {
                    $error = "OTP could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            } else {
                recordFailedAttempt($email);
                
                if (isAccountLocked($email)) {
                    $remaining_lockout = getRemainingLockoutTime($email);
                    $minutes = ceil($remaining_lockout / 60);
                    $error = "Too many failed login attempts. Account locked for $minutes minute(s).";
                } else {
                    $attempts_file = __DIR__ . '/login_attempts/' . md5($email) . '.json';
                    if (file_exists($attempts_file)) {
                        $data = json_decode(file_get_contents($attempts_file), true);
                        $attempts_left = MAX_LOGIN_ATTEMPTS - $data['attempts'];
                        $error = "Invalid email or password! ($attempts_left attempts remaining)";
                    } else {
                        $error = "Invalid email or password!";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

if (isset($_GET['message'])) {
    $success = htmlspecialchars($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - iMarket Seller Centre</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background:     skyblue;
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
            
            font-size: 24px;
            color: #24c7f8ff;
            font-weight: 700;
        }
        
        .logo-icon, img {
            width: 80px;
            height: 80px;
           
          
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
           
           
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
        
        .features {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 20px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .feature-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .feature-text h3 {
            font-size: 16px;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .feature-text p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        /* Right Side - Login Form */
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 45px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-title {
            font-size: 28px;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
        
        .form-input:disabled {
            background: #e9ecef;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .password-input-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #95a5a6;
            cursor: pointer;
            font-size: 20px;
            padding: 5px;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: #65dbffff;
        }
        
        .login-btn {
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
        
        .login-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .login-btn:disabled {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 12px;
        }
        
        .forgot-password a {
            color: #3dbbecff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            color: #95a5a6;
            font-size: 13px;
            margin: 25px 0;
            position: relative;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 42%;
            height: 1px;
            background: #e0e6ed;
        }
        
        .divider::before { left: 0; }
        .divider::after { right: 0; }
        
        .social-login {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .social-btn {
            padding: 12px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            color: #2c3e50;
        }
        
        .social-btn:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
            transform: translateY(-2px);
        }
        
        .signup-link {
            text-align: center;
            font-size: 14px;
            color: #7f8c8d;
            padding-top: 20px;
            border-top: 1px solid #e0e6ed;
        }
        
        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        /* Footer */
        .footer {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            text-align: center;
            color: white;
            font-size: 13px;
            backdrop-filter: blur(10px);
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
            
            .features {
                max-width: 500px;
                margin: 0 auto;
            }
            
            .login-container {
                padding: 35px 25px;
                max-width: 450px;
                margin: 0 auto;
            }
            
            .social-login {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-title {
                font-size: 28px;
            }
            
            .subtitle {
                font-size: 16px;
            }
            
            .login-container {
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
                <h1 class="main-title">Grow Your Business with iMarket</h1>
                <p class="subtitle">Join thousands of successful sellers and reach millions of customers across the platform</p>
                
               
            </div>

            <!-- Right Side - Login Form -->
            <div class="login-container">
                <div class="login-header">
                    <h2 class="login-title">Welcome Back</h2>
                    <p class="login-subtitle">Sign in to your seller account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <span>Your session has expired due to inactivity. Please log in again.</span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= $success ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input 
                            type="email" 
                            id="email"
                            name="email"
                            class="form-input" 
                            placeholder="Enter your email address" 
                            required
                            <?= isAccountLocked($_POST['email'] ?? '') ? 'disabled' : '' ?>
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-input-wrapper">
                            <input 
                                type="password" 
                                id="password"
                                name="password"
                                class="form-input" 
                                placeholder="Enter your password" 
                                required
                                <?= isAccountLocked($_POST['email'] ?? '') ? 'disabled' : '' ?>
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <button 
                        type="submit" 
                        class="login-btn"
                        <?= isAccountLocked($_POST['email'] ?? '') ? 'disabled' : '' ?>
                    >
                        Sign In
                    </button>
                    
                 
                </form>

              

               

                <div class="signup-link">
                    New to iMarket? <a href="register.php">Create an Account</a>
                </div>
            </div>
        </div>
    </main>

   

    <script>
        let showPassword = false;
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            showPassword = !showPassword;
            passwordInput.type = showPassword ? 'text' : 'password';
            
            if (showPassword) {
                toggleBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                toggleBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        }
    </script>
</body>
</html>