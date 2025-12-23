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
   LOAD CURRENT DATA
======================= */
$name = "";
$role = "admin";
$year_of_service = "";

// login table
$stmt = $conn->prepare("SELECT Name, Role FROM login WHERE UserID = ? LIMIT 1");
$stmt->bind_param("s", $userID);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $name = $row["Name"];
    $role = $row["Role"];
}
$stmt->close();

// admin table
$stmt = $conn->prepare("SELECT year_of_service FROM admin WHERE userID = ? LIMIT 1");
$stmt->bind_param("s", $userID);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $year_of_service = $row["year_of_service"];
}
$stmt->close();

/* =======================
   UPDATE PROCESS
======================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_name = trim($_POST["name"]);
    $new_year = trim($_POST["year_of_service"]);

    // update login name
    $stmt = $conn->prepare("UPDATE login SET Name = ? WHERE UserID = ?");
    $stmt->bind_param("ss", $new_name, $userID);
    $stmt->execute();
    $stmt->close();

    // update admin year of service
    $stmt = $conn->prepare("UPDATE admin SET year_of_service = ? WHERE userID = ?");
    $stmt->bind_param("ss", $new_year, $userID);
    $stmt->execute();
    $stmt->close();

    // update session name
    $_SESSION["Name"] = $new_name;

    header("Location: admin_profile.php");
    exit;
}

$initials = strtoupper(substr($name, 0, 2));
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Edit Admin Profile</title>
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

/* NAVBAR */
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
  border:1px solid #fde68a;
  box-shadow:0 10px 24px rgba(234,179,8,0.18);
}

.profile-title{
  font-size:20px;
  font-weight:800;
  color:#92400e;
  margin-bottom:18px;
}

.form-view{
  max-width:600px;
}

.admin-icon{
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
  background:#eab308;
  color:#fff;
}

.cancel{
  background:#e5e7eb;
}
</style>

</head>
<body>

<header class="navbar">
  <div class="nav-left">
    <div class="logo-box">
      <img src="pic/pentakom.png">
    </div>
    <nav class="nav-links">
      <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
      <a href="admin_user_profile.php" class="nav-link">User Profile Management</a>
      <a href="parking_area.php" class="nav-link">Parking Areas</a>
      <a href="parking_space.php" class="nav-link">Parking Spaces</a>
    </nav>
  </div>

  <div class="nav-right">
    <div class="avatar"><?php echo $initials; ?></div>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<main class="main">
  <form class="profile-card" method="POST">
    <div class="profile-title">Edit Admin Profile</div>
    <img class="admin-icon" src="pic/admin.png" alt="Admin Icon">

    <div class="form-view">

      <div class="row">
        <div class="label">Username :</div>
        <input class="input" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
      </div>

      <div class="row">
        <div class="label">Admin ID :</div>
        <input class="input readonly" value="<?php echo htmlspecialchars($userID); ?>" readonly>
      </div>

      <div class="row">
        <div class="label">Role :</div>
        <input class="input readonly" value="<?php echo htmlspecialchars($role); ?>" readonly>
      </div>

      <div class="row">
        <div class="label">Year of Service :</div>
        <input class="input" name="year_of_service" value="<?php echo htmlspecialchars($year_of_service); ?>" required>
      </div>

      <div class="actions">
        <button class="btn save" type="submit">Save Changes</button>
        <button class="btn cancel" type="button" onclick="location.href='admin_profile.php'">Cancel</button>
      </div>

    </div>
  </form>
</main>

</body>
</html>
