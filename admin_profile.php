<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// Prevent back button cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Only admin can access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "admin") {
    header("Location: login.php");
    exit;
}

// Get userID from session
$userID = $_SESSION["UserID"] ?? ($_SESSION["userID"] ?? null);
if (!$userID) {
    die("Session UserID not found. Please login again.");
}

$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =======================
   LOAD LOGIN INFO
======================= */
$name = $_SESSION["Name"] ?? "Admin";
$role = $_SESSION["Role"] ?? "admin";

$stmt = $conn->prepare("SELECT Name, Role FROM login WHERE UserID = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $userID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $name = $row["Name"] ?? $name;
        $role = $row["Role"] ?? $role;
    }
    $stmt->close();
}

$initials = strtoupper(substr($name, 0, 2));

/* =======================
   LOAD ADMIN INFO
   (year_of_service only)
======================= */
$year_of_service = "-";

// CHANGE column name here only if yours is different
$stmt = $conn->prepare("
    SELECT year_of_service
    FROM admin
    WHERE userID = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param("s", $userID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($a = $res->fetch_assoc()) {
        $year_of_service = $a["year_of_service"] ?? "-";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Admin Profile</title>
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
  background: url("pic/yellow.avif") no-repeat center center fixed;
  background-size: cover;
}

/* ===== NAVBAR (same as your admin dashboard theme) ===== */
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
.logo-box img{ height:40px; }

.nav-links{ display:flex; gap:12px; }

.nav-link{
  text-decoration:none;
  color:#4b5563;
  padding:7px 16px;
  border-radius:999px;
}
.nav-link:hover{ background:#fef3c7; color:#92400e; }
.nav-link.active{ background:#eab308; color:#ffffff; }

.nav-right{
  display:flex;
  align-items:center;
  gap:10px;
}

.avatar-link{ text-decoration:none; }

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
  cursor:pointer;
}

.logout-link{
  font-size:12px;
  color:#7f1d1d;
  text-decoration:none;
}

/* ===== PAGE ===== */
.main{
  flex:1;
  padding:26px 40px 40px;
  background: transparent;
}

/* ===== PROFILE CARD ===== */
.profile-card{
  max-width:980px;
  margin:0 auto;
  background:#ffffff;
  border-radius:20px;
  padding:22px 22px 26px;
  border:1px solid #fde68a;
  box-shadow:0 10px 24px rgba(234,179,8,0.18);
  position:relative;
}

.edit-btn{
  position:absolute;
  top:18px;
  right:18px;
  padding:10px 14px;
  border:none;
  border-radius:12px;
  background:#eab308;
  color:#fff;
  font-weight:800;
  font-size:12px;
  cursor:pointer;
}
.edit-btn:hover{ filter:brightness(0.95); }

/* ====== HEADER LAYOUT (ICON TOP, DATA BELOW) ====== */
.header{
  display:flex;
  flex-direction:column;
  align-items:flex-start;   /* keep content aligned like student profile */
  gap:12px;
}

.admin-icon{
  width:110px;
  height:auto;
  margin-top:6px;
}

.title-wrap{
  width:100%;
}

.profile-title{
  font-size:20px;
  font-weight:800;
  color:#92400e;
  margin-bottom:4px;
}
.profile-sub{
  font-size:12px;
  color:#6b7280;
  margin-bottom:18px;
}

/* DATA GRID */
.form-view{
  margin-top:6px;
  max-width:620px;
  width:100%;
}

.row{
  display:grid;
  grid-template-columns: 170px 1fr;
  gap:12px;
  align-items:center;
  margin-bottom:12px;
}

.label{
  font-size:13px;
  font-weight:800;
  color:#111827;
}

.value-box{
  border:1px solid #e5e7eb;
  border-radius:10px;
  background:#f9fafb;
  padding:10px 12px;
  font-size:13px;
  color:#111827;
}

@media(max-width:960px){
  .main{ padding:20px 16px 28px; }
  .admin-icon{ width:90px; }
  .row{ grid-template-columns: 1fr; }
  .edit-btn{ position:static; margin-top:12px; width:100%; }
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
      <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
      <a href="admin_user_profile.php" class="nav-link">User Profile Management</a>
      <a href="parking_area.php" class="nav-link">Parking Areas</a>
      <a href="parking_space.php" class="nav-link">Parking Spaces</a>
    </nav>
  </div>

  <div class="nav-right">
    <!-- click MU / initials to open admin profile -->
    <a class="avatar-link" href="admin_profile.php" title="Admin Profile">
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    </a>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<main class="main">
  <div class="profile-card">

    <button class="edit-btn" onclick="location.href='admin_profile_edit.php'">edit profile</button>

    <div class="header">
  <div class="title-wrap">
    <!-- TITLE FIRST -->
    <div class="profile-title">Admin Profile</div>
    <div class="profile-sub">Welcome, <?php echo htmlspecialchars($name); ?></div>

    <!-- ICON SECOND -->
    <img class="admin-icon" src="pic/admin.png" alt="Admin Icon">

        <div class="form-view">
          <div class="row">
            <div class="label">Username :</div>
            <div class="value-box"><?php echo htmlspecialchars($name); ?></div>
          </div>

          <div class="row">
            <div class="label">Admin ID (UserID) :</div>
            <div class="value-box"><?php echo htmlspecialchars($userID); ?></div>
          </div>

          <div class="row">
            <div class="label">Role :</div>
            <div class="value-box"><?php echo htmlspecialchars($role); ?></div>
          </div>

          <div class="row">
            <div class="label">Year of service :</div>
            <div class="value-box"><?php echo htmlspecialchars($year_of_service); ?></div>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>

</body>
</html>
