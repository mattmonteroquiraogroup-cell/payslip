<?php
session_start();

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Supabase config from .env
$projectUrl = $_ENV['SUPABASE_URL'];
$apiKey     = $_ENV['SUPABASE_KEY'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token        = $_POST['token'] ?? '';
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if (!$token) {
        die("Invalid request. No token provided.");
    }

    // 1. Fetch employee by reset_token
    $url = $projectUrl . "/rest/v1/employees_credentials"
         . "?reset_token=eq." . urlencode($token)
         . "&select=employee_id,reset_token_expires";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $apiKey",
        "Authorization: Bearer $apiKey"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if (empty($data)) {
        die("Invalid or expired reset link.");
    }

    $user = $data[0];

    // 2. Check if token expired (1 minute max)
    if (strtotime($user['reset_token_expires']) < time()) {
        die("Reset link expired. Please request a new one.");
    }

    // 3. Update password and clear token
    $url = $projectUrl . "/rest/v1/employees_credentials"
         . "?employee_id=eq." . urlencode($user['employee_id']);

    $payload = json_encode([
        "password" => $new_password,
        "password_set_timestamp" => date("c"),
        "reset_token" => null,
        "reset_token_expires" => null
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
    $result = curl_exec($ch);
    curl_close($ch);

    echo "Password set successfully! <a href='login.php'>Go to login</a>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Set New Password</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f5f6f8; /* same light background */
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .container {
      background: #fff;
      padding: 40px 30px;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 400px;
      text-align: center;
    }

    .logo {
      margin-bottom: 15px;
    }

    .container h2 {
      margin: 10px 0;
      font-weight: bold;
      color: #000;
    }

    .container p {
      font-size: 14px;
      color: #666;
      margin-bottom: 25px;
    }

    .form-group {
      margin-bottom: 20px;
      text-align: left;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-size: 13px;
      font-weight: bold;
      color: #000;
    }

    input[type="password"] {
      width: 100%;
      padding: 12px 14px;        /* balanced padding */
      border: 1px solid #ccc;
      border-radius: 8px;
      outline: none;
      font-size: 14px;
      line-height: 1.4;          /* fixes placeholder vertical alignment */
      box-sizing: border-box;
    }

    input[type="password"]:focus {
      border-color: #000;
      background: #fafafa;
    }

    input[type="password"]::placeholder {
      color: #999;
      font-size: 14px;
    }

    button {
      width: 100%;
      padding: 12px;
      background: #000;
      border: none;
      color: #fff;
      font-size: 15px;
      font-weight: bold;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.3s;
    }

    button:hover {
      background: #333;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Optional Logo -->
    <div class="logo">
      <img src="qgc.png" alt="Logo" width="80">
    </div>

    <h2>Set New Password</h2>
    <p>Please enter your new password to continue</p>

    <form method="POST">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">

      <div class="form-group">
        <label for="password">New Password</label>
        <input type="password" id="password" name="password" placeholder="Enter new password" required>
      </div>

      <button type="submit">Save Password</button>
    </form>
  </div>
</body>
</html>
