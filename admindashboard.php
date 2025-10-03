<?php
session_start();
require __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

// 🚨 Protect this page
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    header("Location: admindashboard.php");
    exit();
}

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$projectUrl = $_ENV['SUPABASE_URL'];
$apiKey     = $_ENV['SUPABASE_KEY'];
$table      = "payslip_content";

// All valid columns from your schema
$valid_columns = [
    "payroll_date","cutoff_date","employee_id","name","position","subsidiary","salary_type",
    "late_minutes","days_absent","no_of_hours","basic_rate","basic_pay","ot_hours","ot_rate","ot_pay",
    "rdot_hours","rdot_rate","rdot_pay","nd_hours","nd_rate","night_dif_pay","leave_w_pay",
    "special_hol_hours","special_hol_rate","special_holiday_pay","reg_hol_rate","reg_holiday_pay",
    "special_hol_ot_hours","special_hol_ot_rate","special_holiday_ot_pay","reg_hol_ot_hours",
    "reg_hol_ot_rate","regular_holiday_ot_pay","allowance","sign_in_bonus","other_adjustment",
    "total_compensation","less_late","less_absent","less_sss","less_phic","less_hdmf","less_whtax",
    "less_sss_loan","less_sss_sloan","less_pagibig_loan","less_comp_cash_advance","less_company_loan",
    "less_product_equip_loan","less_uniform","less_accountability","salary_overpaid_deduction",
    "total_deduction","net_pay"
];

// Only numeric columns
$numeric_columns = [
    "late_minutes","days_absent","no_of_hours","basic_rate","basic_pay",
    "ot_hours","ot_rate","ot_pay","rdot_hours","rdot_rate","rdot_pay",
    "nd_hours","nd_rate","night_dif_pay","leave_w_pay","special_hol_hours",
    "special_hol_rate","special_holiday_pay","reg_hol_rate","reg_holiday_pay",
    "special_hol_ot_hours","special_hol_ot_rate","special_holiday_ot_pay",
    "reg_hol_ot_hours","reg_hol_ot_rate","regular_holiday_ot_pay",
    "allowance","sign_in_bonus","other_adjustment","total_compensation",
    "less_late","less_absent","less_sss","less_phic","less_hdmf","less_whtax",
    "less_sss_loan","less_sss_sloan","less_pagibig_loan","less_comp_cash_advance",
    "less_company_loan","less_product_equip_loan","less_uniform","less_accountability",
    "salary_overpaid_deduction","total_deduction","net_pay"
];

// Helper: clean strings into UTF-8
function clean_utf8($value) {
    if (is_string($value)) {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
    return $value;
}

// File upload handler
if (isset($_POST['submit'])) {
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $csv_file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $headers = fgetcsv($csv_file);

        // Normalize headers
        $headers = array_map(function($h) {
            $h = trim($h, "\"' \t\n\r\0\x0B");
            $h = strtolower($h);
            $h = preg_replace('/[^a-z0-9]+/', '_', $h);
            $h = preg_replace('/_+/', '_', $h);
            return trim($h, '_');
        }, $headers);

        // Fix common mismatches
        $header_map = ["employee" => "employee_id"];
        foreach ($headers as &$h) {
            if (isset($header_map[$h])) {
                $h = $header_map[$h];
            }
        }

        // Keep only schema-valid headers
        $headers = array_values(array_intersect($headers, $valid_columns));

        $rows = [];
        while (($data = fgetcsv($csv_file)) !== FALSE) {
            $data = array_slice($data, 0, count($headers));
            if (count($data) == count($headers)) {
                $row = array_combine($headers, $data);

                foreach ($row as $key => $value) {
                    if ($value === "" || $value === null) {
                        $row[$key] = null;
                    } elseif (in_array($key, $numeric_columns)) {
                        $row[$key] = is_numeric($value) ? (float)$value : null;
                    } elseif ($key === "payroll_date") {
                        $ts = strtotime($value);
                        $row[$key] = $ts ? date("Y-m-d", $ts) : null;
                    } else {
                        $row[$key] = clean_utf8($value);
                    }
                }

                $rows[] = $row;
            }
        }
        fclose($csv_file);

        if (!empty($rows)) {
            $deduped = [];
            foreach ($rows as $row) {
                $deduped[$row['employee_id']] = $row;
            }
            $rows = array_values($deduped);

            $payload = json_encode($rows, JSON_UNESCAPED_UNICODE);

            if ($payload !== false) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "$projectUrl/rest/v1/$table");
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "apikey: $apiKey",
                    "Authorization: Bearer $apiKey",
                    "Content-Type: application/json",
                    "Prefer: resolution=merge-duplicates"
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpcode == 201) {
                    echo "<p style='color:green;'>✅ Data successfully uploaded to Supabase!</p>";
                } else {
                    echo "<p style='color:red;'>Upload failed. Response: $response</p>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payslip Portal - Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans">

  <!-- Sidebar Toggle -->
  <div class="fixed top-4 left-4 z-50">
    <button id="sidebarToggle" class="bg-blue-600 text-white p-2 rounded-lg shadow-lg">
      ☰
    </button>
  </div>

  <!-- Sidebar -->
  <div id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-white shadow-lg z-40 sidebar-collapsed">
    <div class="p-6 border-b border-gray-200">
      <h1 class="text-xl font-bold text-gray-800">Payslip Portal</h1>
      <p class="text-sm text-gray-600">Admin Dashboard</p>
    </div>
    <nav class="mt-6">
      <a href="#" onclick="showSection('upload')" class="nav-item block px-6 py-3 hover:bg-blue-100">Upload Payslips</a>
      <a href="#" onclick="showSection('employees')" class="nav-item block px-6 py-3 hover:bg-blue-100">Employee Management</a>
      <a href="logout.php" class="block px-6 py-3 text-red-600 hover:bg-red-100">Logout</a>
    </nav>
  </div>

  <!-- Main -->
  <div id="mainContent" class="min-h-screen transition-all duration-300">
    <header class="bg-white shadow-sm border-b border-gray-200 p-4 flex justify-between items-center">
      <h2 id="pageTitle" class="text-2xl font-semibold text-gray-800">Upload Payslips</h2>
      <div class="flex items-center space-x-4">
        <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['complete_name']) ?></span>
      </div>
    </header>

    <!-- Upload Section -->
    <div id="uploadSection" class="section p-6">
      <div class="max-w-3xl mx-auto bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Upload Payslip CSV</h3>
        <form method="post" enctype="multipart/form-data" class="space-y-4">
          <input type="file" name="csv_file" accept=".csv" required class="block w-full">
          <button type="submit" name="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Upload</button>
        </form>
      </div>
    </div>

    <!-- Employees Section -->
    <div id="employeesSection" class="section p-6 hidden">
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold">Employee Directory</h3>
        <p class="text-gray-600">Employee management content goes here…</p>
      </div>
    </div>
  </div>

  <script>
    function showSection(section) {
      document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
      document.getElementById(section + 'Section').classList.remove('hidden');
      document.getElementById('pageTitle').textContent =
        section === 'upload' ? 'Upload Payslips' : 'Employee Management';
    }
  </script>
</body>
</html>
