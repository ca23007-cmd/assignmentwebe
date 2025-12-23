<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// force no cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// hanya safety staff boleh access
// jika Role dalam DB = 'safety_staff', guna string ini.
// kalau awak simpan 'safety staff' (ada space), tukar ke "safety staff".
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "safety_staff") {
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
  background:
    linear-gradient(180deg, rgba(224,242,254,0.78), rgba(219,234,254,0.78)),
    url("pic/blue.webp") no-repeat center center fixed;
  background-size: cover;
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
    .avatar-link{
  text-decoration:none;
  display:inline-block;
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
  background: transparent;
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

    .stats-grid{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:20px;
      margin-bottom:24px;
    }

    .stat-box{
      background:#ffffff;
      border-radius:18px;
      border:1px solid #bfdbfe;
      box-shadow:0 8px 20px rgba(37,99,235,0.15);
      padding:18px 16px;
      text-align:center;
    }

    .stat-title{
      font-size:14px;
      font-weight:600;
      color:#1e40af;
      margin-bottom:6px;
    }

    .stat-number{
      font-size:30px;
      font-weight:800;
      color:#1d4ed8;
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

    .violation-list{
      list-style:none;
      padding:0;
      margin:0;
    }

    .violation-list li{
      display:flex;
      justify-content:space-between;
      padding:8px 0;
      border-bottom:1px solid #e5e7eb;
      font-size:13px;
      color:#374151;
    }

    .violation-list li:last-child{
      border-bottom:none;
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
      .stats-grid{
        grid-template-columns:1fr;
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
      <a href="safety_membership.php" class="nav-link">Membership</a>
      <a href="#" class="nav-link">Issue summon</a>
      <a href="#" class="nav-link">Summon history</a>
      <a href="#" class="nav-link">Demerit points</a>
    </nav>
  </div>

  <div class="nav-right">
  <a href="staff_profile.php" class="avatar-link" title="Staff Profile">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
  </a>
  <a href="logout.php" class="logout-link">LOG OUT</a>
</div>

</header>

<main class="main">
  <div class="page-header">
    <h2>Staff Security Dashboard</h2>
    <p>Overview of demerit points, traffic summons and total types of violations.</p>
  </div>

  <!-- Top numbers (ganti pie chart & bar chart) -->
  <section class="stats-grid">
    <div class="stat-box">
      <div class="stat-title">Active Summons</div>
      <div class="stat-number">15</div>
    </div>
    <div class="stat-box">
      <div class="stat-title">Paid Summons</div>
      <div class="stat-number">30</div>
    </div>
    <div class="stat-box">
      <div class="stat-title">Total Violations Today</div>
      <div class="stat-number">9</div>
    </div>
  </section>

  <!-- Bottom detail list (instead of bar chart) -->
  <section class="bottom-card">
    <h3>Summary by Type of Violation</h3>
    <ul class="violation-list">
      <li>
        <span>No Sticker</span>
        <span>12 cases</span>
      </li>
      <li>
        <span>Expired Sticker</span>
        <span>5 cases</span>
      </li>
      <li>
        <span>Wrong Zone Parking</span>
        <span>8 cases</span>
      </li>
