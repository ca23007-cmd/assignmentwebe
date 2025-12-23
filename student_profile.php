<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// Prevent back button cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Only student can access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "student") {
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

// Load basic user info (login table)
$name = $_SESSION["Name"] ?? "Student";
$role = $_SESSION["Role"] ?? "student";

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
   LOAD STUDENT INFO
   (programme + year of study)
======================= */
$programme = "-";
$studentYear = "-";

$stmt = $conn->prepare("
    SELECT programme, studentYear
    FROM student
    WHERE userID = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param("s", $userID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($s = $res->fetch_assoc()) {
        $programme   = $s["programme"] ?? "-";
        $studentYear = $s["studentYear"] ?? "-";
    }
    $stmt->close();
}

// Load latest vehicle info for this userID
$plateNum = "-";
$vehicleBrand = "-";
$vehicleType = "-";

$stmt = $conn->prepare("
    SELECT plateNum, vehicleBrand, vehicleType
    FROM vehicle
    WHERE userID = ?
    ORDER BY VehicleID DESC
    LIMIT 1
");
$stmt->bind_param("s", $userID);
$stmt->execute();
$res = $stmt->get_result();
if ($v = $res->fetch_assoc()) {
    $plateNum     = $v["plateNum"] ?? "-";
    $vehicleBrand = $v["vehicleBrand"] ?? "-";
    $vehicleType  = $v["vehicleType"] ?? "-";
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Student Profile</title>
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

.avatar-link{ text-decoration:none; }

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

/* profile card */
.profile-card{
  max-width:980px;
  margin:0 auto;
  background:#ffffff;
  border-radius:20px;
  padding:22px 22px 26px;
  border:1px solid #fecaca;
  box-shadow:0 10px 24px rgba(248,113,113,0.18);
  position:relative;
}

.profile-title{
  font-size:20px;
  font-weight:800;
  color:#7f1d1d;
  margin-bottom:4px;
}

.profile-sub{
  font-size:12px;
  color:#6b7280;
  margin-bottom:10px;
}

.edit-btn{
  position:absolute;
  top:18px;
  right:18px;
  padding:10px 14px;
  border:none;
  border-radius:12px;
  background:#dc2626;
  color:#fff;
  font-weight:800;
  font-size:12px;
  cursor:pointer;
}
.edit-btn:hover{ filter:brightness(0.95); }

/* ICON BLOCK (LEFT) */
.profile-icon{
  margin-top:10px;
  width:110px;
}
.profile-icon img{
  width:95px;
  display:block;
}

/* FORM STARTS BELOW ICON */
.form-view{
  margin-top:18px;
  max-width:520px;
}

/* rows */
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
      <a href="student_board.php" class="nav-link">Dashboard</a>
      <a href="student_membership.php" class="nav-link">Membership</a>
      <a href="student_parking_booking.php" class="nav-link">Parking Booking</a>
      <a href="#" class="nav-link">Parking Summon</a>
    </nav>
  </div>

  <div class="nav-right">
    <a class="avatar-link" href="student_profile.php" title="View Profile">
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    </a>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<main class="main">
  <div class="profile-card">

    <div class="profile-title">Student Profile</div>
    <div class="profile-sub">Welcome, <?php echo htmlspecialchars($name); ?></div>

    <!-- ICON on LEFT, and FORM will start BELOW it -->
    <div class="profile-icon">
      <img src="pic/icon.svg" alt="Student Icon">
    </div>

    <button class="edit-btn" onclick="location.href='student_profile_edit.php'">edit profile</button>

    <div class="form-view">
      <div class="row">
        <div class="label">Username :</div>
        <div class="value-box"><?php echo htmlspecialchars($name); ?></div>
      </div>

      <div class="row">
        <div class="label">Matric number :</div>
        <div class="value-box"><?php echo htmlspecialchars($userID); ?></div>
      </div>

      <div class="row">
        <div class="label">Role :</div>
        <div class="value-box"><?php echo htmlspecialchars($role); ?></div>
      </div>

      <div class="row">
        <div class="label">Programme :</div>
        <div class="value-box"><?php echo htmlspecialchars($programme); ?></div>
      </div>

      <div class="row">
        <div class="label">Student year :</div>
        <div class="value-box"><?php echo htmlspecialchars($studentYear); ?></div>
      </div>

      <div class="row">
        <div class="label">Number plate :</div>
        <div class="value-box"><?php echo htmlspecialchars($plateNum); ?></div>
      </div>

      <div class="row">
        <div class="label">Vehicle brand :</div>
        <div class="value-box"><?php echo htmlspecialchars($vehicleBrand); ?></div>
      </div>

      <div class="row">
        <div class="label">Vehicle type :</div>
        <div class="value-box"><?php echo htmlspecialchars($vehicleType); ?></div>
      </div>
    </div>

  </div>
</main>

</body>
</html>
