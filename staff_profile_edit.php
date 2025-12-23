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
   LOAD CURRENT DATA
======================= */
$name = "";
$role = "safety_staff";
$department = "";

// login table
$stmt = $conn->prepare("SELECT Name, Role FROM login WHERE UserID = ? LIMIT 1");
$stmt->bind_param("s", $userID);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $name = $row["Name"] ?? "";
    $role = $row["Role"] ?? "safety_staff";
}
$stmt->close();

// safety_staff table
$stmt = $conn->prepare("SELECT department FROM safety_staff WHERE UserID = ? LIMIT 1");
$stmt->bind_param("s", $userID);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $department = $row["department"] ?? "";
}
$stmt->close();

/* =======================
   UPDATE PROCESS
======================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_name       = trim($_POST["name"] ?? "");
    $new_department = trim($_POST["department"] ?? "");

    // update login name
    $stmt = $conn->prepare("UPDATE login SET Name = ? WHERE UserID = ?");
    $stmt->bind_param("ss", $new_name, $userID);
    $stmt->execute();
    $stmt->close();

    // update staff department
    $stmt = $conn->prepare("UPDATE safety_staff SET department = ? WHERE UserID = ?");
    $stmt->bind_param("ss", $new_department, $userID);
    $stmt->execute();
    $stmt->close();

    // update session name
    $_SESSION["Name"] = $new_name;

    header("Location: staff_profile.php");
    exit;
}

$initials = strtoupper(substr(($name !== "" ? $name : "ST"), 0, 2));
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Edit Staff Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
}

/* ===== Background like staff dashboard (image + soft blue overlay) ===== */
body{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  background:
    linear-gradient(180deg, rgba(224,242,254,0.78), rgba(219,234,254,0.78)),
    url("pic/blue.webp") no-repeat center center fixed;
  background-size: cover;
}

/* NAVBAR (match staff dashboard) */
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
.logo-box img{ height:40px; display:block; }

.nav-links{ display:flex; gap:12px; font-size:14px; }

.nav-link{
  text-decoration:none;
  color:#4b5563;
  padding:7px 16px;
  border-radius:999px;
}
.nav-link:hover{ background:#dbeafe; color:#1d4ed8; }
.nav-link.active{ background:#1d4ed8; color:#ffffff; }

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

/* PAGE */
.main{
  flex:1;
  padding:26px 40px 40px;
  background: transparent;
}

.profile-card{
  max-width:980px;
  margin:0 auto;
  background:#ffffff;
  border-radius:20px;
  padding:26px;
  border:1px solid #bfdbfe;
  box-shadow:0 10px 24px rgba(37,99,235,0.18);
}

.profile-title{
  font-size:20px;
  font-weight:800;
  color:#1e3a8a;
  margin-bottom:18px;
}

.form-view{
  max-width:600px;
}

.staff-icon{
  width:110px;
  height:auto;
  margin-bottom:14px;
}

.row{
  display:grid;
  grid-template-columns: 180px 1fr;
  gap:12px;
  margin-bottom:14px;
}

.label{
  font-size:13px;
  font-weight:800;
  color:#111827;
}

.input{
  padding:10px 12px;
  border-radius:10px;
  border:1px solid #e5e7eb;
  font-size:13px;
}

.readonly{
  background:#f3f4f6;
}

.actions{
  margin-top:22px;
  display:flex;
  gap:12px;
}

.btn{
  padding:10px 18px;
  border:none;
  border-radius:12px;
  font-weight:800;
  cursor:pointer;
}

.save{
  background:#1d4ed8;
  color:#fff;
}

.cancel{
  background:#e5e7eb;
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
  .actions{ flex-direction:column; }
  .btn{ width:100%; }
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
  <form class="profile-card" method="POST">
    <div class="profile-title">Edit Staff Profile</div>
    <img class="staff-icon" src="pic/admin.png" alt="Staff Icon">

    <div class="form-view">

      <div class="row">
        <div class="label">Username :</div>
        <input class="input" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
      </div>

      <div class="row">
        <div class="label">Badge Number (UserID) :</div>
        <input class="input readonly" value="<?php echo htmlspecialchars($userID); ?>" readonly>
      </div>

      <div class="row">
        <div class="label">Role :</div>
        <input class="input readonly" value="<?php echo htmlspecialchars($role); ?>" readonly>
      </div>

      <div class="row">
        <div class="label">Department :</div>
        <input class="input" name="department" value="<?php echo htmlspecialchars($department); ?>" required>
      </div>

      <div class="actions">
        <button class="btn save" type="submit">Save Changes</button>
        <button class="btn cancel" type="button" onclick="location.href='staff_profile.php'">Cancel</button>
      </div>

    </div>
  </form>
</main>

</body>
</html>
