<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);


header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");


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
  <title>MyPetakom – Student_Dashboard</title>
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
      background:linear-gradient(135deg,#fff5f5 0%,#ffe3e3 45%,#ffd6d6 100%);
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
      color:#7f1d1d;
      margin-bottom:4px;
    }
    .page-header p{
      font-size:13px;
      color:#6b7280;
      margin-bottom:18px;
    }

    .top-row{
      display:grid;
      grid-template-columns:repeat(2,1fr);
      gap:20px;
      margin-top:18px;
    }

    .panel{
      background:#ffffff;
      border-radius:20px;
      padding:20px;
      border:1px solid #fecaca;
      box-shadow:0 10px 24px rgba(248,113,113,0.2);
    }

    .panel h3{
      font-size:15px;
      font-weight:600;
      color:#7f1d1d;
      margin-bottom:8px;
    }

    .bottom-panel{
      margin-top:22px;
    }

    .chart-box{
      width:100%;
      height:220px;
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
      .top-row{
        grid-template-columns:1fr;
      }
    }
  </style>

  <script>
    // Lock back button: bila user tekan Back pada dashboard,
    // terus redirect ke logout.php (auto logout + balik login).
    window.addEventListener("load", function () {
      // Tambah state baru supaya Back trigger popstate di page ini
      history.pushState(null, "", location.href);
      window.onpopstate = function () {
        // bila user tekan back → pergi ke logout
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
      <a href="#" class="nav-link active">Dashboard</a>
      <a href="#" class="nav-link">Membership</a>
      <a href="#" class="nav-link">Parking booking</a>
      <a href="#" class="nav-link">Parking summon</a>
    </nav>
  </div>

  <div class="nav-right">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<main class="main">
  <div class="page-header">
    <h2>Student Dashboard</h2>
    <p>Overview of parking usage, users and demerit status.</p>
  </div>

  <div class="top-row">
    <div class="panel">
      <h3>Parking Availability</h3>
      <div class="chart-box"><canvas id="chart1"></canvas></div>
    </div>

    <div class="panel">
      <h3>User Roles</h3>
      <div class="chart-box"><canvas id="chart2"></canvas></div>
    </div>
  </div>

  <div class="panel bottom-panel">
    <h3>Demerit Users</h3>
    <div class="chart-box"><canvas id="chart3"></canvas></div>
  </div>
</main>

<script>
  const parkingAreas = ['Zone A','Zone B','Staff'];
  const used = [12,5,8];
  const available = [33,25,12];

  new Chart(document.getElementById("chart1"), {
    type:"bar",
    data:{
      labels:parkingAreas,
      datasets:[
        {label:"Used", data:used},
        {label:"Available", data:available}
      ]
    },
    options:{responsive:true,maintainAspectRatio:false}
  });

  new Chart(document.getElementById("chart2"), {
    type:"doughnut",
    data:{
      labels:["Student","Staff","Admin"],
      datasets:[{
        data:[350,8,2]
      }]
    },
    options:{responsive:true,maintainAspectRatio:false}
  });

  new Chart(document.getElementById("chart3"), {
    type:"bar",
    data:{
      labels:["<20 pts","<50 pts","<80 pts","80+ pts"],
      datasets:[{
        data:[40,12,4,1]
      }]
    },
    options:{responsive:true,maintainAspectRatio:false}
  });
</script>

</body>
</html>
