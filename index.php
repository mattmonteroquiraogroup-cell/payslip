<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$projectUrl = $_ENV['SUPABASE_URL'];
$apiKey     = $_ENV['SUPABASE_KEY'];

$employee_id = $_SESSION['employee_id'];
$subsidiary  = strtoupper($_SESSION['subsidiary'] ?? 'QGC');

// 🖼️ Map each subsidiary to its logo and color
$subsidiaryStyles = [
    'QGC'              => ['logo' => 'qgc.png', 'color' => '#aaaaaaff'],
    'WATERGATE'        => ['logo' => 'logos/watergate.png', 'color' => '#0284c7'],
    'SARI-SARI MANOKAN'=> ['logo' => 'logos/sari-sari_manokan.png', 'color' => '#00973fff'],
    'PALUTO'           => ['logo' => 'logos/paluto.png', 'color' => '#cc1800ff'],
    'COMMISSARY'       => ['logo' => 'logos/commissary.png', 'color' => '#cc1800ff'],
    'BRIGHTLINE'       => ['logo' => 'BL.png', 'color' => '#df6808ff'],
    'BMMI-WAREHOUSE'   => ['logo' => 'BMMI.png', 'color' => '#df6808ff'],
    'BMMI-DROPSHIPPING'=> ['logo' => 'BMMI.png', 'color' => '#df6808ff'],
];

// fallback if not found
$logoPath = $subsidiaryStyles[$subsidiary]['logo'] ?? 'qgc.png';
$themeColor = $subsidiaryStyles[$subsidiary]['color'] ?? '#949494ff';

// 🔹 Fetch payslip data from Supabase
$url = $projectUrl . '/rest/v1/payslip_content?employee_id=eq.' . urlencode($employee_id) . '&order=cutoff_date.desc';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $apiKey",
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
curl_close($ch);

$payslips = json_decode($response, true) ?? [];

// 🔹 Determine which payslip to show
$selectedPayslip = null;
if (!empty($payslips)) {
    if (isset($_POST['cutoff_date'])) {
        foreach ($payslips as $p) {
            if ($p['cutoff_date'] === $_POST['cutoff_date']) {
                $selectedPayslip = $p;
                break;
            }
        }
    } else {
        $selectedPayslip = $payslips[0]; // latest by default
    }
}

$position = $selectedPayslip['position'] ?? ($_SESSION['position'] ?? '-');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($subsidiary) ?> Payslip</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  
</head>

<body class="bg-gradient-to-br from-gray-200 via-gray-300 to-gray-400 text-white font-sans">
<div class="flex h-screen">


  <!-- Sidebar -->
  <div id="sidebar" class="w-64 bg-black text-white flex flex-col transition-all duration-300 ease-in-out">
      <div class="p-6 border-b border-gray-700 flex items-center justify-between">
          <h1 id="sidebarTitle" class="text-xl font-bold">Payslip Portal</h1>
          <button onclick="toggleSidebar()" class="p-2 hover:bg-gray-800 rounded-lg transition-colors">
              <svg id="toggleIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
              </svg>
          </button>
      </div>

      <nav class="flex-1 p-4 space-y-2">
          <a href="dashboard.php" class="w-full block text-left px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 flex items-center space-x-3">
              <i class="bi bi-speedometer2"></i>
              <span class="nav-text">Dashboard</span>
          </a>
          <a href="payslip.php" class="w-full block text-left px-4 py-3 rounded-lg bg-white text-black flex items-center space-x-3">
              <i class="bi bi-cash-coin"></i>
              <span class="nav-text">My Payslips</span>
          </a>
      </nav>

      <div class="p-4 border-t border-gray-700">
          <a href="logout.php" class="w-full block px-4 py-3 rounded-lg bg-gray-800 hover:bg-gray-700 flex items-center space-x-3">
              <i class="bi bi-box-arrow-right"></i>
              <span class="nav-text">Log Out</span>
          </a>
      </div>
  </div>

  <!-- Main Content -->
  <div class="flex-1 flex flex-col overflow-y-auto">
    <header class="bg-black shadow-sm border-b border-gray-800 px-6 py-4 flex justify-between items-center">
      <h2 class="text-xl font-semibold">Mabuhay, <?= htmlspecialchars($_SESSION['complete_name'] ?? '') ?></h2>
    </header>

    <main class="flex-1 flex flex-col items-center justify-start py-10 px-4">
      <?php if (empty($payslips)): ?>
        <p class="text-black text-lg">No payslips available.</p>
      <?php else: ?>
        <!-- Dropdown for Payslip Dates -->
        <form method="post" class="mb-6">
          <select name="cutoff_date" onchange="this.form.submit()" class="bg-black text-white px-4 py-2 rounded-md">
            <?php foreach ($payslips as $p): ?>
              <?php 
                $isSelected = isset($selectedPayslip) && isset($selectedPayslip['payroll_date']) && $selectedPayslip['payroll_date'] === $p['payroll_date'];
              ?>
              <option value="<?= htmlspecialchars($p['payroll_date']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                <?= date('F j, Y', strtotime($p['payroll_date'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <!-- Payslip Card -->
        <div class="bg-white text-black rounded-xl shadow-lg w-full max-w-3xl p-8">
          <div class="text-center border-b border-gray-300 pb-4 mb-4">
            <img src="<?= htmlspecialchars($logoPath) ?>" class="mx-auto h-12 mb-2" alt="Logo">
            <p class="text-sm text-gray-600">HUERVANA ST., BURGOS-MABINI, LA PAZ, ILOILO CITY, 5000</p>
            <p class="text-sm text-gray-600">MANAGEMENT@QUIRAOGROUP.COM</p>
          </div>

          <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-gray-100 p-3 rounded-md">
              <p class="text-xs text-gray-600">Employee Name:</p>
              <p class="font-semibold"><?= htmlspecialchars($_SESSION['complete_name'] ?? '-') ?></p>
            </div>
            <div class="bg-gray-100 p-3 rounded-md">
              <p class="text-xs text-gray-600">Position:</p>
              <p class="font-semibold"><?= htmlspecialchars($position) ?></p>
            </div>
            <div class="bg-gray-100 p-3 rounded-md">
              <p class="text-xs text-gray-600">Employee ID:</p>
              <p class="font-semibold"><?= htmlspecialchars($employee_id) ?></p>
            </div>
            <div class="bg-gray-100 p-3 rounded-md">
              <p class="text-xs text-gray-600">Payroll Period:</p>
              <p class="font-semibold"><?= htmlspecialchars($selectedPayslip['cutoff_date'] ?? '-') ?></p>
            </div>
          </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
  <div>
    <h3 class="font-semibold mb-2">Earnings</h3>
    <table class="w-full text-sm border-t border-gray-300">
      <tr class="border-b border-gray-200">
        <td>Description</td>
        <td class="text-right">Hours</td>
        <td class="text-right">Amount</td>
      </tr>

      <!-- Basic Pay -->
      <tr>
        <td>Total Basic</td>
        <td class="text-right">
          <?= htmlspecialchars($selectedPayslip['no_of_hours'] ?? '0') ?> hrs
        </td>
        <td class="text-right">
          ₱<?= number_format($selectedPayslip['basic_pay'] ?? 0, 2) ?>
        </td>
      </tr>

      <!-- ✅ Conditional Overtime -->
      <?php if (!empty($selectedPayslip['ot_hours']) && floatval($selectedPayslip['ot_hours']) > 0): ?>
      <tr>
        <td>Overtime</td>
        <td class="text-right">
          <?= htmlspecialchars($selectedPayslip['ot_hours']) ?> hrs
        </td>
        <td class="text-right">
          ₱<?= number_format($selectedPayslip['ot_pay'] ?? 0, 2) ?>
        </td>
      </tr>
      <?php endif; ?>
    </table>
              <p class="font-semibold mt-2 text-right">Total Compensation: ₱<?= number_format($selectedPayslip['basic_pay'] ?? 0, 2) ?></p>
            </div>

            <div>
              <h3 class="font-semibold mb-2">Deductions</h3>
              <table class="w-full text-sm border-t border-gray-300">
                <tr class="border-b border-gray-200">
                  <td>Description</td>
                  <td class="text-right">Amount</td>
                </tr>
                <tr><td>Late</td><td class="text-right">₱<?= number_format($selectedPayslip['less_late'] ?? 0, 2) ?></td></tr>
                <tr><td>PHIC</td><td class="text-right">₱<?= number_format($selectedPayslip['less_phic'] ?? 0, 2) ?></td></tr>
                <tr><td>HDMF</td><td class="text-right">₱<?= number_format($selectedPayslip['less_hdmf'] ?? 0, 2) ?></td></tr>
              </table>
              <p class="font-semibold mt-2 text-right">Total Deduction: ₱<?= number_format($selectedPayslip['total_deduction'] ?? 0, 2) ?></p>
            </div>
          </div>

          <div class="bg-gray-100 rounded-md p-3 text-center">
           <p class="font-bold text-lg"> NET PAY: ₱<?= number_format((float)str_replace([',', ' '], '', $selectedPayslip['net_pay'] ?? 0), 2) ?>
</p>

          </div>
        </div>
      <?php endif; ?>
      
    </main>
  </div>
</div>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const sidebarTitle = document.getElementById('sidebarTitle');
  const toggleIcon = document.getElementById('toggleIcon');
  const navTexts = document.querySelectorAll('.nav-text');
  const navButtons = document.querySelectorAll('nav a');

  if (sidebar.classList.contains('w-64')) {
      sidebar.classList.remove('w-64');
      sidebar.classList.add('w-16');
      sidebarTitle.style.display = 'none';
      navTexts.forEach(t => t.style.display = 'none');
      navButtons.forEach(b => { b.classList.add('justify-center'); b.classList.remove('space-x-3'); });
      toggleIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>';
  } else {
      sidebar.classList.remove('w-16');
      sidebar.classList.add('w-64');
      sidebarTitle.style.display = 'block';
      navTexts.forEach(t => t.style.display = 'block');
      navButtons.forEach(b => { b.classList.remove('justify-center'); b.classList.add('space-x-3'); });
      toggleIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>';
  }
}
</script>
</body>
</html>
