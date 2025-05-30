<?php
session_start();
require_once '../config/db_connection.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Manila'); // Adjust to your timezone

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("Location: forgot-password.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete any existing tokens for this user
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['user_id']]);
            
            // Insert new token
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['user_id'], $token, $expires]);

            $resetLink = "http://localhost:3000/pages-user/reset-password.php?token=$token&email=" . urlencode($email);

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = '';
            $mail->Password = '';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('', 'Bunniwinkle');
            $mail->addAddress($email, $user['name'] ?? '');
            $mail->isHTML(true);
            $mail->Subject = 'Bunniwinkle - Password Reset Link';
            $mail->Body = "
                <h2>Hi {$user['name']} 👋</h2>
                <p>You requested a password reset. Click the link below to create a new password:</p>
                <p><a href='$resetLink'>Reset Password</a></p>
                <p><em>This link is valid for 1 hour.</em></p>
            ";

            $mail->send();

            $_SESSION['success'] = "Reset link has been sent to your email.";
        } else {
            $_SESSION['error'] = "If this email exists, a reset link has been sent.";
        }

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        $_SESSION['error'] = "Something went wrong while sending the email.";
    }

    header("Location: forgot-password.php");
    exit;
}

// Display session messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bunniwinkle - Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/forgot-password.css">

</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="logo-container">
                <img class="logo-image" src="../assets/images/company assets/bunniwinkelanotherlogo.jpg" alt="Logo">
            </div>

            <!-- Flash Messages -->
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Reset Request Form -->
            <form action="forgot-password.php" method="POST">
                <h2>Forgot Password</h2>
                <p>Enter your email to receive a reset token.</p>

                <label for="email">Email</label>
                <input type="email" name="email" id="email" required placeholder="Your email" class="form-control mb-3">

                <button type="submit" class="login-btn">Generate Token</button>

                <div class="text-center mt-3">
                    <a href="../pages/login.php">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>