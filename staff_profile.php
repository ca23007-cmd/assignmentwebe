<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// Prevent back button cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Only safety staff can access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "safety_staff") {
    header("Location: login.php");
    exit;
}

// Badge number stored as UserID in session
$userID = $_SESSION["UserID"] ?? null;
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
$name = $_SESSION["Name"] ?? "Safety Staff";
$role = $_SESSION["Role"] ?? "safety_staff";

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
   LOAD SAFETY STAFF INFO
======================= */
$department = "-";

$stmt = $conn->prepare("
    SELECT department
    FROM safety_staff
    WHERE UserID = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param("s", $userID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($s = $res->fetch_assoc()) {
        $department = $s["department"] ?? "-";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Staff Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
}

/* ===== Background like your staff dashboard (image + soft blue overlay) ===== */
body{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  background:
    linear-gradient(180deg, rgba(224,242,254,0.78), rgba(219,234,254,0.78)),
    url("pic/blue.webp") no-repeat center center fixed;
  background-size: cover;
}

/* ===== NAVBAR (match your staff dashboard look) ===== */
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

.avatar-link{
  text-decoration:none;
  display:inline-block;
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
  cursor:pointer;
  font-size:14px;
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

/* ===== PROFILE CARD (match staff dashboard card feel) ===== */
.profile-card{
  max-width:980px;
  margin:0 auto;
  background:#ffffff;
  border-radius:20px;
  padding:22px 22px 26px;
  border:1px solid #bfdbfe;
  box-shadow:0 10px 24px rgba(37,99,235,0.18);
  position:relative;
}

.edit-btn{
  position:absolute;
  top:18px;
  right:18px;
  padding:10px 14px;
  border:none;
  border-radius:12px;
  background:#1d4ed8;
  color:#fff;
  font-weight:800;
  font-size:12px;
  cursor:pointer;
}
.edit-btn:hover{ filter:brightness(0.95); }

/* ===== HEADER ===== */
.header{
  display:flex;
  flex-direction:column;
  align-items:flex-start;
  gap:12px;
}

.staff-icon{
  width:110px;
  height:auto;
  margin-top:6px;
}

.title-wrap{ width:100%; }

.profile-title{
  font-size:20px;
  font-weight:800;
  color:#1e3a8a;
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
  .navbar{
    padding:10px 20px;
    flex-wrap:wrap;
    gap:10px;
  }
  .main{ padding:20px 16px 28px; }
  .staff-icon{ width:90px; }
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
      <a href="staff_dashboard.php" class="nav-link">Dashboard</a>
      <a href="safety_membership.php" class="nav-link">Membership</a>
      <a href="#" class="nav-link">Issue summon</a>
      <a href="#" class="nav-link">Summon history</a>
      <a href="#" class="nav-link">Demerit points</a>
    </nav>
  </div>

  <div class="nav-right">
    <a class="avatar-link" href="staff_profile.php" title="Staff Profile">
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    </a>
    <a href="logout.php" class="logout-link">LOG OUT</a>
  </div>
</header>

<main class="main">
  <div class="profile-card">

    <button class="edit-btn" onclick="location.href='staff_profile_edit.php'">edit profile</button>

    <div class="header">
      <div class="title-wrap">
        <div class="profile-title">Safety Staff Profile</div>
        <div class="profile-sub">Welcome, <?php echo htmlspecialchars($name); ?></div>

        <!-- if you have a staff icon, change this file -->
        <img class="staff-icon" src="pic/admin.png" alt="Staff Icon">

        <div class="form-view">

          <div class="row">
            <div class="label">Username :</div>
            <div class="value-box"><?php echo htmlspecialchars($name); ?></div>
          </div>

          <div class="row">
            <div class="label">Badge Number (UserID) :</div>
            <div class="value-box"><?php echo htmlspecialchars($userID); ?></div>
          </div>

          <div class="row">
            <div class="label">Role :</div>
            <div class="value-box"><?php echo htmlspecialchars($role); ?></div>
          </div>

          <div class="row">
            <div class="label">Department :</div>
            <div class="value-box"><?php echo htmlspecialchars($department); ?></div>
          </div>

        </div>
      </div>
    </div>

  </div>
</main>

</body>
</html>
