<?php
session_start();  // if not already
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$projectUrl = $_ENV['SUPABASE_URL'];
$apiKey     = $_ENV['SUPABASE_KEY'];

// ✅ Initialize message to avoid "undefined variable"
$message = "";



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? '';
    $password    = $_POST['password'] ?? '';

    if ($employee_id && $password) {
        // Step 1: fetch employee by ID
        $url = $projectUrl . "/rest/v1/employees_credentials?employee_id=eq." . urlencode($employee_id);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $apiKey",
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // Step 2: Check if employee exists
        if (empty($data)) {
            $message = "Employee ID not found.";
        } else {
            $user = $data[0];

            // Step 3: No password set → send reset email
            if (empty($user['password'])) {
                $token   = bin2hex(random_bytes(16));   // secure token
                $expires = date("c", strtotime("+1 minute")); // expires in 1 minute

                // Save token in Supabase
                $url = $projectUrl . "/rest/v1/employees_credentials?employee_id=eq." . urlencode($employee_id);
                $payload = json_encode([
                    "reset_token" => $token,
                    "reset_token_expires" => $expires
                ]);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "apikey: $apiKey",
                    "Authorization: Bearer $apiKey",
                    "Content-Type: application/json",
                    "Prefer: return=representation"
                ]);
                curl_exec($ch);
                curl_close($ch);

                // Reset link
                $resetLink = "http://localhost/qgcpayslip/set_password.php?token=$token";

                // Send email with PHPMailer
               $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['SMTP_HOST'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['SMTP_USER'];
                    $mail->Password   = $_ENV['SMTP_PASS'];
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                    $mail->Port       = $_ENV['SMTP_PORT'];

                    $mail->setFrom($_ENV['SMTP_USER'], $_ENV['SMTP_FROM_NAME']);
                    $mail->addAddress($user['email'], $user['complete_name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Set Your Password';
                    $mail->Body    = "Hello " . htmlspecialchars($user['complete_name']) . ",<br><br>
                        Please click the link below to set your password:<br>
                        <a href='$resetLink'>$resetLink</a><br><br>
                        This link expires in <b>1 minute</b>.";
                    
                    $mail->send();
                    $message = "A password setup link has been sent to your email.";
                } catch (Exception $e) {
                    $message = "Email error: " . $mail->ErrorInfo;
                }
                            }
            // Step 4: Password exists → verify
            elseif (password_verify($password, $user['password'])) {
                $_SESSION['employee_id']   = $user['employee_id'];
                $_SESSION['complete_name'] = $user['complete_name'];
                $_SESSION['role']          = $user['role'];
                header("Location: index.php");
                exit;
            } else {
                $message = "Invalid password.";
            }
        }
    } else {
        $message = "Please enter Employee ID and Password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <style>
      /* keep your CSS from before */
      body {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #ffffff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .login-container {background:white;padding:3rem 2.5rem;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);width:100%;max-width:400px;border:1px solid #d3d0d0;}
      .login-header {text-align:center;margin-bottom:2rem;}
      .logo-container {margin-bottom:1.5rem;display:flex;justify-content:center;}
      .login-title {font-size:2rem;font-weight:600;color:#212529;margin:0;margin-bottom:0.5rem;}
      .form-group {margin-bottom:1.5rem;}
      .form-label {display:block;margin-bottom:0.5rem;font-weight:500;color:#212529;font-size:0.9rem;}
      .form-input {width:100%;padding:0.875rem 1rem;border:2px solid #e9ecef;border-radius:8px;font-size:1rem;transition:all 0.2s ease;background-color:white;color:#212529;box-sizing:border-box;}
      .form-input:focus {outline:none;border-color:#212529;box-shadow:0 0 0 3px rgba(33,37,41,0.1);}
      .login-button {width:100%;padding:0.875rem;background-color:#212529;color:white;border:none;border-radius:8px;font-size:1rem;font-weight:500;cursor:pointer;transition:all 0.2s ease;margin-bottom:1rem;}
      .login-button:hover {background-color:#495057;transform:translateY(-1px);}
      .error {color:red;text-align:center;margin-bottom:1rem;}
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-header">
      <div class="logo-container">
        <img src="qgc.png" alt="QGC Logo" width="100" height="100">
      </div>
      <h1 class="login-title">Payslip Portal</h1>
      <p class="login-subtitle">Please sign in to your account</p>
    </div>

    <?php if ($message): ?>
      <div class="error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="employee_id" class="form-label">Employee ID</label>
        <input type="text" id="employee_id" name="employee_id" class="form-input" placeholder="Enter your Employee ID" required>
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
      </div>

      <button type="submit" class="login-button">Sign In</button>
    </form>
  </div>
</body>
</html>