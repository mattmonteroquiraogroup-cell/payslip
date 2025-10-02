<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$projectUrl = $_ENV['SUPABASE_URL'];
$apiKey     = $_ENV['SUPABASE_KEY'];

$table = "employees_credentials";

// Fetch all rows
$url = $projectUrl . "/rest/v1/$table?select=employee_id,complete_name,email,position";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $apiKey",
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ Supabase API connected!<br><br>";
    echo "<pre>";
    print_r(json_decode($response, true));
    echo "</pre>";
} else {
    echo "❌ Failed to connect. HTTP code: $httpCode<br>";
    echo "Response: $response";
}
