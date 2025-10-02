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

    echo "Password set successfully! <a href='index.php'>Go to login</a>";
    exit;
}
?>

<form method="POST">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
  <label>Set New Password:</label><br>
  <input type="password" name="password" required>
  <button type="submit">Save Password</button>
</form>
