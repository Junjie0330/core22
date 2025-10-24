<?php
session_start();
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Make sure PHPMailer is installed

// ✅ Verify OTP
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['otp'])) {
    $entered_otp = trim($_POST['otp']);

    if (isset($_SESSION['otp'], $_SESSION['otp_expires']) && time() < $_SESSION['otp_expires']) {
        if ($entered_otp == $_SESSION['otp']) {
            // ✅ OTP correct → log user in
            session_regenerate_id(true);
            $_SESSION['user_id'] = $_SESSION['pending_user'];
            $_SESSION['email']   = $_SESSION['pending_email'];
            $_SESSION['last_activity'] = time();

            // Clear OTP data
            unset($_SESSION['otp'], $_SESSION['otp_expires'], $_SESSION['pending_user'], $_SESSION['pending_email'], $_SESSION['resend_attempts']);

            header("Location:homes.php");
            exit();
        } else {
            $error = "Invalid OTP!";
        }
    } else {
        $error = "OTP expired! Please log in again.";
        header("Location: login.php");
        exit();
    }
}

// ✅ Resend OTP with attempt limit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['resend'])) {
    if (isset($_SESSION['pending_user'], $_SESSION['pending_email'])) {
        // Initialize resend attempts if not set
        if (!isset($_SESSION['resend_attempts'])) {
            $_SESSION['resend_attempts'] = 0;
        }

        if ($_SESSION['resend_attempts'] < 3) {
            $_SESSION['resend_attempts']++;

            // Generate new OTP
            $otp = random_int(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expires'] = time() + 300; // 5 mins

            // Send OTP email again
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'yourgmail@gmail.com'; // 🔑 your Gmail
                $mail->Password   = 'your_app_password';   // 🔑 App password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('yourgmail@gmail.com', 'Your App Name');
                $mail->addAddress($_SESSION['pending_email']);

                $mail->isHTML(true);
                $mail->Subject = 'Your New OTP Code';
                $mail->Body    = "Your new OTP code is: <b>$otp</b>. It will expire in 5 minutes.";

                $mail->send();
                $success = "A new OTP has been sent to your email. (" . $_SESSION['resend_attempts'] . "/3)";
            } catch (Exception $e) {
                $error = "Failed to resend OTP. Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "You have reached the maximum resend attempts (3). Please log in again.";
            header("Refresh:3; url=login.php"); // Redirect after 3 seconds
        }
    } else {
        $error = "Session expired. Please log in again.";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg p-4">
                <h3 class="text-center mb-4">Enter OTP</h3>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- OTP Verification Form -->
                <form method="POST">
                    <div class="mb-3">
                        <label for="otp" class="form-label">6-digit OTP</label>
                        <input type="text" class="form-control" id="otp" name="otp" maxlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Verify</button>
                </form>

                <!-- ✅ Resend OTP Button (disabled if attempts reached) -->
                <?php if (!isset($_SESSION['resend_attempts']) || $_SESSION['resend_attempts'] < 3): ?>
                    <form method="POST" class="mt-3">
                        <button type="submit" name="resend" class="btn btn-link w-100">Resend OTP</button>
                    </form>
                <?php else: ?>
                    <p class="text-danger text-center mt-3">Resend limit reached. Please log in again.</p>
                <?php endif; ?>

                <p class="text-center mt-3"><a href="login.php">Back to Login</a></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
