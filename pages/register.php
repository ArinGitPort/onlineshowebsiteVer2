<?php
require_once '../config/db_connection.php'; // ensure this path is correct
require '../vendor/autoload.php'; // PHPMailer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// No need for: require 'path/to/PHPMailer/src/SMTP.php'; // Remove this if using Composer

function clean_input($data) {
  return htmlspecialchars(trim($data));
}

$success = false;
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $firstName = clean_input($_POST['firstName'] ?? '');
  $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
  $password = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirmPassword'] ?? '';
  $agreed = isset($_POST['termsAgreement']);

  if (!$firstName || !$email || !$password || !$confirmPassword) {
    $error = "Please fill in all fields.";
  } elseif (!$agreed) {
    $error = "You must agree to the terms.";
  } elseif ($password !== $confirmPassword) {
    $error = "Passwords do not match.";
  } elseif (strlen($password) < 8) {
    $error = "Password must be at least 8 characters.";
  } else {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetchColumn() > 0) {
      $error = "Email is already registered.";
    } else {
      // Get role_id for 'Customer'
      $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_name = 'Customer' LIMIT 1");
      $roleStmt->execute();
      $role_id = $roleStmt->fetchColumn();

      if (!$role_id) {
        $error = "Default role 'Customer' not found.";
      } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        // Generate activation token and mark the account as inactive
        $activation_token = bin2hex(random_bytes(16));
        $is_active = 0;
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, role_id, activation_token, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $success = $insert->execute([$firstName, $email, $hashedPassword, $role_id, $activation_token, $is_active]);

        if (!$success) {
          $error = "Something went wrong. Please try again.";
        } else {
          // Send activation email using PHPMailer
          $mail = new PHPMailer(true);
          try {
            // Server settings – update these to match your configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'monochromecell@gmail.com';
            $mail->Password   = 'eknj ybgw kmqc krga';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('monochromecell@gmail.com', 'Bunniwinkle');
            $mail->addAddress($email, $firstName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Account Activation - Bunniwinkle';
            // Update the domain name below to your own and include email if desired
            $activationLink = "http://localhost:3000/pages-user/email-validation.php?token=" . $activation_token . "&email=" . urlencode($email);
            $mail->Body    = "Hi " . $firstName . ",<br><br>Please click the following link to activate your account:<br><a href='" . $activationLink . "'>" . $activationLink . "</a><br><br>Thank you!";
            $mail->AltBody = "Hi " . $firstName . ", please visit the following link to activate your account: " . $activationLink;

            $mail->send();
            $success = true;
          } catch (Exception $e) {
            $error = "Message could not be sent. Mailer Error: " . $mail->ErrorInfo;
          }
        }
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Register | Bunniwinkle</title>
  <link rel="stylesheet" href="../assets/css/register.css" />
  <link rel="icon" href="../assets/images/iconlogo/bunniwinkleIcon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    .error-msg { color: red; font-size: 0.85rem; display: none; margin-top: 4px; }
    .success-msg { color: green; font-size: 0.85rem; display: none; margin-top: 4px; }
  </style>
</head>
<body>
  <div class="login-wrapper">
    <div class="login-card">
      <div class="logo-container">
        <img class="logo-image" src="../assets/images/company assets/bunniwinkelanotherlogo.jpg" alt="Logo" />
      </div>

      <!-- PHP Feedback -->
      <?php if ($success): ?>
        <div class="alert alert-success" role="alert" id="successAlert">
          🎉 Registered successfully! Please check your email to activate your account.
        </div>
      <?php elseif (!empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?= $error ?></div>
      <?php endif; ?>

      <!-- Registration Form -->
      <form action="" method="POST" id="registerForm" novalidate>
        <label for="firstName">Name</label>
        <input type="text" name="firstName" id="firstName" required placeholder="Enter your name" />

        <label for="email">Email</label>
        <input type="email" name="email" id="email" required placeholder="Enter your email" />

        <label for="password">Password</label>
        <input type="password" name="password" id="password" required placeholder="Create a password" minlength="8" />
        <div id="passwordStrengthMsg" class="error-msg">Password must be at least 8 characters.</div>

        <label for="confirmPassword">Confirm Password</label>
        <input type="password" name="confirmPassword" id="confirmPassword" required placeholder="Confirm your password" minlength="8" />

        <div class="options-row">
          <div class="checkbox-wrapper">
            <input type="checkbox" id="showPassword" onclick="togglePassword()" />
            <label for="showPassword">Show Password</label>
          </div>

          <div class="checkbox-wrapper">
            <input type="checkbox" id="termsAgreement" name="termsAgreement" required />
            <label for="termsAgreement">I agree to the <a href="terms.php" target="_blank">Terms</a></label>
          </div>
        </div>

        <button type="submit" class="login-btn">Register</button>

        <div class="register-link">
          <p>Already have an account? <a href="../pages/login.php">Login here</a></p>
        </div>
      </form>
    </div>
  </div>

  <script>
    function togglePassword() {
      const pw = document.getElementById("password");
      const cpw = document.getElementById("confirmPassword");
      const type = pw.type === "password" ? "text" : "password";
      pw.type = type;
      cpw.type = type;
    }

    const form = document.getElementById("registerForm");
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirmPassword");
    const pwStrength = document.getElementById("passwordStrengthMsg");

    password.addEventListener("input", () => {
      pwStrength.style.display = password.value.length >= 8 ? "none" : "block";
    });

    form.addEventListener("submit", (event) => {
      confirmPassword.setCustomValidity("");
      if (password.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity("Passwords do not match.");
      }
      if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
      }
    });
  </script>
</body>
</html>
