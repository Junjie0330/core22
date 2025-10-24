<?php
session_start();
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

// ✅ Verify OTP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['otp'])) {
    $entered_otp = trim($_POST['otp']);

    if (isset($_SESSION['otp'], $_SESSION['otp_expires']) && time() < $_SESSION['otp_expires']) {
        if ($entered_otp == $_SESSION['otp']) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $_SESSION['pending_user'];
            $_SESSION['email']   = $_SESSION['pending_email'];
            $_SESSION['last_activity'] = time();

            unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['pending_user'], $_SESSION['pending_email'], $_SESSION['resend_attempts']);

            header("Location: homes.php");
            exit();
        } else {
            $error = "Invalid OTP code!";
        }
    } else {
        $error = "OTP expired! Please log in again.";
        header("Refresh:2; url=index.php");
    }
}

// ✅ Resend OTP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['resend'])) {
    if (isset($_SESSION['pending_user'], $_SESSION['pending_email'])) {
        if (!isset($_SESSION['resend_attempts'])) {
            $_SESSION['resend_attempts'] = 0;
        }

        if ($_SESSION['resend_attempts'] < 3) {
            $_SESSION['resend_attempts']++;
            $otp = random_int(100000, 999999);
            $_SESSION['otp'] = (string)$otp;
            $_SESSION['otp_expires'] = time() + 300;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'mahinlorouvel@gmail.com';
                $mail->Password   = 'vixt qyty emfx lnrd';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('mahinlorouvel@gmail.com', 'iMarket Seller Centre');
                $mail->addAddress($_SESSION['pending_email'], $_SESSION['pending_name'] ?? '');

                $mail->isHTML(true);
                $mail->Subject = 'Your New OTP Code - iMarket';
                $mail->Body    = "Your new OTP code is: <b>$otp</b><br><br>Valid for 5 minutes.";

                $mail->send();
                $success = "New code sent! (" . $_SESSION['resend_attempts'] . "/3)";
            } catch (Exception $e) {
                $error = "Failed to resend. Try again.";
            }
        } else {
            $error = "Maximum attempts reached. Please log in again.";
            header("Refresh:3; url=index.php");
        }
    } else {
        header("Location: index.php");
        exit();
    }
}

$remaining_time = isset($_SESSION['otp_expires']) ? max(0, $_SESSION['otp_expires'] - time()) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - iMarket Seller Centre</title>
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
            width: 80px;
            height: 80px;
           
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
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
        
        .security-features {
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
        
        /* Right Side - OTP Form */
        .otp-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 45px;
        }
        
        .otp-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .otp-icon {
            width: 70px;
            height: 70px;
            background: skyblue;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .otp-title {
            font-size: 28px;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .otp-subtitle {
            color: #7f8c8d;
            font-size: 14px;
            line-height: 1.5;
        }

        .otp-subtitle strong {
            color: #2c3e50;
            display: block;
            margin-top: 5px;
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

        .timer {
            background: #fff3cd;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
            font-size: 14px;
            color: #856404;
            font-weight: 600;
        }

        .timer.expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 25px 0;
        }

        .otp-digit {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            background: #f8f9fa;
            color: #2c3e50;
            transition: all 0.3s;
        }

        .otp-digit:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .verify-btn {
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
        
        .verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .resend-section {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e6ed;
        }

        .resend-btn {
            background: none;
            border: 2px solid skyblue;
            color: #4fbcfcff;
            padding: 10px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .resend-btn:hover:not(:disabled) {
            background: skyblue;
            color: white;
        }

        .resend-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            border-color: #95a5a6;
            color: #95a5a6;
        }

        .max-attempts {
            color: #ef4444;
            font-size: 13px;
            margin-top: 15px;
            font-weight: 600;
        }
        
        .back-link {
            text-align: center;
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
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

            .security-features {
                max-width: 500px;
                margin: 0 auto;
            }
            
            .otp-container {
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
            
            .otp-container {
                padding: 30px 20px;
            }

            .otp-digit {
                width: 42px;
                height: 42px;
                font-size: 18px;
            }

            .otp-inputs {
                gap: 8px;
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
                   
                    <div style="font-size: 20px; font-weight: 400; color: #7f8c8d;">Seller Centre</div>
                </div>
            </div>
            <a href="#" class="need-help">Need Help?</a>
        </div>
    </header>

    <main class="main-content">
        <div class="content-wrapper">
            <!-- Left Side -->
            <div class="left-side">
              
                <h1 class="subtitle">We've sent a verification code to your email to ensure it's really you</h1>
                
               
            </div>

            <!-- Right Side - OTP Form -->
            <div class="otp-container">
                <div class="otp-header">
                    <div class="otp-icon">
                        <svg width="35" height="35" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                    <h2 class="otp-title">Verify Your Email</h2>
                    <p class="otp-subtitle">
                        Enter the 6-digit code sent to
                        <strong><?= htmlspecialchars($_SESSION['pending_email'] ?? 'your email') ?></strong>
                    </p>
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
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($remaining_time > 0): ?>
                    <div class="timer" id="timer-box">
                        Code expires in <strong id="timer"><?= gmdate("i:s", $remaining_time) ?></strong>
                    </div>
                <?php else: ?>
                    <div class="timer expired"> Code expired. Request a new one below.</div>
                <?php endif; ?>

                <form method="POST" id="otp-form">
                    <div class="otp-inputs">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                        <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off">
                    </div>
                    <input type="hidden" name="otp" id="otp-value">
                    <button type="submit" class="verify-btn">Verify Code</button>
                </form>

                <div class="resend-section">
                    <?php if (!isset($_SESSION['resend_attempts']) || $_SESSION['resend_attempts'] < 3): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="resend" class="resend-btn">Resend Code</button>
                        </form>
                    <?php else: ?>
                        <p class="max-attempts">Maximum attempts reached (3/3)</p>
                    <?php endif; ?>
                </div>

                <div class="back-link">
                    <a href="index.php">← Back to Login</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        const inputs = document.querySelectorAll('.otp-digit');
        const form = document.getElementById('otp-form');
        const hiddenInput = document.getElementById('otp-value');

        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (!/^\d*$/.test(e.target.value)) {
                    e.target.value = '';
                    return;
                }

                if (e.target.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }

                const otp = Array.from(inputs).map(i => i.value).join('');
                if (otp.length === 6) {
                    hiddenInput.value = otp;
                    form.submit();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = e.clipboardData.getData('text').slice(0, 6);
                if (/^\d+$/.test(paste)) {
                    paste.split('').forEach((char, i) => {
                        if (inputs[i]) inputs[i].value = char;
                    });
                    if (paste.length === 6) {
                        hiddenInput.value = paste;
                        form.submit();
                    }
                }
            });
        });

        inputs[0]?.focus();

        <?php if ($remaining_time > 0): ?>
        let time = <?= $remaining_time ?>;
        const timer = document.getElementById('timer');
        const box = document.getElementById('timer-box');

        setInterval(() => {
            if (--time <= 0) {
                box.className = 'timer expired';
                box.innerHTML = '⚠️ Code expired. Request a new one below.';
            } else {
                timer.textContent = `${Math.floor(time/60)}:${(time%60).toString().padStart(2,'0')}`;
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>