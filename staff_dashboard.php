<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// force no cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// hanya safety staff boleh access (role diset lowercase dalam login.php)
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "safety staff") {
    header("Location: login.php");
    exit;
}

$name = $_SESSION["Name"] ?? "Staff";
$initials = strtoupper(substr($name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MyPetakom – Security Staff Dashboard</title>
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
      background:linear-gradient(135deg,#ecfeff 0%,#e0f2fe 45%,#dbeafe 100%);
    }

    .navbar{
      background:#ffffff;
      padding:12px 32px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      border-bottom:1px solid #bfdbfe;
      box-shadow:0 4px 14px rgba(37,99,235,0.15);
    }

    .nav-left{
      display:flex;
      align-items:center;
      gap:24px;
    }

    .logo-box{
      padding:4px 8px;
      border-radius:14px;
      border:1px solid #bfdbfe;
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
      background:#dbeafe;
      color:#1d4ed8;
    }
    .nav-link.active{
      background:#1d4ed8;
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
      border:2px solid #60a5fa;
      display:flex;
      align-items:center;
      justify-content:center;
      background:#dbeafe;
      color:#1d4ed8;
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
    }

    .page-header h2{
      font-size:22px;
      font-weight:700;
      color:#1e3a8a;
      margin-bottom:4px;
    }
    .page-header p{
      font-size:13px;
      color:#6b7280;
      margin-bottom:24px;
    }

    .top-charts{
      display:flex;
      justify-content:center;
      gap:40px;
      margin-bottom:26px;
    }

    .chart-card{
      width:320px;
      height:200px;
      border-radius:18px;
      background:#ffffff;
      border:1px solid #bfdbfe;
      box-shadow:0 8px 20px rgba(37,99,235,0.15);
      padding:14px 16px;
      display:flex;
      flex-direction:column;
      gap:8px;
    }

    .chart-title{
      font-size:14px;
      font-weight:600;
      color:#1e40af;
    }

    .chart-wrapper{
      flex:1;
      position:relative;
      width:100%;
      height:100%;
    }

    .bottom-card{
      max-width:720px;
      margin:0 auto;
      background:#ffffff;
      border-radius:20px;
      border:1px solid #bfdbfe;
      box-shadow:0 10px 24px rgba(37,99,235,0.18);
      padding:18px 20px;
      margin-top:10px;
    }

    .bottom-card h3{
      font-size:15px;
      font-weight:600;
      color:#1e40af;
      margin-bottom:10px;
      text-align:left;
    }

    .bottom-chart{
      width:100%;
      height:260px;
      position:relative;
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
      .top-charts{
        flex-direction:column;
        align-items:center;
      }
      .chart-card{
        width:100%;
        max-width:360px;
      }
      .bottom-card{
        width:100%;
      }
    }
  </style>

  <script>
    // Lock back button on staff dashboard → auto logout
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
      <a href="#" class="nav-link active">Dashboard</a>
      <a href="#" class="nav-link">Membership</a>
      <a href="#" class="nav-link">Issue summon</a>
      <a href="#" class="nav-link">Summon history</a>
      <a href="#" class="nav-link">Demerit points</a>
    </nav>
  </div>

  <div class="nav-right">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    <a href="logout.php" class="logout-link">LOG OUT</a>
  </div>
</header>

<main class="main">
  <div class="page-header">
    <h2>Staff Security Dashboard</h2>
    <p>Overview of demerit points, traffic summons and total types of violations.</p>
  </div>

  <!-- Top: 2 pie charts -->
  <section class="top-charts">
    <div class="chart-card">
      <div class="chart-title">Piechart Demerit Points</div>
      <div class="chart-wrapper">
        <canvas id="demeritPie"></canvas>
      </div>
    </div>

    <div class="chart-card">
      <div class="chart-title">Piechart Traffic Summon</div>
      <div class="chart-wrapper">
        <canvas id="summonPie"></canvas>
      </div>
    </div>
  </section>

  <!-- Bottom: Bar chart -->
  <section class="bottom-card">
    <h3>Barchart: Total Type of Violation</h3>
    <div class="bottom-chart">
      <canvas id="violationBar"></canvas>
    </div>
  </section>
</main>

<script>
  // Dummy data – boleh tukar dengan data sebenar kemudian

  // Piechart Demerit Points
  const demeritLabels = ["Low (0–10 pts)", "Medium (11–30 pts)", "High (31+ pts)"];
  const demeritData = [45, 18, 5];

  new Chart(document.getElementById("demeritPie"), {
    type:"pie",
    data:{
      labels:demeritLabels,
      datasets:[{
        data:demeritData
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false
    }
  });

  // Piechart Traffic Summon
  const summonLabels = ["Paid", "Unpaid", "Under Appeal"];
  const summonData = [30, 12, 3];

  new Chart(document.getElementById("summonPie"), {
    type:"pie",
    data:{
      labels:summonLabels,
      datasets:[{
        data:summonData
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false
    }
  });

  // Barchart: Total Type of Violation
  const violationLabels = ["No Sticker", "Expired Sticker", "Wrong Zone", "Double Parking", "Blocking Lane"];
  const violationCounts = [12, 5, 8, 4, 3];

  new Chart(document.getElementById("violationBar"), {
    type:"bar",
    data:{
      labels:violationLabels,
      datasets:[{
        label:"Total Violations",
        data:violationCounts
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
