<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// Prevent back button
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Only student can access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "student") {
    header("Location: login.php");
    exit;
}

$name = $_SESSION["Name"] ?? "Student";
$initials = strtoupper(substr($name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Student Dashboard</title>
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
  background: url("pic/red.avif") no-repeat center center fixed;
  background-size: cover;
}

.navbar{
  background:#ffffff;
  padding:12px 32px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:1px solid #fecaca;
  box-shadow:0 4px 14px rgba(248,113,113,0.25);
}

.nav-left{
  display:flex;
  align-items:center;
  gap:24px;
}

.logo-box{
  padding:4px 8px;
  border-radius:14px;
  border:1px solid #fecaca;
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
  background:#fee2e2;
  color:#b91c1c;
}
.nav-link.active{
  background:#dc2626;
  color:#ffffff;
}

.nav-right{
  display:flex;
  align-items:center;
  gap:10px;
}

.avatar-link{
  text-decoration:none;
}

.avatar{
  width:38px;
  height:38px;
  border-radius:50%;
  border:2px solid #f87171;
  display:flex;
  align-items:center;
  justify-content:center;
  background:#fee2e2;
  color:#b91c1c;
  font-weight:700;
  cursor:pointer;
}

.logout-link{
  font-size:12px;
  color:#7f1d1d;
  text-decoration:none;
}

.main{
  flex:1;
  padding:26px 40px 40px;
}

.page-header h2{
  font-size:22px;
  font-weight:700;
  color:#7f1d1d;
}
.page-header p{
  font-size:13px;
  color:#6b7280;
  margin-bottom:20px;
}

/* ===== Layout based on wireframe ===== */
.dashboard-grid{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:20px;
  margin-top:10px;
}

.chart-box{
  background:#ffffff;
  border-radius:20px;
  padding:16px 16px 12px;
  border:1px solid #fecaca;
  box-shadow:0 10px 24px rgba(248,113,113,0.18);
}

.chart-title{
  font-size:13px;
  font-weight:800;
  color:#7f1d1d;
  margin-bottom:6px;
}

.chart-sub{
  font-size:12px;
  color:#6b7280;
  margin-bottom:10px;
}

.chart-wrap{
  height:220px;
}

.big-chart{
  grid-column:1 / -1;
}

.big-chart .chart-wrap{
  height:280px;
}

@media(max-width:960px){
  .main{ padding:20px 16px 28px; }
  .dashboard-grid{ grid-template-columns:1fr; }
  .big-chart .chart-wrap{ height:240px; }
}
</style>

<script>
// Prevent Back button
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
      <a href="student_board.php" class="nav-link active">Dashboard</a>
      <a href="student_membership.php" class="nav-link">Membership</a>
      <a href="student_parking_booking.php" class="nav-link">Parking Booking</a>
      <a href="#" class="nav-link">Parking Summon</a>
    </nav>
  </div>

  <div class="nav-right">
    <!-- CLICKABLE AVATAR -->
    <a href="student_profile.php" class="avatar-link" title="User Profile">
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    </a>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<main class="main">
  <div class="page-header">
    <h2>Student Dashboard</h2>
    <p>Graphs overview (parking availability and demerit summary).</p>
  </div>

  <div class="dashboard-grid">

    <!-- Doughnut -->
    <section class="chart-box">
      <div class="chart-title">Graph: Parking availability</div>
      <div class="chart-sub">Doughnut chart (Available vs Used)</div>
      <div class="chart-wrap">
        <canvas id="parkingAvailLeft"></canvas>
      </div>
    </section>

    <!-- Line -->
    <section class="chart-box">
      <div class="chart-title">Graph: Parking availability</div>
      <div class="chart-sub">Line chart (Availability trend)</div>
      <div class="chart-wrap">
        <canvas id="parkingAvailRight"></canvas>
      </div>
    </section>

    <!-- Bar -->
    <section class="chart-box big-chart">
      <div class="chart-title">Barchart: total user that been demerit</div>
      <div class="chart-sub">Example by faculty / month (replace with real data later)</div>
      <div class="chart-wrap">
        <canvas id="demeritBar"></canvas>
      </div>
    </section>

  </div>
</main>

<script>
new Chart(document.getElementById("parkingAvailLeft"), {
  type: "doughnut",
  data: {
    labels: ["Available", "Used"],
    datasets: [{ data: [70, 12] }]
  },
  options: {
    responsive:true,
    maintainAspectRatio:false,
    plugins:{ legend:{ position:"bottom" } }
  }
});

new Chart(document.getElementById("parkingAvailRight"), {
  type: "line",
  data: {
    labels:["Mon","Tue","Wed","Thu","Fri","Sat","Sun"],
    datasets:[{
      label:"Available Parking",
      data:[80,76,72,70,68,74,70],
      tension:0.35,
      borderWidth:2,
      pointRadius:3
    }]
  },
  options:{
    responsive:true,
    maintainAspectRatio:false,
    scales:{ y:{ beginAtZero:true } }
  }
});

new Chart(document.getElementById("demeritBar"), {
  type:"bar",
  data:{
    labels:["Jan","Feb","Mar","Apr","May","Jun"],
    datasets:[{
      label:"Total Users Demerit",
      data:[3,5,2,7,4,6]
    }]
  },
  options:{
    responsive:true,
    maintainAspectRatio:false,
    scales:{ y:{ beginAtZero:true } }
  }
});
</script>

</body>
</html>
