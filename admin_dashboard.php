<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// force no cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// hanya admin boleh access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$name = $_SESSION["Name"] ?? "Admin";
$initials = strtoupper(substr($name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MyPetakom – Administrator Reports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    *{
      margin:0;
      padding:0;
      box-sizing:border-box;
      font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
    }

    body{
      min-height:100vh;
      display:flex;
      flex-direction:column;
      background:#fefce8;
    }

    .navbar{
      background:#ffffff;
      padding:12px 32px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      border-bottom:1px solid #facc15;
      box-shadow:0 4px 14px rgba(234,179,8,0.25);
    }

    .nav-left{
      display:flex;
      align-items:center;
      gap:24px;
    }

    .logo-box{
      padding:4px 8px;
      border-radius:14px;
      border:1px solid #facc15;
      background:#ffffff;
    }
    .logo-box img{
      height:40px;
      display:block;
    }

    .nav-links{
      display:flex;
      gap:12px;
      font-size:14px;
    }

    .nav-link{
      text-decoration:none;
      color:#4b5563;
      padding:7px 16px;
      border-radius:999px;
    }
    .nav-link:hover{
      background:#fef3c7;
      color:#92400e;
    }
    .nav-link.active{
      background:#eab308;
      color:#ffffff;
    }

    .nav-right{
      display:flex;
      align-items:center;
      gap:10px;
    }

    .avatar{
      width:38px;
      height:38px;
      border-radius:50%;
      border:2px solid #facc15;
      display:flex;
      align-items:center;
      justify-content:center;
      background:#fef3c7;
      color:#92400e;
      font-weight:700;
      font-size:14px;
    }

    .logout-link{
      font-size:12px;
      color:#7f1d1d;
      text-decoration:none;
    }

    .main{
      flex:1;
      padding:26px 40px 40px;
      background:#fffbeb;
    }

    .page-header h2{
      font-size:22px;
      font-weight:700;
      color:#92400e;
      margin-bottom:4px;
    }
    .page-header p{
      font-size:13px;
      color:#6b7280;
      margin-bottom:22px;
    }

    .report-block{
      background:#ffffff;
      border-radius:16px;
      border:1px solid #fde68a;
      box-shadow:0 6px 18px rgba(234,179,8,0.2);
      padding:16px 20px;
      margin-bottom:18px;
    }

    .report-title{
      font-size:14px;
      font-weight:600;
      color:#92400e;
      margin-bottom:4px;
    }

    .report-subtitle{
      font-size:12px;
      color:#6b7280;
      margin-bottom:10px;
    }

    .chart-box{
      width:100%;
      height:220px;
      position:relative;
    }

    table{
      width:100%;
      border-collapse:collapse;
      font-size:13px;
    }
    thead{
      background:#fef3c7;
    }
    th, td{
      padding:8px 10px;
      border:1px solid #e5e7eb;
      text-align:left;
    }
    th{
      font-weight:600;
      color:#4b5563;
    }
    tbody tr:nth-child(even){
      background:#fffbeb;
    }

    @media(max-width:960px){
      .navbar{
        padding:10px 20px;
        flex-wrap:wrap;
        gap:10px;
      }
      .main{
        padding:20px 16px 28px;
      }
    }
  </style>

  <script>
    // Lock BACK button – admin tekan back → auto logout
    window.addEventListener("load", function () {
      history.pushState(null, "", location.href);
      window.onpopstate = function () {
        window.location.href = "logout.php";
      };
    });
  </script>
</head>
<body>

<header class="navbar">
  <div class="nav-left">
    <div class="logo-box">
      <img src="pic/pentakom.png" alt="Logo UMP x PETAKOM">
    </div>

    <nav class="nav-links">
  <a href="admin_dashboard.php" class="nav-link active">Dashboard</a>
  <a href="admin_user_profile.php" class="nav-link">User Profile Management</a>
  <a href="#" class="nav-link">Parking Areas</a>
  <a href="#" class="nav-link">Parking Spaces</a>
</nav>

  </div>

  <div class="nav-right">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<main class="main">
 <div class="page-header">
    <h2>Administrator Dashboard</h2>
    <p>Daily booking trend, parking occupancy and peak hour analysis with booking records.</p>
</div>


  <!-- Graph 1: Daily Booking Trend (Line Chart) -->
  <section class="report-block">
    <div class="report-title">Graph 1: Daily Booking Trend</div>
    <div class="report-subtitle">Line Chart – Daily Booking Trend</div>
    <div class="chart-box">
      <canvas id="dailyBookingChart"></canvas>
    </div>
  </section>

  <!-- Graph 2: Parking Occupancy Percentage (Pie Chart) -->
  <section class="report-block">
    <div class="report-title">Graph 2: Parking Occupancy Percentage</div>
    <div class="report-subtitle">Pie Chart – Parking Occupancy Percentage</div>
    <div class="chart-box">
      <canvas id="occupancyChart"></canvas>
    </div>
  </section>

  <!-- Graph 3: Peak Hour Comparison (Bar Chart) -->
  <section class="report-block">
    <div class="report-title">Graph 3: Peak Hour Comparison</div>
    <div class="report-subtitle">Bar Chart – Peak Hour Comparison</div>
    <div class="chart-box">
      <canvas id="peakHourChart"></canvas>
    </div>
  </section>

  <!-- Booking Records Table -->
  <section class="report-block">
    <div class="report-title">Booking Records Table</div>
    <div class="report-subtitle">[ID | Name | Parking Space | Date | Status | Time]</div>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Parking Space</th>
            <th>Date</th>
            <th>Status</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <!-- dummy data – nanti boleh tarik dari DB -->
          <tr>
            <td>BK001</td>
            <td>Adam Zack</td>
            <td>Zone A – A12</td>
            <td>2025-12-07</td>
            <td>Checked-in</td>
            <td>08:10</td>
          </tr>
          <tr>
            <td>BK002</td>
            <td>Sofia Ahmad</td>
            <td>Zone B – B05</td>
            <td>2025-12-07</td>
            <td>Booked</td>
            <td>09:30</td>
          </tr>
          <tr>
            <td>BK003</td>
            <td>Abu Bakar</td>
            <td>Staff – S03</td>
            <td>2025-12-07</td>
            <td>Cancelled</td>
            <td>10:00</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
  // === Graph 1: Daily Booking Trend (Line) ===
  const days = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
  const bookings = [18, 22, 25, 30, 28, 15, 10];

  new Chart(document.getElementById("dailyBookingChart"), {
    type:"line",
    data:{
      labels:days,
      datasets:[{
        label:"Total Bookings",
        data:bookings,
        tension:0.3
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false
    }
  });

  // === Graph 2: Parking Occupancy Percentage (Pie) ===
  const zones = ["Zone A","Zone B","Zone C","Staff"];
  const occupancy = [80, 60, 50, 70]; // percentage

  new Chart(document.getElementById("occupancyChart"), {
    type:"pie",
    data:{
      labels:zones,
      datasets:[{
        data:occupancy
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false
    }
  });

  // === Graph 3: Peak Hour Comparison (Bar) ===
  const hours = ["08:00","10:00","12:00","14:00","16:00"];
  const peakCounts = [25, 32, 30, 18, 12];

  new Chart(document.getElementById("peakHourChart"), {
    type:"bar",
    data:{
      labels:hours,
      datasets:[{
        label:"Bookings per hour",
        data:peakCounts
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false
    }
  });
</script>

</body>
</html>