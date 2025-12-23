
<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
<title>MyPetakom – Administrator Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Chart.js -->
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
  background: url("pic/yellow.avif") no-repeat center center fixed;
  background-size: cover;
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
}
.nav-links{
  display:flex;
  gap:12px;
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
}
.logout-link{
  font-size:12px;
  color:#7f1d1d;
  text-decoration:none;
}

.main{
  flex:1;
  padding:26px 40px 40px;
  background: transparent;
}


.page-header h2{
  font-size:22px;
  font-weight:700;
  color:#92400e;
}
.page-header p{
  font-size:13px;
  color:#6b7280;
  margin-bottom:20px;
}

.avatar-link{
  text-decoration:none;
  display:inline-block;
}

/* ====== Graph Boxes (based on wireframe) ====== */
.graph-stack{
  display:flex;
  flex-direction:column;
  gap:18px;
  margin-bottom:22px;
}

.graph-box{
  background:#ffffff;
  border-radius:16px;
  border:1px solid #fde68a;
  box-shadow:0 6px 18px rgba(234,179,8,0.18);
  padding:14px 16px;
}

.graph-title{
  font-size:14px;
  font-weight:700;
  color:#92400e;
  margin-bottom:2px;
}
.graph-subtitle{
  font-size:12px;
  color:#6b7280;
  margin-bottom:10px;
}

/* Fixed height for charts */
.chart-wrap{
  height:240px;
  border:1px solid #f3f4f6;
  border-radius:12px;
  padding:10px;
  background:#ffffff;
}

/* Table */
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
  font-weight:700;
  color:#92400e;
}
.report-subtitle{
  font-size:12px;
  color:#6b7280;
  margin-bottom:10px;
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
  .chart-wrap{
    height:220px;
  }
}
</style>

<script>
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
      <img src="pic/pentakom.png" alt="Logo">
    </div>

    <nav class="nav-links">
      <a href="admin_dashboard.php" class="nav-link active">Dashboard</a>
      <a href="admin_user_profile.php" class="nav-link">User Profile Management</a>
      <a href="parking_area.php" class="nav-link">Parking Areas</a>
      <a href="parking_space.php" class="nav-link">Parking Spaces</a>
    </nav>
  </div>

  <div class="nav-right">
   <a href="admin_profile.php" class="avatar-link" title="Admin Profile">
 <a href="admin_profile.php" class="avatar-link" title="Admin Profile">
  <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
</a>
<a href="logout.php" class="logout-link">Log out</a>

  </div>
</header>

<main class="main">
  <div class="page-header">
    <h2>Administrator Dashboard</h2>
    <p>Overview of system usage, booking performance and user activity.</p>
  </div>

  <!-- ====== Graph 1: Daily Booking Trend (Line Chart) ====== -->
  <section class="graph-box">
    <div class="graph-title">Graph 1 : Daily Booking Trend</div>
    <div class="graph-subtitle">Line Chart Daily Booking Trend</div>
    <div class="chart-wrap">
      <canvas id="dailyTrend"></canvas>
    </div>
  </section>

  <div style="height:18px;"></div>

  <!-- ====== Graph 2: Parking Occupancy Percentage (Pie Chart) ====== -->
  <section class="graph-box">
    <div class="graph-title">Graph 2 : Parking Occupancy Percentage</div>
    <div class="graph-subtitle">Piechart Parking Occupancy Percentage</div>
    <div class="chart-wrap">
      <canvas id="occupancyPie"></canvas>
    </div>
  </section>

  <div style="height:18px;"></div>

  <!-- ====== Graph 3: Peak Hour Comparison (Bar Chart) ====== -->
  <section class="graph-box">
    <div class="graph-title">Graph 3 : Peak Hour Comparison</div>
    <div class="graph-subtitle">Bar Chart Peak Hour Comparison</div>
    <div class="chart-wrap">
      <canvas id="peakHourBar"></canvas>
    </div>
  </section>

  <div style="height:18px;"></div>

  <!-- ====== Booking Records Table ====== -->
  <section class="report-block">
    <div class="report-title">Booking Records Table</div>
    <div class="report-subtitle">ID | Name | Parking Space | Date | Status | Time</div>

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
  // ===== Graph 1: Daily Booking Trend (Line) =====
  new Chart(document.getElementById("dailyTrend"), {
    type: "line",
    data: {
      labels: ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"],
      datasets: [{
        label: "Bookings",
        data: [12, 18, 14, 22, 30, 24, 28],
        tension: 0.35,
        fill: false,
        borderWidth: 2,
        pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: true } },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });

  // ===== Graph 2: Parking Occupancy Percentage (Pie) =====
  new Chart(document.getElementById("occupancyPie"), {
    type: "pie",
    data: {
      labels: ["Occupied", "Available"],
      datasets: [{
        data: [68, 32]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: "right" } }
    }
  });

  // ===== Graph 3: Peak Hour Comparison (Bar) =====
  new Chart(document.getElementById("peakHourBar"), {
    type: "bar",
    data: {
      labels: ["8AM","9AM","10AM","11AM","12PM","1PM","2PM","3PM","4PM","5PM"],
      datasets: [{
        label: "Bookings",
        data: [6, 10, 14, 32, 26, 18, 14, 12, 9, 7],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: true } },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
</script>

</body>
</html>

